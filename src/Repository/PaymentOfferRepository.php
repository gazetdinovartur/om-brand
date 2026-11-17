<?php

namespace App\Repository;

use App\Entity\PaymentOffer;
use App\Entity\SiteUser;
use App\Enum\PaymentOfferStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PaymentOffer> */
class PaymentOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentOffer::class);
    }

    /** @return list<PaymentOffer> */
    public function findForSiteUser(SiteUser $user): array
    {
        /** @var list<PaymentOffer> $offers */
        $offers = $this->createQueryBuilder('o')
            ->andWhere('o.siteUser = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $offers;
    }

    /** Any paid practice package (including free first meeting). */
    public function userHasPaidPracticeAccess(SiteUser $user): bool
    {
        return $this->countPaidPracticeForUser($user) > 0;
    }

    /** Paid single/cycle (or legacy paid) — gallery of recordings. */
    public function userHasPracticeRecordingsAccess(SiteUser $user): bool
    {
        foreach ($this->findForSiteUser($user) as $offer) {
            if ($offer->grantsPracticeRecordingsAccess()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Room access: remaining sessions, or paying customer while live/replay is on.
     * Free first meeting does not unlock room after sessionsLeft hits 0.
     */
    public function userHasPracticeRoomAccess(SiteUser $user, bool $hasLiveOrReplay): bool
    {
        if ($this->countPaidPracticeWithRemainingSessionsForUser($user) > 0) {
            return true;
        }

        if (!$hasLiveOrReplay) {
            return false;
        }

        return $this->userHasPracticeRecordingsAccess($user);
    }

    public function findBestDebitableOfferForUser(SiteUser $user): ?PaymentOffer
    {
        $best = null;
        foreach ($this->findForSiteUser($user) as $offer) {
            if (PaymentOfferStatus::Paid !== $offer->getStatus() || !$offer->isPracticeOffer()) {
                continue;
            }
            if ($offer->getSessionsLeft() <= 0) {
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

        return $best;
    }

    private function countPaidPracticeForUser(SiteUser $user): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.siteUser = :user')
            ->andWhere('o.status = :paid')
            ->andWhere('o.practiceFormat IS NOT NULL OR o.note LIKE :practiceNote')
            ->setParameter('user', $user)
            ->setParameter('paid', PaymentOfferStatus::Paid)
            ->setParameter('practiceNote', '%Практика /practice%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countPaidPracticeWithRemainingSessionsForUser(SiteUser $user): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.siteUser = :user')
            ->andWhere('o.status = :paid')
            ->andWhere('o.sessionsLeft > 0')
            ->andWhere('o.practiceFormat IS NOT NULL OR o.note LIKE :practiceNote')
            ->setParameter('user', $user)
            ->setParameter('paid', PaymentOfferStatus::Paid)
            ->setParameter('practiceNote', '%Практика /practice%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByYookassaPaymentId(string $yookassaPaymentId): ?PaymentOffer
    {
        $yookassaPaymentId = trim($yookassaPaymentId);
        if ('' === $yookassaPaymentId) {
            return null;
        }

        return $this->findOneBy(['yookassaPaymentId' => $yookassaPaymentId]);
    }
}
