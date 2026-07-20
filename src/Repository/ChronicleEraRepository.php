<?php

namespace App\Repository;

use App\Entity\ChronicleEra;
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

    public function findBySlug(string $slug): ?ChronicleEra
    {
        $era = $this->findOneBy(['slug' => $slug]);

        return $era instanceof ChronicleEra ? $era : null;
    }
}
