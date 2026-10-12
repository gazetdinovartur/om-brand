<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class LibraryTwigExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('library_inline', $this->renderInline(...), ['is_safe' => ['html']]),
        ];
    }

    public function renderInline(string $text, string $blockId): string
    {
        $escaped = htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
        $safeBlockId = preg_replace('/[^a-zA-Z0-9_-]/', '', $blockId) ?? '';

        return preg_replace_callback(
            '/\[\[note:(\d+)]]/',
            static function (array $matches) use ($safeBlockId): string {
                $number = (int) $matches[1];

                return sprintf(
                    '<sup class="reader-note-ref"><a id="note-ref-%1$d-%2$s" href="#note-%1$d" aria-label="Примечание %1$d">%1$d</a></sup>',
                    $number,
                    $safeBlockId,
                );
            },
            $escaped,
        ) ?? $escaped;
    }
}
