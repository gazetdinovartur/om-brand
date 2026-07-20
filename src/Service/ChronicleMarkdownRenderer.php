<?php

namespace App\Service;

use App\Entity\ChronicleEntry;

final class ChronicleMarkdownRenderer
{
    public function toHtml(?string $markdown): string
    {
        if (null === $markdown || '' === trim($markdown)) {
            return '';
        }

        $text = htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/', '<a href="$2" rel="noopener" target="_blank">$1</a>', $text) ?? $text;

        $paragraphs = preg_split("/\n{2,}/", trim($text)) ?: [];
        $html = [];
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ('' === $paragraph) {
                continue;
            }

            if (preg_match('/^(- .+\n?)+$/', $paragraph)) {
                $items = preg_split("/\n/", $paragraph) ?: [];
                $lis = [];
                foreach ($items as $item) {
                    $item = preg_replace('/^- /', '', trim($item));
                    if ('' !== $item) {
                        $lis[] = '<li>'.$item.'</li>';
                    }
                }
                if ([] !== $lis) {
                    $html[] = '<ul>'.implode('', $lis).'</ul>';
                }
                continue;
            }

            $html[] = '<p>'.nl2br($paragraph).'</p>';
        }

        return implode("\n", $html);
    }

    public function estimateReadingMinutes(ChronicleEntry $entry): int
    {
        $text = $entry->getLede() ?? '';
        $text .= ' '.$entry->getExcerpt();

        foreach ($entry->getBlocks() as $block) {
            $text .= ' '.($block->getBody() ?? '');
            $text .= ' '.($block->getCaption() ?? '');
            $text .= ' '.($block->getAuthor() ?? '');
        }

        $words = preg_split('/\s+/u', trim(strip_tags($text)), -1, PREG_SPLIT_NO_EMPTY);
        $count = \is_array($words) ? \count($words) : 0;

        return max(1, (int) ceil($count / 180));
    }
}
