<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LibraryBook;
use App\Entity\LibraryChapter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<LibraryChapter> */
final class LibraryChapterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LibraryChapter::class);
    }

    public function findAdjacent(LibraryChapter $chapter, int $direction): ?LibraryChapter
    {
        $operator = $direction < 0 ? '<' : '>';
        $order = $direction < 0 ? 'DESC' : 'ASC';

        return $this->createQueryBuilder('chapter')
            ->andWhere('chapter.book = :book')
            ->andWhere(sprintf('chapter.sortOrder %s :sortOrder', $operator))
            ->setParameter('book', $chapter->getBook())
            ->setParameter('sortOrder', $chapter->getSortOrder())
            ->orderBy('chapter.sortOrder', $order)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
