<?php

declare(strict_types=1);

namespace App\Library;

final readonly class LibrarySource
{
    public const FORMAT_TXT = 'txt';
    public const FORMAT_MD = 'md';
    public const FORMAT_FB2 = 'fb2';
    public const FORMAT_EPUB = 'epub';
    public const FORMAT_PDF = 'pdf';

    public function __construct(
        /** Decoded text for txt/md/fb2; raw bytes for epub/pdf. */
        public string $text,
        public string $checksum,
        public string $sourceName,
        public string $format = self::FORMAT_TXT,
    ) {
    }
}
