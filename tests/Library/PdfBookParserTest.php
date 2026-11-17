<?php

declare(strict_types=1);

namespace App\Tests\Library;

use App\Library\LibrarySource;
use App\Library\PdfBookParser;
use PHPUnit\Framework\TestCase;

final class PdfBookParserTest extends TestCase
{
    public function testInvalidPdfFallsBackToViewerMode(): void
    {
        $parser = new PdfBookParser();
        $source = new LibrarySource(
            '%PDF-1.4 not-a-real-pdf',
            hash('sha256', 'x'),
            'demo.pdf',
            LibrarySource::FORMAT_PDF,
        );

        $parsed = $parser->parse($source, 'Демо', 'Автор');

        self::assertSame('pdf_fallback', $parsed->renderMode);
        self::assertTrue($parsed->keepSourceFile);
        self::assertSame('Демо', $parsed->title);
        self::assertNotEmpty($parsed->chapters);
    }

    public function testPageNoiseAndPageNumbersAreStripped(): void
    {
        $parser = new PdfBookParser();
        $method = new \ReflectionMethod(PdfBookParser::class, 'cleanPageText');

        $clean = $method->invoke($parser, <<<'TXT'
В любом случае, бывает время,
когда приходится быстро действовать.
4
Пульс у Делберта был быстрый.
~
:r
г:
г-:
TXT);

        self::assertStringContainsString('быстро действовать', $clean);
        self::assertStringContainsString('Пульс у Делберта', $clean);
        self::assertStringNotContainsString("\n4\n", $clean);
        self::assertDoesNotMatchRegularExpression('/^\d+$/m', $clean);
        self::assertStringNotContainsString('г-:', $clean);
    }

    public function testHyphenatedLineBreakIsJoined(): void
    {
        $parser = new PdfBookParser();
        $method = new \ReflectionMethod(PdfBookParser::class, 'cleanPageText');

        $clean = $method->invoke($parser, "краниосакраль-\nная система помогает");

        self::assertSame('краниосакральная система помогает', $clean);
    }

    public function testRealApledgerPdfUsesFilenameTitleAndFitsDbLimits(): void
    {
        $path = '/Users/arturlun/Downloads/Вы и ваш внутренний доктор Д.Апледжер.pdf';
        if (!is_readable($path)) {
            self::markTestSkipped('Тестовый PDF отсутствует локально.');
        }

        $bytes = (string) file_get_contents($path);
        $parser = new PdfBookParser();
        $parsed = $parser->parse(new LibrarySource(
            $bytes,
            hash('sha256', $bytes),
            basename($path),
            LibrarySource::FORMAT_PDF,
        ));

        self::assertSame('html', $parsed->renderMode);
        self::assertFalse($parsed->keepSourceFile);
        self::assertLessThanOrEqual(255, mb_strlen($parsed->title));
        self::assertLessThanOrEqual(180, mb_strlen($parsed->author));
        self::assertStringContainsString('внутренний доктор', mb_strtolower($parsed->title));
        self::assertSame('Без автора', $parsed->author);
        self::assertNotEmpty($parsed->chapters);
        self::assertGreaterThan(1000, $parsed->totalCharacters);
        foreach ($parsed->chapters as $chapter) {
            self::assertLessThanOrEqual(255, mb_strlen($chapter->title));
            self::assertLessThanOrEqual(120, mb_strlen($chapter->slug));
        }
    }
}
