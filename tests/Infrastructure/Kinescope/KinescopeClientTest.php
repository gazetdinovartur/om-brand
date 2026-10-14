<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Kinescope;

use App\Infrastructure\Kinescope\KinescopeClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class KinescopeClientTest extends TestCase
{
    public function testCreateLiveEventPostsEventAndStream(): void
    {
        $calls = [];
        $client = new KinescopeClient(
            new MockHttpClient(static function (string $method, string $url, array $options = []) use (&$calls): MockResponse {
                $calls[] = [$method, $url];
                if (str_ends_with($url, '/v2/live/events') && 'POST' === $method) {
                    return new MockResponse(json_encode([
                        'data' => ['id' => 'evt-1', 'play_link' => 'https://kinescope.io/evt-1'],
                    ], \JSON_THROW_ON_ERROR));
                }
                if (str_contains($url, '/stream') && 'POST' === $method) {
                    return new MockResponse(json_encode([
                        'data' => [
                            'server' => 'rtmp://ingest.example/live',
                            'stream_key' => 'key-abc',
                        ],
                    ], \JSON_THROW_ON_ERROR));
                }
                if (str_contains($url, '/enable')) {
                    return new MockResponse('{}');
                }

                return new MockResponse('{}', ['http_code' => 404]);
            }),
            'test-token',
            '11111111-1111-1111-1111-111111111111',
        );

        $result = $client->createLiveEvent('Практика', new \DateTimeImmutable('+1 day'));
        self::assertSame('evt-1', $result->eventId);
        self::assertSame('rtmp://ingest.example/live', $result->ingestUrl);
        self::assertSame('key-abc', $result->streamKey);
        self::assertGreaterThanOrEqual(2, count($calls));
    }

    public function testGetVideoParsesPosterAndDuration(): void
    {
        $client = new KinescopeClient(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'data' => [
                        'id' => 'vid-1',
                        'title' => 'Запись',
                        'status' => 'done',
                        'duration' => 125.4,
                        'poster' => ['original' => 'https://cdn.example/p.jpg'],
                    ],
                ], \JSON_THROW_ON_ERROR)),
            ]),
            'test-token',
        );

        $info = $client->getVideo('vid-1');
        self::assertSame('vid-1', $info->id);
        self::assertSame('Запись', $info->title);
        self::assertSame('done', $info->status);
        self::assertSame('https://cdn.example/p.jpg', $info->posterUrl);
        self::assertSame(125.4, $info->duration);
        self::assertTrue($info->isReady());
    }

    public function testCreateSpeakRoomRequiresOwner(): void
    {
        $client = new KinescopeClient(new MockHttpClient([]), 'token', '', '');
        $this->expectException(\RuntimeException::class);
        $client->createSpeakRoom('Встреча');
    }
}
