<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ChronicleBlock;
use App\Entity\ChronicleEntry;
use App\Enum\ChronicleBlockType;

/**
 * Avoid repeating the same prose in hero title, lede, and first body paragraph.
 */
final class ChronicleDisplayDeduper
{
    public function showHeroLede(ChronicleEntry $entry): bool
    {
        $lede = trim((string) $entry->getLede());
        if ('' === $lede) {
            return false;
        }

        $nLede = $this->normalize($lede);
        $nTitle = $this->normalize($entry->getTitle());
        if ('' === $nLede || $nLede === $nTitle) {
            return false;
        }

        // Title is just a shortened lede — keep lede only if body won't repeat it.
        $first = $this->firstParagraphBody($entry);
        if (null === $first) {
            return true;
        }

        $nFirst = $this->normalize($first);
        if ($nFirst === $nLede || str_starts_with($nFirst, $nLede) || str_starts_with($nLede, $nFirst)) {
            return false;
        }

        if ($this->sameProse($nLede, $nFirst)) {
            return false;
        }

        return true;
    }

    /**
     * @return list<ChronicleBlock>
     */
    public function blocksForDisplay(ChronicleEntry $entry): array
    {
        $showLede = $this->showHeroLede($entry);
        $nTitle = $this->normalize($entry->getTitle());
        $nLede = $this->normalize((string) $entry->getLede());
        $skippedFirstParagraph = false;
        $out = [];

        foreach ($entry->getBlocks() as $block) {
            if (!$block instanceof ChronicleBlock) {
                continue;
            }

            if (!$skippedFirstParagraph && ChronicleBlockType::Paragraph === $block->getType()) {
                $skippedFirstParagraph = true;
                $nBody = $this->normalize((string) $block->getBody());

                if ('' === $nBody) {
                    continue;
                }

                // Body is the title (short one-liner posts).
                if ($this->sameProse($nBody, $nTitle)) {
                    continue;
                }

                // Lede already carries this text in the hero.
                if ($showLede && $this->sameProse($nBody, $nLede)) {
                    continue;
                }

                $out[] = $block;
                continue;
            }

            $out[] = $block;
        }

        return $out;
    }

    public function showCardLede(ChronicleEntry $entry): bool
    {
        $lede = trim((string) $entry->getLede());
        if ('' === $lede) {
            return false;
        }

        return !$this->sameProse($this->normalize($lede), $this->normalize($entry->getTitle()));
    }

    private function firstParagraphBody(ChronicleEntry $entry): ?string
    {
        foreach ($entry->getBlocks() as $block) {
            if (!$block instanceof ChronicleBlock) {
                continue;
            }
            if (ChronicleBlockType::Paragraph === $block->getType()) {
                $body = trim((string) $block->getBody());

                return '' === $body ? null : $body;
            }
        }

        return null;
    }

    private function normalize(string $text): string
    {
        $text = strip_tags($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace("/\n{2,}/u", "\n", $text) ?? $text;
        $text = trim($text);

        return mb_strtolower($text);
    }

    private function sameProse(string $a, string $b): bool
    {
        if ('' === $a || '' === $b) {
            return false;
        }
        if ($a === $b) {
            return true;
        }

        // One is a trimmed/prefix form of the other (short title vs full line).
        $shorter = mb_strlen($a) <= mb_strlen($b) ? $a : $b;
        $longer = mb_strlen($a) <= mb_strlen($b) ? $b : $a;

        if (str_starts_with($longer, $shorter)) {
            $rest = trim(mb_substr($longer, mb_strlen($shorter)), " \n\t.,;:—–-");

            // Prefix match only counts as duplicate when the remainder is tiny
            // OR when the shorter is already most of the longer (title cut).
            if ('' === $rest) {
                return true;
            }
            if (mb_strlen($shorter) >= (int) (mb_strlen($longer) * 0.72)) {
                return true;
            }
        }

        return false;
    }
}
