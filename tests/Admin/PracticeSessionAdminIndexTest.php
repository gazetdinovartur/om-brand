<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\AdminUser;
use App\Entity\PracticeSession;
use App\Enum\PracticeSessionKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PracticeSessionAdminIndexTest extends WebTestCase
{
    public function testIndexRendersKindEnumAsLabel(): void
    {
        $client = $this->loginAsAdmin();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $session = (new PracticeSession())
            ->setTitle('Админ список '.bin2hex(random_bytes(3)))
            ->setKind(PracticeSessionKind::Speak);
        $em->persist($session);
        $em->flush();

        $client->request('GET', '/admin/practice-session');
        while ($client->getResponse()->isRedirection()) {
            $loc = (string) $client->getResponse()->headers->get('Location');
            if (str_contains($loc, '/admin/login')) {
                self::fail('Redirected to login: '.$loc);
            }
            $client->followRedirect();
        }

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Сессии практики', $html);
        self::assertStringContainsString($session->getTitle(), $html);
        self::assertStringContainsString('Встреча (Speak)', $html);
        self::assertStringContainsString('title="Редактировать"', $html);
        self::assertStringContainsString('title="Удалить"', $html);
        self::assertStringNotContainsString('could not be converted to string', $html);
        self::assertStringNotContainsString('Object of class', $html);
    }

    private function loginAsAdmin(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $admin = $em->getRepository(AdminUser::class)->findOneBy(['email' => 'practice-session-admin-test@localhost']);
        if (!$admin instanceof AdminUser) {
            $admin = (new AdminUser())->setEmail('practice-session-admin-test@localhost');
            $em->persist($admin);
        }
        $admin->setPassword($hasher->hashPassword($admin, 'TestAdmin1!'));
        $em->flush();

        $crawler = $client->request('GET', '/admin/login');
        $form = $crawler->selectButton('Войти')->form([
            '_username' => 'practice-session-admin-test@localhost',
            '_password' => 'TestAdmin1!',
        ]);
        $client->submit($form);
        if ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        }

        return $client;
    }
}
