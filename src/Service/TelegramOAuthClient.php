<?php

declare(strict_types=1);

namespace App\Service;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Telegram Login via OAuth 2.0 / OIDC Authorization Code + PKCE.
 *
 * @see https://core.telegram.org/bots/telegram-login
 */
final class TelegramOAuthClient
{
    private const AUTHORIZE_URL = 'https://oauth.telegram.org/auth';
    private const TOKEN_URL = 'https://oauth.telegram.org/token';
    private const JWKS_URL = 'https://oauth.telegram.org/.well-known/jwks.json';
    private const ISSUER = 'https://oauth.telegram.org';
    private const SCOPE = 'openid profile telegram:bot_access';
    private const SESSION_STATE = 'telegram_oauth.state';
    private const SESSION_VERIFIER = 'telegram_oauth.verifier';
    private const SESSION_TARGET = 'telegram_oauth.target';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        #[Autowire('%env(default:app.telegram_oidc_client_id:TELEGRAM_OIDC_CLIENT_ID)%')]
        private readonly string $clientId = '',
        #[Autowire('%env(default:app.telegram_oidc_client_secret:TELEGRAM_OIDC_CLIENT_SECRET)%')]
        private readonly string $clientSecret = '',
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $defaultUri = '',
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->clientId) && '' !== trim($this->clientSecret);
    }

    /**
     * OAuth login only on the public domain from DEFAULT_URI (BotFather Allowed URLs).
     */
    public function isAllowedOnHost(string $requestHost): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $host = strtolower(trim($requestHost));
        if ('' === $host || $this->isNonPublicHost($host)) {
            return false;
        }

        $allowedHost = strtolower((string) parse_url($this->defaultUri, \PHP_URL_HOST));
        if ('' === $allowedHost || $this->isNonPublicHost($allowedHost)) {
            return false;
        }

        return $this->hostsMatch($host, $allowedHost);
    }

    public function redirectUri(): string
    {
        $base = rtrim(trim($this->defaultUri), '/');
        if ('' === $base) {
            throw new \RuntimeException('Задайте DEFAULT_URI для Telegram OAuth callback.');
        }

        return $base.'/library/login/telegram/callback';
    }

    public function beginAuthorization(SessionInterface $session, ?string $targetPath): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Telegram OAuth не настроен.');
        }

        $verifier = $this->base64Url(random_bytes(64));
        $challenge = $this->base64Url(hash('sha256', $verifier, true));
        $state = bin2hex(random_bytes(16));

        $session->set(self::SESSION_STATE, $state);
        $session->set(self::SESSION_VERIFIER, $verifier);
        $session->set(self::SESSION_TARGET, $targetPath);

        return self::AUTHORIZE_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => trim($this->clientId),
            'redirect_uri' => $this->redirectUri(),
            'scope' => self::SCOPE,
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], '', '&', \PHP_QUERY_RFC3986);
    }

    /**
     * @return array{ok: true, user: array{id: int|string, username?: ?string, first_name?: ?string, last_name?: ?string, photo_url?: ?string}, targetPath: ?string}|array{ok: false, message: string}
     */
    public function completeAuthorization(SessionInterface $session, string $code, string $state): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'Вход через Telegram временно недоступен.'];
        }

        $expectedState = (string) $session->get(self::SESSION_STATE, '');
        $verifier = (string) $session->get(self::SESSION_VERIFIER, '');
        $targetPath = $session->get(self::SESSION_TARGET);
        $session->remove(self::SESSION_STATE);
        $session->remove(self::SESSION_VERIFIER);
        $session->remove(self::SESSION_TARGET);

        if ('' === $code) {
            return ['ok' => false, 'message' => 'Telegram не вернул код авторизации.'];
        }

        if ('' === $expectedState || '' === $state || !hash_equals($expectedState, $state)) {
            return ['ok' => false, 'message' => 'Сессия входа устарела. Попробуйте ещё раз.'];
        }

        if ('' === $verifier) {
            return ['ok' => false, 'message' => 'Сессия входа устарела. Попробуйте ещё раз.'];
        }

        try {
            $idToken = $this->exchangeCode($code, $verifier);
            $user = $this->verifyIdToken($idToken);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Не удалось подтвердить вход через Telegram.'];
        }

        return [
            'ok' => true,
            'user' => $user,
            'targetPath' => \is_string($targetPath) ? $targetPath : null,
        ];
    }

    private function exchangeCode(string $code, string $verifier): string
    {
        $clientId = trim($this->clientId);
        $clientSecret = trim($this->clientSecret);
        $basic = base64_encode($clientId.':'.$clientSecret);

        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'headers' => [
                'Authorization' => 'Basic '.$basic,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri(),
                'client_id' => $clientId,
                'code_verifier' => $verifier,
            ],
            'timeout' => 10,
        ]);

        /** @var array{id_token?: string, error?: string} $payload */
        $payload = $response->toArray(false);
        $idToken = (string) ($payload['id_token'] ?? '');
        if ('' === $idToken) {
            throw new \RuntimeException('Telegram token response without id_token.');
        }

        return $idToken;
    }

    /**
     * @return array{id: int|string, username?: ?string, first_name?: ?string, last_name?: ?string, photo_url?: ?string}
     */
    private function verifyIdToken(string $idToken): array
    {
        $keys = $this->jwks();
        $decoded = JWT::decode($idToken, JWK::parseKeySet($keys));
        $claims = (array) $decoded;

        if (($claims['iss'] ?? null) !== self::ISSUER) {
            throw new \RuntimeException('Invalid Telegram id_token issuer.');
        }

        $aud = $claims['aud'] ?? null;
        $clientId = trim($this->clientId);
        $audOk = (\is_string($aud) && hash_equals($clientId, $aud))
            || (\is_array($aud) && \in_array($clientId, array_map('strval', $aud), true));
        if (!$audOk) {
            throw new \RuntimeException('Invalid Telegram id_token audience.');
        }

        $id = $claims['id'] ?? $claims['sub'] ?? null;
        if (null === $id || '' === (string) $id) {
            throw new \RuntimeException('Telegram id_token without user id.');
        }

        $name = isset($claims['name']) ? trim((string) $claims['name']) : '';
        $given = isset($claims['given_name']) ? trim((string) $claims['given_name']) : '';
        $family = isset($claims['family_name']) ? trim((string) $claims['family_name']) : '';
        if ('' === $given && '' === $family && '' !== $name) {
            $parts = preg_split('/\s+/u', $name, 2) ?: [];
            $given = $parts[0] ?? '';
            $family = $parts[1] ?? '';
        }

        return [
            'id' => $id,
            'username' => isset($claims['preferred_username']) ? (string) $claims['preferred_username'] : null,
            'first_name' => '' !== $given ? $given : null,
            'last_name' => '' !== $family ? $family : null,
            'photo_url' => isset($claims['picture']) ? (string) $claims['picture'] : null,
        ];
    }

    /**
     * @return array{keys: list<array<string, mixed>>}
     */
    private function jwks(): array
    {
        /** @var array{keys: list<array<string, mixed>>} $jwks */
        $jwks = $this->cache->get('telegram_oidc_jwks', function (ItemInterface $item): array {
            $item->expiresAfter(3600);
            $response = $this->httpClient->request('GET', self::JWKS_URL, ['timeout' => 10]);
            /** @var array{keys?: list<array<string, mixed>>} $data */
            $data = $response->toArray(false);
            if (!isset($data['keys']) || !\is_array($data['keys']) || [] === $data['keys']) {
                throw new \RuntimeException('Telegram JWKS empty.');
            }

            return $data;
        });

        return $jwks;
    }

    private function isNonPublicHost(string $host): bool
    {
        if (\in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        if (str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
            return true;
        }

        if (false !== filter_var($host, \FILTER_VALIDATE_IP)) {
            return false === filter_var(
                $host,
                \FILTER_VALIDATE_IP,
                \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE
            );
        }

        return false;
    }

    private function hostsMatch(string $left, string $right): bool
    {
        $normalize = static fn (string $host): string => str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        return $normalize($left) === $normalize($right);
    }

    private function base64Url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
