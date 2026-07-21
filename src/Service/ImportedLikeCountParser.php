<?php

namespace App\Service;

/**
 * Parses imported like/reaction totals from meta callout bodies.
 *
 * Examples:
 *  - "❤ 12"
 *  - "🔥 2 · ❤ 1" → 3
 *  - "❤‍🔥 3 · 🕊 1" → 4
 */
final class ImportedLikeCountParser
{
    public function parse(?string $body): ?int
    {
        if (null === $body || '' === trim($body)) {
            return null;
        }

        if (preg_match_all('/(\d+)/u', $body, $matches) && [] !== $matches[1]) {
            $sum = 0;
            foreach ($matches[1] as $n) {
                $sum += max(0, (int) $n);
            }

            return $sum > 0 ? $sum : 0;
        }

        if (preg_match('/(?:^|\s)(\d+)\s*(?:лайк|like)/iu', $body, $matches)) {
            return max(0, (int) $matches[1]);
        }

        return null;
    }
}
