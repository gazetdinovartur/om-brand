<?php

declare(strict_types=1);

namespace App\Tests\Library;

use App\Library\LibrarySourceReader;
use App\Library\LibraryTextParser;
use PHPUnit\Framework\TestCase;

final class LibraryTextParserTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
    }

    public function testReadsWindows1251AndBuildsStableStructuredChapters(): void
    {
        $path = $this->temporaryPath('.txt');
        $encoded = iconv('UTF-8', 'Windows-1251', $this->fixtureText());
        self::assertIsString($encoded);
        file_put_contents($path, $encoded);

        $source = (new LibrarySourceReader())->read($path);
        $parser = new LibraryTextParser();
        $book = $parser->parse($source);
        $sameBook = $parser->parse($source);

        self::assertSame('Автор Тестовый', $book->author);
        self::assertSame('ТЕСТОВАЯ КНИГА', $book->title);
        self::assertCount(3, $book->chapters);
        self::assertSame(['introduction', 'chapter-1', 'conclusion'], array_map(
            static fn ($chapter): string => $chapter->slug,
            $book->chapters,
        ));
        self::assertSame('Текст примечания.', $book->footnotes['1']);
        self::assertStringContainsString('[[note:1]]', $book->chapters[0]->blocks[0]['text']);
        self::assertStringNotContainsString('RoyalLib', json_encode($book->chapters, \JSON_THROW_ON_ERROR));
        self::assertSame($book->chapters[0]->blocks[0]['id'], $sameBook->chapters[0]->blocks[0]['id']);
        self::assertGreaterThan(0, $book->totalCharacters);
    }

    public function testReadsLargestTextFileFromZipWithoutExtractingIt(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('Расширение zip недоступно.');
        }

        $path = $this->temporaryPath('.zip');
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $encoded = iconv('UTF-8', 'Windows-1251', $this->fixtureText());
        self::assertIsString($encoded);
        $zip->addFromString('book.txt', $encoded);
        $zip->addFromString('readme.txt', 'small');
        $zip->addFromString('site.url', 'https://example.test');
        $zip->close();

        $source = (new LibrarySourceReader())->read($path);

        self::assertSame('book.txt', $source->sourceName);
        self::assertStringContainsString('ТЕСТОВАЯ КНИГА', $source->text);
    }

    private function temporaryPath(string $extension): string
    {
        $path = sys_get_temp_dir().'/om-library-'.bin2hex(random_bytes(8)).$extension;
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function fixtureText(): string
    {
        return <<<'TEXT'
Спасибо, что скачали книгу в бесплатной электронной библиотеке Royallib.ru: https://example.test

Автор Тестовый

"ТЕСТОВАЯ КНИГА"

Введение

Первый абзац[1 - Текст примечания.].

Глава 1. Проверка

1.1. Подраздел.

Текст главы без редакторских исправлений.

Заключение

Финальный абзац.

Спасибо, что скачали книгу в бесплатной электронной библиотеке Royallib.ru: https://example.test

notes

Примечания

1

Текст примечания.
TEXT;
    }
}
