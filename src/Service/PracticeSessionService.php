<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PaymentOffer;
use App\Entity\PracticeSession;
use App\Entity\PracticeSessionAttendance;
use App\Entity\SiteUser;
use App\Enum\PaymentOfferStatus;
use App\Enum\PracticeSessionStatus;
use App\Repository\PaymentOfferRepository;
use App\Repository\PracticeSessionAttendanceRepository;
use App\Repository\PracticeSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class PracticeSessionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PracticeSessionRepository $sessions,
        private readonly PaymentOfferRepository $offers,
        private readonly PracticeSessionAttendanceRepository $attendances,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * End of stream: replay + video id + debit attendees once. Idempotent.
     *
     * @return array{session: PracticeSession, debited: bool, changed: bool}
     */
    public function finishWithRecording(PracticeSession $session, ?string $videoId = null): array
    {
        $changed = false;
        $videoId = null !== $videoId && '' !== trim($videoId) ? trim($videoId) : null;

        if (null !== $videoId && $session->getKinescopeVideoId() !== $videoId) {
            $session->setKinescopeVideoId($videoId);
            $changed = true;
        }

        if (
            PracticeSessionStatus::Completed !== $session->getStatus()
            && PracticeSessionStatus::Replay !== $session->getStatus()
        ) {
            $session->setStatus(PracticeSessionStatus::Replay);
            $changed = true;
        }

        $debited = $this->debitOnce($session);
        if ($changed || $debited) {
            $session->touch();
            $this->entityManager->flush();
        }

        return [
            'session' => $session,
            'debited' => $debited,
            'changed' => $changed || $debited,
        ];
    }

    /**
     * Archive session without a second debit.
     */
    public function archive(PracticeSession $session): void
    {
        if (PracticeSessionStatus::Completed === $session->getStatus()) {
            return;
        }

        $session->setStatus(PracticeSessionStatus::Completed);
        $session->touch();
        $this->entityManager->flush();
    }

    /**
     * Mark that this offer's holder entered the room for the session (idempotent).
     */
    public function registerAttendance(PracticeSession $session, PaymentOffer $offer): bool
    {
        if (!$offer->isPracticeOffer() || PaymentOfferStatus::Paid !== $offer->getStatus()) {
            return false;
        }
        if ($offer->getSessionsLeft() <= 0) {
            return false;
        }
        if ($this->attendances->findOneBySessionAndOffer($session, $offer) instanceof PracticeSessionAttendance) {
            return false;
        }

        $row = (new PracticeSessionAttendance())
            ->setSession($session)
            ->setPaymentOffer($offer);
        $this->entityManager->persist($row);
        $this->entityManager->flush();

        $this->logger->info('Practice attendance registered.', [
            'session_id' => $session->getId(),
            'offer_id' => $offer->getId(),
        ]);

        return true;
    }

    public function registerAttendanceForUser(PracticeSession $session, SiteUser $user): bool
    {
        $offer = $this->offers->findBestDebitableOfferForUser($user);
        if (!$offer instanceof PaymentOffer) {
            return false;
        }

        return $this->registerAttendance($session, $offer);
    }

    /**
     * Debit 1 from each offer that attended this session. Once per session.
     * Offers without attendance are never touched.
     */
    public function debitOnce(PracticeSession $session): bool
    {
        if ($session->isDebited()) {
            return false;
        }

        $targets = [];
        foreach ($this->attendances->findForSession($session) as $attendance) {
            $offer = $attendance->getPaymentOffer();
            if (!$offer instanceof PaymentOffer) {
                continue;
            }
            if (PaymentOfferStatus::Paid !== $offer->getStatus() || $offer->getSessionsLeft() <= 0) {
                continue;
            }
            $targets[$offer->getId() ?? spl_object_id($offer)] = $offer;
        }

        foreach ($targets as $offer) {
            $offer->setSessionsLeft(max(0, $offer->getSessionsLeft() - 1));
        }

        $session->setDebitedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->logger->info('Practice session debited.', [
            'session_id' => $session->getId(),
            'offers' => \count($targets),
        ]);

        return true;
    }

    public function handleLiveFinishedWebhook(string $eventId, ?string $videoId): bool
    {
        $session = $this->sessions->findOneByKinescopeEventId($eventId);
        if (!$session instanceof PracticeSession) {
            $this->logger->warning('Kinescope live.finished: no PracticeSession for event.', [
                'event_id' => $eventId,
            ]);

            return false;
        }

        $this->finishWithRecording($session, $videoId);

        return true;
    }

    /**
     * Best remaining balance among paid practice offers (for room / cabinet UI).
     *
     * @param list<PaymentOffer> $offers
     *
     * @return array{total: int, left: int, offer: ?PaymentOffer}
     */
    public function remainingForUserOffers(array $offers): array
    {
        $best = null;
        foreach ($offers as $offer) {
            if (PaymentOfferStatus::Paid !== $offer->getStatus()) {
                continue;
            }
            if (!$offer->isPracticeOffer()) {
                continue;
            }
            if (
                null === $best
                || $offer->getSessionsLeft() > $best->getSessionsLeft()
                || (
                    $offer->getSessionsLeft() === $best->getSessionsLeft()
                    && $offer->getSessionsTotal() > $best->getSessionsTotal()
                )
            ) {
                $best = $offer;
            }
        }

        if (!$best instanceof PaymentOffer) {
            return ['total' => 0, 'left' => 0, 'offer' => null];
        }

        return [
            'total' => $best->getSessionsTotal(),
            'left' => $best->getSessionsLeft(),
            'offer' => $best,
        ];
    }
}
