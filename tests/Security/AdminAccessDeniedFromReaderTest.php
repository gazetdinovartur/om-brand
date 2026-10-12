<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\SiteUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminAccessDeniedFromReaderTest extends WebTestCase
{
    public function testLibraryReaderIsRedirectedToAdminLoginInsteadOfForbidden(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $email = 'reader-admin-403-'.bin2hex(random_bytes(3)).'@localhost';
        $reader = (new SiteUser())->setEmail($email)->setDisplayName('Reader');
        $em->persist($reader);
        $em->flush();

        $client->loginUser($reader, 'main');
        $client->request('GET', '/admin');

        self::assertTrue($client->getResponse()->isRedirect());
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/admin/login', $location);
        self::assertStringNotContainsString('403', (string) $client->getResponse()->getStatusCode());
        self::assertSame(302, $client->getResponse()->getStatusCode());
    }
}
