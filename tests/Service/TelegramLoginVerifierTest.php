<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\TelegramLoginVerifier;
use PHPUnit\Framework\TestCase;

final class TelegramLoginVerifierTest extends TestCase
{
    public function testAcceptsValidTelegramPayload(): void
    {
        $token = '123456:ABC-DEF';
        $verifier = new TelegramLoginVerifier($token);
        $payload = [
            'id' => 42,
            'first_name' => 'Иван',
            'username' => 'ivan',
            'auth_date' => time(),
        ];
        $payload['hash'] = $this->sign($payload, $token);

        $result = $verifier->verify($payload);

        self::assertTrue($result['ok']);
        self::assertSame(42, $result['user']['id']);
        self::assertSame('ivan', $result['user']['username']);
    }

    public function testRejectsTamperedPayload(): void
    {
        $token = '123456:ABC-DEF';
        $verifier = new TelegramLoginVerifier($token);
        $payload = [
            'id' => 42,
            'first_name' => 'Иван',
            'auth_date' => time(),
            'hash' => 'deadbeef',
        ];

        $result = $verifier->verify($payload);

        self::assertFalse($result['ok']);
    }

    /** @param array<string, scalar> $payload */
    private function sign(array $payload, string $botToken): string
    {
        $check = $payload;
        unset($check['hash']);
        ksort($check);
        $parts = [];
        foreach ($check as $key => $value) {
            $parts[] = $key.'='.$value;
        }

        return hash_hmac('sha256', implode("\n", $parts), hash('sha256', $botToken, true));
    }
}
