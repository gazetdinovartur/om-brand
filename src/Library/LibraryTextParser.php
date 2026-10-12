<?php

declare(strict_types=1);

namespace App\Library;

/**
 * @deprecated Use PlainTextBookParser or LibraryBookParser.
 */
final class LibraryTextParser
{
    public function __construct(
        private readonly PlainTextBookParser $parser = new PlainTextBookParser(),
    ) {
    }

    public function parse(
        LibrarySource $source,
        ?string $titleOverride = null,
        ?string $authorOverride = null,
    ): ParsedLibraryBook {
        return $this->parser->parse($source, $titleOverride, $authorOverride);
    }
}
