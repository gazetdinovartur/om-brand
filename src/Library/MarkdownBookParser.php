<?php

declare(strict_types=1);

namespace App\Library;

final class MarkdownBookParser
{
    public function parse(
        LibrarySource $source,
        ?string $titleOverride = null,
        ?string $authorOverride = null,
    ): ParsedLibraryBook {
        $text = str_replace(["\r\n", "\r"], "\n", $source->text);
        [$frontTitle, $frontAuthor, $body] = $this->extractFrontMatter($text);

        $lines = explode("\n", $body);
        $rawChapters = [];
        $preamble = [];
        $currentTitle = null;
        $currentSlug = null;
        $currentParagraphs = [];
        $buffer = [];
        $inFence = false;
        $bookTitleFromH1 = null;

        $flushBuffer = static function () use (&$buffer, &$currentParagraphs, &$preamble, &$currentTitle): void {
            $paragraph = BookParseSupport::normalizeParagraph(implode("\n", $buffer));
            $buffer = [];
            if ('' === $paragraph) {
                return;
            }
            if (null === $currentTitle) {
                $preamble[] = $paragraph;
            } else {
                $currentParagraphs[] = $paragraph;
            }
        };

        $flushChapter = static function () use (&$rawChapters, &$currentTitle, &$currentSlug, &$currentParagraphs): void {
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

        foreach ($lines as $line) {
            if (preg_match('/^```/', $line)) {
                $inFence = !$inFence;
                $buffer[] = $line;
                continue;
            }
            if ($inFence) {
                $buffer[] = $line;
                continue;
            }

            if (preg_match('/^(#{1,2})\s+(.+?)\s*$/u', $line, $matches)) {
                $flushBuffer();
                $level = strlen($matches[1]);
                $heading = trim($matches[2]);
                if (1 === $level && null === $bookTitleFromH1 && null === $currentTitle && [] === $rawChapters) {
                    $bookTitleFromH1 = $heading;
                    continue;
                }
                $flushChapter();
                $currentTitle = $heading;
                $currentSlug = BookParseSupport::slugify($heading, 'chapter-'.(\count($rawChapters) + 1));
                continue;
            }

            if ('' === trim($line)) {
                $flushBuffer();
                continue;
            }
            $buffer[] = $line;
        }
        $flushBuffer();
        $flushChapter();

        if ([] === $rawChapters) {
            $rawChapters[] = [
                'title' => 'Книга',
                'slug' => 'book',
                'paragraphs' => $preamble,
            ];
            $preamble = [];
        }

        $footnotes = [];
        $chapters = BookParseSupport::chaptersFromRaw($rawChapters, $footnotes);
        [$title, $author] = BookParseSupport::resolveTitleAuthor(
            $titleOverride,
            $authorOverride,
            $frontTitle ?? $bookTitleFromH1,
            $frontAuthor,
            $source->sourceName,
        );

        return BookParseSupport::finishBook($title, $author, $source->checksum, $chapters, $footnotes);
    }

    /** @return array{0: ?string, 1: ?string, 2: string} */
    private function extractFrontMatter(string $text): array
    {
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $text, $matches)) {
            return [null, null, trim($text)];
        }

        $title = null;
        $author = null;
        foreach (preg_split('/\n/', $matches[1]) ?: [] as $line) {
            if (preg_match('/^title:\s*(.+)$/iu', $line, $m)) {
                $title = trim($m[1], " \t\"'");
            }
            if (preg_match('/^author:\s*(.+)$/iu', $line, $m)) {
                $author = trim($m[1], " \t\"'");
            }
        }

        return [$title, $author, trim($matches[2])];
    }
}
