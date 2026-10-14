<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Yookassa;

use App\Infrastructure\Yookassa\YookassaClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class YookassaClientTest extends TestCase
{
    public function testIsConfiguredRequiresShopAndSecret(): void
    {
        self::assertFalse((new YookassaClient(new MockHttpClient(), '', ''))->isConfigured());
        self::assertFalse((new YookassaClient(new MockHttpClient(), 'shop', ''))->isConfigured());
        self::assertFalse((new YookassaClient(new MockHttpClient(), '', 'secret'))->isConfigured());
        self::assertTrue((new YookassaClient(new MockHttpClient(), 'shop', 'secret'))->isConfigured());
    }

    public function testCreatePaymentSendsReceiptAndReturnsGatewayUrl(): void
    {
        $captured = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = [
                'method' => $method,
                'url' => $url,
                'headers' => $options['headers'] ?? [],
                'body' => json_decode((string) ($options['body'] ?? ''), true, 512, \JSON_THROW_ON_ERROR),
            ];

            return new MockResponse(json_encode([
                'id' => 'pay-abc',
                'confirmation' => [
                    'confirmation_url' => 'https://yoomoney.ru/checkout/pay-abc',
                ],
            ], \JSON_THROW_ON_ERROR));
        });

        $client = new YookassaClient($http, 'shop-1', 'secret-1');
        $result = $client->createPayment(
            email: 'payer@example.test',
            amountRubles: 222,
            description: 'Практика разовая',
            returnUrl: 'https://arturlun.ru/account?tab=practice&yk_return=1&token=tok',
            metadata: ['offer_token' => 'tok'],
        );

        self::assertSame('pay-abc', $result->paymentId);
        self::assertSame('https://yoomoney.ru/checkout/pay-abc', $result->gatewayUrl);
        self::assertSame('POST', $captured['method']);
        self::assertStringContainsString('/v3/payments', $captured['url']);
        self::assertSame('222.00', $captured['body']['amount']['value']);
        self::assertSame('payer@example.test', $captured['body']['receipt']['customer']['email']);
        self::assertSame(6, $captured['body']['receipt']['items'][0]['vat_code']);
        self::assertSame('tok', $captured['body']['metadata']['offer_token']);

        $headerBlob = json_encode($captured['headers'], \JSON_THROW_ON_ERROR);
        self::assertStringContainsString('Idempotence-Key', $headerBlob);
        self::assertStringContainsString('Basic ', $headerBlob);
    }

    public function testCreatePaymentFailsWhenNotConfigured(): void
    {
        $client = new YookassaClient(new MockHttpClient(), '', '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('YooKassa is not configured');
        $client->createPayment('a@b.c', 100, 'x', 'https://example.test/return');
    }

    public function testCreatePaymentRejectsInvalidApiResponse(): void
    {
        $http = new MockHttpClient([
            new MockResponse('{"id":"only-id"}', ['http_code' => 200]),
        ]);
        $client = new YookassaClient($http, 'shop', 'secret');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid YooKassa API response');
        $client->createPayment('a@b.c', 100, 'x', 'https://example.test/return');
    }

    public function testVerifyPaymentReturnsStatusPayload(): void
    {
        $http = new MockHttpClient([
            new MockResponse(json_encode([
                'id' => 'pay-1',
                'status' => 'succeeded',
            ], \JSON_THROW_ON_ERROR)),
        ]);
        $client = new YookassaClient($http, 'shop', 'secret');

        $payload = $client->verifyPayment('pay-1');

        self::assertSame('succeeded', $payload['status']);
        self::assertSame('pay-1', $payload['id']);
    }

    public function testVerifyPaymentFailsOnHttpError(): void
    {
        $http = new MockHttpClient([
            new MockResponse('nope', ['http_code' => 404]),
        ]);
        $client = new YookassaClient($http, 'shop', 'secret');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('YooKassa payment verification failed');
        $client->verifyPayment('missing');
    }
}
