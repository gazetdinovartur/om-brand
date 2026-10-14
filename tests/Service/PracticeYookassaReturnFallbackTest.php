<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\PaymentOfferStatus;
use App\Infrastructure\Yookassa\YookassaClient;
use App\Repository\PaymentOfferRepository;
use App\Service\PaymentOfferService;
use App\Tests\Support\PracticePaymentFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Return-URL fallback (yk_return) и доступ в комнату — без HTTP-login:
 * loginUser в проекте сейчас не удерживает сессию на main (ломает и library-тесты).
 */
final class PracticeYookassaReturnFallbackTest extends KernelTestCase
{
    use PracticePaymentFixtures;

    private EntityManagerInterface $em;
    private PaymentOfferService $offers;
    private PaymentOfferRepository $offerRepo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->offerRepo = static::getContainer()->get(PaymentOfferRepository::class);
    }

    public function testReturnFallbackMarksSucceededPaymentAsPaid(): void
    {
        $paymentId = $this->uniqueYookassaPaymentId('ret-ok');
        $this->wireYookassa($this->mockYookassaClient([
            $this->yookassaVerifyResponse('succeeded', $paymentId),
        ]));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($this->em, $inquiry, $user, yookassaPaymentId: $paymentId);

        $synced = $this->offers->syncOfferFromYookassaIfPending($offer);

        self::assertSame(PaymentOfferStatus::Paid, $synced->getStatus());
        self::assertTrue($this->offerRepo->userHasPaidPracticeAccess($user));
    }

    public function testReturnFallbackLeavesPendingWhenGatewayStillProcessing(): void
    {
        $paymentId = $this->uniqueYookassaPaymentId('ret-pending');
        $this->wireYookassa($this->mockYookassaClient([
            $this->yookassaVerifyResponse('pending', $paymentId),
        ]));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($this->em, $inquiry, $user, yookassaPaymentId: $paymentId);

        $synced = $this->offers->syncOfferFromYookassaIfPending($offer);

        self::assertSame(PaymentOfferStatus::Pending, $synced->getStatus());
        self::assertFalse($this->offerRepo->userHasPaidPracticeAccess($user));
    }

    public function testReturnFallbackMarksCanceled(): void
    {
        $paymentId = $this->uniqueYookassaPaymentId('ret-cancel');
        $this->wireYookassa($this->mockYookassaClient([
            $this->yookassaVerifyResponse('canceled', $paymentId),
        ]));

        $user = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($this->em, $inquiry, $user, yookassaPaymentId: $paymentId);

        $synced = $this->offers->syncOfferFromYookassaIfPending($offer);

        self::assertSame(PaymentOfferStatus::Cancelled, $synced->getStatus());
        self::assertFalse($this->offerRepo->userHasPaidPracticeAccess($user));
    }

    public function testRoomAccessRequiresPaidOfferOnSameUser(): void
    {
        $owner = $this->createSiteUser($this->em);
        $other = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $owner->getEmail());
        $offer = $this->createPendingOffer($this->em, $inquiry, $owner);
        $offer->setStatus(PaymentOfferStatus::Paid)->setPaidAt(new \DateTimeImmutable());
        $this->em->flush();

        self::assertTrue($this->offerRepo->userHasPaidPracticeAccess($owner));
        self::assertFalse($this->offerRepo->userHasPaidPracticeAccess($other));
    }

    public function testOfferOwnershipMismatchIsDetectableForReturnGuard(): void
    {
        $owner = $this->createSiteUser($this->em);
        $intruder = $this->createSiteUser($this->em);
        $inquiry = $this->createInquiry($this->em, (string) $owner->getEmail());
        $offer = $this->createPendingOffer($this->em, $inquiry, $owner);

        self::assertNotSame($offer->getSiteUser()?->getId(), $intruder->getId());
        self::assertSame($offer->getSiteUser()?->getId(), $owner->getId());
    }

    private function wireYookassa(YookassaClient $client): void
    {
        $container = static::getContainer();
        $container->set(YookassaClient::class, $client);
        $this->offers = $container->get(PaymentOfferService::class);
    }
}
