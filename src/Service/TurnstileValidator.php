<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TurnstileValidator
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(TURNSTILE_SECRET_KEY)%')]
        private readonly string $secretKey = '',
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->secretKey;
    }

    public function validate(?string $token, ?string $remoteIp): bool
    {
        if (!$this->isConfigured()) {
            return true;
        }

        if (null === $token || '' === trim($token)) {
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'body' => [
                    'secret' => $this->secretKey,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ],
            ]);

            $data = $response->toArray(false);

            return ($data['success'] ?? false) === true;
        } catch (\Throwable) {
            return false;
        }
    }
}
