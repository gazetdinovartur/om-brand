<?php

declare(strict_types=1);

namespace App\Infrastructure\Kinescope;

use App\Infrastructure\Kinescope\Dto\LiveEventResult;
use App\Infrastructure\Kinescope\Dto\SpeakRoomResult;
use App\Infrastructure\Kinescope\Dto\VideoInfo;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Kinescope API (Live v2, Speak v1, Videos v1).
 */
final class KinescopeClient
{
    private const API_BASE = 'https://api.kinescope.io';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiToken,
        private readonly string $parentId = '',
        private readonly string $speakOwnerId = '',
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->apiToken);
    }

    public function getParentId(): string
    {
        return trim($this->parentId);
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function listProjects(): array
    {
        $data = $this->request('GET', '/v1/projects');
        $items = $data['data'] ?? [];
        if (!\is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (!\is_array($item) || !isset($item['id'])) {
                continue;
            }
            $out[] = [
                'id' => (string) $item['id'],
                'name' => (string) ($item['name'] ?? $item['id']),
            ];
        }

        return $out;
    }

    public function resolveParentId(?string $hint = null): ?string
    {
        $configured = trim($this->parentId);
        if ('' !== $configured && $this->looksLikeUuid($configured)) {
            return $configured;
        }

        $needle = strtolower(trim($hint ?? $configured));
        if ('' === $needle) {
            return null;
        }

        foreach ($this->listProjects() as $project) {
            if (strtolower($project['name']) === $needle || $project['id'] === $needle) {
                return $project['id'];
            }
        }

        return $this->looksLikeUuid($needle) ? $needle : null;
    }

    public function createLiveEvent(
        string $name,
        ?\DateTimeImmutable $scheduledAt = null,
        bool $withChatAfter = true,
    ): LiveEventResult {
        $this->assertConfigured();

        $payload = [
            'name' => mb_substr($name, 0, 200),
            'type' => 'one-time',
            'auto_start' => true,
            'chat_after_stream' => $withChatAfter,
            'chat_active' => $withChatAfter,
            'chat_preview' => true,
            'show_members' => true,
        ];

        $parentId = $this->resolveParentId();
        if (null !== $parentId) {
            $payload['parent_id'] = $parentId;
            $payload['record'] = ['parent_id' => $parentId];
        }

        if ($scheduledAt instanceof \DateTimeImmutable) {
            $payload['scheduled'] = [
                'time' => $scheduledAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            ];
        }

        $created = $this->request('POST', '/v2/live/events', $payload);
        $event = \is_array($created['data'] ?? null) ? $created['data'] : $created;
        if (!\is_array($event)) {
            throw new \RuntimeException('Kinescope create live event: unexpected response.');
        }
        $eventId = self::stringifyField($event['id'] ?? null);
        if ('' === $eventId) {
            throw new \RuntimeException('Kinescope create live event: missing id.');
        }

        // Create response already has rtmp_link + streamkey when auto_start=true.
        // Extra POST /stream often fails (stream already exists) and used to abort
        // before we saved event id → orphan events + no «В эфир» button.
        [$ingest, $key] = self::extractIngestCredentials($event);

        if (null === $ingest || null === $key) {
            try {
                $streamPayload = [];
                if ($scheduledAt instanceof \DateTimeImmutable) {
                    $streamPayload['started_at'] = $scheduledAt
                        ->setTimezone(new \DateTimeZone('UTC'))
                        ->format('Y-m-d\TH:i:s\Z');
                }
                $stream = $this->request('POST', '/v2/live/events/'.$eventId.'/stream', $streamPayload);
                $streamData = \is_array($stream['data'] ?? null) ? $stream['data'] : $stream;
                if (\is_array($streamData)) {
                    [$ingestFromStream, $keyFromStream] = self::extractIngestCredentials($streamData);
                    $ingest ??= $ingestFromStream;
                    $key ??= $keyFromStream;
                }
            } catch (\Throwable) {
                // Stream may already exist — re-read event.
            }
        }

        if (null === $ingest || null === $key) {
            try {
                $fresh = $this->getLiveEvent($eventId);
                [$ingestFromGet, $keyFromGet] = self::extractIngestCredentials($fresh);
                $ingest ??= $ingestFromGet;
                $key ??= $keyFromGet;
            } catch (\Throwable) {
            }
        }

        try {
            $this->request('PUT', '/v2/live/events/'.$eventId.'/enable');
        } catch (\Throwable) {
            // Some accounts auto-enable after schedule — ignore.
        }

        $playLink = self::stringifyField($event['play_link'] ?? null) ?: null;

        return new LiveEventResult($eventId, $ingest, $key, $playLink);
    }

    public function getLiveEvent(string $eventId): array
    {
        $this->assertConfigured();
        $response = $this->request('GET', '/v2/live/events/'.$eventId);

        return \is_array($response['data'] ?? null) ? $response['data'] : $response;
    }

    public function completeLiveEvent(string $eventId): void
    {
        $this->assertConfigured();
        $this->request('PUT', '/v2/live/events/'.$eventId.'/complete');
    }

    /**
     * @return list<string> video ids
     */
    public function listEventVideoIds(string $eventId): array
    {
        $this->assertConfigured();
        $response = $this->request('GET', '/v2/live/events/'.$eventId.'/videos');
        $items = $response['data'] ?? [];
        if (!\is_array($items)) {
            return [];
        }

        $ids = [];
        foreach ($items as $item) {
            if (\is_array($item) && isset($item['id'])) {
                $ids[] = (string) $item['id'];
            } elseif (\is_string($item)) {
                $ids[] = $item;
            }
        }

        return $ids;
    }

    public function getVideo(string $videoId): VideoInfo
    {
        $this->assertConfigured();
        $response = $this->request('GET', '/v1/videos/'.$videoId);
        $data = \is_array($response['data'] ?? null) ? $response['data'] : $response;

        $poster = null;
        if (isset($data['poster']['original']) && \is_string($data['poster']['original'])) {
            $poster = $data['poster']['original'];
        } elseif (isset($data['poster_url']) && \is_string($data['poster_url'])) {
            $poster = $data['poster_url'];
        }

        $duration = null;
        if (isset($data['duration']) && is_numeric($data['duration'])) {
            $duration = (float) $data['duration'];
        }

        return new VideoInfo(
            id: (string) ($data['id'] ?? $videoId),
            title: (string) ($data['title'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            posterUrl: $poster,
            duration: $duration,
        );
    }

    public function createSpeakRoom(
        string $name,
        string $type = 'permanent_meeting',
        bool $autorecording = true,
    ): SpeakRoomResult {
        $this->assertConfigured();

        $ownerId = trim($this->speakOwnerId);
        if ('' === $ownerId) {
            throw new \RuntimeException('KINESCOPE_SPEAK_OWNER_ID is required to create Speak rooms.');
        }

        $payload = [
            'name' => mb_substr($name, 0, 200),
            'owner_id' => $ownerId,
            'type' => $type,
            'autorecording' => $autorecording,
            'waiting_room' => false,
            'wait_for_host' => true,
        ];

        $response = $this->request('POST', '/v1/speak/rooms', $payload);
        $data = \is_array($response['data'] ?? null) ? $response['data'] : $response;

        $roomId = self::stringifyField($data['id'] ?? null);
        $code = self::stringifyField($data['code'] ?? null);
        $link = self::stringifyField($data['link'] ?? null);
        if ('' === $link && '' !== $code) {
            $link = 'https://speak.kinescope.io/'.$code;
        }
        if ('' === $roomId || '' === $code) {
            throw new \RuntimeException('Kinescope create Speak room: missing id/code.');
        }

        return new SpeakRoomResult($roomId, $code, $link);
    }

    public function getSpeakRoom(string $roomId): array
    {
        $this->assertConfigured();
        $response = $this->request('GET', '/v1/speak/rooms/'.$roomId);

        return \is_array($response['data'] ?? null) ? $response['data'] : $response;
    }

    /**
     * Workspace members (Speak owner_id must be one of these user_id values).
     *
     * @return list<array{user_id: string, email: string, username: string}>
     */
    public function listModerators(int $perPage = 50): array
    {
        $this->assertConfigured();
        $response = $this->request('GET', '/v1/moderators?per_page='.$perPage);
        $items = $response['data'] ?? [];
        if (!\is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $userId = self::stringifyField($item['user_id'] ?? null);
            if ('' === $userId) {
                continue;
            }
            $out[] = [
                'user_id' => $userId,
                'email' => self::stringifyField($item['email'] ?? null),
                'username' => self::stringifyField($item['username'] ?? null),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id: string, name: string, owner_id: string, code: string}>
     */
    public function listSpeakRooms(int $perPage = 20): array
    {
        $this->assertConfigured();
        $response = $this->request('GET', '/v1/speak/rooms?per_page='.$perPage);
        $items = $response['data'] ?? [];
        if (!\is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $out[] = [
                'id' => self::stringifyField($item['id'] ?? null),
                'name' => self::stringifyField($item['name'] ?? null),
                'owner_id' => self::stringifyField($item['owner_id'] ?? null),
                'code' => self::stringifyField($item['code'] ?? null),
            ];
        }

        return $out;
    }

    /**
     * Upload public JWK for Speak/chat JWT verification.
     *
     * @param array{kty: string, e: string, use: string, kid: string, alg: string, n: string, expires_at: string} $jwk
     */
    public function uploadJwk(array $jwk): array
    {
        $this->assertConfigured();

        return $this->request('POST', '/v1/jwk', $jwk);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listWebhooks(): array
    {
        $this->assertConfigured();
        $response = $this->request('GET', '/v1/webhooks');
        $items = $response['data'] ?? $response;
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

    /**
     * @param list<string> $events
     *
     * @return array<string, mixed>
     */
    public function createWebhook(
        string $name,
        string $endpoint,
        string $login,
        string $password,
        array $events,
    ): array {
        $this->assertConfigured();

        return $this->request('POST', '/v1/webhooks', [
            'name' => $name,
            'endpoint' => $endpoint,
            'login' => $login,
            'password' => $password,
            'events' => array_values($events),
        ]);
    }

    /**
     * @param list<string> $events
     *
     * @return array<string, mixed>
     */
    public function updateWebhook(
        string $webhookId,
        string $name,
        string $endpoint,
        string $login,
        string $password,
        array $events,
    ): array {
        $this->assertConfigured();

        return $this->request('PUT', '/v1/webhooks/'.$webhookId, [
            'name' => $name,
            'endpoint' => $endpoint,
            'login' => $login,
            'password' => $password,
            'events' => array_values($events),
        ]);
    }

    /**
     * @param array<string, mixed>|null $json
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $json = null): array
    {
        $options = [
            'headers' => [
                'Authorization' => 'Bearer '.trim($this->apiToken),
                'Accept' => 'application/json',
            ],
        ];
        if (null !== $json) {
            $options['json'] = $json;
        }

        $response = $this->httpClient->request($method, self::API_BASE.$path, $options);
        $status = $response->getStatusCode();
        $content = $response->getContent(false);
        $decoded = [] !== trim($content)
            ? json_decode($content, true)
            : [];

        if ($status < 200 || $status >= 300) {
            $message = self::formatApiErrorMessage($decoded, $content);
            throw new \RuntimeException(sprintf('Kinescope API %s %s failed (%d): %s', $method, $path, $status, $message));
        }

        return \is_array($decoded) ? $decoded : [];
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Kinescope is not configured (KINESCOPE_API_TOKEN).');
        }
    }

    private function looksLikeUuid(string $value): bool
    {
        return 1 === preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value,
        );
    }

    /**
     * @param mixed $decoded
     */
    private static function formatApiErrorMessage(mixed $decoded, string $fallback): string
    {
        if (!\is_array($decoded)) {
            return '' !== trim($fallback) ? $fallback : 'unknown error';
        }

        foreach (['message', 'error', 'errors', 'detail'] as $key) {
            if (!\array_key_exists($key, $decoded)) {
                continue;
            }
            $text = self::stringifyField($decoded[$key]);
            if ('' !== $text) {
                return $text;
            }
        }

        $encoded = json_encode($decoded, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        return false !== $encoded && '[]' !== $encoded && '{}' !== $encoded
            ? $encoded
            : ('' !== trim($fallback) ? $fallback : 'unknown error');
    }

    private static function stringifyField(mixed $value): string
    {
        if (null === $value || false === $value) {
            return '';
        }
        if (\is_string($value) || \is_int($value) || \is_float($value)) {
            return trim((string) $value);
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (\is_array($value)) {
            $encoded = json_encode($value, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

            return false !== $encoded ? $encoded : '';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{0: ?string, 1: ?string} [ingestUrl, streamKey]
     */
    private static function extractIngestCredentials(array $data): array
    {
        $ingest = null;
        foreach (['rtmp_link', 'server', 'rtmp_url', 'ingest_url'] as $ingestKey) {
            $candidate = self::stringifyField($data[$ingestKey] ?? null);
            if ('' !== $candidate) {
                $ingest = $candidate;
                break;
            }
        }

        $key = null;
        foreach (['streamkey', 'stream_key', 'key'] as $keyName) {
            $candidate = self::stringifyField($data[$keyName] ?? null);
            if ('' !== $candidate) {
                $key = $candidate;
                break;
            }
        }

        return [$ingest, $key];
    }
}
