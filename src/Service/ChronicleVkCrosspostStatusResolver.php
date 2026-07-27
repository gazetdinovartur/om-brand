<?php

namespace App\Service;

use App\Entity\ChronicleEntry;
use App\Enum\ChronicleStatus;

/**
 * Derives admin UI status for VK crosspost from persisted entry fields.
 */
final class ChronicleVkCrosspostStatusResolver
{
    private const MIN_SCHEDULE_OFFSET_SECONDS = 600;

    private const MAX_SCHEDULE_OFFSET_SECONDS = 2678400;

    public function __construct(
        private readonly VkCredentials $credentials,
    ) {
    }

    public function resolve(ChronicleEntry $entry): ChronicleVkCrosspostStatusView
    {
        if (!$this->credentials->isEnabled()) {
            return new ChronicleVkCrosspostStatusView('disabled', '—', 'Кросспост в VK отключён');
        }

        if ($entry->isImportedFromVk()) {
            return new ChronicleVkCrosspostStatusView(
                'imported',
                'импорт с VK',
                'Запись подтянута с стены VK, не кросспост',
            );
        }

        $error = $entry->getVkCrosspostError();
        if (VkCredentials::PENDING_MARKER === $error) {
            return new ChronicleVkCrosspostStatusView('progress', 'отправка…', null);
        }

        $postId = $entry->getVkPostId();
        $wallUrl = $this->wallUrl($postId);

        if (null !== $postId) {
            if ($this->isVkPostScheduled($entry)) {
                return new ChronicleVkCrosspostStatusView(
                    'scheduled',
                    'запланировано',
                    $this->scheduleHint($entry),
                    $wallUrl,
                );
            }

            return new ChronicleVkCrosspostStatusView('posted', 'опубликовано', null, $wallUrl);
        }

        if (null !== $error && '' !== $error) {
            return new ChronicleVkCrosspostStatusView('error', 'ошибка', $error);
        }

        if ($entry->isVkCrosspostRequested()) {
            if (!$this->credentials->canCrosspost()) {
                return new ChronicleVkCrosspostStatusView('offline', 'нет VK', 'Подключите VK в редакторе');
            }

            if ($this->isDeferred($entry)) {
                return new ChronicleVkCrosspostStatusView(
                    'deferred',
                    'ожидает дату',
                    $this->deferredHint($entry),
                );
            }

            return new ChronicleVkCrosspostStatusView('pending', 'ожидает', 'Крон отправит пост в VK');
        }

        return new ChronicleVkCrosspostStatusView('none', '—', null);
    }

    private function wallUrl(?int $postId): ?string
    {
        if (null === $postId) {
            return null;
        }

        $ownerId = $this->credentials->ownerId();
        if (null === $ownerId) {
            return null;
        }

        return sprintf('https://vk.com/wall%d_%d', $ownerId, $postId);
    }

    private function isVkPostScheduled(ChronicleEntry $entry): bool
    {
        if (ChronicleStatus::Scheduled === $entry->getStatus()) {
            return true;
        }

        $vkPostedAt = $entry->getVkPostedAt();
        if (null === $vkPostedAt) {
            return false;
        }

        return $vkPostedAt > new \DateTimeImmutable();
    }

    private function isDeferred(ChronicleEntry $entry): bool
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

        return $targetTs > $now + self::MAX_SCHEDULE_OFFSET_SECONDS;
    }

    private function scheduleHint(ChronicleEntry $entry): ?string
    {
        $publishedAt = $entry->getPublishedAt();
        if (null !== $publishedAt) {
            return 'На сайте: '.$publishedAt->format('d.m.Y H:i');
        }

        $vkPostedAt = $entry->getVkPostedAt();
        if (null !== $vkPostedAt) {
            return 'В VK: '.$vkPostedAt->format('d.m.Y H:i');
        }

        return null;
    }

    private function deferredHint(ChronicleEntry $entry): ?string
    {
        $publishedAt = $entry->getPublishedAt();
        if (null === $publishedAt) {
            return 'VK допускает отложку не дальше ~31 дней';
        }

        return sprintf(
            'Публикация %s — VK допускает отложку не дальше ~31 дней',
            $publishedAt->format('d.m.Y H:i'),
        );
    }
}
