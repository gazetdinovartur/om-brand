<?php

namespace App\Repository;

use App\Entity\ChronicleEntry;
use App\Entity\ChronicleEra;
use App\Entity\ChronicleSeries;
use App\Entity\ChronicleTag;
use App\Entity\ContentLike;
use App\Enum\ChronicleStatus;
use App\Enum\ContentLikeTarget;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
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

    public function findForEditor(int $id): ?ChronicleEntry
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.blocks', 'b')->addSelect('b')
            ->leftJoin('b.images', 'bi')->addSelect('bi')
            ->leftJoin('e.tags', 't')->addSelect('t')
            ->leftJoin('e.era', 'era')->addSelect('era')
            ->leftJoin('e.series', 's')->addSelect('s')
            ->andWhere('e.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param array{
     *     era?: ?ChronicleEra,
     *     tag?: ?ChronicleTag,
     *     series?: ?ChronicleSeries,
     *     year?: ?int,
     *     liked?: bool|null,
     *     visitorToken?: string|null
     * } $filters
     *
     * @return list<ChronicleEntry>
     */
    public function findFiltered(array $filters = [], int $limit = 24, int $offset = 0): array
    {
        $qb = $this->filteredQuery($filters);

        if (!empty($filters['liked']) && !empty($filters['visitorToken'])) {
            $qb->orderBy('cl.createdAt', 'DESC')
                ->addOrderBy('e.publishedAt', 'DESC');
        } else {
            $qb->orderBy('e.publishedAt', 'DESC');
        }

        return $qb
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
     *     liked?: bool|null,
     *     visitorToken?: string|null
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
               AND is_admin_only = 0
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
        $now = new \DateTimeImmutable();
        $candidates = $this->relatedCandidates($entry, $now);

        if ([] === $candidates) {
            return $this->recentPublishedExcept($entry, $now, $limit);
        }

        usort(
            $candidates,
            fn (ChronicleEntry $a, ChronicleEntry $b): int => $this->compareRelated($entry, $a, $b),
        );

        $picked = \array_slice($candidates, 0, $limit);
        if (\count($picked) >= $limit) {
            return $picked;
        }

        $pickedIds = array_flip(array_map(static fn (ChronicleEntry $e): int => (int) $e->getId(), $picked));
        foreach ($this->recentPublishedExcept($entry, $now, $limit * 3) as $fallback) {
            $id = (int) $fallback->getId();
            if (isset($pickedIds[$id])) {
                continue;
            }
            $picked[] = $fallback;
            $pickedIds[$id] = true;
            if (\count($picked) >= $limit) {
                break;
            }
        }

        return $picked;
    }

    /** @return list<ChronicleEntry> */
    private function relatedCandidates(ChronicleEntry $entry, \DateTimeImmutable $now): array
    {
        $qb = $this->publishedPublicQuery($now)
            ->leftJoin('e.tags', 't')
            ->andWhere('e.id != :id')
            ->setParameter('id', $entry->getId());

        $or = $qb->expr()->orX();
        $hasSignal = false;

        if (null !== $entry->getEra()) {
            $or->add('e.era = :era');
            $qb->setParameter('era', $entry->getEra());
            $hasSignal = true;
        }

        if (null !== $entry->getSeries()) {
            $or->add('e.series = :series');
            $qb->setParameter('series', $entry->getSeries());
            $hasSignal = true;
        }

        $tagIds = array_values(array_filter(array_map(
            static fn (\App\Entity\ChronicleTag $tag): ?int => $tag->getId(),
            $entry->getTags()->toArray(),
        )));

        if ([] !== $tagIds) {
            $or->add('t.id IN (:tagIds)');
            $qb->setParameter('tagIds', $tagIds);
            $hasSignal = true;
        }

        if (!$hasSignal) {
            return [];
        }

        /** @var list<ChronicleEntry> $rows */
        $rows = $qb->andWhere($or)->distinct()->getQuery()->getResult();

        return $rows;
    }

    /** @return list<ChronicleEntry> */
    private function recentPublishedExcept(ChronicleEntry $entry, \DateTimeImmutable $now, int $limit): array
    {
        /** @var list<ChronicleEntry> $rows */
        $rows = $this->publishedPublicQuery($now)
            ->andWhere('e.id != :id')
            ->setParameter('id', $entry->getId())
            ->orderBy('e.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    private function publishedPublicQuery(\DateTimeImmutable $now): QueryBuilder
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->andWhere('e.publishedAt IS NOT NULL')
            ->andWhere('e.publishedAt <= :now')
            ->andWhere('e.isUnlisted = false')
            ->andWhere('e.isAdminOnly = false')
            ->setParameter('status', ChronicleStatus::Published)
            ->setParameter('now', $now);
    }

    private function compareRelated(ChronicleEntry $entry, ChronicleEntry $a, ChronicleEntry $b): int
    {
        $scoreA = $this->scoreRelated($entry, $a);
        $scoreB = $this->scoreRelated($entry, $b);
        if ($scoreA !== $scoreB) {
            return $scoreB <=> $scoreA;
        }

        $dateA = $a->getPublishedAt()?->getTimestamp() ?? 0;
        $dateB = $b->getPublishedAt()?->getTimestamp() ?? 0;
        if ($dateA !== $dateB) {
            return $dateB <=> $dateA;
        }

        return ((int) $b->getId()) <=> ((int) $a->getId());
    }

    private function scoreRelated(ChronicleEntry $entry, ChronicleEntry $candidate): int
    {
        $score = 0;

        $entrySeriesId = $entry->getSeries()?->getId();
        $candidateSeriesId = $candidate->getSeries()?->getId();
        if (null !== $entrySeriesId && $entrySeriesId === $candidateSeriesId) {
            $score += 40;
        }

        $entryTagIds = [];
        foreach ($entry->getTags() as $tag) {
            $id = $tag->getId();
            if (null !== $id) {
                $entryTagIds[$id] = true;
            }
        }
        foreach ($candidate->getTags() as $tag) {
            $id = $tag->getId();
            if (null !== $id && isset($entryTagIds[$id])) {
                $score += 25;
            }
        }

        $entryEraId = $entry->getEra()?->getId();
        $candidateEraId = $candidate->getEra()?->getId();
        if (null !== $entryEraId && $entryEraId === $candidateEraId) {
            $score += 8;
        }

        if (null !== $candidate->getCoverImagePath() && '' !== trim($candidate->getCoverImagePath())) {
            $score += 3;
        }

        if ($candidate->isFeatured()) {
            $score += 2;
        }

        return $score;
    }

    /** @return list<ChronicleEntry> */
    public function findForSitemap(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->andWhere('e.publishedAt IS NOT NULL')
            ->andWhere('e.publishedAt <= :now')
            ->andWhere('e.isUnlisted = false')
            ->andWhere('e.isAdminOnly = false')
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
     *     liked?: bool|null,
     *     visitorToken?: string|null
     * } $filters
     */
    private function filteredQuery(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->andWhere('e.publishedAt IS NOT NULL')
            ->andWhere('e.publishedAt <= :now')
            ->andWhere('e.isUnlisted = false')
            ->andWhere('e.isAdminOnly = false')
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

        if (!empty($filters['liked'])) {
            $token = $filters['visitorToken'] ?? null;
            if (\is_string($token) && '' !== $token) {
                $qb->innerJoin(
                    ContentLike::class,
                    'cl',
                    Join::WITH,
                    'cl.targetId = e.id AND cl.targetType = :likeType AND cl.visitorToken = :visitorToken'
                )
                    ->setParameter('likeType', ContentLikeTarget::Chronicle)
                    ->setParameter('visitorToken', $token);
            } else {
                $qb->andWhere('1 = 0');
            }
        } elseif (\array_key_exists('featured', $filters) && null !== $filters['featured']) {
            $qb->andWhere('e.isFeatured = :featured')
                ->setParameter('featured', (bool) $filters['featured']);
        }

        return $qb;
    }
}
