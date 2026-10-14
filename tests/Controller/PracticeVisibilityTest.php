<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PracticeVisibilityTest extends WebTestCase
{
    public function testAnonymousGetsNotFoundWhenPracticeNotPublic(): void
    {
        $client = static::createClient();
        putenv('PRACTICE_PUBLIC_ENABLED=0');
        $_ENV['PRACTICE_PUBLIC_ENABLED'] = '0';
        $_SERVER['PRACTICE_PUBLIC_ENABLED'] = '0';

        $client->request('GET', '/practice');
        self::assertResponseStatusCodeSame(404);

        putenv('PRACTICE_PUBLIC_ENABLED=1');
        $_ENV['PRACTICE_PUBLIC_ENABLED'] = '1';
        $_SERVER['PRACTICE_PUBLIC_ENABLED'] = '1';
    }

    public function testAdminCanOpenPracticeWhenNotPublic(): void
    {
        $client = static::createClient();
        putenv('PRACTICE_PUBLIC_ENABLED=0');
        $_ENV['PRACTICE_PUBLIC_ENABLED'] = '0';
        $_SERVER['PRACTICE_PUBLIC_ENABLED'] = '0';

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $admin = $em->getRepository(AdminUser::class)->findOneBy(['email' => 'practice-vis-admin@localhost']);
        if (!$admin instanceof AdminUser) {
            $admin = (new AdminUser())->setEmail('practice-vis-admin@localhost');
            $em->persist($admin);
        }
        $admin->setPassword($hasher->hashPassword($admin, 'TestAdmin1!'));
        $em->flush();

        $crawler = $client->request('GET', '/admin/login');
        $form = $crawler->selectButton('Войти')->form([
            '_username' => 'practice-vis-admin@localhost',
            '_password' => 'TestAdmin1!',
        ]);
        $client->submit($form);
        if ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        }

        $client->request('GET', '/practice');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');

        putenv('PRACTICE_PUBLIC_ENABLED=1');
        $_ENV['PRACTICE_PUBLIC_ENABLED'] = '1';
        $_SERVER['PRACTICE_PUBLIC_ENABLED'] = '1';
    }
}
