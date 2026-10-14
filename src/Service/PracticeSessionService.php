<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PaymentOffer;
use App\Entity\PracticeSession;
use App\Enum\PaymentOfferStatus;
use App\Enum\PracticeSessionStatus;
use App\Repository\PaymentOfferRepository;
use App\Repository\PracticeSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class PracticeSessionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PracticeSessionRepository $sessions,
        private readonly PaymentOfferRepository $offers,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * End of stream: replay + video id + one debit. Idempotent.
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
     * Debit 1 from every paid practice offer with sessionsLeft > 0. Once per session.
     */
    public function debitOnce(PracticeSession $session): bool
    {
        if ($session->isDebited()) {
            return false;
        }

        $targets = $this->offers->findPaidPracticeWithRemainingSessions();
        foreach ($targets as $offer) {
            $offer->setSessionsLeft(max(0, $offer->getSessionsLeft() - 1));
        }

        $session->setDebitedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->logger->info('Practice session debited.', [
            'session_id' => $session->getId(),
            'offers' => count($targets),
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
