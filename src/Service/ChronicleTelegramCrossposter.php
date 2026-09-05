<?php

namespace App\Service;

use App\Entity\ChronicleEntry;
use App\Enum\ChronicleStatus;
use App\Repository\ChronicleEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ChronicleTelegramCrossposter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChronicleEntryRepository $entries,
        private readonly TelegramChannelCredentials $credentials,
        private readonly ChronicleTelegramMessageBuilder $builder,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{posted: int, skipped: int, failed: int, deferred: int}
     */
    public function crosspostDue(int $limit = 20): array
    {
        $stats = ['posted' => 0, 'skipped' => 0, 'failed' => 0, 'deferred' => 0];
        if (!$this->credentials->canCrosspost()) {
            return $stats;
        }

        foreach ($this->entries->findDueForTelegramCrosspost($limit) as $entry) {
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

        if (!$entry->isTgCrosspostRequested()) {
            return 'skipped';
        }

        if (null !== $entry->getTgMessageId()) {
            return 'skipped';
        }

        if (!$this->isEligible($entry)) {
            return 'skipped';
        }

        if (TelegramChannelCredentials::PENDING_MARKER === $entry->getTgCrosspostError()) {
            return 'skipped';
        }

        if (ChronicleStatus::Scheduled === $entry->getStatus()) {
            $publishedAt = $entry->getPublishedAt();
            if (null !== $publishedAt && $publishedAt > new \DateTimeImmutable('+1 minute')) {
                return 'deferred';
            }
        }

        $entry->setTgCrosspostError(TelegramChannelCredentials::PENDING_MARKER);
        $this->em->flush();

        try {
            $payload = $this->builder->build($entry);
            $messageId = $this->sendToChannel($payload['message'], $payload['imagePaths']);

            $entry->setTgMessageId($messageId);
            $entry->setTgPostedAt(new \DateTimeImmutable());
            $entry->setTgCrosspostError(null);
            $this->em->flush();

            $this->logger->info('Telegram channel crosspost ok', [
                'entry_id' => $entry->getId(),
                'tg_message_id' => $messageId,
            ]);

            return 'posted';
        } catch (\Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 500);
            $entry->setTgCrosspostError($message);
            $this->em->flush();

            $this->logger->error('Telegram channel crosspost failed', [
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

        $messageId = $entry->getTgMessageId();
        if (null === $messageId) {
            return 'skipped';
        }

        try {
            $payload = $this->builder->build($entry);
            $this->editChannelMessage($messageId, $payload['message']);

            $entry->setTgCrosspostError(null);
            $this->em->flush();

            return 'updated';
        } catch (\Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 500);
            $entry->setTgCrosspostError($message);
            $this->em->flush();

            return 'failed';
        }
    }

    /**
     * @param list<string> $imagePaths
     */
    private function sendToChannel(string $message, array $imagePaths): int
    {
        $token = $this->credentials->botToken();
        $chatId = $this->credentials->channelId();
        $firstImage = $imagePaths[0] ?? null;

        if (null !== $firstImage && is_file($firstImage)) {
            $response = $this->httpClient->request('POST', sprintf('https://api.telegram.org/bot%s/sendPhoto', $token), [
                'body' => [
                    'chat_id' => $chatId,
                    'caption' => mb_substr($message, 0, 1024),
                    'photo' => fopen($firstImage, 'r'),
                    'disable_notification' => 'false',
                ],
                'timeout' => 120,
            ]);
        } else {
            $response = $this->httpClient->request('POST', sprintf('https://api.telegram.org/bot%s/sendMessage', $token), [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'disable_web_page_preview' => false,
                ],
                'timeout' => 60,
            ]);
        }

        /** @var array{ok?: bool, result?: array{message_id?: int}, description?: string} $data */
        $data = $response->toArray(false);
        if (empty($data['ok']) || !isset($data['result']['message_id'])) {
            $desc = (string) ($data['description'] ?? 'Telegram API error');
            throw new \RuntimeException($desc);
        }

        return (int) $data['result']['message_id'];
    }

    private function editChannelMessage(int $messageId, string $message): void
    {
        $token = $this->credentials->botToken();
        $chatId = $this->credentials->channelId();

        $response = $this->httpClient->request('POST', sprintf('https://api.telegram.org/bot%s/editMessageText', $token), [
            'json' => [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $message,
                'disable_web_page_preview' => false,
            ],
            'timeout' => 60,
        ]);

        /** @var array{ok?: bool, description?: string} $data */
        $data = $response->toArray(false);
        if (empty($data['ok'])) {
            // Photo posts cannot be edited as text — leave a clear error.
            $desc = (string) ($data['description'] ?? 'Telegram edit failed');
            throw new \RuntimeException($desc);
        }
    }

    private function isEligible(ChronicleEntry $entry): bool
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
}
