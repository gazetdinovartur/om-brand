<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Kinescope;

use App\Infrastructure\Kinescope\KinescopeClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class KinescopeClientUpdateSpeakTest extends TestCase
{
    public function testUpdateSpeakRoomPatchesSyncableFields(): void
    {
        $captured = null;
        $client = new KinescopeClient(
            new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
                $captured = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? ''];

                return new MockResponse(json_encode(['data' => ['id' => 'room-1']], \JSON_THROW_ON_ERROR));
            }),
            'token',
            '',
            '11111111-1111-1111-1111-111111111111',
        );

        $client->updateSpeakRoom('room-1', [
            'name' => 'Новое имя',
            'autorecording' => true,
            'transcription_autostart' => false,
        ]);

        self::assertSame('PATCH', $captured['method'] ?? null);
        self::assertStringContainsString('/v1/speak/rooms/room-1', (string) ($captured['url'] ?? ''));
        $body = json_decode((string) ($captured['body'] ?? ''), true);
        self::assertIsArray($body);
        self::assertSame('Новое имя', $body['name']);
        self::assertTrue($body['autorecording']);
        self::assertFalse($body['transcription_autostart']);
    }

    public function testCreateSpeakRoomSendsWaitingAndTranscriptionFlags(): void
    {
        $captured = null;
        $client = new KinescopeClient(
            new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
                $captured = $options['body'] ?? '';

                return new MockResponse(json_encode([
                    'data' => [
                        'id' => 'room-2',
                        'code' => 'abc-code',
                        'link' => 'https://speak.kinescope.io/abc-code',
                    ],
                ], \JSON_THROW_ON_ERROR), ['http_code' => 201]);
            }),
            'token',
            '',
            '11111111-1111-1111-1111-111111111111',
        );

        $client->createSpeakRoom('Встреча', 'permanent_meeting', true, true, false, true);
        $body = json_decode((string) $captured, true);
        self::assertIsArray($body);
        self::assertTrue($body['waiting_room']);
        self::assertFalse($body['wait_for_host']);
        self::assertTrue($body['transcription_autostart']);
        self::assertTrue($body['autorecording']);
    }

    public function testListSpeakCallsParsesItems(): void
    {
        $capturedUrl = null;
        $client = new KinescopeClient(
            new MockHttpClient(static function (string $method, string $url) use (&$capturedUrl): MockResponse {
                $capturedUrl = $method.' '.$url;

                return new MockResponse(json_encode([
                    'data' => [
                        [
                            'id' => 'call-1',
                            'transcript_id' => 'tr-1',
                            'created_at' => '2025-10-22T02:32:32.840208Z',
                            'updated_at' => '2025-10-22T03:33:22.877221Z',
                        ],
                        ['id' => 'call-2'],
                    ],
                ], \JSON_THROW_ON_ERROR));
            }),
            'token',
        );

        $calls = $client->listSpeakCalls('room-calls-1');
        self::assertStringContainsString('GET', (string) $capturedUrl);
        self::assertStringContainsString('/v1/speak/rooms/room-calls-1/calls', (string) $capturedUrl);
        self::assertCount(2, $calls);
        self::assertSame('call-1', $calls[0]['id']);
        self::assertSame('tr-1', $calls[0]['transcript_id']);
        self::assertNull($calls[1]['transcript_id']);
    }

    public function testDeleteSpeakRoomUsesDelete(): void
    {
        $captured = null;
        $client = new KinescopeClient(
            new MockHttpClient(static function (string $method, string $url) use (&$captured): MockResponse {
                $captured = ['method' => $method, 'url' => $url];

                return new MockResponse(json_encode(['data' => ['success' => true]], \JSON_THROW_ON_ERROR));
            }),
            'token',
        );

        $client->deleteSpeakRoom('room-del-1');
        self::assertSame('DELETE', $captured['method'] ?? null);
        self::assertStringContainsString('/v1/speak/rooms/room-del-1', (string) ($captured['url'] ?? ''));
    }
}
