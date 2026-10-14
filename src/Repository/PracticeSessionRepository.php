<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PracticeSession;
use App\Enum\PracticeSessionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PracticeSession> */
class PracticeSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PracticeSession::class);
    }

    public function findOneByKinescopeEventId(string $eventId): ?PracticeSession
    {
        $eventId = trim($eventId);
        if ('' === $eventId) {
            return null;
        }

        return $this->findOneBy(['kinescopeEventId' => $eventId]);
    }

    public function findOneByKinescopeRoomId(string $roomId): ?PracticeSession
    {
        $roomId = trim($roomId);
        if ('' === $roomId) {
            return null;
        }

        return $this->findOneBy(['kinescopeRoomId' => $roomId]);
    }

    public function findOneByKinescopeVideoId(string $videoId): ?PracticeSession
    {
        $videoId = trim($videoId);
        if ('' === $videoId) {
            return null;
        }

        return $this->findOneBy(['kinescopeVideoId' => $videoId]);
    }

    public function findLive(): ?PracticeSession
    {
        /** @var PracticeSession|null $session */
        $session = $this->createQueryBuilder('s')
            ->andWhere('s.status = :live')
            ->setParameter('live', PracticeSessionStatus::Live)
            ->orderBy('s.startsAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $session;
    }

    public function findLatestReplay(): ?PracticeSession
    {
        /** @var PracticeSession|null $session */
        $session = $this->createQueryBuilder('s')
            ->andWhere('s.status = :replay')
            ->setParameter('replay', PracticeSessionStatus::Replay)
            ->orderBy('s.startsAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $session;
    }

    public function findNextScheduled(?\DateTimeImmutable $after = null): ?PracticeSession
    {
        $after ??= new \DateTimeImmutable();

        /** @var PracticeSession|null $session */
        $session = $this->createQueryBuilder('s')
            ->andWhere('s.status = :scheduled')
            ->andWhere('s.startsAt >= :after')
            ->setParameter('scheduled', PracticeSessionStatus::Scheduled)
            ->setParameter('after', $after)
            ->orderBy('s.startsAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $session;
    }

    /** Active room focus: live → latest replay. */
    public function findRoomFocus(): ?PracticeSession
    {
        return $this->findLive() ?? $this->findLatestReplay();
    }

    public function hasLiveOrReplay(): bool
    {
        $count = (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.status IN (:statuses)')
            ->setParameter('statuses', [
                PracticeSessionStatus::Live,
                PracticeSessionStatus::Replay,
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /** @return list<PracticeSession> */
    public function findRecordings(int $limit = 20): array
    {
        /** @var list<PracticeSession> $sessions */
        $sessions = $this->createQueryBuilder('s')
            ->andWhere('s.kinescopeVideoId IS NOT NULL')
            ->andWhere('s.status IN (:statuses)')
            ->setParameter('statuses', [
                PracticeSessionStatus::Replay,
                PracticeSessionStatus::Completed,
            ])
            ->orderBy('s.startsAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $sessions;
    }
}
