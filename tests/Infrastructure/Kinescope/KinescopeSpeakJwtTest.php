<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Kinescope;

use App\Infrastructure\Kinescope\KinescopeSpeakJwt;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;

final class KinescopeSpeakJwtTest extends TestCase
{
    public function testNotConfiguredWithoutKey(): void
    {
        $jwt = new KinescopeSpeakJwt('', 'kid-1');
        self::assertFalse($jwt->isConfigured());
        self::assertSame(
            'https://speak.kinescope.io/abc-defg-hij',
            $jwt->buildAdminEmbedUrl('abc-defg-hij'),
        );
    }

    public function testSignsAdminTokenWithKid(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($key);
        $privatePem = '';
        self::assertTrue(openssl_pkey_export($key, $privatePem));
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        $publicPem = $details['key'];

        $jwt = new KinescopeSpeakJwt($privatePem, 'test-kid');
        self::assertTrue($jwt->isConfigured());

        $token = $jwt->signAdminToken('room-code-1', 600);
        $decoded = JWT::decode($token, new Key($publicPem, 'RS256'));

        self::assertSame('speak', $decoded->aud);
        self::assertSame('room-code-1', $decoded->room_id);
        self::assertSame('admin', $decoded->role);

        $url = $jwt->buildAdminEmbedUrl('room-code-1', 600);
        self::assertStringStartsWith('https://speak.kinescope.io/room-code-1?token=', $url);
    }
}
