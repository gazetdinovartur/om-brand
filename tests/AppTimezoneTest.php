<?php

declare(strict_types=1);

namespace App\Tests;

use App\AppTimezone;
use PHPUnit\Framework\TestCase;

final class AppTimezoneTest extends TestCase
{
    public function testLocalizesMoscowEveningAsYekaterinburgMidnight(): void
    {
        // 2026-09-05 00:00 Asia/Yekaterinburg == 2026-09-04 22:00 Europe/Moscow
        $publishedAt = new \DateTimeImmutable('2026-09-04T22:00:00+03:00');
        $local = AppTimezone::localize($publishedAt);

        self::assertSame('Asia/Yekaterinburg', $local->getTimezone()->getName());
        self::assertSame('2026-09-05', $local->format('Y-m-d'));
        self::assertSame('00:00:00', $local->format('H:i:s'));
        self::assertSame('2026-09-05T00:00:00+05:00', $local->format('c'));
    }
}
