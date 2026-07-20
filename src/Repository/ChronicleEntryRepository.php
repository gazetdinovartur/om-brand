<?php

namespace App\Repository;

use App\Entity\ChronicleEntry;
use App\Entity\ChronicleEra;
use App\Entity\ChronicleSeries;
use App\Entity\ChronicleTag;
use App\Enum\ChronicleStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    /**
     * @param array{
     *     era?: ?ChronicleEra,
     *     tag?: ?ChronicleTag,
     *     series?: ?ChronicleSeries,
     *     year?: ?int,
     *     featured?: bool|null
     * } $filters
     *
     * @return list<ChronicleEntry>
     */
    public function findFiltered(array $filters = [], int $limit = 24, int $offset = 0): array
    {
        return $this->filteredQuery($filters)
            ->orderBy('e.isFeatured', 'DESC')
            ->addOrderBy('e.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array{
     *     era?: ?ChronicleEra,
     *     tag?: ?ChronicleTag,
     *     series?: ?ChronicleSeries,
     *     year?: ?int,
     *     featured?: bool|null
     * } $filters
     */
    public function countFiltered(array $filters = []): int
    {
        return (int) $this->filteredQuery($filters)
            ->select('COUNT(DISTINCT e.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<int> */
    public function findPublishedYears(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $rows = $conn->fetchFirstColumn(
            'SELECT DISTINCT YEAR(published_at) AS y
             FROM chronicle_entry
             WHERE status = ?
               AND published_at IS NOT NULL
               AND published_at <= ?
               AND is_unlisted = 0
             ORDER BY y DESC',
            [ChronicleStatus::Published->value, (new \DateTimeImmutable())->format('Y-m-d H:i:s')]
        );

        return array_map(static fn ($y) => (int) $y, $rows);
    }

    /** @return list<ChronicleEntry> */
    public function findFeedOrdered(int $limit = 50, int $offset = 0): array
    {
        return $this->findFiltered([], $limit, $offset);
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
    public function findByEra(ChronicleEra $era, int $limit = 50, int $offset = 0): array
    {
        return $this->findFiltered(['era' => $era], $limit, $offset);
    }

    /** @return list<ChronicleEntry> */
    public function findByTag(ChronicleTag $tag, int $limit = 50, int $offset = 0): array
    {
        return $this->findFiltered(['tag' => $tag], $limit, $offset);
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

    /**
     * @param array{
     *     era?: ?ChronicleEra,
     *     tag?: ?ChronicleTag,
     *     series?: ?ChronicleSeries,
     *     year?: ?int,
     *     featured?: bool|null
     * } $filters
     */
    private function filteredQuery(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->andWhere('e.publishedAt IS NOT NULL')
            ->andWhere('e.publishedAt <= :now')
            ->andWhere('e.isUnlisted = false')
            ->setParameter('status', ChronicleStatus::Published)
            ->setParameter('now', new \DateTimeImmutable());

        if (!empty($filters['era'])) {
            $qb->andWhere('e.era = :era')->setParameter('era', $filters['era']);
        }

        if (!empty($filters['series'])) {
            $qb->andWhere('e.series = :series')->setParameter('series', $filters['series']);
        }

        if (!empty($filters['tag'])) {
            $qb->innerJoin('e.tags', 't')
                ->andWhere('t = :tag')
                ->setParameter('tag', $filters['tag']);
        }

        if (!empty($filters['year'])) {
            $year = (int) $filters['year'];
            $qb->andWhere('e.publishedAt >= :yearStart')
                ->andWhere('e.publishedAt < :yearEnd')
                ->setParameter('yearStart', new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year)))
                ->setParameter('yearEnd', new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year + 1)));
        }

        if (\array_key_exists('featured', $filters) && null !== $filters['featured']) {
            $qb->andWhere('e.isFeatured = :featured')
                ->setParameter('featured', (bool) $filters['featured']);
        }

        return $qb;
    }
}
