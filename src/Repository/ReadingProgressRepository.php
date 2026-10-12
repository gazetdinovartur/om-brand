<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LibraryBook;
use App\Entity\ReadingProgress;
use App\Entity\SiteUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ReadingProgress> */
final class ReadingProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReadingProgress::class);
    }

    public function findForUserAndBook(SiteUser $user, LibraryBook $book): ?ReadingProgress
    {
        return $this->findOneBy([
            'siteUser' => $user,
            'book' => $book,
        ]);
    }

    /**
     * @param list<LibraryBook> $books
     *
     * @return array<int, ReadingProgress>
     */
    public function findIndexedForUser(SiteUser $user, array $books): array
    {
        if ([] === $books) {
            return [];
        }

        $items = $this->createQueryBuilder('progress')
            ->andWhere('progress.siteUser = :user')
            ->andWhere('progress.book IN (:books)')
            ->setParameter('user', $user)
            ->setParameter('books', $books)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($items as $item) {
            if (!$item instanceof ReadingProgress || null === $item->getBook()?->getId()) {
                continue;
            }
            $indexed[(int) $item->getBook()->getId()] = $item;
        }

        return $indexed;
    }
}
