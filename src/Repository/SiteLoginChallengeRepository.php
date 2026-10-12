<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SiteLoginChallenge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SiteLoginChallenge> */
final class SiteLoginChallengeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteLoginChallenge::class);
    }

    public function findLatestActiveForEmail(string $email, ?\DateTimeImmutable $now = null): ?SiteLoginChallenge
    {
        $now ??= new \DateTimeImmutable();
        $email = mb_strtolower(trim($email));

        /** @var SiteLoginChallenge|null $challenge */
        $challenge = $this->createQueryBuilder('challenge')
            ->andWhere('challenge.email = :email')
            ->andWhere('challenge.consumedAt IS NULL')
            ->andWhere('challenge.expiresAt > :now')
            ->setParameter('email', $email)
            ->setParameter('now', $now)
            ->orderBy('challenge.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $challenge;
    }

    public function invalidateOpenForEmail(string $email): void
    {
        $email = mb_strtolower(trim($email));
        $this->createQueryBuilder('challenge')
            ->update()
            ->set('challenge.consumedAt', ':now')
            ->andWhere('challenge.email = :email')
            ->andWhere('challenge.consumedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('email', $email)
            ->getQuery()
            ->execute();
    }
}
