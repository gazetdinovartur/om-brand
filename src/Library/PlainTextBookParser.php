<?php

declare(strict_types=1);

namespace App\Library;

final class PlainTextBookParser
{
    public function parse(
        LibrarySource $source,
        ?string $titleOverride = null,
        ?string $authorOverride = null,
    ): ParsedLibraryBook {
        $text = $this->normalizeSource($source->text);
        [$mainText, $notesText] = $this->splitMainTextAndNotes($text);
        $footnotes = $this->parseFootnotes($notesText);

        $parts = preg_split('/\n{2,}/u', trim($mainText)) ?: [];
        $paragraphs = [];
        foreach ($parts as $part) {
            $paragraph = BookParseSupport::normalizeParagraph($part);
            if ('' !== $paragraph && !$this->isPromoLine($paragraph)) {
                $paragraphs[] = $paragraph;
            }
        }

        $allCapsCandidates = 0;
        foreach ($paragraphs as $paragraph) {
            if (null === $this->explicitChapterSlug($paragraph) && $this->looksLikeAllCapsHeading($paragraph)) {
                ++$allCapsCandidates;
            }
        }
        // ALL-CAPS headings only after an explicit marker, or when the book is clearly ALL-CAPS-structured.
        $allCapsOnlyBook = $allCapsCandidates >= 3;

        $rawChapters = [];
        $preamble = [];
        $currentTitle = null;
        $currentSlug = null;
        $currentParagraphs = [];
        $seenExplicit = false;

        $flush = static function () use (&$rawChapters, &$currentTitle, &$currentSlug, &$currentParagraphs): void {
            if (null === $currentTitle || null === $currentSlug) {
                return;
            }
            $rawChapters[] = [
                'title' => $currentTitle,
                'slug' => $currentSlug,
                'paragraphs' => $currentParagraphs,
            ];
            $currentTitle = null;
            $currentSlug = null;
            $currentParagraphs = [];
        };

        foreach ($paragraphs as $paragraph) {
            $slug = $this->explicitChapterSlug($paragraph);
            if (null !== $slug) {
                $seenExplicit = true;
            } elseif (($seenExplicit || $allCapsOnlyBook) && $this->looksLikeAllCapsHeading($paragraph)) {
                $slug = BookParseSupport::slugify($paragraph, 'section');
            }

            if (null !== $slug) {
                $flush();
                $currentTitle = $paragraph;
                $currentSlug = $slug;
                continue;
            }

            if (null === $currentTitle) {
                $preamble[] = $paragraph;
                continue;
            }

            $currentParagraphs[] = $paragraph;
        }
        $flush();

        [$detectedAuthor, $detectedTitle] = $this->detectMetadata($preamble);

        if ([] === $rawChapters) {
            // Unstructured text: do not invent author/title from body paragraphs.
            $rawChapters[] = [
                'title' => 'Книга',
                'slug' => 'book',
                'paragraphs' => $preamble,
            ];
            $detectedAuthor = '';
            $detectedTitle = '';
        }

        $chapters = BookParseSupport::chaptersFromRaw($rawChapters, $footnotes);
        [$title, $author] = BookParseSupport::resolveTitleAuthor(
            $titleOverride,
            $authorOverride,
            '' !== $detectedTitle ? $detectedTitle : null,
            '' !== $detectedAuthor ? $detectedAuthor : null,
            $source->sourceName,
        );

        return BookParseSupport::finishBook(
            $title,
            $author,
            $source->checksum,
            $chapters,
            $footnotes,
        );
    }

    private function normalizeSource(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+\n/u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{4,}/u', "\n\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @param list<string> $preamble
     *
     * @return array{0: string, 1: string}
     */
    private function detectMetadata(array $preamble): array
    {
        $candidates = [];
        foreach ($preamble as $line) {
            if (
                '' === $line
                || str_contains($line, '://')
                || $this->isPromoLine($line)
            ) {
                continue;
            }
            $candidates[] = $line;
        }

        if (\count($candidates) >= 2) {
            $title = (string) array_pop($candidates);
            $author = (string) array_pop($candidates);
            $title = preg_replace('/^[\"«“](.*)[\"»”]$/u', '$1', $title) ?? $title;

            return [trim($author), trim($title)];
        }

        if (1 === \count($candidates)) {
            $only = preg_replace('/^[\"«“](.*)[\"»”]$/u', '$1', $candidates[0]) ?? $candidates[0];

            return ['', trim($only)];
        }

        return ['', ''];
    }

    /** @return array{0: string, 1: string} */
    private function splitMainTextAndNotes(string $bookText): array
    {
        $notesText = '';
        if (preg_match('/\n\s*notes\s*\n\s*Примечания\s*\n/ui', $bookText, $match, \PREG_OFFSET_CAPTURE)) {
            $offset = (int) $match[0][1];
            $notesText = substr($bookText, $offset + strlen((string) $match[0][0]));
            $bookText = substr($bookText, 0, $offset);
        }

        // Strip trailing royallib promo only after the last chapter marker, never the lead-in.
        if (preg_match('/\nСпасибо, что скачали книгу.*$/us', $bookText, $match, \PREG_OFFSET_CAPTURE)) {
            $bookText = substr($bookText, 0, (int) $match[0][1]);
        }

        return [trim($bookText), trim($notesText)];
    }

    /** @return array<string, string> */
    private function parseFootnotes(string $notesText): array
    {
        if ('' === $notesText) {
            return [];
        }

        $parts = preg_split('/\n{2,}/u', trim($notesText)) ?: [];
        $footnotes = [];
        $currentNumber = null;
        foreach ($parts as $part) {
            $part = BookParseSupport::normalizeParagraph($part);
            if (preg_match('/^\d+$/', $part)) {
                $currentNumber = (int) $part;
                continue;
            }
            if (null === $currentNumber || '' === $part) {
                continue;
            }
            $key = (string) $currentNumber;
            $footnotes[$key] = isset($footnotes[$key])
                ? $footnotes[$key]."\n\n".$part
                : $part;
            $currentNumber = null;
        }

        return $footnotes;
    }

    private function explicitChapterSlug(string $paragraph): ?string
    {
        if ('Введение' === $paragraph || preg_match('/^Введение\b/u', $paragraph)) {
            return 'introduction';
        }
        if ('Заключение' === $paragraph || preg_match('/^Заключение\b/u', $paragraph)) {
            return 'conclusion';
        }
        if (preg_match('/^Глава\s+(\d+)\b/u', $paragraph, $matches)) {
            return 'chapter-'.(int) $matches[1];
        }
        if (preg_match('/^Часть\s+(\d+)\b/u', $paragraph, $matches)) {
            return 'part-'.(int) $matches[1];
        }
        if (preg_match('/^Chapter\s+(\d+)\b/iu', $paragraph, $matches)) {
            return 'chapter-'.(int) $matches[1];
        }
        if (preg_match('/^Part\s+(\d+)\b/iu', $paragraph, $matches)) {
            return 'part-'.(int) $matches[1];
        }

        return null;
    }

    private function looksLikeAllCapsHeading(string $paragraph): bool
    {
        if (BookParseSupport::length($paragraph) > 80 || str_ends_with($paragraph, '.')) {
            return false;
        }
        if (!preg_match('/\p{L}/u', $paragraph)) {
            return false;
        }
        $letters = preg_replace('/[^\p{L}]/u', '', $paragraph) ?? '';
        if ('' === $letters) {
            return false;
        }

        return mb_strtoupper($letters, 'UTF-8') === $letters
            && BookParseSupport::length($letters) >= 3;
    }

    private function isPromoLine(string $line): bool
    {
        return str_starts_with($line, 'Спасибо')
            || str_starts_with($line, 'Все книги')
            || str_starts_with($line, 'Эта же книга')
            || str_starts_with($line, 'Приятного чтения');
    }
}
