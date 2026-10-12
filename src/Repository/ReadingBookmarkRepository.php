<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LibraryBook;
use App\Entity\LibraryChapter;
use App\Entity\ReadingBookmark;
use App\Entity\SiteUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ReadingBookmark> */
final class ReadingBookmarkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReadingBookmark::class);
    }

    /** @return list<ReadingBookmark> */
    public function findForUserAndBook(SiteUser $user, LibraryBook $book): array
    {
        return $this->findBy(
            ['siteUser' => $user, 'book' => $book],
            ['createdAt' => 'DESC'],
        );
    }

    public function findAtLocator(
        SiteUser $user,
        LibraryBook $book,
        LibraryChapter $chapter,
        string $blockId,
    ): ?ReadingBookmark {
        return $this->findOneBy([
            'siteUser' => $user,
            'book' => $book,
            'chapter' => $chapter,
            'blockId' => $blockId,
        ]);
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

        $rows = $this->createQueryBuilder('bookmark')
            ->select('IDENTITY(bookmark.book) AS bookId, COUNT(bookmark.id) AS bookmarkCount')
            ->andWhere('bookmark.siteUser = :user')
            ->andWhere('bookmark.book IN (:books)')
            ->setParameter('user', $user)
            ->setParameter('books', $books)
            ->groupBy('bookmark.book')
            ->getQuery()
            ->getArrayResult();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['bookId']] = (int) $row['bookmarkCount'];
        }

        return $indexed;
    }
}
