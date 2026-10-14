<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\PaymentOfferStatus;
use App\Enum\PracticeSessionStatus;
use App\Service\PracticeSessionService;
use App\Tests\Support\PracticePaymentFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PracticeSessionServiceTest extends KernelTestCase
{
    use PracticePaymentFixtures;

    public function testFinishWithRecordingSetsReplayAndDebitsOnce(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var PracticeSessionService $service */
        $service = $container->get(PracticeSessionService::class);

        $user = $this->createSiteUser($em);
        $inquiry = $this->createInquiry($em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $user, amountKopecks: 555_00);
        $offer->setStatus(PaymentOfferStatus::Paid)->setPaidAt(new \DateTimeImmutable());
        $em->flush();

        self::assertSame(4, $offer->getSessionsLeft());

        $session = $this->createPracticeSession(
            $em,
            status: PracticeSessionStatus::Live,
            eventId: 'evt-finish-'.bin2hex(random_bytes(3)),
        );

        $first = $service->finishWithRecording($session, 'vid-123');
        self::assertTrue($first['debited']);
        self::assertSame(PracticeSessionStatus::Replay, $session->getStatus());
        self::assertSame('vid-123', $session->getKinescopeVideoId());
        self::assertTrue($session->isDebited());

        $em->refresh($offer);
        self::assertSame(3, $offer->getSessionsLeft());

        $second = $service->finishWithRecording($session, 'vid-123');
        self::assertFalse($second['debited']);
        $em->refresh($offer);
        self::assertSame(3, $offer->getSessionsLeft());
    }

    public function testArchiveDoesNotDebitAgain(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var PracticeSessionService $service */
        $service = $container->get(PracticeSessionService::class);

        $user = $this->createSiteUser($em);
        $inquiry = $this->createInquiry($em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $user);
        $offer->setStatus(PaymentOfferStatus::Paid)->setPaidAt(new \DateTimeImmutable());
        $em->flush();

        $session = $this->createPracticeSession($em, status: PracticeSessionStatus::Live, eventId: 'evt-arch');
        $service->finishWithRecording($session, 'vid-arch');
        $em->refresh($offer);
        $leftAfterFinish = $offer->getSessionsLeft();

        $service->archive($session);
        self::assertSame(PracticeSessionStatus::Completed, $session->getStatus());
        $em->refresh($offer);
        self::assertSame($leftAfterFinish, $offer->getSessionsLeft());
    }

    public function testWebhookHandlerMatchesEventId(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var PracticeSessionService $service */
        $service = $container->get(PracticeSessionService::class);

        $eventId = 'evt-hook-'.bin2hex(random_bytes(3));
        $session = $this->createPracticeSession($em, status: PracticeSessionStatus::Live, eventId: $eventId);

        self::assertTrue($service->handleLiveFinishedWebhook($eventId, 'vid-hook'));
        $em->refresh($session);
        self::assertSame(PracticeSessionStatus::Replay, $session->getStatus());
        self::assertSame('vid-hook', $session->getKinescopeVideoId());

        self::assertFalse($service->handleLiveFinishedWebhook('unknown-event', 'vid-x'));
    }
}
