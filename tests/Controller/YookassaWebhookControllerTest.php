<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Inquiry;
use App\Enum\InquiryStatus;
use App\Enum\PaymentOfferStatus;
use App\Infrastructure\Yookassa\YookassaClient;
use App\Tests\Support\PracticePaymentFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class YookassaWebhookControllerTest extends WebTestCase
{
    use PracticePaymentFixtures;

    public function testWebhookIsPublicAndMarksOfferPaidAfterVerify(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $paymentId = $this->uniqueYookassaPaymentId('http');
        $container->set(YookassaClient::class, $this->mockYookassaClient([
            $this->yookassaVerifyResponse('succeeded', $paymentId),
        ]));

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $user = $this->createSiteUser($em);
        $inquiry = $this->createInquiry($em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $user, yookassaPaymentId: $paymentId);

        $client->request(
            'POST',
            '/webhooks/yookassa',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'event' => 'payment.succeeded',
                'object' => [
                    'id' => $paymentId,
                    'status' => 'succeeded',
                ],
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);

        $em->clear();
        $fresh = $em->find($offer::class, $offer->getId());
        self::assertSame(PaymentOfferStatus::Paid, $fresh?->getStatus());
        $freshInquiry = $em->find(Inquiry::class, $inquiry->getId());
        self::assertSame(InquiryStatus::Done, $freshInquiry?->getStatus());
    }

    public function testWebhookReturnsOkForUnknownPaymentId(): void
    {
        $client = static::createClient();
        static::getContainer()->set(YookassaClient::class, $this->mockYookassaClient([]));

        $client->request(
            'POST',
            '/webhooks/yookassa',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'event' => 'payment.succeeded',
                'object' => ['id' => $this->uniqueYookassaPaymentId('foreign')],
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);
    }

    public function testWebhookAcceptsInvalidJsonWithOk(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/webhooks/yookassa',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{not-json',
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);
    }

    public function testWebhookMarksCanceled(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $paymentId = $this->uniqueYookassaPaymentId('http-cancel');
        $container->set(YookassaClient::class, $this->mockYookassaClient([
            $this->yookassaVerifyResponse('canceled', $paymentId),
        ]));

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $user = $this->createSiteUser($em);
        $inquiry = $this->createInquiry($em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $user, yookassaPaymentId: $paymentId);

        $client->request(
            'POST',
            '/webhooks/yookassa',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'event' => 'payment.canceled',
                'object' => ['id' => $paymentId],
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $em->clear();
        $fresh = $em->find($offer::class, $offer->getId());
        self::assertSame(PaymentOfferStatus::Cancelled, $fresh?->getStatus());
    }
}
