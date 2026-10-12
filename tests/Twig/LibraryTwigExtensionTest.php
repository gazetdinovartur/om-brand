<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\LibraryTwigExtension;
use PHPUnit\Framework\TestCase;

final class LibraryTwigExtensionTest extends TestCase
{
    public function testEscapesTextAndLinksFootnotesToStableIds(): void
    {
        $html = (new LibraryTwigExtension())->renderInline(
            'Текст <script> с примечанием[[note:3]] дальше.',
            'b-safe_1',
        );

        self::assertStringContainsString('Текст &lt;script&gt; с примечанием', $html);
        self::assertStringContainsString('id="note-ref-3-b-safe_1"', $html);
        self::assertStringContainsString('href="#note-3"', $html);
        self::assertStringContainsString('aria-label="Примечание 3"', $html);
        self::assertStringNotContainsString('<script>', $html);
    }
}
