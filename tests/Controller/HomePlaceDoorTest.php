<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomePlaceDoorTest extends WebTestCase
{
    public function testHomeShowsCenteredPlaceDoorToAccount(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.house-rooms__list');
        self::assertSelectorExists('.house-rooms__icon--place');
        // Практика временно скрыта с карты (HouseContent::PRACTICE_PUBLIC_ENABLED).
        self::assertSelectorNotExists('.house-rooms__icon--practice');

        $placeLink = $crawler->filter('a.house-rooms__door')->reduce(
            static fn ($node): bool => str_contains($node->text(), 'Своё место'),
        );
        self::assertSame(1, $placeLink->count());
        $href = (string) $placeLink->attr('href');
        self::assertSame('/account', parse_url($href, \PHP_URL_PATH) ?: $href);
        self::assertStringContainsString('Своё место', $placeLink->text());
    }
}
