<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LibraryBook;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<LibraryBook> */
final class LibraryBookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LibraryBook::class);
    }

    /** @return list<LibraryBook> */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('book')
            ->addOrderBy('book.author', 'ASC')
            ->addOrderBy('book.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
