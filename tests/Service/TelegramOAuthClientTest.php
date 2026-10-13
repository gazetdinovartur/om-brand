<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\TelegramOAuthClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\Cache\CacheInterface;

final class TelegramOAuthClientTest extends TestCase
{
    public function testNotConfiguredWithoutCredentials(): void
    {
        $client = $this->client(clientId: '', clientSecret: '');

        self::assertFalse($client->isConfigured());
        self::assertFalse($client->isAllowedOnHost('arturlun.ru'));
    }

    public function testHiddenOnLocalhostEvenWhenConfigured(): void
    {
        $client = $this->client();

        self::assertTrue($client->isConfigured());
        self::assertFalse($client->isAllowedOnHost('localhost'));
        self::assertFalse($client->isAllowedOnHost('127.0.0.1'));
        self::assertFalse($client->isAllowedOnHost('arturlun.ru')); // DEFAULT_URI is localhost in this case
    }

    public function testAllowedWhenHostMatchesPublicDefaultUri(): void
    {
        $client = $this->client(defaultUri: 'https://arturlun.ru');

        self::assertTrue($client->isAllowedOnHost('arturlun.ru'));
        self::assertTrue($client->isAllowedOnHost('www.arturlun.ru'));
        self::assertFalse($client->isAllowedOnHost('localhost'));
    }

    public function testBeginAuthorizationStoresPkceAndReturnsAuthorizeUrl(): void
    {
        $client = $this->client(defaultUri: 'https://arturlun.ru');
        $session = new Session(new MockArraySessionStorage());

        $url = $client->beginAuthorization($session, '/library/book/demo');

        self::assertStringStartsWith('https://oauth.telegram.org/auth?', $url);
        self::assertStringContainsString('response_type=code', $url);
        self::assertStringContainsString('client_id=42', $url);
        self::assertStringContainsString('code_challenge_method=S256', $url);
        self::assertStringContainsString(rawurlencode('https://arturlun.ru/library/login/telegram/callback'), $url);
        self::assertNotSame('', (string) $session->get('telegram_oauth.state'));
        self::assertNotSame('', (string) $session->get('telegram_oauth.verifier'));
        self::assertSame('/library/book/demo', $session->get('telegram_oauth.target'));
    }

    public function testCompleteAuthorizationRejectsInvalidState(): void
    {
        $client = $this->client(defaultUri: 'https://arturlun.ru');
        $session = new Session(new MockArraySessionStorage());
        $client->beginAuthorization($session, '/library');

        $result = $client->completeAuthorization($session, 'code', 'wrong-state');

        self::assertFalse($result['ok']);
        self::assertStringContainsString('Сессия', $result['message']);
    }

    private function client(
        string $clientId = '42',
        string $clientSecret = 'secret',
        string $defaultUri = 'http://localhost:8085',
        ?CacheInterface $cache = null,
    ): TelegramOAuthClient {
        return new TelegramOAuthClient(
            new MockHttpClient(static fn (): MockResponse => new MockResponse('{}')),
            $cache ?? new ArrayAdapter(),
            $clientId,
            $clientSecret,
            $defaultUri,
        );
    }
}
