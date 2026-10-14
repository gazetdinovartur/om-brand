<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Kinescope;

use App\Infrastructure\Kinescope\KinescopeClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class KinescopeClientErrorMessageTest extends TestCase
{
    public function testArrayErrorPayloadDoesNotTriggerArrayToStringWarning(): void
    {
        $client = new KinescopeClient(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'message' => ['owner_id' => ['is invalid'], 'type' => ['must be one of']],
                ], \JSON_THROW_ON_ERROR), ['http_code' => 400]),
            ]),
            'token',
            '',
            '11111111-1111-1111-1111-111111111111',
        );

        try {
            $client->createSpeakRoom('Test');
            self::fail('Expected RuntimeException');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('failed (400)', $exception->getMessage());
            self::assertStringContainsString('owner_id', $exception->getMessage());
            self::assertStringNotContainsString('Array to string conversion', $exception->getMessage());
        }
    }
}
