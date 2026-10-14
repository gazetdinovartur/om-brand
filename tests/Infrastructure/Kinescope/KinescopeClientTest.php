<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Kinescope;

use App\Infrastructure\Kinescope\KinescopeClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class KinescopeClientTest extends TestCase
{
    public function testCreateLiveEventUsesCredentialsFromCreateResponse(): void
    {
        $calls = [];
        $client = new KinescopeClient(
            new MockHttpClient(static function (string $method, string $url, array $options = []) use (&$calls): MockResponse {
                $calls[] = [$method, $url];
                if (str_ends_with($url, '/v2/live/events') && 'POST' === $method) {
                    return new MockResponse(json_encode([
                        'data' => [
                            'id' => 'evt-1',
                            'play_link' => 'https://kinescope.io/evt-1',
                            'rtmp_link' => 'rtmp://rtmp.kinescope.io/live',
                            'streamkey' => 'key-from-create',
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
        self::assertSame('rtmp://rtmp.kinescope.io/live', $result->ingestUrl);
        self::assertSame('key-from-create', $result->streamKey);
        // No second POST /stream when credentials already present
        self::assertFalse(
            (bool) array_filter($calls, static fn (array $c): bool => str_contains($c[1], '/stream') && 'POST' === $c[0]),
        );
    }

    public function testCreateLiveEventSurvivesFailedStreamStepViaGetEvent(): void
    {
        $client = new KinescopeClient(
            new MockHttpClient(static function (string $method, string $url): MockResponse {
                if (str_ends_with($url, '/v2/live/events') && 'POST' === $method) {
                    return new MockResponse(json_encode([
                        'data' => ['id' => 'evt-2', 'play_link' => 'https://kinescope.io/evt-2'],
                    ], \JSON_THROW_ON_ERROR));
                }
                if (str_contains($url, '/stream') && 'POST' === $method) {
                    return new MockResponse(json_encode([
                        'message' => 'stream already exists',
                    ], \JSON_THROW_ON_ERROR), ['http_code' => 400]);
                }
                if (str_contains($url, '/v2/live/events/evt-2') && 'GET' === $method) {
                    return new MockResponse(json_encode([
                        'data' => [
                            'id' => 'evt-2',
                            'rtmp_link' => 'rtmp://rtmp.kinescope.io/live',
                            'streamkey' => 'key-from-get',
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

        $result = $client->createLiveEvent('Практика');
        self::assertSame('evt-2', $result->eventId);
        self::assertSame('rtmp://rtmp.kinescope.io/live', $result->ingestUrl);
        self::assertSame('key-from-get', $result->streamKey);
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
