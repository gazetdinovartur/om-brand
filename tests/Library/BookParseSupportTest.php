<?php

declare(strict_types=1);

namespace App\Tests\Library;

use App\Library\BookParseSupport;
use PHPUnit\Framework\TestCase;

final class BookParseSupportTest extends TestCase
{
    public function testResolveTitleAuthorRejectsBodyExcerptsAndClamps(): void
    {
        $body = str_repeat('Слово. ', 40);
        [$title, $author] = BookParseSupport::resolveTitleAuthor(
            null,
            null,
            $body,
            $body,
            'Vy_i_vash_vnutrenniy_doktor.pdf',
        );

        self::assertSame('Vy i vash vnutrenniy doktor', $title);
        self::assertSame('Без автора', $author);
        self::assertLessThanOrEqual(255, mb_strlen($title));
        self::assertLessThanOrEqual(180, mb_strlen($author));
    }

    public function testLooksLikeBodyExcerpt(): void
    {
        self::assertFalse(BookParseSupport::looksLikeBodyExcerpt('Джон Апледжер'));
        self::assertTrue(BookParseSupport::looksLikeBodyExcerpt(str_repeat('а', 200)));
        self::assertTrue(BookParseSupport::looksLikeBodyExcerpt(
            'Первое предложение длинное. Второе предложение тоже длиннее обычного заголовка книги или автора.',
        ));
    }
}
