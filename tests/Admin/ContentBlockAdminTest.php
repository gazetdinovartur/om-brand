<?php

namespace App\Tests\Admin;

use App\Entity\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ContentBlockAdminTest extends WebTestCase
{
    public function testLandingContentBlocksAreHiddenFromAdminMenu(): void
    {
        $client = $this->loginAsAdmin();

        $client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Настройки');
        self::assertSelectorTextNotContains('body', 'Контент лендинга');
    }

    private function loginAsAdmin(): KernelBrowser
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $admin = $entityManager->getRepository(AdminUser::class)->findOneBy(['email' => 'content-admin-test@localhost']);
        if (!$admin instanceof AdminUser) {
            $admin = (new AdminUser())->setEmail('content-admin-test@localhost');
            $entityManager->persist($admin);
        }

        $admin->setPassword($hasher->hashPassword($admin, 'TestAdmin1!'));
        $entityManager->flush();

        $crawler = $client->request('GET', '/admin/login');
        $form = $crawler->selectButton('Войти')->form([
            '_username' => 'content-admin-test@localhost',
            '_password' => 'TestAdmin1!',
        ]);
        $client->submit($form);
        if ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        }

        return $client;
    }
}
