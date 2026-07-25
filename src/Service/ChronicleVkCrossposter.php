<?php

namespace App\Service;

use App\Entity\ChronicleEntry;
use App\Repository\ChronicleEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class ChronicleVkCrossposter
{
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
     * Crosspost all due visible entries that lack vk_post_id.
     *
     * @return array{posted: int, skipped: int, failed: int}
     */
    public function crosspostDue(int $limit = 20): array
    {
        $stats = ['posted' => 0, 'skipped' => 0, 'failed' => 0];
        if (!$this->credentials->canCrosspost()) {
            return $stats;
        }

        foreach ($this->entries->findDueForVkCrosspost($limit) as $entry) {
            $result = $this->crosspostEntry($entry);
            ++$stats[$result];
        }

        return $stats;
    }

    /**
     * @return 'posted'|'skipped'|'failed'
     */
    public function crosspostEntry(ChronicleEntry $entry): string
    {
        if (!$this->credentials->canCrosspost()) {
            return 'skipped';
        }

        if (null !== $entry->getVkPostId()) {
            return 'skipped';
        }

        if ($entry->isImportedFromVk()) {
            return 'skipped';
        }

        if (!$entry->isVisibleInFeed()) {
            return 'skipped';
        }

        if (VkCredentials::PENDING_MARKER === $entry->getVkCrosspostError()) {
            return 'skipped';
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
            );

            $entry->setVkPostId($postId);
            $entry->setVkPostedAt(new \DateTimeImmutable());
            $entry->setVkCrosspostError(null);
            $this->em->flush();

            $this->logger->info('VK crosspost ok', [
                'entry_id' => $entry->getId(),
                'vk_post_id' => $postId,
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
}
