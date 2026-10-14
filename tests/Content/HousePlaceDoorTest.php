<?php

declare(strict_types=1);

namespace App\Tests\Content;

use App\Content\HouseContent;
use PHPUnit\Framework\TestCase;

final class HousePlaceDoorTest extends TestCase
{
    public function testMapRoomsIncludePlaceDoorToAccount(): void
    {
        $rooms = HouseContent::mapRooms();
        $place = null;
        foreach ($rooms as $room) {
            if (($room['id'] ?? null) === 'place') {
                $place = $room;
                break;
            }
        }

        self::assertNotNull($place);
        self::assertSame('Своё место', $place['label']);
        self::assertSame('web_account', $place['route']);
        self::assertArrayNotHasKey('external', $place);
        self::assertStringContainsString('ключи', mb_strtolower($place['invite']));
        self::assertStringNotContainsString('практика', mb_strtolower($place['invite']));
    }

    public function testPracticeHiddenFromPublicNavigationAndMap(): void
    {
        putenv('PRACTICE_PUBLIC_ENABLED=0');
        $_ENV['PRACTICE_PUBLIC_ENABLED'] = '0';
        $_SERVER['PRACTICE_PUBLIC_ENABLED'] = '0';

        self::assertFalse(\App\Content\HouseContent::isPracticePublicEnabled());

        foreach (\App\Content\HouseContent::navigationItems() as $item) {
            self::assertNotSame('web_practice', $item['route'] ?? null);
        }
        foreach (\App\Content\HouseContent::mapRooms() as $room) {
            self::assertNotSame('practice', $room['id'] ?? null);
        }

        // Restore test default from phpunit.xml.dist
        putenv('PRACTICE_PUBLIC_ENABLED=1');
        $_ENV['PRACTICE_PUBLIC_ENABLED'] = '1';
        $_SERVER['PRACTICE_PUBLIC_ENABLED'] = '1';
    }

    public function testPracticeAndPlaceIconsMatchChosenMotifs(): void
    {
        $icons = (string) file_get_contents(dirname(__DIR__, 2).'/templates/web/_partials/house_room_icon.html.twig');

        self::assertStringContainsString("id == 'practice'", $icons);
        self::assertStringContainsString('M16.5 10.5h15', $icons);
        self::assertStringContainsString('M21.2 13.2v4.2', $icons);

        self::assertStringContainsString("id == 'place'", $icons);
        self::assertStringContainsString("r=\"15.4\"", $icons);
        self::assertStringContainsString('house-rooms__accent', $icons);
        self::assertStringNotContainsString('place_arch', $icons);
        self::assertStringNotContainsString('place_key', $icons);
        self::assertStringNotContainsString('place_dot', $icons);
    }

    public function testHouseDoorsAreCenteredWithFlex(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2).'/public/css/site.css');
        $chunk = explode('.house-rooms__list {', $css, 2)[1] ?? '';
        $chunk = explode('}', $chunk, 2)[0] ?? '';

        self::assertStringContainsString('display: flex', $chunk);
        self::assertStringContainsString('justify-content: center', $chunk);
        self::assertStringContainsString('flex-wrap: wrap', $chunk);
    }
}
