<?php

namespace App\Repository;

use App\Entity\ContentLike;
use App\Enum\ContentLikeTarget;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContentLike>
 */
class ContentLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentLike::class);
    }

    public function findOneForVisitor(ContentLikeTarget $type, int $targetId, string $visitorToken): ?ContentLike
    {
        return $this->findOneBy([
            'targetType' => $type,
            'targetId' => $targetId,
            'visitorToken' => $visitorToken,
        ]);
    }

    public function countForTarget(ContentLikeTarget $type, int $targetId): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.targetType = :type')
            ->andWhere('l.targetId = :id')
            ->setParameter('type', $type)
            ->setParameter('id', $targetId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
