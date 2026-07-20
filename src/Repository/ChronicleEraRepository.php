<?php

namespace App\Repository;

use App\Entity\ChronicleEra;
use App\Enum\ChronicleStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChronicleEra>
 */
class ChronicleEraRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChronicleEra::class);
    }

    /** @return list<ChronicleEra> */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.sortOrder', 'ASC')
            ->addOrderBy('e.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Eras that have at least one public published entry. @return list<ChronicleEra> */
    public function findWithPublishedOrdered(): array
    {
        return $this->createQueryBuilder('era')
            ->innerJoin('era.entries', 'e')
            ->andWhere('e.status = :status')
            ->andWhere('e.publishedAt IS NOT NULL')
            ->andWhere('e.publishedAt <= :now')
            ->andWhere('e.isUnlisted = false')
            ->setParameter('status', ChronicleStatus::Published)
            ->setParameter('now', new \DateTimeImmutable())
            ->groupBy('era.id')
            ->orderBy('era.sortOrder', 'ASC')
            ->addOrderBy('era.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySlug(string $slug): ?ChronicleEra
    {
        $era = $this->findOneBy(['slug' => $slug]);

        return $era instanceof ChronicleEra ? $era : null;
    }
}