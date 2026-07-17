<?php

namespace App\Repository;

use App\Entity\CaseStudyImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CaseStudyImage> */
class CaseStudyImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CaseStudyImage::class);
    }
}
