<?php

declare(strict_types=1);

namespace App\Library;

use Smalot\PdfParser\Parser as SmalotPdfParser;

/**
 * Extract text from PDF into chapters/blocks; mark poor extractions for viewer fallback.
 */
final class PdfBookParser
{
    private const MIN_CHARS_PER_PAGE = 80;
    private const MIN_PRINTABLE_RATIO = 0.72;
    private const MIN_TOTAL_CHARS = 400;

    public function __construct(
        private readonly PlainTextBookParser $plainText = new PlainTextBookParser(),
    ) {
    }

    public function parse(
        LibrarySource $source,
        ?string $titleOverride = null,
        ?string $authorOverride = null,
    ): ParsedLibraryBook {
        $parser = new SmalotPdfParser();
        try {
            $pdf = $parser->parseContent($source->text);
        } catch (\Throwable $e) {
            return $this->fallbackBook($source, $titleOverride, $authorOverride, 0, $e->getMessage());
        }

        $pages = $pdf->getPages();
        $pageCount = \count($pages);
        $pageTexts = [];
        $totalRaw = 0;
        $printable = 0;

        foreach ($pages as $index => $page) {
            try {
                $text = trim((string) $page->getText());
            } catch (\Throwable) {
                $text = '';
            }
            $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
            $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
            $pageTexts[] = $text;
            $len = mb_strlen($text);
            $totalRaw += $len;
            $printable += $this->printableLength($text);
        }

        $avgPerPage = $pageCount > 0 ? $totalRaw / $pageCount : 0;
        $ratio = $totalRaw > 0 ? $printable / $totalRaw : 0.0;
        $good = $pageCount > 0
            && $totalRaw >= self::MIN_TOTAL_CHARS
            && $avgPerPage >= self::MIN_CHARS_PER_PAGE
            && $ratio >= self::MIN_PRINTABLE_RATIO;

        if (!$good) {
            return $this->fallbackBook($source, $titleOverride, $authorOverride, $pageCount);
        }

        $joined = implode("\n\n", array_filter($pageTexts, static fn (string $t): bool => '' !== trim($t)));
        $textSource = new LibrarySource(
            $joined,
            $source->checksum,
            $source->sourceName,
            LibrarySource::FORMAT_TXT,
        );
        $parsed = $this->plainText->parse($textSource, $titleOverride, $authorOverride);

        return new ParsedLibraryBook(
            $parsed->title,
            $parsed->author,
            $source->checksum,
            $parsed->chapters,
            $parsed->footnotes,
            $parsed->totalCharacters,
            'html',
            false,
            $pageCount,
        );
    }

    private function fallbackBook(
        LibrarySource $source,
        ?string $titleOverride,
        ?string $authorOverride,
        int $pageCount,
        ?string $reason = null,
    ): ParsedLibraryBook {
        [$title, $author] = BookParseSupport::resolveTitleAuthor(
            $titleOverride,
            $authorOverride,
            null,
            null,
            $source->sourceName,
        );

        $pages = max(1, $pageCount);
        $blocks = [];
        for ($i = 1; $i <= $pages; ++$i) {
            $blocks[] = [
                'id' => 'pdf-p-'.$i,
                'type' => 'paragraph',
                'text' => $reason ? 'PDF (fallback): '.$reason : sprintf('Страница %d', $i),
            ];
        }

        $chapter = new ParsedLibraryChapter(
            'PDF',
            'pdf',
            1,
            $blocks,
            array_sum(array_map(static fn (array $b): int => mb_strlen($b['text']), $blocks)),
        );

        return new ParsedLibraryBook(
            $title,
            $author,
            $source->checksum,
            [$chapter],
            [],
            $chapter->characterCount,
            'pdf_fallback',
            true,
            $pages,
        );
    }

    private function printableLength(string $text): int
    {
        $clean = preg_replace('/[^\p{L}\p{N}\p{P}\p{Z}]/u', '', $text) ?? '';

        return mb_strlen($clean);
    }
}
