<?php

namespace App\Service;

use App\Entity\ChronicleEntry;
use App\Enum\ChronicleStatus;
use App\Repository\ChronicleEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class ChronicleVkCrossposter
{
  /** VK: publish_date не раньше чем через 10 минут. */
    private const MIN_SCHEDULE_OFFSET_SECONDS = 600;

    /** VK: отложенная публикация не дальше ~31 дней (практический лимит API). */
    private const MAX_SCHEDULE_OFFSET_SECONDS = 2678400;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChronicleEntryRepository $entries,
        private readonly VkCredentials $credentials,
        private readonly VkApiClient $vk,
        private readonly ChronicleVkMessageBuilder $builder,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Crosspost entries with vk_crosspost_requested and no vk_post_id yet.
     *
     * @return array{posted: int, skipped: int, failed: int, deferred: int}
     */
    public function crosspostDue(int $limit = 20): array
    {
        $stats = ['posted' => 0, 'skipped' => 0, 'failed' => 0, 'deferred' => 0];
        if (!$this->credentials->canCrosspost()) {
            return $stats;
        }

        foreach ($this->entries->findDueForVkCrosspost($limit) as $entry) {
            $result = $this->crosspostEntry($entry);
            if (isset($stats[$result])) {
                ++$stats[$result];
            } else {
                ++$stats['skipped'];
            }
        }

        return $stats;
    }

    /**
     * @return 'posted'|'skipped'|'failed'|'deferred'
     */
    public function crosspostEntry(ChronicleEntry $entry): string
    {
        if (!$this->credentials->canCrosspost()) {
            return 'skipped';
        }

        if (!$entry->isVkCrosspostRequested()) {
            return 'skipped';
        }

        if (null !== $entry->getVkPostId()) {
            return 'skipped';
        }

        if ($entry->isImportedFromVk()) {
            return 'skipped';
        }

        if (!$this->isEligibleForVk($entry)) {
            return 'skipped';
        }

        if (VkCredentials::PENDING_MARKER === $entry->getVkCrosspostError()) {
            return 'skipped';
        }

        $publishDate = $this->resolvePublishDate($entry);
        if ($this->shouldDeferScheduledPost($entry, $publishDate)) {
            return 'deferred';
        }

        $entry->setVkCrosspostError(VkCredentials::PENDING_MARKER);
        $this->em->flush();

        try {
            $ownerId = $this->vk->resolveOwnerId();
            $payload = $this->builder->build($entry);
            $attachments = $this->vk->uploadWallPhotos($ownerId, $payload['imagePaths']);
            $postId = $this->vk->wallPost(
                $ownerId,
                $payload['message'],
                $attachments,
                'chronicle-entry-'.(int) $entry->getId(),
                $payload['copyright'] ?? $payload['shortUrl'] ?? null,
                $publishDate,
            );

            $entry->setVkPostId($postId);
            $entry->setVkPostedAt($this->resolveVkPostedAt($entry, $publishDate));
            $entry->setVkCrosspostError(null);
            $this->em->flush();

            $this->logger->info('VK crosspost ok', [
                'entry_id' => $entry->getId(),
                'vk_post_id' => $postId,
                'publish_date' => $publishDate,
            ]);

            return 'posted';
        } catch (\Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 500);
            $entry->setVkCrosspostError($message);
            $this->em->flush();

            $this->logger->error('VK crosspost failed', [
                'entry_id' => $entry->getId(),
                'error' => $message,
            ]);

            return 'failed';
        }
    }

    /**
     * @return 'updated'|'skipped'|'failed'
     */
    public function updateEntry(ChronicleEntry $entry): string
    {
        if (!$this->credentials->canCrosspost()) {
            return 'skipped';
        }

        $postId = $entry->getVkPostId();
        if (null === $postId) {
            return 'skipped';
        }

        if ($entry->isImportedFromVk()) {
            return 'skipped';
        }

        try {
            $ownerId = $this->vk->resolveOwnerId();
            $payload = $this->builder->build($entry);
            $attachments = $this->vk->uploadWallPhotos($ownerId, $payload['imagePaths']);
            $this->vk->wallEdit(
                $ownerId,
                $postId,
                $payload['message'],
                $attachments,
                $payload['copyright'] ?? $payload['shortUrl'] ?? null,
            );

            $entry->setVkCrosspostError(null);
            $this->em->flush();

            $this->logger->info('VK wall edit ok', [
                'entry_id' => $entry->getId(),
                'vk_post_id' => $postId,
            ]);

            return 'updated';
        } catch (\Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 500);
            $entry->setVkCrosspostError($message);
            $this->em->flush();

            $this->logger->error('VK wall edit failed', [
                'entry_id' => $entry->getId(),
                'error' => $message,
            ]);

            return 'failed';
        }
    }

    private function isEligibleForVk(ChronicleEntry $entry): bool
    {
        if ($entry->isAdminOnly()) {
            return false;
        }

        if (null === $entry->getPublishedAt()) {
            return false;
        }

        return ChronicleStatus::Published === $entry->getStatus()
            || ChronicleStatus::Scheduled === $entry->getStatus();
    }

  /**
   * Unix publish_date for wall.post, or null for immediate publication.
   */
    private function resolvePublishDate(ChronicleEntry $entry): ?int
    {
        $publishedAt = $entry->getPublishedAt();
        if (null === $publishedAt) {
            return null;
        }

        $targetTs = $publishedAt->getTimestamp();
        $now = time();

        if (ChronicleStatus::Scheduled !== $entry->getStatus()) {
            return null;
        }

        if ($targetTs <= $now + 60) {
            return null;
        }

        $minTs = $now + self::MIN_SCHEDULE_OFFSET_SECONDS;
        $maxTs = $now + self::MAX_SCHEDULE_OFFSET_SECONDS;

        if ($targetTs > $maxTs) {
            return null;
        }

        return max($targetTs, $minTs);
    }

    private function shouldDeferScheduledPost(ChronicleEntry $entry, ?int $publishDate): bool
    {
        if (ChronicleStatus::Scheduled !== $entry->getStatus()) {
            return false;
        }

        $publishedAt = $entry->getPublishedAt();
        if (null === $publishedAt) {
            return false;
        }

        $targetTs = $publishedAt->getTimestamp();
        $now = time();

        if ($targetTs <= $now + 60) {
            return false;
        }

        if ($targetTs > $now + self::MAX_SCHEDULE_OFFSET_SECONDS) {
            return true;
        }

        return null === $publishDate && $targetTs > $now + self::MIN_SCHEDULE_OFFSET_SECONDS;
    }

    private function resolveVkPostedAt(ChronicleEntry $entry, ?int $publishDate): \DateTimeImmutable
    {
        if (null !== $publishDate) {
            return new \DateTimeImmutable('@'.$publishDate);
        }

        $publishedAt = $entry->getPublishedAt();
        if (null !== $publishedAt && ChronicleStatus::Scheduled === $entry->getStatus()) {
            return $publishedAt;
        }

        return new \DateTimeImmutable();
    }
}
