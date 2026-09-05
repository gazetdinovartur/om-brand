<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class VkApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $errorCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

/**
 * Thin VK API wrapper (api.vk.com).
 */
class VkApiClient
{
    private const API = 'https://api.vk.com/method/';
    private const VERSION = '5.199';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly VkCredentials $credentials,
    ) {
    }

    /**
     * @param array<string, scalar|null> $params
     *
     * @return array<string, mixed>
     */
    public function call(string $method, array $params = [], ?string $token = null): array
    {
        $accessToken = $token ?? $this->credentials->userApiToken();
        if ('' === $accessToken) {
            throw new VkApiException(
                'Нужен user-токен VK (админка → Подключить VK). Community-токен для этого метода не подходит.',
            );
        }

        $payload = array_merge($params, [
            'access_token' => $accessToken,
            'v' => self::VERSION,
        ]);

        $response = $this->httpClient->request('POST', self::API.$method, [
            'body' => $payload,
            'timeout' => 60,
        ]);

        /** @var array{response?: mixed, error?: array{error_code?: int, error_msg?: string}} $data */
        $data = $response->toArray(false);

        if (isset($data['error']) && \is_array($data['error'])) {
            $code = isset($data['error']['error_code']) ? (int) $data['error']['error_code'] : null;
            $msg = (string) ($data['error']['error_msg'] ?? 'VK API error');
            if (27 === $code || str_contains(mb_strtolower($msg), 'group authorization failed')) {
                $msg = 'Group auth: нужен user-токен (Подключить VK), не ключ сообщества. '.$msg;
            }
            throw new VkApiException($msg, $code);
        }

        if (!\array_key_exists('response', $data)) {
            throw new VkApiException('Unexpected VK API response.');
        }

        $responsePayload = $data['response'];
        if (!\is_array($responsePayload)) {
            return ['value' => $responsePayload];
        }

        /** @var array<string, mixed> $responsePayload */
        return $responsePayload;
    }

    /**
     * @return list<string> attachment strings photo{owner}_{id}
     */
    public function uploadWallPhotos(int $ownerId, array $absolutePaths): array
    {
        // Community tokens usually cannot upload wall photos (error 27).
        if ($ownerId < 0 && !$this->credentials->hasUserToken()) {
            return [];
        }

        $attachments = [];
        foreach (array_slice($absolutePaths, 0, 10) as $path) {
            if (!is_file($path)) {
                continue;
            }
            $attachments[] = $this->uploadOneWallPhoto($ownerId, $path);
        }

        return $attachments;
    }

    private function uploadOneWallPhoto(int $ownerId, string $absolutePath): string
    {
        $token = $this->credentials->userApiToken();
        if ('' === $token) {
            return [];
        }

        $server = $this->call('photos.getWallUploadServer', [
            'user_id' => $ownerId > 0 ? $ownerId : null,
            'group_id' => $ownerId < 0 ? abs($ownerId) : null,
        ], $token);

        $uploadUrl = (string) ($server['upload_url'] ?? '');
        if ('' === $uploadUrl) {
            throw new VkApiException('photos.getWallUploadServer: missing upload_url');
        }

        $uploadResponse = $this->httpClient->request('POST', $uploadUrl, [
            'body' => [
                'photo' => fopen($absolutePath, 'r'),
            ],
            'timeout' => 120,
        ]);

        /** @var array{server?: int|string, photo?: string, hash?: string} $uploaded */
        $uploaded = $uploadResponse->toArray(false);
        if (!isset($uploaded['server'], $uploaded['photo'], $uploaded['hash'])) {
            throw new VkApiException('VK photo upload failed.');
        }

        $saved = $this->call('photos.saveWallPhoto', [
            'user_id' => $ownerId > 0 ? $ownerId : null,
            'group_id' => $ownerId < 0 ? abs($ownerId) : null,
            'server' => $uploaded['server'],
            'photo' => $uploaded['photo'],
            'hash' => $uploaded['hash'],
        ], $token);

        $photo = $saved[0] ?? null;
        if (!\is_array($photo) || !isset($photo['owner_id'], $photo['id'])) {
            // saveWallPhoto returns a list; call() may wrap oddly — handle list response
            if (isset($saved[0]) && \is_array($saved[0])) {
                $photo = $saved[0];
            }
        }

        // When API returns a numeric list, our call() typed it as array<string,mixed> —
        // re-fetch raw via dedicated handling:
        if (!\is_array($photo) || !isset($photo['owner_id'], $photo['id'])) {
            foreach ($saved as $item) {
                if (\is_array($item) && isset($item['owner_id'], $item['id'])) {
                    $photo = $item;
                    break;
                }
            }
        }

        if (!\is_array($photo) || !isset($photo['owner_id'], $photo['id'])) {
            throw new VkApiException('photos.saveWallPhoto: unexpected payload');
        }

        return sprintf('photo%d_%d', (int) $photo['owner_id'], (int) $photo['id']);
    }

    /**
     * @param list<string> $attachments
     */
    public function wallPost(
        int $ownerId,
        string $message,
        array $attachments = [],
        ?string $guid = null,
        ?string $copyright = null,
        ?int $publishDate = null,
    ): int {
        $params = [
            'owner_id' => $ownerId,
            'from_group' => $ownerId < 0 ? 1 : 0,
            'message' => $message,
            'guid' => $guid ?? bin2hex(random_bytes(8)),
        ];
        if ([] !== $attachments) {
            $params['attachments'] = implode(',', $attachments);
        }
        if (null !== $copyright && '' !== trim($copyright)) {
            // Системная пометка «Источник» под постом.
            $params['copyright'] = trim($copyright);
        }
        if (null !== $publishDate) {
            $params['publish_date'] = $publishDate;
        }

        $result = $this->call('wall.post', $params, $this->credentials->postingToken());
        $postId = $result['post_id'] ?? $result['value'] ?? null;
        if (!is_numeric($postId)) {
            throw new VkApiException('wall.post: missing post_id');
        }

        return (int) $postId;
    }

    /**
     * @param list<string> $attachments
     */
    public function wallEdit(
        int $ownerId,
        int $postId,
        string $message,
        array $attachments = [],
        ?string $copyright = null,
    ): void {
        $params = [
            'owner_id' => $ownerId,
            'post_id' => $postId,
            'message' => $message,
        ];
        if ([] !== $attachments) {
            $params['attachments'] = implode(',', $attachments);
        }
        if (null !== $copyright && '' !== trim($copyright)) {
            $params['copyright'] = trim($copyright);
        }

        $this->call('wall.edit', $params, $this->requireUserToken('wall.edit'));
    }

    public function resolveOwnerId(): int
    {
        return $this->resolveConfiguredOwner(
            $this->credentials->ownerId(),
            $this->credentials->ownerIdRaw(),
            'VK_OWNER_ID',
        );
    }

    /**
     * Owner for inbound wall sync (personal page via VK_SYNC_OWNER_ID, else VK_OWNER_ID).
     */
    public function resolveSyncOwnerId(): int
    {
        return $this->resolveConfiguredOwner(
            $this->credentials->syncOwnerId(),
            $this->credentials->syncOwnerIdRaw(),
            'VK_SYNC_OWNER_ID',
        );
    }

    private function resolveConfiguredOwner(?int $configured, string $raw, string $envLabel): int
    {
        if (null !== $configured) {
            return $configured;
        }

        if ('' !== $raw) {
            $screen = ltrim($raw, '-@');
            if ('' !== $screen) {
                return $this->resolveScreenNameToOwnerId($screen);
            }
        }

        $me = $this->call('users.get', [], $this->requireUserToken('users.get'));
        $first = $me[0] ?? null;
        if (!\is_array($first)) {
            foreach ($me as $item) {
                if (\is_array($item) && isset($item['id'])) {
                    $first = $item;
                    break;
                }
            }
        }
        if (!\is_array($first) || !isset($first['id'])) {
            throw new VkApiException(sprintf(
                'Cannot resolve VK owner id (set %s to numeric id or screen name).',
                $envLabel,
            ));
        }

        return (int) $first['id'];
    }

    /**
     * Resolve club / public / custom screen name to negative owner_id.
     */
    private function resolveScreenNameToOwnerId(string $screenName): int
    {
        $resolved = $this->call('utils.resolveScreenName', [
            'screen_name' => $screenName,
        ], $this->requireUserToken('utils.resolveScreenName'));

        $type = (string) ($resolved['type'] ?? '');
        $objectId = isset($resolved['object_id']) ? (int) $resolved['object_id'] : 0;
        if ($objectId <= 0) {
            throw new VkApiException(sprintf('VK screen name "%s" not found. Use numeric VK_OWNER_ID=-group_id.', $screenName));
        }

        if (\in_array($type, ['group', 'page', 'event'], true)) {
            return -$objectId;
        }

        if ('user' === $type) {
            return $objectId;
        }

        throw new VkApiException(sprintf('Unsupported VK screen name type "%s" for "%s".', $type, $screenName));
    }

    private function requireUserToken(string $method): string
    {
        $token = $this->credentials->userApiToken();
        if ('' === $token) {
            throw new VkApiException(sprintf(
                'Метод %s недоступен с ключом сообщества. Подключите user-токен: /admin/oauth/vk/start',
                $method,
            ));
        }

        return $token;
    }

    /**
     * Exchange OAuth code for access token.
     *
     * @return array{access_token: string, user_id?: int, expires_in?: int}
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        $appId = $this->credentials->appId();
        $secret = $this->credentials->appSecret();
        if ('' === $appId || '' === $secret) {
            throw new VkApiException('VK_APP_ID and VK_APP_SECRET (or VK_SECRET_KEY) are required for OAuth.');
        }

        $response = $this->httpClient->request('GET', 'https://oauth.vk.com/access_token', [
            'query' => [
                'client_id' => $appId,
                'client_secret' => $secret,
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ],
            'timeout' => 30,
        ]);

        /** @var array{access_token?: string, user_id?: int, expires_in?: int, error?: string, error_description?: string} $data */
        $data = $response->toArray(false);
        if (!isset($data['access_token']) || !\is_string($data['access_token']) || '' === $data['access_token']) {
            $msg = (string) ($data['error_description'] ?? $data['error'] ?? 'OAuth token exchange failed');
            throw new VkApiException($msg);
        }

        return [
            'access_token' => $data['access_token'],
            'user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'expires_in' => isset($data['expires_in']) ? (int) $data['expires_in'] : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function wallGet(int $ownerId, int $count = 20): array
    {
        $result = $this->call('wall.get', [
            'owner_id' => $ownerId,
            'count' => min(100, max(1, $count)),
            'filter' => 'owner',
        ], $this->requireUserToken('wall.get'));

        $items = $result['items'] ?? [];
        if (!\is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (\is_array($item)) {
                $out[] = $item;
            }
        }

        return $out;
    }
}
