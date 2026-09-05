<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * VK ID (id.vk.ru) Authorization Code + PKCE.
 * redirect_uri must be your site domain registered in the VK ID app (not oauth.vk.com/blank.html).
 */
final class VkIdOAuth
{
    private const AUTHORIZE = 'https://id.vk.ru/authorize';
    private const TOKEN = 'https://id.vk.ru/oauth2/auth';

    public function __construct(
        private readonly VkCredentials $credentials,
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(default:app.site_url:APP_SITE_URL)%')]
        private readonly string $siteUrl = '',
    ) {
    }

    /**
     * Exact redirect URI for authorize + token exchange (must match id.vk.ru settings).
     */
    public function redirectUri(): string
    {
        $base = rtrim($this->siteUrl, '/');
        if ('' === $base) {
            throw new VkApiException('Задайте APP_SITE_URL в .env (например http://localhost:8085 или https://arturlun.ru).');
        }

        return $base.'/admin/oauth/vk/callback';
    }

    /**
     * @return array{verifier: string, challenge: string, state: string}
     */
    public function createPkceSession(): array
    {
        $verifier = $this->base64Url(random_bytes(64));
        $challenge = $this->base64Url(hash('sha256', $verifier, true));
        $state = bin2hex(random_bytes(16));

        return [
            'verifier' => $verifier,
            'challenge' => $challenge,
            'state' => $state,
        ];
    }

    public function buildAuthorizeUrl(string $codeChallenge, string $state, string $scope = 'vkid.personal_info'): string
    {
        $appId = $this->credentials->appId();
        if ('' === $appId) {
            throw new VkApiException('VK_APP_ID is required.');
        }

        return self::AUTHORIZE.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $appId,
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'scope' => $scope,
        ]);
    }

    /**
     * @return array{code: string, device_id: string, state: ?string}|null
     */
    public function extractAuthCodeFromRequest(array $query): ?array
    {
        if (isset($query['payload']) && \is_string($query['payload']) && '' !== $query['payload']) {
            /** @var array{code?: string, device_id?: string, state?: string} $payload */
            $payload = json_decode($query['payload'], true);
            if (!\is_array($payload)) {
                $payload = json_decode(urldecode($query['payload']), true) ?? [];
            }
            $code = (string) ($payload['code'] ?? '');
            $deviceId = (string) ($payload['device_id'] ?? '');
            if ('' !== $code && '' !== $deviceId) {
                return [
                    'code' => $code,
                    'device_id' => $deviceId,
                    'state' => isset($payload['state']) ? (string) $payload['state'] : null,
                ];
            }
        }

        $code = (string) ($query['code'] ?? '');
        $deviceId = (string) ($query['device_id'] ?? '');
        if ('' === $code || '' === $deviceId) {
            return null;
        }

        return [
            'code' => $code,
            'device_id' => $deviceId,
            'state' => isset($query['state']) ? (string) $query['state'] : null,
        ];
    }

    /**
     * Parse pasted URL (manual fallback).
     *
     * @return array{code: string, device_id: string, state: ?string}|null
     */
    public function extractAuthCodePayload(string $raw): ?array
    {
        $raw = trim($raw);
        if ('' === $raw) {
            return null;
        }

        $queryString = '';
        if (str_contains($raw, '?')) {
            $queryString = (string) (parse_url($raw, \PHP_URL_QUERY) ?: '');
        } elseif (str_contains($raw, 'code=') || str_contains($raw, 'payload=')) {
            $queryString = ltrim($raw, '?#');
        }

        if ('' === $queryString) {
            return null;
        }

        parse_str($queryString, $params);

        return $this->extractAuthCodeFromRequest($params);
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, user_id: ?int, expires_in: ?int, device_id: string}
     */
    public function exchangeCode(string $code, string $deviceId, string $codeVerifier, ?string $state = null): array
    {
        $appId = $this->credentials->appId();
        if ('' === $appId) {
            throw new VkApiException('VK_APP_ID is required.');
        }

        $body = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'client_id' => $appId,
            'device_id' => $deviceId,
            'redirect_uri' => $this->redirectUri(),
        ];
        if (null !== $state && '' !== $state) {
            $body['state'] = $state;
        }

        $response = $this->httpClient->request('POST', self::TOKEN, [
            'body' => $body,
            'timeout' => 30,
        ]);

        /** @var array{access_token?: string, refresh_token?: string, user_id?: int|string, expires_in?: int, error?: string, error_description?: string|array} $data */
        $data = $response->toArray(false);

        if (isset($data['error'])) {
            $desc = $data['error_description'] ?? $data['error'];
            if (\is_array($desc)) {
                $desc = json_encode($desc, \JSON_UNESCAPED_UNICODE) ?: 'VK ID token error';
            }
            throw new VkApiException((string) $desc);
        }

        $token = (string) ($data['access_token'] ?? '');
        if ('' === $token) {
            throw new VkApiException('VK ID: access_token missing in response.');
        }

        return [
            'access_token' => $token,
            'refresh_token' => isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
            'user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'expires_in' => isset($data['expires_in']) ? (int) $data['expires_in'] : null,
            'device_id' => $deviceId,
        ];
    }

    private function base64Url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
