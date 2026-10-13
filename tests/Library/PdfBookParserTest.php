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
}
