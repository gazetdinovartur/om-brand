<?php

namespace App\Repository;

use App\Entity\EmailSubscriber;
use App\Enum\EmailSubscriberStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailSubscriber>
 */
class EmailSubscriberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailSubscriber::class);
    }

    public function findOneByEmail(string $email): ?EmailSubscriber
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    public function findOneByConfirmToken(string $token): ?EmailSubscriber
    {
        return $this->findOneBy(['confirmToken' => $token]);
    }

    public function findOneByUnsubscribeToken(string $token): ?EmailSubscriber
    {
        return $this->findOneBy(['unsubscribeToken' => $token]);
    }

    /**
     * @return list<EmailSubscriber>
     */
    public function findConfirmed(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->setParameter('status', EmailSubscriberStatus::Confirmed)
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
