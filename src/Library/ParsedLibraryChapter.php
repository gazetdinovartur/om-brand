<?php

declare(strict_types=1);

namespace App\Library;

final readonly class ParsedLibraryChapter
{
    /**
     * @param list<array{id: string, type: string, text: string, level?: int}> $blocks
     */
    public function __construct(
        public string $title,
        public string $slug,
        public int $sortOrder,
        public array $blocks,
        public int $characterCount,
    ) {
    }
}
