<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LibraryAccessTest extends WebTestCase
{
    public function testGuestIsSentToLibraryLoginWithReturnTarget(): void
    {
        $client = static::createClient();
        $client->request('GET', '/library');

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/library/login', $location);
        self::assertStringContainsString('_target_path=/library', $location);
    }

    public function testLoginGateIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/library/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Вход');
        self::assertSelectorTextContains('.library-login__lead', 'Библиотека');
        // На localhost Telegram OAuth недоступен — без кнопки и без «или».
        self::assertSelectorNotExists('[data-login-method="telegram"]');
        self::assertSelectorNotExists('[data-login-divider="telegram-email"]');
        self::assertSelectorNotExists('.library-login__telegram-btn');
    }
}
