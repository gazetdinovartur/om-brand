<?php

namespace App\Tests\Content;

use App\Content\ChronicleSeedContent;
use PHPUnit\Framework\TestCase;

final class ChronicleSeedContentTest extends TestCase
{
    public function testBodyIsCompleteAndUnmodifiedOpening(): void
    {
        self::assertStringStartsWith("на середину лета\n\nпочтение моë", ChronicleSeedContent::ENTRY_BODY);
    }

    public function testBodyContainsClosingLines(): void
    {
        self::assertStringContainsString('тяжело дышать этим летом', ChronicleSeedContent::ENTRY_BODY);
        self::assertStringContainsString('завтра по ленина, спустя год, ночью пойдут православные', ChronicleSeedContent::ENTRY_BODY);
    }

    public function testBodyContainsItalicBlockIntact(): void
    {
        self::assertStringContainsString('рассудит без скриптов', ChronicleSeedContent::ENTRY_BODY);
        self::assertStringContainsString('(за скобки)*', ChronicleSeedContent::ENTRY_BODY);
    }
}
