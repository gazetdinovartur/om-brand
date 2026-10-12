<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\AdminUser;
use App\Entity\LibraryBook;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class LibraryBookAdminTest extends WebTestCase
{
    private ?string $sourcePath = null;

    protected function tearDown(): void
    {
        if (null !== $this->sourcePath) {
            @unlink($this->sourcePath);
        }
        parent::tearDown();
    }

    public function testGuestCannotOpenLibraryCrud(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/library-book');

        self::assertTrue($client->getResponse()->isRedirect());
        self::assertStringContainsString('/admin/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testAdminCanOpenIndexAndUploadBook(): void
    {
        $client = $this->loginAsAdmin();
        $slug = 'admin-library-upload-'.bin2hex(random_bytes(4));
        $this->sourcePath = sys_get_temp_dir().'/'.$slug.'.txt';
        file_put_contents($this->sourcePath, $this->fixtureText());

        $client->request('GET', '/admin/library-book');
        while ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        }
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Загрузить книгу');

        $crawler = $client->request('GET', '/admin/library-book/upload');
        while ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
            $crawler = $client->getCrawler();
        }
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Файл книги');

        $form = $crawler->selectButton('Импортировать')->form([
            'library_book_import[slug]' => $slug,
        ]);
        $form['library_book_import[file]']->upload($this->sourcePath);
        $client->submit($form);
        while ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        }

        self::assertResponseIsSuccessful();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $book = $em->getRepository(LibraryBook::class)->findOneBy(['slug' => $slug]);
        self::assertInstanceOf(LibraryBook::class, $book);
        self::assertSame('ТЕСТОВАЯ КНИГА', $book->getTitle());
    }

    private function loginAsAdmin(): KernelBrowser
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $admin = $entityManager->getRepository(AdminUser::class)->findOneBy(['email' => 'library-admin-test@localhost']);
        if (!$admin instanceof AdminUser) {
            $admin = (new AdminUser())->setEmail('library-admin-test@localhost');
            $entityManager->persist($admin);
        }
        $admin->setPassword($hasher->hashPassword($admin, 'TestAdmin1!'));
        $entityManager->flush();

        $crawler = $client->request('GET', '/admin/login');
        $form = $crawler->selectButton('Войти')->form([
            '_username' => 'library-admin-test@localhost',
            '_password' => 'TestAdmin1!',
        ]);
        $client->submit($form);
        if ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        }

        return $client;
    }

    private function fixtureText(): string
    {
        return <<<'TEXT'
Автор Тестовый

"ТЕСТОВАЯ КНИГА"

Введение

Первый абзац.

Глава 1. Проверка

1.1. Подраздел.

Текст главы.

Заключение

Финальный абзац.
TEXT;
    }
}
