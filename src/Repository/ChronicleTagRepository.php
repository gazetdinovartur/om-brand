<?php

namespace App\Repository;

use App\Entity\ChronicleTag;
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

    public function findBySlug(string $slug): ?ChronicleTag
    {
        $tag = $this->findOneBy(['slug' => $slug]);

        return $tag instanceof ChronicleTag ? $tag : null;
    }
}
