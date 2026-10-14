<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Kinescope;

use App\Infrastructure\Kinescope\KinescopeClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class KinescopeClientWebhookTest extends TestCase
{
    public function testCreateWebhookPostsExpectedBody(): void
    {
        $captured = null;
        $client = new KinescopeClient(
            new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
                $captured = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? ''];

                return new MockResponse(json_encode(['data' => ['id' => 'wh-1']], \JSON_THROW_ON_ERROR), [
                    'http_code' => 201,
                ]);
            }),
            'token',
        );

        $result = $client->createWebhook(
            'arturlun-practice',
            'https://arturlun.ru/webhooks/kinescope',
            'user',
            'pass',
            ['live.finished', 'speak.call.finished'],
        );

        self::assertSame('wh-1', $result['data']['id'] ?? null);
        self::assertSame('POST', $captured['method'] ?? null);
        self::assertStringContainsString('/v1/webhooks', (string) ($captured['url'] ?? ''));
        $body = json_decode((string) ($captured['body'] ?? ''), true);
        self::assertIsArray($body);
        self::assertSame('https://arturlun.ru/webhooks/kinescope', $body['endpoint']);
        self::assertSame(['live.finished', 'speak.call.finished'], $body['events']);
    }
}
