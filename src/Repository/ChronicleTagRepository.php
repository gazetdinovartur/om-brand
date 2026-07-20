<?php

namespace App\Repository;

use App\Entity\ChronicleTag;
use App\Enum\ChronicleStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChronicleTag>
 */
class ChronicleTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChronicleTag::class);
    }

    /** @return list<ChronicleTag> */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Tags used on at least one public published entry. @return list<ChronicleTag> */
    public function findWithPublishedOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.entries', 'e')
            ->andWhere('e.status = :status')
            ->andWhere('e.publishedAt IS NOT NULL')
            ->andWhere('e.publishedAt <= :now')
            ->andWhere('e.isUnlisted = false')
            ->setParameter('status', ChronicleStatus::Published)
            ->setParameter('now', new \DateTimeImmutable())
            ->groupBy('t.id')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySlug(string $slug): ?ChronicleTag
    {
        $tag = $this->findOneBy(['slug' => $slug]);

        return $tag instanceof ChronicleTag ? $tag : null;
    }
}