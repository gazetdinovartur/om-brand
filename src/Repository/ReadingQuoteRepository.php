<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LibraryBook;
use App\Entity\ReadingQuote;
use App\Entity\SiteUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReadingQuote>
 */
final class ReadingQuoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReadingQuote::class);
    }

    /** @return list<ReadingQuote> */
    public function findForUserAndBook(SiteUser $user, LibraryBook $book): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.siteUser = :user')
            ->andWhere('q.book = :book')
            ->setParameter('user', $user)
            ->setParameter('book', $book)
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<ReadingQuote> */
    public function findRecentForUser(SiteUser $user, int $limit = 40): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.siteUser = :user')
            ->setParameter('user', $user)
            ->orderBy('q.createdAt', 'DESC')
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

        $rows = $this->createQueryBuilder('q')
            ->select('IDENTITY(q.book) AS bookId, COUNT(q.id) AS cnt')
            ->andWhere('q.siteUser = :user')
            ->andWhere('q.book IN (:books)')
            ->setParameter('user', $user)
            ->setParameter('books', $books)
            ->groupBy('q.book')
            ->getQuery()
            ->getArrayResult();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['bookId']] = (int) $row['cnt'];
        }

        return $indexed;
    }
}
