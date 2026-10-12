<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LibraryBook;
use App\Entity\LibraryChapter;

final class ReadingProgressCalculator
{
    public function calculateBasisPoints(
        LibraryBook $book,
        LibraryChapter $currentChapter,
        string $blockId,
    ): int {
        $total = max(1, $book->getTotalCharacters());
        $consumed = 0;

        foreach ($book->getChapters() as $chapter) {
            if (!$this->isSameChapter($chapter, $currentChapter)) {
                $consumed += $chapter->getCharacterCount();
                continue;
            }

            foreach ($chapter->getBlocks() as $block) {
                if ($block['id'] === $blockId) {
                    $consumed += (int) round($this->length($block['text']) / 2);

                    return min(10000, max(0, (int) round($consumed / $total * 10000)));
                }
                $consumed += $this->length($block['text']);
            }

            break;
        }

        return min(10000, max(0, (int) round($consumed / $total * 10000)));
    }

    private function isSameChapter(LibraryChapter $left, LibraryChapter $right): bool
    {
        if ($left === $right) {
            return true;
        }

        $leftId = $left->getId();
        $rightId = $right->getId();

        return null !== $leftId && $leftId === $rightId;
    }

    private function length(string $value): int
    {
        $length = iconv_strlen($value, 'UTF-8');

        return false === $length ? strlen($value) : $length;
    }
}
