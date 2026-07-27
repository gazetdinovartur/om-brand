<?php

namespace App\Tests\Service;

use App\Entity\EmailSubscriber;
use App\Enum\EmailSubscriberStatus;
use App\Service\EmailSubscriptionService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class EmailSubscriptionServiceTest extends KernelTestCase
{
    private EmailSubscriptionService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = static::getContainer()->get(EmailSubscriptionService::class);
    }

    public function testRequestSubscribeRejectsInvalidEmail(): void
    {
        $result = $this->service->requestSubscribe('not-an-email');

        self::assertFalse($result['ok']);
        self::assertSame('Укажите корректный email.', $result['message']);
    }

    public function testConfirmReturnsNullForInvalidToken(): void
    {
        self::assertNull($this->service->confirm(''));
        self::assertNull($this->service->confirm('short'));
        self::assertNull($this->service->confirm(str_repeat('a', 64)));
    }

    public function testConfirmActivatesPendingSubscriber(): void
    {
        $token = bin2hex(random_bytes(32));
        $subscriber = new EmailSubscriber('confirm-'.bin2hex(random_bytes(4)).'@example.test');
        $subscriber->markPendingForConfirm();

        $em = static::getContainer()->get('doctrine')->getManager();
        $em->persist($subscriber);
        $em->flush();

        $confirmToken = $subscriber->getConfirmToken();
        $confirmed = $this->service->confirm($confirmToken);

        self::assertNotNull($confirmed);
        self::assertTrue($confirmed->isConfirmed());
        self::assertSame(EmailSubscriberStatus::Confirmed, $confirmed->getStatus());
        self::assertNotNull($confirmed->getConfirmedAt());
        self::assertNotSame($confirmToken, $confirmed->getConfirmToken());
    }

    public function testUnsubscribeMarksSubscriberUnsubscribed(): void
    {
        $subscriber = new EmailSubscriber('unsub-'.bin2hex(random_bytes(4)).'@example.test');
        $subscriber->confirm();

        $em = static::getContainer()->get('doctrine')->getManager();
        $em->persist($subscriber);
        $em->flush();

        $unsubscribeToken = $subscriber->getUnsubscribeToken();
        $result = $this->service->unsubscribe($unsubscribeToken);

        self::assertNotNull($result);
        self::assertSame(EmailSubscriberStatus::Unsubscribed, $result->getStatus());
        self::assertNotSame($unsubscribeToken, $result->getUnsubscribeToken());
    }
}
