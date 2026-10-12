<?php

declare(strict_types=1);

namespace App\Library;

final class LibraryBookParser
{
    public function __construct(
        private readonly PlainTextBookParser $plainText = new PlainTextBookParser(),
        private readonly MarkdownBookParser $markdown = new MarkdownBookParser(),
        private readonly Fb2BookParser $fb2 = new Fb2BookParser(),
        private readonly EpubBookParser $epub = new EpubBookParser(),
    ) {
    }

    public function parse(
        LibrarySource $source,
        ?string $titleOverride = null,
        ?string $authorOverride = null,
    ): ParsedLibraryBook {
        return match ($source->format) {
            LibrarySource::FORMAT_MD => $this->markdown->parse($source, $titleOverride, $authorOverride),
            LibrarySource::FORMAT_FB2 => $this->fb2->parse($source, $titleOverride, $authorOverride),
            LibrarySource::FORMAT_EPUB => $this->epub->parse($source, $titleOverride, $authorOverride),
            default => $this->plainText->parse($source, $titleOverride, $authorOverride),
        };
    }
}
