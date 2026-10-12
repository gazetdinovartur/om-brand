<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\LibraryBook;
use App\Entity\LibraryChapter;
use App\Service\ReadingProgressCalculator;
use PHPUnit\Framework\TestCase;

final class ReadingProgressCalculatorTest extends TestCase
{
    public function testCalculatesPortableProgressFromChapterAndBlock(): void
    {
        $book = (new LibraryBook())->setTotalCharacters(400);
        $first = (new LibraryChapter())
            ->setSlug('first')
            ->setCharacterCount(200)
            ->setBlocks([
                ['id' => 'first-a', 'type' => 'paragraph', 'text' => str_repeat('а', 100)],
                ['id' => 'first-b', 'type' => 'paragraph', 'text' => str_repeat('б', 100)],
            ]);
        $second = (new LibraryChapter())
            ->setSlug('second')
            ->setCharacterCount(200)
            ->setBlocks([
                ['id' => 'second-a', 'type' => 'paragraph', 'text' => str_repeat('в', 100)],
                ['id' => 'second-b', 'type' => 'paragraph', 'text' => str_repeat('г', 100)],
            ]);
        $book->addChapter($first)->addChapter($second);

        $basisPoints = (new ReadingProgressCalculator())->calculateBasisPoints($book, $second, 'second-a');

        self::assertSame(6250, $basisPoints);
    }
}
