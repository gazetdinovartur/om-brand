<?php

namespace App\Service;

use App\Entity\ChronicleEntry;

/**
 * Builds Telegram channel message from a chronicle entry (reuses VK body + short URL footer).
 */
final class ChronicleTelegramMessageBuilder
{
    private const MAX_MESSAGE_CHARS = 4000;

    public function __construct(
        private readonly ChronicleVkMessageBuilder $vkBuilder,
    ) {
    }

    /**
     * @return array{message: string, imagePaths: list<string>, shortUrl: string}
     */
    public function build(ChronicleEntry $entry): array
    {
        $vk = $this->vkBuilder->build($entry);
        $shortUrl = $vk['shortUrl'];
        $message = trim($vk['message']);
        if ('' !== $shortUrl && !str_contains($message, $shortUrl)) {
            $message = '' !== $message ? $message."\n\n".$shortUrl : $shortUrl;
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_CHARS) {
            $message = rtrim(mb_substr($message, 0, self::MAX_MESSAGE_CHARS - 1)).'…';
        }

        return [
            'message' => $message,
            'imagePaths' => $vk['imagePaths'],
            'shortUrl' => $shortUrl,
        ];
    }
}
