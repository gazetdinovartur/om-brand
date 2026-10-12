<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PasskeyAccessTest extends WebTestCase
{
    public function testCredentialManagementRequiresAdmin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/passkeys');

        self::assertResponseRedirects('/admin/login');
    }

    public function testLibraryPasskeyOptionsEndpointIsPublic(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/library/passkey/login/options', []);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('ok', $payload['status'] ?? null);
        self::assertNotEmpty($payload['challenge'] ?? null);
        self::assertSame('required', $payload['userVerification'] ?? null);
    }

    public function testAdminPasswordlessOptionsEndpointIsPublic(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/admin/passkey/login/options', []);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('ok', $payload['status'] ?? null);
        self::assertNotEmpty($payload['challenge'] ?? null);
    }
}
