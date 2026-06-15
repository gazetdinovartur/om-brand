<?php

namespace App\Repository;

use App\Entity\ContentBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ContentBlock> */
class ContentBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentBlock::class);
    }

    /** @return list<ContentBlock> */
    public function findVisibleOrdered(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.isVisible = :visible')
            ->setParameter('visible', true)
            ->orderBy('b.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneBySlug(string $slug): ?ContentBlock
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
