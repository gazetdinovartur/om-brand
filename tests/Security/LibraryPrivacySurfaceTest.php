<?php

declare(strict_types=1);

namespace App\Tests\Security;

use PHPUnit\Framework\TestCase;

final class LibraryPrivacySurfaceTest extends TestCase
{
    public function testLibraryIsKeptOutOfPublicDiscoverySurfaces(): void
    {
        $robots = (string) file_get_contents(dirname(__DIR__, 2).'/src/Controller/SeoController.php');
        $seo = (string) file_get_contents(dirname(__DIR__, 2).'/src/Seo/SeoMetadataFactory.php');
        $house = (string) file_get_contents(dirname(__DIR__, 2).'/src/Content/HouseContent.php');

        self::assertStringContainsString('Disallow: /library', $robots);
        self::assertStringContainsString('Disallow: /account', $robots);
        self::assertStringNotContainsString('web_library', explode('function sitemap', $robots, 2)[1] ?? '');
        self::assertStringContainsString("robots: 'noindex, nofollow, noarchive'", $seo);
        self::assertStringContainsString("'route' => 'web_library', 'label' => 'Библиотека'", $house);
        self::assertStringContainsString("'id' => 'library'", $house);
        self::assertStringContainsString("'id' => 'place'", $house);
        self::assertStringContainsString("'route' => 'web_account'", $house);
    }
}
