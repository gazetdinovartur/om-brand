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
        $accessToken = $token ?? $this->credentials->userAccessToken();
        if ('' === $accessToken) {
            throw new VkApiException('VK user access token is not configured.');
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
        $server = $this->call('photos.getWallUploadServer', [
            'user_id' => $ownerId > 0 ? $ownerId : null,
            'group_id' => $ownerId < 0 ? abs($ownerId) : null,
        ]);

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
        ]);

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
    public function wallPost(int $ownerId, string $message, array $attachments = [], ?string $guid = null): int
    {
        $params = [
            'owner_id' => $ownerId,
            'from_group' => 0,
            'message' => $message,
            'guid' => $guid ?? bin2hex(random_bytes(8)),
        ];
        if ([] !== $attachments) {
            $params['attachments'] = implode(',', $attachments);
        }

        $result = $this->call('wall.post', $params);
        $postId = $result['post_id'] ?? $result['value'] ?? null;
        if (!is_numeric($postId)) {
            throw new VkApiException('wall.post: missing post_id');
        }

        return (int) $postId;
    }

    public function resolveOwnerId(): int
    {
        $configured = $this->credentials->ownerId();
        if (null !== $configured) {
            return $configured;
        }

        $me = $this->call('users.get');
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
            throw new VkApiException('Cannot resolve VK owner id (set VK_OWNER_ID).');
        }

        return (int) $first['id'];
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
        ]);

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
