<?php

namespace App\Repository;

use App\Entity\ChronicleEntry;
use App\Entity\ChronicleEra;
use App\Entity\ChronicleTag;
use App\Enum\ChronicleStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChronicleEntry>
 */
class ChronicleEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChronicleEntry::class);
    }

    /** @return list<ChronicleEntry> */
    public function findFeedOrdered(int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->andWhere('e.publishedAt IS NOT NULL')
            ->andWhere('e.publishedAt <= :now')
            ->andWhere('e.isUnlisted = false')
            ->setParameter('status', ChronicleStatus::Published)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('e.isFeatured', 'DESC')
            ->addOrderBy('e.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function findPublishedBySlug(string $slug): ?ChronicleEntry
    {
        $entry = $this->createQueryBuilder('e')
            ->andWhere('e.slug = :slug')
            ->andWhere('e.status = :status')
            ->andWhere('e.publishedAt IS NOT NULL')
            ->andWhere('e.publishedAt <= :now')
            ->setParameter('slug', $slug)
            ->setParameter('status', ChronicleStatus::Published)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();

        return $entry instanceof ChronicleEntry ? $entry : null;
    }

    public function findByShortHash(string $hash): ?ChronicleEntry
    {
        $entry = $this->findOneBy(['shortHash' => $hash]);
        if (!$entry instanceof ChronicleEntry || !$entry->isPublic()) {
            return null;
        }

        return $entry;
    }

    public function findByPreviewToken(string $token): ?ChronicleEntry
    {
        $entry = $this->findOneBy(['previewToken' => $token]);

        return $entry instanceof ChronicleEntry ? $entry : null;
    }

    /** @return list<ChronicleEntry> */
    public function findByEra(ChronicleEra $era, int $limit = 50): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.era = :era')
            ->andWhere('e.status = :status')
            ->andWhere('e.publishedAt IS NOT NULL')
            ->andWhere('e.publishedAt <= :now')
            ->andWhere('e.isUnlisted = false')
            ->setParameter('era', $era)
            ->setParameter('status', ChronicleStatus::Published)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('e.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<ChronicleEntry> */
    public function findByTag(ChronicleTag $tag, int $limit = 50): array
    {
        return $this->createQueryBuilder('e')
            ->innerJoin('e.tags', 't')
            ->andWhere('t = :tag')
            ->andWhere('e.status = :status')
            ->andWhere('e.publishedAt IS NOT NULL')
            ->andWhere('e.publishedAt <= :now')
            ->andWhere('e.isUnlisted = false')
            ->setParameter('tag', $tag)
            ->setParameter('status', ChronicleStatus::Published)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('e.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<ChronicleEntry> */
    public function findRelated(ChronicleEntry $entry, int $limit = 3): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.id != :id')
            ->andWhere('e.status = :status')
            ->andWhere('e.publishedAt IS NOT NULL')
            ->andWhere('e.publishedAt <= :now')
            ->andWhere('e.isUnlisted = false')
            ->setParameter('id', $entry->getId())
            ->setParameter('status', ChronicleStatus::Published)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('e.publishedAt', 'DESC')
            ->setMaxResults($limit);

        if (null !== $entry->getEra()) {
            $qb->andWhere('e.era = :era')->setParameter('era', $entry->getEra());
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<ChronicleEntry> */
    public function findForSitemap(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->andWhere('e.publishedAt IS NOT NULL')
            ->andWhere('e.publishedAt <= :now')
            ->andWhere('e.isUnlisted = false')
            ->setParameter('status', ChronicleStatus::Published)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('e.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<ChronicleEntry> */
    public function findScheduledReady(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->andWhere('e.publishedAt IS NOT NULL')
            ->andWhere('e.publishedAt <= :now')
            ->setParameter('status', ChronicleStatus::Scheduled)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    public function isShortHashTaken(string $hash, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.shortHash = :hash')
            ->setParameter('hash', $hash);

        if (null !== $excludeId) {
            $qb->andWhere('e.id != :id')->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
