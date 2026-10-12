<?php

declare(strict_types=1);

namespace App\Library;

/**
 * Shared helpers for format-specific book parsers.
 */
final class BookParseSupport
{
    public static function resolveTitleAuthor(
        ?string $titleOverride,
        ?string $authorOverride,
        ?string $detectedTitle,
        ?string $detectedAuthor,
        string $sourceName,
    ): array {
        $title = trim((string) ($titleOverride ?? $detectedTitle ?? ''));
        $author = trim((string) ($authorOverride ?? $detectedAuthor ?? ''));

        if ('' === $title) {
            $title = self::humanizeSourceName($sourceName);
        }
        if ('' === $author) {
            $author = 'Без автора';
        }

        return [$title, $author];
    }

    public static function humanizeSourceName(string $sourceName): string
    {
        $base = pathinfo($sourceName, \PATHINFO_FILENAME);
        $base = preg_replace('/[_\-]+/u', ' ', $base) ?? $base;
        $base = trim($base);

        return '' === $base ? 'Без названия' : $base;
    }

    public static function slugify(string $value, string $fallback): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug) ?? $slug;
        $slug = trim($slug, '-');
        if ('' === $slug) {
            $slug = $fallback;
        }

        return substr($slug, 0, 120);
    }

    /**
     * @param list<string> $paragraphs
     *
     * @return array{0: list<array{id: string, type: string, text: string, level?: int}>, 1: int}
     */
    public static function blocksFromParagraphs(array $paragraphs, array &$footnotes = []): array
    {
        $blocks = [];
        $blockHashes = [];
        $characters = 0;

        foreach ($paragraphs as $paragraph) {
            $paragraph = self::normalizeParagraph($paragraph);
            if ('' === $paragraph) {
                continue;
            }

            $type = preg_match('/^\d+\.\d+\.(?:\s|$)/u', $paragraph) ? 'heading' : 'paragraph';
            if (preg_match('/^#{1,6}\s+\S/u', $paragraph) || preg_match('/^<h[1-6]\b/i', $paragraph)) {
                $type = 'heading';
            }

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
            $blocks[] = $block;
            $characters += self::length($paragraph);
        }

        return [$blocks, $characters];
    }

    /**
     * @param list<ParsedLibraryChapter> $chapters
     * @param array<string, string>      $footnotes
     */
    public static function finishBook(
        string $title,
        string $author,
        string $checksum,
        array $chapters,
        array $footnotes,
    ): ParsedLibraryBook {
        if ([] === $chapters) {
            [$blocks, $characters] = self::blocksFromParagraphs(['Пустой файл.']);
            $chapters = [
                new ParsedLibraryChapter('Книга', 'book', 10, $blocks, $characters),
            ];
        }

        $totalCharacters = array_sum(array_map(
            static fn (ParsedLibraryChapter $chapter): int => $chapter->characterCount,
            $chapters,
        ));
        foreach ($footnotes as $footnote) {
            $totalCharacters += self::length($footnote);
        }

        ksort($footnotes, \SORT_NUMERIC);

        return new ParsedLibraryBook(
            $title,
            $author,
            $checksum,
            $chapters,
            $footnotes,
            $totalCharacters,
        );
    }

    /**
     * @param list<array{title: string, slug: string, paragraphs: list<string>}> $rawChapters
     * @param array<string, string>                                              $footnotes
     *
     * @return list<ParsedLibraryChapter>
     */
    public static function chaptersFromRaw(array $rawChapters, array &$footnotes = []): array
    {
        $chapters = [];
        $usedSlugs = [];
        foreach ($rawChapters as $index => $raw) {
            $title = trim($raw['title']);
            if ('' === $title) {
                $title = 'Глава '.($index + 1);
            }
            $baseSlug = $raw['slug'] !== '' ? $raw['slug'] : self::slugify($title, 'chapter-'.($index + 1));
            $slug = $baseSlug;
            $suffix = 2;
            while (isset($usedSlugs[$slug])) {
                $slug = substr($baseSlug, 0, 100).'-'.$suffix;
                ++$suffix;
            }
            $usedSlugs[$slug] = true;

            [$blocks, $characters] = self::blocksFromParagraphs($raw['paragraphs'], $footnotes);
            if ([] === $blocks) {
                continue;
            }
            $chapters[] = new ParsedLibraryChapter(
                $title,
                $slug,
                (\count($chapters) + 1) * 10,
                $blocks,
                $characters,
            );
        }

        return $chapters;
    }

    public static function normalizeParagraph(string $paragraph): string
    {
        $paragraph = preg_replace('/\h*\n\h*/u', ' ', trim($paragraph)) ?? trim($paragraph);

        return preg_replace('/\h{2,}/u', ' ', $paragraph) ?? $paragraph;
    }

    public static function length(string $value): int
    {
        $length = iconv_strlen($value, 'UTF-8');

        return false === $length ? strlen($value) : $length;
    }

    public static function stripHtmlToParagraphs(string $html): array
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#</(p|div|h[1-6]|li|tr|br|blockquote)\s*>#i', "\n\n", $html) ?? $html;
        $html = preg_replace('#<(br|hr)\s*/?>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $parts = preg_split('/\n{2,}/u', $text) ?: [];
        $paragraphs = [];
        foreach ($parts as $part) {
            $part = self::normalizeParagraph($part);
            if ('' !== $part) {
                $paragraphs[] = $part;
            }
        }

        return $paragraphs;
    }
}
