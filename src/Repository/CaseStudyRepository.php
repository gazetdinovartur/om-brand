<?php

namespace App\Repository;

use App\Entity\CaseStudy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CaseStudy> */
class CaseStudyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CaseStudy::class);
    }

    /** @return list<CaseStudy> */
    public function findPublishedOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<CaseStudy> */
    public function findLandingOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isPublished = :published')
            ->andWhere('c.showOnLanding = :landing')
            ->setParameter('published', true)
            ->setParameter('landing', true)
            ->orderBy('c.isFeatured', 'DESC')
            ->addOrderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPublishedBySlug(string $slug): ?CaseStudy
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isPublished = :published')
            ->andWhere('c.slug = :slug')
            ->setParameter('published', true)
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countPublished(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.isPublished = :published')
            ->setParameter('published', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
