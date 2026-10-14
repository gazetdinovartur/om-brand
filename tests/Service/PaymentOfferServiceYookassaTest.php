<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\InquiryStatus;
use App\Enum\InquiryType;
use App\Enum\PaymentOfferStatus;
use App\Infrastructure\Yookassa\YookassaClient;
use App\Service\PaymentOfferService;
use App\Tests\Support\PracticePaymentFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PaymentOfferServiceYookassaTest extends KernelTestCase
{
    use PracticePaymentFixtures;

    private EntityManagerInterface $em;
    private PaymentOfferService $offers;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testCreateForInquiryAttachesYookassaPayment(): void
    {
        $paymentId = $this->uniqueYookassaPaymentId('create');
        $this->wireYookassa($this->mockYookassaClient([
            $this->yookassaCreateResponse($paymentId, 'https://yookassa.test/pay/1'),
        ]));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());

        $offer = $this->offers->createForInquiry(
            inquiry: $inquiry,
            title: 'Практика — разовая',
            amountKopecks: 222_00,
            siteUser: $user,
            notifyTelegram: false,
            createYookassaPayment: true,
            payerEmail: (string) $user->getEmail(),
        );

        self::assertSame(PaymentOfferStatus::Pending, $offer->getStatus());
        self::assertSame($paymentId, $offer->getYookassaPaymentId());
        self::assertSame('https://yookassa.test/pay/1', $offer->getPaymentUrl());
        self::assertSame($user->getId(), $offer->getSiteUser()?->getId());
    }

    public function testCreateForInquiryMarksFreeOfferPaidWithoutYookassa(): void
    {
        $this->wireYookassa(new YookassaClient(new MockHttpClient(), '', ''));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());

        $offer = $this->offers->createForInquiry(
            inquiry: $inquiry,
            title: 'Первая встреча',
            amountKopecks: 0,
            siteUser: $user,
            notifyTelegram: false,
            markPaid: true,
            createYookassaPayment: false,
        );

        self::assertSame(PaymentOfferStatus::Paid, $offer->getStatus());
        self::assertNotNull($offer->getPaidAt());
        self::assertNull($offer->getYookassaPaymentId());
    }

    public function testMarkAsPaidIsIdempotent(): void
    {
        $this->wireYookassa($this->mockYookassaClient([]));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());
        $offer = $this->createPendingOffer(
            $this->em,
            $inquiry,
            $user,
            yookassaPaymentId: $this->uniqueYookassaPaymentId('idem'),
        );

        self::assertTrue($this->offers->markAsPaid($offer));
        self::assertSame(PaymentOfferStatus::Paid, $offer->getStatus());
        self::assertNotNull($offer->getPaidAt());

        $paidAt = $offer->getPaidAt();
        self::assertFalse($this->offers->markAsPaid($offer));
        self::assertSame($paidAt?->getTimestamp(), $offer->getPaidAt()?->getTimestamp());
    }

    public function testMarkAsPaidClosesPracticeInquiry(): void
    {
        $this->wireYookassa($this->mockYookassaClient([]));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());
        self::assertSame(InquiryStatus::New, $inquiry->getStatus());
        self::assertSame(InquiryType::Practice, $inquiry->getInquiryType());

        $offer = $this->createPendingOffer(
            $this->em,
            $inquiry,
            $user,
            yookassaPaymentId: $this->uniqueYookassaPaymentId('close'),
        );

        self::assertTrue($this->offers->markAsPaid($offer));
        $this->em->refresh($inquiry);
        self::assertSame(InquiryStatus::Done, $inquiry->getStatus());
    }

    public function testCreateFreePracticeOfferClosesInquiryWithoutYookassa(): void
    {
        $this->wireYookassa(new YookassaClient(new MockHttpClient(), '', ''));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());

        $offer = $this->offers->createForInquiry(
            inquiry: $inquiry,
            title: 'Первая встреча',
            amountKopecks: 0,
            siteUser: $user,
            notifyTelegram: false,
            markPaid: true,
            createYookassaPayment: false,
        );

        self::assertSame(PaymentOfferStatus::Paid, $offer->getStatus());
        $this->em->refresh($inquiry);
        self::assertSame(InquiryStatus::Done, $inquiry->getStatus());
    }

    public function testAttachYookassaPaymentRequiresValidEmail(): void
    {
        $this->wireYookassa($this->mockYookassaClient([]));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($this->em, $inquiry, $user);

        $this->expectException(\InvalidArgumentException::class);
        $this->offers->attachYookassaPayment($offer, 'not-an-email');
    }

    public function testSyncFromYookassaMarksSucceededAsPaid(): void
    {
        $paymentId = $this->uniqueYookassaPaymentId('sync-ok');
        $this->wireYookassa($this->mockYookassaClient([
            $this->yookassaVerifyResponse('succeeded', $paymentId),
        ]));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());
        $this->createPendingOffer($this->em, $inquiry, $user, yookassaPaymentId: $paymentId);

        $synced = $this->offers->syncFromYookassa($paymentId);

        self::assertNotNull($synced);
        self::assertSame(PaymentOfferStatus::Paid, $synced->getStatus());
    }

    public function testSyncFromYookassaMarksCanceled(): void
    {
        $paymentId = $this->uniqueYookassaPaymentId('sync-cancel');
        $this->wireYookassa($this->mockYookassaClient([
            $this->yookassaVerifyResponse('canceled', $paymentId),
        ]));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());
        $this->createPendingOffer($this->em, $inquiry, $user, yookassaPaymentId: $paymentId);

        $synced = $this->offers->syncFromYookassa($paymentId);

        self::assertSame(PaymentOfferStatus::Cancelled, $synced?->getStatus());
    }

    public function testSyncFromYookassaReturnsNullForUnknownPayment(): void
    {
        $this->wireYookassa($this->mockYookassaClient([]));

        self::assertNull($this->offers->syncFromYookassa($this->uniqueYookassaPaymentId('unknown')));
    }

    public function testHandleYookassaWebhookVerifiesAndPays(): void
    {
        $paymentId = $this->uniqueYookassaPaymentId('wh');
        $this->wireYookassa($this->mockYookassaClient([
            $this->yookassaVerifyResponse('succeeded', $paymentId),
        ]));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($this->em, $inquiry, $user, yookassaPaymentId: $paymentId);

        $this->offers->handleYookassaWebhook([
            'event' => 'payment.succeeded',
            'object' => ['id' => $paymentId, 'status' => 'succeeded'],
        ]);

        $this->em->refresh($offer);
        self::assertSame(PaymentOfferStatus::Paid, $offer->getStatus());
    }

    public function testHandleYookassaWebhookIgnoresUnknownId(): void
    {
        $this->wireYookassa($this->mockYookassaClient([]));

        $this->offers->handleYookassaWebhook([
            'event' => 'payment.succeeded',
            'object' => ['id' => $this->uniqueYookassaPaymentId('foreign')],
        ]);

        $this->addToAssertionCount(1);
    }

    public function testSyncOfferFromYookassaIfPendingSkipsAlreadyPaid(): void
    {
        $calls = 0;
        $this->wireYookassa($this->mockYookassaClient(static function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse('{}', ['http_code' => 500]);
        }));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());
        $offer = $this->createPendingOffer(
            $this->em,
            $inquiry,
            $user,
            yookassaPaymentId: $this->uniqueYookassaPaymentId('skip'),
        );
        $this->offers->markAsPaid($offer);

        $result = $this->offers->syncOfferFromYookassaIfPending($offer);

        self::assertSame(PaymentOfferStatus::Paid, $result->getStatus());
        self::assertSame(0, $calls);
    }

    public function testGetPayUrlPrefersDirectGatewayUrl(): void
    {
        $this->wireYookassa($this->mockYookassaClient([]));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());
        $offer = $this->createPendingOffer(
            $this->em,
            $inquiry,
            $user,
            paymentUrl: 'https://yookassa.test/direct',
        );

        self::assertSame('https://yookassa.test/direct', $this->offers->getPayUrl($offer));
        self::assertStringContainsString('/oplata/', $this->offers->getLegacyClientUrl($offer));
        self::assertSame('https://yookassa.test/direct', $this->offers->getClientUrl($offer));
    }

    public function testMarkAsPaidDoesNotCloseNonPracticeInquiry(): void
    {
        $this->wireYookassa($this->mockYookassaClient([]));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry(
            $this->em,
            (string) $user->getEmail(),
            type: InquiryType::Consultation,
        );
        $offer = $this->createPendingOffer(
            $this->em,
            $inquiry,
            $user,
            yookassaPaymentId: $this->uniqueYookassaPaymentId('consult'),
        );

        self::assertTrue($this->offers->markAsPaid($offer));
        $this->em->refresh($inquiry);
        self::assertSame(InquiryStatus::New, $inquiry->getStatus());
    }

    public function testCreateForInquiryWithoutYookassaDoesNotBuildSbpUrl(): void
    {
        $this->wireYookassa(new YookassaClient(new MockHttpClient(), '', ''));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());

        $offer = $this->offers->createForInquiry(
            inquiry: $inquiry,
            title: 'Без шлюза',
            amountKopecks: 100_00,
            siteUser: $user,
            notifyTelegram: false,
            createYookassaPayment: false,
        );

        self::assertSame(PaymentOfferStatus::Pending, $offer->getStatus());
        self::assertNull($offer->getPaymentUrl());
        self::assertNull($offer->getYookassaPaymentId());
    }

    public function testAttachYookassaRequiresConfiguredClient(): void
    {
        $this->wireYookassa(new YookassaClient(new MockHttpClient(), '', ''));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($this->em, $inquiry, $user, paymentUrl: null);

        $this->expectException(\RuntimeException::class);
        $this->offers->attachYookassaPayment($offer, (string) $user->getEmail());
    }

    public function testMarkAsCancelledIgnoresAlreadyPaid(): void
    {
        $this->wireYookassa($this->mockYookassaClient([]));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());
        $offer = $this->createPendingOffer(
            $this->em,
            $inquiry,
            $user,
            yookassaPaymentId: $this->uniqueYookassaPaymentId('cancel-paid'),
        );
        $this->offers->markAsPaid($offer);

        $this->offers->markAsCancelled($offer);
        self::assertSame(PaymentOfferStatus::Paid, $offer->getStatus());
    }

    public function testMarkAsCancelledSetsCancelledOnPending(): void
    {
        $this->wireYookassa($this->mockYookassaClient([]));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($this->em, $inquiry, $user);

        $this->offers->markAsCancelled($offer);
        self::assertSame(PaymentOfferStatus::Cancelled, $offer->getStatus());
    }

    public function testGetValidOfferExpiresPendingPastExpiresAt(): void
    {
        $this->wireYookassa($this->mockYookassaClient([]));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($this->em, $inquiry, $user);
        $offer->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $this->em->flush();

        try {
            $this->offers->getValidOffer($offer->getToken());
            self::fail('Expected NotFoundHttpException for expired offer');
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            self::assertStringContainsString('истёк', $e->getMessage());
        }

        $this->em->refresh($offer);
        self::assertSame(PaymentOfferStatus::Expired, $offer->getStatus());
    }

    public function testUnlinkFromSiteUserKeepsInquiry(): void
    {
        $this->wireYookassa($this->mockYookassaClient([]));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($this->em, $inquiry, $user);

        $this->offers->unlinkFromSiteUser($offer);
        self::assertNull($offer->getSiteUser());
        self::assertSame($inquiry->getId(), $offer->getInquiry()?->getId());
    }

    public function testSyncSucceededClosesPracticeInquiry(): void
    {
        $paymentId = $this->uniqueYookassaPaymentId('sync-close');
        $this->wireYookassa($this->mockYookassaClient([
            $this->yookassaVerifyResponse('succeeded', $paymentId),
        ]));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());
        $this->createPendingOffer($this->em, $inquiry, $user, yookassaPaymentId: $paymentId);

        $this->offers->syncFromYookassa($paymentId);
        $this->em->refresh($inquiry);
        self::assertSame(InquiryStatus::Done, $inquiry->getStatus());
    }

    private function wireYookassa(YookassaClient $client): void
    {
        $container = static::getContainer();
        $container->set(YookassaClient::class, $client);
        $this->offers = $container->get(PaymentOfferService::class);
    }
}
