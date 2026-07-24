<?php

namespace App\Tests\Repository;

use App\Repository\ChronicleEntryRepository;
use PHPUnit\Framework\TestCase;

final class ChronicleSearchQueryTest extends TestCase
{
    public function testNormalizeRejectsShortAndEmpty(): void
    {
        self::assertNull(ChronicleEntryRepository::normalizeSearchQuery(''));
        self::assertNull(ChronicleEntryRepository::normalizeSearchQuery('  '));
        self::assertNull(ChronicleEntryRepository::normalizeSearchQuery('а'));
        self::assertSame('ом', ChronicleEntryRepository::normalizeSearchQuery('  ом  '));
    }

    public function testNormalizeCollapsesWhitespaceAndCapsLength(): void
    {
        self::assertSame('живая музыка', ChronicleEntryRepository::normalizeSearchQuery("живая\n  музыка"));
        $long = str_repeat('я', 120);
        $normalized = ChronicleEntryRepository::normalizeSearchQuery($long);
        self::assertNotNull($normalized);
        self::assertSame(100, mb_strlen($normalized));
    }

    public function testSearchTermsSkipShortTokensAndDedup(): void
    {
        self::assertSame(['живая', 'музыка'], ChronicleEntryRepository::searchTerms('живая и музыка и'));
        self::assertSame(['ом', 'бренд'], ChronicleEntryRepository::searchTerms('ом ом бренд'));
    }
}
