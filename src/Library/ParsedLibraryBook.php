<?php

declare(strict_types=1);

namespace App\Library;

final readonly class ParsedLibraryBook
{
    /**
     * @param list<ParsedLibraryChapter> $chapters
     * @param array<string, string>       $footnotes
     */
    public function __construct(
        public string $title,
        public string $author,
        public string $checksum,
        public array $chapters,
        public array $footnotes,
        public int $totalCharacters,
        public string $renderMode = 'html',
        public bool $keepSourceFile = false,
        public int $pdfPageCount = 0,
    ) {
    }
}
