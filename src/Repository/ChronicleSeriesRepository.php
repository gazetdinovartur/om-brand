<?php

namespace App\Repository;

use App\Enum\ChronicleStatus;
use App\Entity\ChronicleSeries;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChronicleSeries>
 */
class ChronicleSeriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChronicleSeries::class);
    }

    /** @return list<ChronicleSeries> */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.sortOrder', 'ASC')
            ->addOrderBy('s.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Series that have at least one public published entry. @return list<ChronicleSeries> */
    public function findWithPublishedOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.entries', 'e')
            ->andWhere('e.status = :status')
            ->andWhere('e.publishedAt IS NOT NULL')
            ->andWhere('e.publishedAt <= :now')
            ->andWhere('e.isUnlisted = false')
            ->setParameter('status', ChronicleStatus::Published)
            ->setParameter('now', new \DateTimeImmutable())
            ->groupBy('s.id')
            ->orderBy('s.sortOrder', 'ASC')
            ->addOrderBy('s.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}