<?php

namespace App\Service;

/** Parses VK/Telegram imported like counts from meta callout bodies like "❤ 12". */
final class ImportedLikeCountParser
{
    public function parse(?string $body): ?int
    {
        if (null === $body || '' === trim($body)) {
            return null;
        }

        if (preg_match('/❤\s*(\d+)/u', $body, $matches)) {
            return max(0, (int) $matches[1]);
        }

        if (preg_match('/(?:^|\s)(\d+)\s*(?:лайк|like)/iu', $body, $matches)) {
            return max(0, (int) $matches[1]);
        }

        return null;
    }
}
