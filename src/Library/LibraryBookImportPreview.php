<?php

declare(strict_types=1);

namespace App\Library;

final readonly class LibraryBookImportPreview
{
    public function __construct(
        public string $sourceName,
        public string $title,
        public string $author,
        public string $slug,
        public int $chapterCount,
        public int $footnoteCount,
        public int $totalCharacters,
        public string $checksum,
        public ParsedLibraryBook $parsed,
    ) {
    }
}
