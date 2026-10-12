<?php

declare(strict_types=1);

namespace App\Tests\Library;

use App\Library\LibraryBookParser;
use App\Library\LibrarySource;
use App\Library\LibrarySourceReader;
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
        $encoded = iconv('UTF-8', 'Windows-1251', $this->royallibFixture());
        self::assertIsString($encoded);
        file_put_contents($path, $encoded);

        $source = (new LibrarySourceReader())->read($path);
        $parser = new LibraryBookParser();
        $book = $parser->parse($source);
        $sameBook = $parser->parse($source);

        self::assertSame(LibrarySource::FORMAT_TXT, $source->format);
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

    public function testPlainTextWithoutIntroductionBecomesSingleChapter(): void
    {
        $path = $this->temporaryPath('.txt');
        file_put_contents($path, "Просто абзац один.\n\nПросто абзац два.\n");

        $book = (new LibraryBookParser())->parse((new LibrarySourceReader())->read($path));

        self::assertSame('Без автора', $book->author);
        self::assertCount(1, $book->chapters);
        self::assertSame('book', $book->chapters[0]->slug);
        self::assertSame('Книга', $book->chapters[0]->title);
    }

    public function testPlainTextChapterWithoutIntroduction(): void
    {
        $path = $this->temporaryPath('.txt');
        file_put_contents($path, "Имя Автора\n\n\"Книга без введения\"\n\nГлава 1. Старт\n\nТекст.\n");

        $book = (new LibraryBookParser())->parse((new LibrarySourceReader())->read($path));

        self::assertSame('Имя Автора', $book->author);
        self::assertSame('Книга без введения', $book->title);
        self::assertSame(['chapter-1'], array_map(static fn ($c) => $c->slug, $book->chapters));
    }

    public function testReadsLargestMarkdownFromZip(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('Расширение zip недоступно.');
        }

        $path = $this->temporaryPath('.zip');
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('readme.txt', 'small');
        $zip->addFromString('book.md', "# Большая книга\n\n## Глава\n\nТекст главы.\n");
        $zip->close();

        $source = (new LibrarySourceReader())->read($path);
        $book = (new LibraryBookParser())->parse($source);

        self::assertSame(LibrarySource::FORMAT_MD, $source->format);
        self::assertSame('book.md', $source->sourceName);
        self::assertSame('Большая книга', $book->title);
        self::assertSame('Глава', $book->chapters[0]->title);
    }

    public function testParsesMarkdownWithFrontMatter(): void
    {
        $path = $this->temporaryPath('.md');
        file_put_contents($path, <<<'MD'
---
title: Маркдаун-книга
author: Автор МД
---

## Первая

Текст один.

## Вторая

Текст два.
MD);

        $book = (new LibraryBookParser())->parse((new LibrarySourceReader())->read($path));

        self::assertSame('Маркдаун-книга', $book->title);
        self::assertSame('Автор МД', $book->author);
        self::assertCount(2, $book->chapters);
    }

    public function testParsesFb2(): void
    {
        $path = $this->temporaryPath('.fb2');
        file_put_contents($path, <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<FictionBook xmlns="http://www.gribuser.ru/xml/fictionbook/2.0">
  <description>
    <title-info>
      <book-title>ФБ2 Книга</book-title>
      <author>
        <first-name>Иван</first-name>
        <last-name>Тестов</last-name>
      </author>
    </title-info>
  </description>
  <body>
    <section>
      <title><p>Введение</p></title>
      <p>Абзац введения.</p>
    </section>
    <section>
      <title><p>Глава 1</p></title>
      <p>Абзац главы.</p>
    </section>
  </body>
</FictionBook>
XML);

        $book = (new LibraryBookParser())->parse((new LibrarySourceReader())->read($path));

        self::assertSame('ФБ2 Книга', $book->title);
        self::assertSame('Иван Тестов', $book->author);
        self::assertCount(2, $book->chapters);
        self::assertStringContainsString('Абзац введения', $book->chapters[0]->blocks[0]['text']);
    }

    public function testParsesWindows1251Fb2WithoutMojibake(): void
    {
        $utf8 = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<FictionBook xmlns="http://www.gribuser.ru/xml/fictionbook/2.0">
  <description>
    <title-info>
      <book-title>Мистицизм звука</book-title>
      <author>
        <first-name>Хазрат</first-name>
        <last-name>Хан</last-name>
      </author>
    </title-info>
  </description>
  <body>
    <section>
      <title><p>Предисловие</p></title>
      <p>Текст предисловия.</p>
    </section>
    <section>
      <title><p>Глава 1</p></title>
      <p>Текст главы.</p>
    </section>
  </body>
</FictionBook>
XML;
        $encoded = iconv('UTF-8', 'Windows-1251', $utf8);
        self::assertIsString($encoded);
        $encoded = preg_replace('/encoding="UTF-8"/', 'encoding="windows-1251"', $encoded, 1);
        self::assertIsString($encoded);

        $path = $this->temporaryPath('.fb2');
        file_put_contents($path, $encoded);

        $book = (new LibraryBookParser())->parse(
            (new LibrarySourceReader())->read($path, 'Хан_Хазрат_Мистицизм_звука_royallib_ru.fb2'),
        );

        self::assertSame('Мистицизм звука', $book->title);
        self::assertSame('Хазрат Хан', $book->author);
        self::assertCount(2, $book->chapters);
        self::assertSame('Предисловие', $book->chapters[0]->title);
        self::assertStringNotContainsString('Рњ', $book->title);
    }

    public function testParsesEpub(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('Расширение zip недоступно.');
        }

        $path = $this->temporaryPath('.epub');
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->addFromString('META-INF/container.xml', <<<'XML'
<?xml version="1.0"?>
<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <rootfiles>
    <rootfile full-path="OPS/content.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
        $zip->addFromString('OPS/content.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf" unique-identifier="id" version="2.0">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:title>EPUB Книга</dc:title>
    <dc:creator>Автор EPUB</dc:creator>
  </metadata>
  <manifest>
    <item id="c1" href="chap1.xhtml" media-type="application/xhtml+xml"/>
    <item id="c2" href="chap2.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="c1"/>
    <itemref idref="c2"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/chap1.xhtml', '<html><head><title>Глава A</title></head><body><h1>Глава A</h1><p>Текст A.</p></body></html>');
        $zip->addFromString('OPS/chap2.xhtml', '<html><body><h1>Глава B</h1><p>Текст B.</p></body></html>');
        $zip->close();

        $source = (new LibrarySourceReader())->read($path);
        $book = (new LibraryBookParser())->parse($source);

        self::assertSame(LibrarySource::FORMAT_EPUB, $source->format);
        self::assertSame('EPUB Книга', $book->title);
        self::assertSame('Автор EPUB', $book->author);
        self::assertCount(2, $book->chapters);
        self::assertSame('Глава A', $book->chapters[0]->title);
    }

    private function temporaryPath(string $extension): string
    {
        $path = sys_get_temp_dir().'/om-library-'.bin2hex(random_bytes(8)).$extension;
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function royallibFixture(): string
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
