<?php

namespace App\Tests\Controller;

use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PushSubscribeControllerTest extends WebTestCase
{
    public function testSubscribeRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/push/subscribe',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'endpoint' => 'https://push.example.test/no-csrf',
                'keys' => ['p256dh' => 'k', 'auth' => 'a'],
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testSubscribePersistsSubscription(): void
    {
        $client = static::createClient();
        $token = bin2hex(random_bytes(4));
        $endpoint = 'https://push.example.test/api/'.$token;

        $client->request(
            'POST',
            '/api/push/subscribe',
            server: $this->subscribeHeaders($client),
            content: json_encode([
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => 'p256dh-'.bin2hex(random_bytes(8)),
                    'auth' => 'auth-'.bin2hex(random_bytes(8)),
                ],
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);

        $repo = static::getContainer()->get(PushSubscriptionRepository::class);
        self::assertNotNull($repo->findOneByEndpoint($endpoint));
    }

    public function testUnsubscribeRemovesSubscription(): void
    {
        $client = static::createClient();
        $endpoint = 'https://push.example.test/remove/'.bin2hex(random_bytes(4));

        $client->request(
            'POST',
            '/api/push/subscribe',
            server: $this->subscribeHeaders($client),
            content: json_encode([
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => 'p256dh-'.bin2hex(random_bytes(8)),
                    'auth' => 'auth-'.bin2hex(random_bytes(8)),
                ],
            ], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();

        $client->request(
            'POST',
            '/api/push/unsubscribe',
            server: $this->subscribeHeaders($client),
            content: json_encode(['endpoint' => $endpoint], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);

        $repo = static::getContainer()->get(PushSubscriptionRepository::class);
        self::assertNull($repo->findOneByEndpoint($endpoint));
    }

    /**
     * @return array<string, string>
     */
    private function subscribeHeaders(KernelBrowser $client): array
    {
        $crawler = $client->request('GET', '/chronicle');
        self::assertGreaterThan(0, $crawler->filter('[data-csrf]')->count());
        $csrf = $crawler->filter('[data-csrf]')->attr('data-csrf');

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-CSRF-TOKEN' => $csrf,
        ];
    }
}
