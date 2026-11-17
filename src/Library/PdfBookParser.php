<?php

declare(strict_types=1);

namespace App\Library;

use Smalot\PdfParser\Parser as SmalotPdfParser;

/**
 * Extract text from PDF into chapters/blocks; mark poor extractions for viewer fallback.
 *
 * Good extractions become a continuous HTML reader. Title/author come from overrides or
 * the filename — never from body heuristics (PDF page text often looks like chapter
 * headings and blows past DB column limits).
 */
final class PdfBookParser
{
    private const MIN_CHARS_PER_PAGE = 80;
    private const MIN_PRINTABLE_RATIO = 0.72;
    private const MIN_TOTAL_CHARS = 400;
    private const PAGES_PER_CHAPTER = 20;

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

        foreach ($pages as $page) {
            try {
                $text = (string) $page->getText();
            } catch (\Throwable) {
                $text = '';
            }
            $text = $this->cleanPageText($text);
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

        return $this->textBookFromPages($source, $pageTexts, $pageCount, $titleOverride, $authorOverride);
    }

    /**
     * @param list<string> $pageTexts
     */
    private function textBookFromPages(
        LibrarySource $source,
        array $pageTexts,
        int $pageCount,
        ?string $titleOverride,
        ?string $authorOverride,
    ): ParsedLibraryBook {
        [$title, $author] = BookParseSupport::resolveTitleAuthor(
            $titleOverride,
            $authorOverride,
            null,
            null,
            $source->sourceName,
        );

        $rawChapters = [];
        $chunk = [];
        $chunkStart = 1;
        foreach ($pageTexts as $index => $pageText) {
            $pageNumber = $index + 1;
            $paragraphs = $this->paragraphsFromPage($pageText);
            if ([] !== $paragraphs) {
                $chunk = [...$chunk, ...$paragraphs];
            }

            $isLast = $pageNumber === $pageCount;
            $full = \count($chunk) > 0 && (
                0 === $pageNumber % self::PAGES_PER_CHAPTER
                || $isLast
            );
            if (!$full) {
                continue;
            }

            $end = $pageNumber;
            $rawChapters[] = [
                'title' => $chunkStart === $end
                    ? sprintf('Страница %d', $chunkStart)
                    : sprintf('Страницы %d–%d', $chunkStart, $end),
                'slug' => 'pages-'.$chunkStart.'-'.$end,
                'paragraphs' => $chunk,
            ];
            $chunk = [];
            $chunkStart = $end + 1;
        }

        if ([] === $rawChapters) {
            return $this->fallbackBook($source, $titleOverride, $authorOverride, $pageCount);
        }

        $footnotes = [];
        $chapters = BookParseSupport::chaptersFromRaw($rawChapters, $footnotes);

        return new ParsedLibraryBook(
            $title,
            $author,
            $source->checksum,
            $chapters,
            $footnotes,
            array_sum(array_map(static fn (ParsedLibraryChapter $c): int => $c->characterCount, $chapters)),
            'html',
            false,
            $pageCount,
        );
    }

    /** @return list<string> */
    private function paragraphsFromPage(string $pageText): array
    {
        $pageText = trim($pageText);
        if ('' === $pageText || $this->isJunkParagraph($pageText)) {
            return [];
        }

        $parts = preg_split('/\n{2,}/u', $pageText) ?: [];
        $paragraphs = [];
        foreach ($parts as $part) {
            $paragraph = BookParseSupport::normalizeParagraph($part);
            $paragraph = $this->stripInlinePageNumbers($paragraph);
            if ('' !== $paragraph && !$this->isJunkParagraph($paragraph)) {
                $paragraphs[] = $paragraph;
            }
        }

        return $paragraphs;
    }

    /**
     * Drop glyph noise, lone page numbers, soft-wrap lines into readable paragraphs.
     */
    private function cleanPageText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text) ?? $text;
        $lines = preg_split('/\n/u', $text) ?: [];
        $kept = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/[ \t]+/u', ' ', $line) ?? $line);
            if ($this->isJunkLine($line)) {
                continue;
            }
            $kept[] = $line;
        }
        if ([] === $kept) {
            return '';
        }

        $joined = '';
        foreach ($kept as $line) {
            if ('' === $joined) {
                $joined = $line;
                continue;
            }
            // Перенос со дефисом: "раз-" + "витие" → "развитие"
            if (preg_match('/\p{L}-$/u', $joined) && preg_match('/^\p{Ll}/u', $line)) {
                $joined = mb_substr($joined, 0, -1).$line;
                continue;
            }
            $joined .= ' '.$line;
        }

        $joined = preg_replace('/\s{2,}/u', ' ', $joined) ?? $joined;
        $joined = $this->stripInlinePageNumbers(trim($joined));

        return $this->isJunkParagraph($joined) ? '' : $joined;
    }

    private function isJunkLine(string $line): bool
    {
        if ('' === $line) {
            return true;
        }
        // Одиночный номер страницы / колонтитул.
        if (1 === preg_match('/^\d{1,4}[~.]?$/u', $line)) {
            return true;
        }
        $letters = preg_replace('/[^\p{L}]/u', '', $line) ?? '';
        $letterCount = mb_strlen($letters);
        if (0 === $letterCount) {
            return true;
        }
        $ratio = $letterCount / max(1, mb_strlen($line));
        if ($ratio < 0.4 && $letterCount < 12) {
            return true;
        }
        // Строки вроде "~ т:" / "г: г-:" / ":l :1"
        if ($letterCount <= 4 && 1 === preg_match('/[:~|;,.•·\-\/\\\\]/u', $line)) {
            return true;
        }

        return false;
    }

    private function isJunkParagraph(string $paragraph): bool
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $paragraph) ?? '';
        $letterCount = mb_strlen($letters);
        if ($letterCount < 20) {
            return true;
        }
        $ratio = $letterCount / max(1, mb_strlen($paragraph));

        return $ratio < 0.45;
    }

    /** Убрать вкрапления номеров страниц внутри абзаца: "текст 12 дальше". */
    private function stripInlinePageNumbers(string $paragraph): string
    {
        $paragraph = preg_replace('/(?<=\p{L})\s+\d{1,3}\s+(?=\p{Ll})/u', ' ', $paragraph) ?? $paragraph;
        $paragraph = preg_replace('/\s+\d{1,3}~\s*/u', ' ', $paragraph) ?? $paragraph;

        return trim(preg_replace('/\s{2,}/u', ' ', $paragraph) ?? $paragraph);
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
