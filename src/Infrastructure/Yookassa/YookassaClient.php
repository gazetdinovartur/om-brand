<?php

namespace App\Infrastructure\Yookassa;

use App\Infrastructure\Yookassa\Dto\CreatePaymentResult;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Клиент API ЮKassa v3 (порт логики из hanumanfest).
 */
final class YookassaClient
{
    private const API_BASE = 'https://api.yookassa.ru/v3';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $shopId,
        private readonly string $secretKey,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->shopId) && '' !== trim($this->secretKey);
    }

    /**
     * @param array<string, string> $metadata
     */
    public function createPayment(
        string $email,
        int $amountRubles,
        string $description,
        string $returnUrl,
        array $metadata = [],
    ): CreatePaymentResult {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('YooKassa is not configured.');
        }

        $amountFormatted = number_format($amountRubles, 2, '.', '');
        $payload = [
            'amount' => [
                'value' => $amountFormatted,
                'currency' => 'RUB',
            ],
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $returnUrl,
            ],
            'capture' => true,
            'description' => mb_substr($description, 0, 128),
            'receipt' => [
                'customer' => [
                    'email' => $email,
                ],
                'items' => [
                    [
                        'description' => mb_substr($description, 0, 128),
                        'quantity' => '1.00',
                        'amount' => [
                            'value' => $amountFormatted,
                            'currency' => 'RUB',
                        ],
                        'vat_code' => 6,
                    ],
                ],
            ],
        ];

        if ([] !== $metadata) {
            $payload['metadata'] = $metadata;
        }

        $response = $this->httpClient->request('POST', self::API_BASE.'/payments', [
            'headers' => $this->authHeaders([
                'Idempotence-Key' => Uuid::v4()->toRfc4122(),
            ]),
            'json' => $payload,
            'timeout' => 20,
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new \RuntimeException('YooKassa payment creation failed: '.$response->getContent(false));
        }

        $body = $response->toArray();
        if (!isset($body['id'], $body['confirmation']['confirmation_url'])) {
            throw new \RuntimeException('Invalid YooKassa API response');
        }

        return new CreatePaymentResult(
            paymentId: (string) $body['id'],
            gatewayUrl: (string) $body['confirmation']['confirmation_url'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyPayment(string $paymentId): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('YooKassa is not configured.');
        }

        $response = $this->httpClient->request('GET', self::API_BASE.'/payments/'.$paymentId, [
            'headers' => $this->authHeaders(),
            'timeout' => 20,
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new \RuntimeException('YooKassa payment verification failed: '.$response->getContent(false));
        }

        $body = $response->toArray();
        if (!isset($body['status'])) {
            throw new \RuntimeException('Invalid YooKassa verification response');
        }

        return $body;
    }

    /**
     * @param array<string, string> $extra
     *
     * @return array<string, string>
     */
    private function authHeaders(array $extra = []): array
    {
        return array_merge([
            'Authorization' => 'Basic '.base64_encode($this->shopId.':'.$this->secretKey),
            'Content-Type' => 'application/json',
        ], $extra);
    }
}
