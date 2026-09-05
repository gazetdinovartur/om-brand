<?php

namespace App\Service;

use App\Entity\ChronicleEntry;
use App\Enum\ChronicleStatus;

final class ChronicleTelegramCrosspostStatusResolver
{
    public function __construct(
        private readonly TelegramChannelCredentials $credentials,
    ) {
    }

    public function resolve(ChronicleEntry $entry): ChronicleTelegramCrosspostStatusView
    {
        if (!$this->credentials->isEnabled()) {
            return new ChronicleTelegramCrosspostStatusView('disabled', '—', 'Кросспост в Telegram отключён');
        }

        $error = $entry->getTgCrosspostError();
        if (TelegramChannelCredentials::PENDING_MARKER === $error) {
            return new ChronicleTelegramCrosspostStatusView('progress', 'отправка…', null);
        }

        $messageId = $entry->getTgMessageId();
        if (null !== $messageId) {
            return new ChronicleTelegramCrosspostStatusView(
                'posted',
                'опубликовано',
                null,
                $this->channelUrl(),
            );
        }

        if (null !== $error && '' !== $error) {
            return new ChronicleTelegramCrosspostStatusView('error', 'ошибка', $error);
        }

        if ($entry->isTgCrosspostRequested()) {
            if (!$this->credentials->canCrosspost()) {
                return new ChronicleTelegramCrosspostStatusView(
                    'offline',
                    'нет TG',
                    'Задайте TELEGRAM_BOT_TOKEN и TELEGRAM_CHANNEL_ID',
                );
            }

            if (ChronicleStatus::Scheduled === $entry->getStatus()) {
                $publishedAt = $entry->getPublishedAt();
                if (null !== $publishedAt && $publishedAt > new \DateTimeImmutable('+1 minute')) {
                    return new ChronicleTelegramCrosspostStatusView(
                        'deferred',
                        'ожидает дату',
                        'Отправится после публикации на сайте',
                    );
                }
            }

            return new ChronicleTelegramCrosspostStatusView('pending', 'ожидает', 'Крон отправит пост в канал');
        }

        return new ChronicleTelegramCrosspostStatusView('none', '—', null);
    }

    private function channelUrl(): ?string
    {
        $id = $this->credentials->channelId();
        if ('' === $id) {
            return null;
        }

        if (str_starts_with($id, '@')) {
            return 'https://t.me/'.ltrim($id, '@');
        }

        return null;
    }
}
