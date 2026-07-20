<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\ChronicleBlock;
use App\Entity\ChronicleEntry;
use App\Enum\ChronicleBlockType;
use App\Service\ChronicleDisplayDeduper;
use PHPUnit\Framework\TestCase;

final class ChronicleDisplayDeduperTest extends TestCase
{
    private ChronicleDisplayDeduper $deduper;

    protected function setUp(): void
    {
        $this->deduper = new ChronicleDisplayDeduper();
    }

    public function testHidesLedeWhenSameAsFirstParagraph(): void
    {
        $entry = $this->entry(
            'собираю альбом музыки, выпущенной за последние',
            'собираю альбом музыки, выпущенной за последние полгода. первый альбом',
            "собираю альбом музыки, выпущенной за последние полгода. первый альбом\n\nзаписанной на коленке",
        );

        self::assertFalse($this->deduper->showHeroLede($entry));
        $blocks = $this->deduper->blocksForDisplay($entry);
        self::assertCount(1, $blocks);
        self::assertSame(ChronicleBlockType::Paragraph, $blocks[0]->getType());
    }

    public function testKeepsOnlyTitleWhenBodyEqualsTitle(): void
    {
        $entry = $this->entry(
            'шли по ленина православные',
            'шли по ленина православные',
            'шли по ленина православные',
        );

        self::assertFalse($this->deduper->showHeroLede($entry));
        self::assertSame([], $this->deduper->blocksForDisplay($entry));
    }

    public function testKeepsDistinctLedeAndBody(): void
    {
        $entry = $this->entry(
            'Короткий заголовок',
            'Отдельный лид, которого нет в первом абзаце.',
            'А здесь уже другой текст статьи, длинный и самостоятельный.',
        );

        self::assertTrue($this->deduper->showHeroLede($entry));
        self::assertCount(1, $this->deduper->blocksForDisplay($entry));
    }

    private function entry(string $title, string $lede, string $firstParagraph): ChronicleEntry
    {
        $entry = new ChronicleEntry();
        $entry->setTitle($title);
        $entry->setLede($lede);

        $block = new ChronicleBlock();
        $block->setType(ChronicleBlockType::Paragraph);
        $block->setBody($firstParagraph);
        $block->setSortOrder(10);
        $entry->addBlock($block);

        return $entry;
    }
}
