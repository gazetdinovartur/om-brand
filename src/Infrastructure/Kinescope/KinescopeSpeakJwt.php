<?php

declare(strict_types=1);

namespace App\Infrastructure\Kinescope;

use Firebase\JWT\JWT;

/**
 * Speak host JWT (aud=speak, role=admin). Requires RSA private key + kid registered in Kinescope.
 */
final class KinescopeSpeakJwt
{
    private readonly string $privateKeyPem;

    public function __construct(
        string $privateKeyPathOrPem = '',
        private readonly string $kid = '',
    ) {
        $this->privateKeyPem = self::resolvePem($privateKeyPathOrPem);
    }

    public function isConfigured(): bool
    {
        return '' !== $this->privateKeyPem && '' !== trim($this->kid);
    }

    public function buildAdminEmbedUrl(string $roomCode, int $ttlSeconds = 3600): string
    {
        $roomCode = trim($roomCode);
        if ('' === $roomCode) {
            throw new \InvalidArgumentException('Speak room code is empty.');
        }

        $base = 'https://speak.kinescope.io/'.$roomCode;
        if (!$this->isConfigured()) {
            return $base;
        }

        return $base.'?token='.$this->signAdminToken($roomCode, $ttlSeconds);
    }

    public function signAdminToken(string $roomCode, int $ttlSeconds = 3600): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Speak JWT is not configured (KINESCOPE_JWT_PRIVATE_KEY_PATH / KINESCOPE_JWT_KID).');
        }

        $now = time();
        $payload = [
            'aud' => 'speak',
            'room_id' => trim($roomCode),
            'role' => 'admin',
            'iat' => $now,
            'nbf' => $now - 5,
            'exp' => $now + max(300, $ttlSeconds),
        ];

        return JWT::encode($payload, $this->privateKeyPem, 'RS256', $this->kid);
    }

    private static function resolvePem(string $pathOrPem): string
    {
        $value = trim(str_replace(["\\n", "\r\n"], ["\n", "\n"], $pathOrPem));
        if ('' === $value) {
            return '';
        }

        if (str_contains($value, 'BEGIN') && str_contains($value, 'PRIVATE KEY')) {
            return $value;
        }

        if (is_file($value) && is_readable($value)) {
            $contents = file_get_contents($value);

            return false === $contents ? '' : trim($contents);
        }

        return '';
    }
}
