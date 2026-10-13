<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LibraryBook;
use App\Entity\ReadingNote;
use App\Entity\SiteUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReadingNote>
 */
final class ReadingNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReadingNote::class);
    }

    /** @return list<ReadingNote> */
    public function findForUserAndBook(SiteUser $user, LibraryBook $book): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.siteUser = :user')
            ->andWhere('n.book = :book')
            ->setParameter('user', $user)
            ->setParameter('book', $book)
            ->orderBy('n.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<ReadingNote> */
    public function findRecentForUser(SiteUser $user, int $limit = 40): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.siteUser = :user')
            ->setParameter('user', $user)
            ->orderBy('n.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<LibraryBook> $books
     *
     * @return array<int, int>
     */
    public function countIndexedForUser(SiteUser $user, array $books): array
    {
        if ([] === $books) {
            return [];
        }

        $rows = $this->createQueryBuilder('n')
            ->select('IDENTITY(n.book) AS bookId, COUNT(n.id) AS cnt')
            ->andWhere('n.siteUser = :user')
            ->andWhere('n.book IN (:books)')
            ->setParameter('user', $user)
            ->setParameter('books', $books)
            ->groupBy('n.book')
            ->getQuery()
            ->getArrayResult();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['bookId']] = (int) $row['cnt'];
        }

        return $indexed;
    }
}
