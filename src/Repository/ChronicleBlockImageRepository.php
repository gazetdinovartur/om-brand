<?php

namespace App\Repository;

use App\Entity\ChronicleBlockImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChronicleBlockImage>
 */
class ChronicleBlockImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChronicleBlockImage::class);
    }
}
