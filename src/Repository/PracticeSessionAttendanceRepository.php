<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PaymentOffer;
use App\Entity\PracticeSession;
use App\Entity\PracticeSessionAttendance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PracticeSessionAttendance> */
class PracticeSessionAttendanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PracticeSessionAttendance::class);
    }

    public function findOneBySessionAndOffer(PracticeSession $session, PaymentOffer $offer): ?PracticeSessionAttendance
    {
        return $this->findOneBy([
            'session' => $session,
            'paymentOffer' => $offer,
        ]);
    }

    /** @return list<PracticeSessionAttendance> */
    public function findForSession(PracticeSession $session): array
    {
        /** @var list<PracticeSessionAttendance> $rows */
        $rows = $this->createQueryBuilder('a')
            ->andWhere('a.session = :session')
            ->setParameter('session', $session)
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
