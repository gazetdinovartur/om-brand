<?php

namespace App\Tests\Service;

use App\Entity\PushSubscription;
use App\Repository\PushSubscriptionRepository;
use App\Service\PushSubscriptionService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PushSubscriptionServiceTest extends KernelTestCase
{
    private PushSubscriptionService $service;

    private PushSubscriptionRepository $subscriptions;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = static::getContainer()->get(PushSubscriptionService::class);
        $this->subscriptions = static::getContainer()->get(PushSubscriptionRepository::class);
    }

    public function testSubscribeRejectsInvalidEndpoint(): void
    {
        $request = Request::create('/');
        $response = new Response();

        $result = $this->service->subscribe([
            'endpoint' => 'not-a-url',
            'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
        ], $request, $response);

        self::assertFalse($result['ok']);
        self::assertSame('Некорректный endpoint подписки.', $result['message']);
    }

    public function testSubscribePersistsSubscription(): void
    {
        $token = bin2hex(random_bytes(4));
        $endpoint = 'https://push.example.test/subscribe/'.$token;
        $request = Request::create('/');
        $response = new Response();

        $result = $this->service->subscribe([
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'p256dh-key-'.bin2hex(random_bytes(8)),
                'auth' => 'auth-key-'.bin2hex(random_bytes(8)),
            ],
        ], $request, $response);

        self::assertTrue($result['ok']);
        self::assertSame('Подписка на уведомления включена.', $result['message']);

        $stored = $this->subscriptions->findOneByEndpoint($endpoint);
        self::assertInstanceOf(PushSubscription::class, $stored);
        self::assertNotNull($response->headers->getCookies());
    }

    public function testUnsubscribeRemovesSubscription(): void
    {
        $endpoint = 'https://push.example.test/remove/'.bin2hex(random_bytes(4));
        $row = new PushSubscription($endpoint, 'p256dh', 'auth');
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->persist($row);
        $em->flush();

        $result = $this->service->unsubscribe(['endpoint' => $endpoint]);

        self::assertTrue($result['ok']);
        self::assertNull($this->subscriptions->findOneByEndpoint($endpoint));
    }
}
