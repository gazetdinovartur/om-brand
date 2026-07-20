<?php

namespace App\Tests\Service;

use App\Entity\ChronicleEntry;
use App\Service\ChronicleMarkdownRenderer;
use PHPUnit\Framework\TestCase;

final class ChronicleMarkdownRendererTest extends TestCase
{
    private ChronicleMarkdownRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new ChronicleMarkdownRenderer();
    }

    public function testEmptyReturnsEmptyString(): void
    {
        self::assertSame('', $this->renderer->toHtml(null));
        self::assertSame('', $this->renderer->toHtml('   '));
    }

    public function testBoldItalicAndLink(): void
    {
        $html = $this->renderer->toHtml('**жирный** и *курсив* [сайт](https://example.com)');

        self::assertStringContainsString('<strong>жирный</strong>', $html);
        self::assertStringContainsString('<em>курсив</em>', $html);
        self::assertStringContainsString('href="https://example.com"', $html);
    }

    public function testParagraphsAndList(): void
    {
        $html = $this->renderer->toHtml("абзац один\n\n- пункт\n- ещё");

        self::assertStringContainsString('<p>абзац один</p>', $html);
        self::assertStringContainsString('<ul>', $html);
        self::assertStringContainsString('<li>пункт</li>', $html);
    }

    public function testReadingTimeMinimumOneMinute(): void
    {
        $entry = new ChronicleEntry();
        $entry->setTitle('t');

        self::assertSame(1, $this->renderer->estimateReadingMinutes($entry));
    }
}
