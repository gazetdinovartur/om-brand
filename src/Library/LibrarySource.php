<?php

declare(strict_types=1);

namespace App\Library;

final readonly class LibrarySource
{
    public function __construct(
        public string $text,
        public string $checksum,
        public string $sourceName,
    ) {
    }
}
