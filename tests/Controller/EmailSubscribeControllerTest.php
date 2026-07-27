<?php

namespace App\Tests\Controller;

use App\Entity\EmailSubscriber;
use App\Enum\EmailSubscriberStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EmailSubscribeControllerTest extends WebTestCase
{
    public function testSubscribeRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/email/subscribe',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'csrf@example.test'], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(403);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertFalse($payload['ok']);
    }

    public function testSubscribeRejectsInvalidEmail(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/email/subscribe',
            server: $this->subscribeHeaders($client),
            content: json_encode(['email' => 'bad'], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertFalse($payload['ok']);
    }

    public function testConfirmPageShowsSuccessForValidToken(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $subscriber = new EmailSubscriber('confirm-page-'.bin2hex(random_bytes(4)).'@example.test');
        $em->persist($subscriber);
        $em->flush();

        $client->request('GET', '/subscribe/email/confirm/'.$subscriber->getConfirmToken());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Подписка подтверждена', (string) $client->getResponse()->getContent());

        $em->refresh($subscriber);
        self::assertTrue($subscriber->isConfirmed());
    }

    public function testUnsubscribePageShowsSuccessForValidToken(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $subscriber = new EmailSubscriber('unsub-page-'.bin2hex(random_bytes(4)).'@example.test');
        $subscriber->confirm();
        $em->persist($subscriber);
        $em->flush();

        $client->request('GET', '/subscribe/email/unsubscribe/'.$subscriber->getUnsubscribeToken());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Вы отписались', (string) $client->getResponse()->getContent());

        $em->refresh($subscriber);
        self::assertSame(EmailSubscriberStatus::Unsubscribed, $subscriber->getStatus());
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
