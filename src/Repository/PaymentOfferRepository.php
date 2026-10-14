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

    public function userHasPaidPracticeAccess(SiteUser $user): bool
    {
        $count = (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.siteUser = :user')
            ->andWhere('o.status = :paid')
            ->setParameter('user', $user)
            ->setParameter('paid', PaymentOfferStatus::Paid)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Room access: paid practice with remaining sessions, or any paid while live/replay exists.
     */
    public function userHasPracticeRoomAccess(SiteUser $user, bool $hasLiveOrReplay): bool
    {
        if (!$this->userHasPaidPracticeAccess($user)) {
            return false;
        }

        $withLeft = (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.siteUser = :user')
            ->andWhere('o.status = :paid')
            ->andWhere('o.sessionsLeft > 0')
            ->setParameter('user', $user)
            ->setParameter('paid', PaymentOfferStatus::Paid)
            ->getQuery()
            ->getSingleScalarResult();

        if ($withLeft > 0) {
            return true;
        }

        return $hasLiveOrReplay;
    }

    /** @return list<PaymentOffer> */
    public function findPaidPracticeWithRemainingSessions(): array
    {
        /** @var list<PaymentOffer> $offers */
        $offers = $this->createQueryBuilder('o')
            ->andWhere('o.status = :paid')
            ->andWhere('o.sessionsLeft > 0')
            ->andWhere('o.practiceFormat IS NOT NULL OR o.note LIKE :practiceNote')
            ->setParameter('paid', PaymentOfferStatus::Paid)
            ->setParameter('practiceNote', '%Практика /practice%')
            ->getQuery()
            ->getResult();

        return $offers;
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
