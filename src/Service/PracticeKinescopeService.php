<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PracticeSession;
use App\Entity\SpeakMeeting;
use App\Enum\PracticeSessionKind;
use App\Enum\PracticeSessionStatus;
use App\Infrastructure\Kinescope\KinescopeClient;
use App\Repository\PracticeSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class PracticeKinescopeService
{
    public function __construct(
        private readonly KinescopeClient $kinescope,
        private readonly PracticeSessionRepository $sessions,
        private readonly PracticeSessionService $practiceSessions,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createRemoteForSession(PracticeSession $session): PracticeSession
    {
        if (!$this->kinescope->isConfigured()) {
            throw new \RuntimeException('Kinescope API token is not configured.');
        }

        if (PracticeSessionKind::Speak === $session->getKind()) {
            if (null !== $session->getKinescopeRoomId()) {
                return $session;
            }

            return $this->createSpeakForSession($session);
        }

        if (null !== $session->getKinescopeEventId()) {
            return $this->refreshLiveCredentials($session);
        }

        return $this->createLiveForSession($session);
    }

    public function createLiveForSession(PracticeSession $session): PracticeSession
    {
        if (null !== $session->getKinescopeEventId()) {
            return $this->refreshLiveCredentials($session);
        }

        $result = $this->kinescope->createLiveEvent($session->getTitle(), $session->getStartsAt());
        $session
            ->setKind(PracticeSessionKind::Live)
            ->setKinescopeEventId($result->eventId)
            ->setKinescopeIngestUrl($result->ingestUrl)
            ->setKinescopeStreamKey($result->streamKey)
            ->touch();
        $this->entityManager->flush();

        return $session;
    }

    public function createSpeakForSession(PracticeSession $session): PracticeSession
    {
        if (null !== $session->getKinescopeRoomId()) {
            return $session;
        }

        $result = $this->kinescope->createSpeakRoom($session->getTitle(), 'scheduled_meeting', true);
        $session
            ->setKind(PracticeSessionKind::Speak)
            ->setKinescopeRoomId($result->roomId)
            ->setKinescopeRoomCode($result->code)
            ->touch();
        $this->entityManager->flush();

        return $session;
    }

    /**
     * Re-read RTMP credentials for an existing event (no second event create).
     */
    public function refreshLiveCredentials(PracticeSession $session): PracticeSession
    {
        $eventId = $session->getKinescopeEventId();
        if (null === $eventId || !$this->kinescope->isConfigured()) {
            return $session;
        }

        $data = $this->kinescope->getLiveEvent($eventId);
        $ingest = null;
        $key = null;
        foreach (['rtmp_link', 'server', 'rtmp_url', 'ingest_url'] as $ingestKey) {
            $candidate = trim((string) ($data[$ingestKey] ?? ''));
            if ('' !== $candidate) {
                $ingest = $candidate;
                break;
            }
        }
        foreach (['streamkey', 'stream_key', 'key'] as $keyName) {
            $candidate = trim((string) ($data[$keyName] ?? ''));
            if ('' !== $candidate) {
                $key = $candidate;
                break;
            }
        }
        if (null !== $ingest) {
            $session->setKinescopeIngestUrl($ingest);
        }
        if (null !== $key) {
            $session->setKinescopeStreamKey($key);
        }
        $session->touch();
        $this->entityManager->flush();

        return $session;
    }

    public function createSpeakMeetingRemote(SpeakMeeting $meeting): SpeakMeeting
    {
        if (!$this->kinescope->isConfigured()) {
            throw new \RuntimeException('Kinescope API token is not configured.');
        }

        if (null !== $meeting->getKinescopeRoomId()) {
            return $meeting;
        }

        $result = $this->kinescope->createSpeakRoom(
            $meeting->getTitle(),
            $meeting->getRoomType(),
            $meeting->isAutorecording(),
        );
        $meeting
            ->setKinescopeRoomId($result->roomId)
            ->setKinescopeRoomCode($result->code)
            ->setSpeakEmbedUrl($result->link)
            ->touch();
        $this->entityManager->flush();

        return $meeting;
    }

    public function goLive(PracticeSession $session): void
    {
        $session->setStatus(PracticeSessionStatus::Live);
        $session->touch();
        $this->entityManager->flush();
    }

    public function finishLiveViaApi(PracticeSession $session): array
    {
        $eventId = $session->getKinescopeEventId();
        if (null !== $eventId && $this->kinescope->isConfigured()) {
            try {
                $this->kinescope->completeLiveEvent($eventId);
            } catch (\Throwable $exception) {
                $this->logger->warning('Kinescope complete live failed.', [
                    'event_id' => $eventId,
                    'exception' => $exception->getMessage(),
                ]);
            }

            $videoIds = $this->kinescope->listEventVideoIds($eventId);
            $videoId = $videoIds[0] ?? $session->getKinescopeVideoId();
            if (null !== $videoId) {
                $this->enrichFromVideo($session, $videoId);
            }

            return $this->practiceSessions->finishWithRecording($session, $videoId);
        }

        return $this->practiceSessions->finishWithRecording($session, $session->getKinescopeVideoId());
    }

    public function syncSession(PracticeSession $session): PracticeSession
    {
        if (!$this->kinescope->isConfigured()) {
            return $session;
        }

        if (null !== $session->getKinescopeVideoId()) {
            $this->enrichFromVideo($session, $session->getKinescopeVideoId());
        } elseif (null !== $session->getKinescopeEventId()) {
            $videoIds = $this->kinescope->listEventVideoIds($session->getKinescopeEventId());
            if ([] !== $videoIds) {
                $this->enrichFromVideo($session, $videoIds[0]);
            }
        }

        $session->touch();
        $this->entityManager->flush();

        return $session;
    }

    public function handleKinescopeWebhook(array $payload): void
    {
        $event = (string) ($payload['event'] ?? '');
        $data = $payload['data'] ?? [];
        if (!\is_array($data)) {
            return;
        }

        match ($event) {
            'live.connected' => $this->onLiveConnected($data),
            'live.finished' => $this->onLiveFinished($data),
            'live.cancelled' => $this->onLiveCancelled($data),
            'media.update.status' => $this->onMediaStatus($data),
            'speak.call.started' => $this->onSpeakCallStarted($data),
            'speak.call.finished', 'speak.record.finished' => $this->onSpeakFinished($data),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function onLiveConnected(array $data): void
    {
        $eventId = trim((string) ($data['event_id'] ?? ''));
        $session = $this->sessions->findOneByKinescopeEventId($eventId);
        if (!$session instanceof PracticeSession) {
            return;
        }
        if (PracticeSessionStatus::Live !== $session->getStatus()) {
            $session->setStatus(PracticeSessionStatus::Live);
            $this->entityManager->flush();
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function onLiveFinished(array $data): void
    {
        $eventId = trim((string) ($data['event_id'] ?? ''));
        $videoId = trim((string) ($data['video_id'] ?? ''));
        if ('' === $eventId) {
            return;
        }
        if ('00000000-0000-0000-0000-000000000000' === $videoId) {
            $videoId = '';
        }

        $session = $this->sessions->findOneByKinescopeEventId($eventId);
        if (!$session instanceof PracticeSession) {
            return;
        }

        if ('' !== $videoId && $this->kinescope->isConfigured()) {
            try {
                $this->enrichFromVideo($session, $videoId);
            } catch (\Throwable $exception) {
                $this->logger->warning('Enrich video after live.finished failed.', [
                    'video_id' => $videoId,
                    'exception' => $exception->getMessage(),
                ]);
            }
        } elseif ($this->kinescope->isConfigured()) {
            try {
                $ids = $this->kinescope->listEventVideoIds($eventId);
                if ([] !== $ids) {
                    $videoId = $ids[0];
                    $this->enrichFromVideo($session, $videoId);
                }
            } catch (\Throwable) {
            }
        }

        $this->practiceSessions->finishWithRecording($session, '' !== $videoId ? $videoId : null);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function onLiveCancelled(array $data): void
    {
        $eventId = trim((string) ($data['event_id'] ?? ''));
        $session = $this->sessions->findOneByKinescopeEventId($eventId);
        if (!$session instanceof PracticeSession) {
            return;
        }
        if (\in_array($session->getStatus(), [PracticeSessionStatus::Replay, PracticeSessionStatus::Completed], true)) {
            return;
        }
        $session->setStatus(PracticeSessionStatus::Completed);
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function onMediaStatus(array $data): void
    {
        $videoId = trim((string) ($data['id'] ?? $data['video_id'] ?? ''));
        if ('' === $videoId) {
            return;
        }

        $session = $this->sessions->findOneByKinescopeVideoId($videoId);
        if (!$session instanceof PracticeSession) {
            return;
        }

        if ($this->kinescope->isConfigured()) {
            $this->enrichFromVideo($session, $videoId);
            $this->entityManager->flush();
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function onSpeakCallStarted(array $data): void
    {
        $roomId = trim((string) ($data['room_id'] ?? ''));
        $session = $this->sessions->findOneByKinescopeRoomId($roomId);
        if (!$session instanceof PracticeSession) {
            return;
        }
        if (PracticeSessionStatus::Live !== $session->getStatus()) {
            $session->setStatus(PracticeSessionStatus::Live);
            $this->entityManager->flush();
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function onSpeakFinished(array $data): void
    {
        $roomId = trim((string) ($data['room_id'] ?? ''));
        $session = $this->sessions->findOneByKinescopeRoomId($roomId);
        if (!$session instanceof PracticeSession) {
            return;
        }

        // Speak record.finished has record.id, not always catalog video id yet.
        $recordId = '';
        if (isset($data['record']) && \is_array($data['record'])) {
            $recordId = trim((string) ($data['record']['id'] ?? ''));
        }
        $videoId = trim((string) ($data['video_id'] ?? $recordId));

        if ('' !== $videoId && $this->kinescope->isConfigured()) {
            try {
                $this->enrichFromVideo($session, $videoId);
            } catch (\Throwable $exception) {
                $session->setKinescopeVideoId($videoId);
                $this->logger->info('Speak finish: video not ready yet.', [
                    'video_id' => $videoId,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        $this->practiceSessions->finishWithRecording($session, $session->getKinescopeVideoId());
    }

    private function enrichFromVideo(PracticeSession $session, string $videoId): void
    {
        $info = $this->kinescope->getVideo($videoId);
        $session->setKinescopeVideoId($info->id);
        if ('' !== $info->title && '' === $session->getTitle()) {
            $session->setTitle($info->title);
        }
        if (null !== $info->posterUrl) {
            $session->setPosterUrl($info->posterUrl);
        }
        if (null !== $info->duration) {
            $session->setDurationSeconds((int) round($info->duration));
        }
        if ($info->isReady() || 'done' === $info->status) {
            $session->setRecordingReadyAt($session->getRecordingReadyAt() ?? new \DateTimeImmutable());
        }
    }
}
