<?php

declare(strict_types=1);

namespace App\Library;

final class LibraryTextParser
{
    public function parse(
        LibrarySource $source,
        ?string $titleOverride = null,
        ?string $authorOverride = null,
    ): ParsedLibraryBook {
        $text = $this->normalizeSource($source->text);
        if (!preg_match('/^Введение\s*$/mu', $text, $introMatch, \PREG_OFFSET_CAPTURE)) {
            throw new \RuntimeException('Не найдено начало книги: заголовок «Введение».');
        }

        $introOffset = (int) $introMatch[0][1];
        $metadataText = substr($text, 0, $introOffset);
        [$detectedAuthor, $detectedTitle] = $this->detectMetadata($metadataText);

        $bookText = substr($text, $introOffset);
        [$mainText, $notesText] = $this->splitMainTextAndNotes($bookText);
        $footnotes = $this->parseFootnotes($notesText);

        $chapters = $this->parseChapters($mainText, $footnotes);
        if ([] === $chapters) {
            throw new \RuntimeException('В книге не найдено ни одной главы.');
        }

        $totalCharacters = array_sum(array_map(
            static fn (ParsedLibraryChapter $chapter): int => $chapter->characterCount,
            $chapters,
        ));
        foreach ($footnotes as $footnote) {
            $totalCharacters += $this->length($footnote);
        }

        $title = trim((string) ($titleOverride ?? $detectedTitle));
        $author = trim((string) ($authorOverride ?? $detectedAuthor));
        if ('' === $title || '' === $author) {
            throw new \RuntimeException('Не удалось определить название или автора; передайте --title и --author.');
        }

        ksort($footnotes, \SORT_NUMERIC);

        return new ParsedLibraryBook(
            $title,
            $author,
            $source->checksum,
            $chapters,
            $footnotes,
            $totalCharacters,
        );
    }

    private function normalizeSource(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+\n/u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{4,}/u', "\n\n\n", $text) ?? $text;

        return trim($text);
    }

    /** @return array{0: string, 1: string} */
    private function detectMetadata(string $metadataText): array
    {
        $lines = preg_split('/\n+/u', $metadataText) ?: [];
        $candidates = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (
                '' === $line
                || str_contains($line, '://')
                || str_starts_with($line, 'Спасибо')
                || str_starts_with($line, 'Все книги')
                || str_starts_with($line, 'Эта же книга')
                || str_starts_with($line, 'Приятного чтения')
            ) {
                continue;
            }
            $candidates[] = $line;
        }

        if (\count($candidates) < 2) {
            return ['', ''];
        }

        $title = (string) array_pop($candidates);
        $author = (string) array_pop($candidates);
        $title = preg_replace('/^[\"«“](.*)[\"»”]$/u', '$1', $title) ?? $title;

        return [trim($author), trim($title)];
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

        $promoOffset = strpos($bookText, 'Спасибо, что скачали книгу');
        if (false !== $promoOffset) {
            $bookText = substr($bookText, 0, $promoOffset);
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
            $part = $this->normalizeParagraph($part);
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

    /**
     * @param array<string, string> $footnotes
     *
     * @return list<ParsedLibraryChapter>
     */
    private function parseChapters(string $mainText, array &$footnotes): array
    {
        $parts = preg_split('/\n{2,}/u', trim($mainText)) ?: [];
        $chapters = [];
        $currentTitle = null;
        $currentSlug = null;
        $currentBlocks = [];
        $currentCharacters = 0;
        $blockHashes = [];

        $finishChapter = function () use (
            &$chapters,
            &$currentTitle,
            &$currentSlug,
            &$currentBlocks,
            &$currentCharacters,
            &$blockHashes,
        ): void {
            if (null === $currentTitle || null === $currentSlug) {
                return;
            }
            $chapters[] = new ParsedLibraryChapter(
                $currentTitle,
                $currentSlug,
                (\count($chapters) + 1) * 10,
                $currentBlocks,
                $currentCharacters,
            );
            $currentTitle = null;
            $currentSlug = null;
            $currentBlocks = [];
            $currentCharacters = 0;
            $blockHashes = [];
        };

        foreach ($parts as $part) {
            $paragraph = $this->normalizeParagraph($part);
            if ('' === $paragraph) {
                continue;
            }

            $majorSlug = $this->majorChapterSlug($paragraph);
            if (null !== $majorSlug) {
                $finishChapter();
                $currentTitle = $paragraph;
                $currentSlug = $majorSlug;
                continue;
            }
            if (null === $currentTitle) {
                continue;
            }

            $type = preg_match('/^\d+\.\d+\.(?:\s|$)/u', $paragraph) ? 'heading' : 'paragraph';
            $paragraph = preg_replace_callback(
                '/\[(\d+)\s*-\s*([^\]]+)]/u',
                static function (array $matches) use (&$footnotes): string {
                    $number = (int) $matches[1];
                    $key = (string) $number;
                    if (!isset($footnotes[$key])) {
                        $footnotes[$key] = trim((string) $matches[2]);
                    }

                    return sprintf('[[note:%d]]', $number);
                },
                $paragraph,
            ) ?? $paragraph;

            $hash = substr(hash('sha256', $type."\0".$paragraph), 0, 12);
            $blockHashes[$hash] = ($blockHashes[$hash] ?? 0) + 1;
            $id = sprintf('b-%s-%d', $hash, $blockHashes[$hash]);
            $block = [
                'id' => $id,
                'type' => $type,
                'text' => $paragraph,
            ];
            if ('heading' === $type) {
                $block['level'] = 2;
            }
            $currentBlocks[] = $block;
            $currentCharacters += $this->length($paragraph);
        }

        $finishChapter();

        return $chapters;
    }

    private function majorChapterSlug(string $paragraph): ?string
    {
        if ('Введение' === $paragraph) {
            return 'introduction';
        }
        if ('Заключение' === $paragraph) {
            return 'conclusion';
        }
        if (preg_match('/^Глава\s+(\d+)\b/u', $paragraph, $matches)) {
            return 'chapter-'.(int) $matches[1];
        }

        return null;
    }

    private function normalizeParagraph(string $paragraph): string
    {
        $paragraph = preg_replace('/\h*\n\h*/u', ' ', trim($paragraph)) ?? trim($paragraph);

        return preg_replace('/\h{2,}/u', ' ', $paragraph) ?? $paragraph;
    }

    private function length(string $value): int
    {
        $length = iconv_strlen($value, 'UTF-8');

        return false === $length ? strlen($value) : $length;
    }
}
