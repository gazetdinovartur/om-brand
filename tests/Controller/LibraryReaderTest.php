<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AdminUser;
use App\Entity\LibraryBook;
use App\Entity\LibraryChapter;
use App\Entity\ReadingBookmark;
use App\Entity\ReadingProgress;
use App\Entity\SiteUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LibraryReaderTest extends WebTestCase
{
    public function testPrivateReaderSavesUserScopedProgressAndBookmarks(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$admin, $book, $chapter] = $this->createLibraryFixture($em);

        $client->loginUser($admin, 'admin');
        $client->request('GET', '/library');

        self::assertResponseIsSuccessful();
        self::assertSame('noindex, nofollow, noarchive', $client->getResponse()->headers->get('X-Robots-Tag'));
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertSelectorTextContains('.site-nav', 'Библиотека');

        $crawler = $client->request('GET', sprintf(
            '/library/%s/read/%s',
            $book->getSlug(),
            $chapter->getSlug(),
        ));
        self::assertResponseIsSuccessful();
        $reader = $crawler->filter('[data-reader]');
        $progressToken = (string) $reader->attr('data-reader-progress-token');
        $bookmarkToken = (string) $reader->attr('data-reader-bookmark-token');
        $blockId = $chapter->getBlocks()[0]['id'];

        $client->jsonRequest('POST', '/library/'.$book->getSlug().'/progress', [
            '_token' => $progressToken,
            'chapter' => $chapter->getSlug(),
            'block' => $blockId,
        ]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('POST', '/library/'.$book->getSlug().'/bookmarks', [
            '_token' => $bookmarkToken,
            'chapter' => $chapter->getSlug(),
            'block' => $blockId,
        ]);
        self::assertResponseIsSuccessful();
        $em->clear();

        $siteUser = $em->getRepository(SiteUser::class)->findOneBy(['email' => $admin->getEmail()]);
        self::assertInstanceOf(SiteUser::class, $siteUser);

        $progress = $em->getRepository(ReadingProgress::class)->findOneBy([
            'siteUser' => $siteUser->getId(),
            'book' => $book->getId(),
        ]);
        self::assertInstanceOf(ReadingProgress::class, $progress);
        self::assertSame($blockId, $progress->getBlockId());
        self::assertCount(1, $em->getRepository(ReadingBookmark::class)->findBy([
            'siteUser' => $siteUser->getId(),
            'book' => $book->getId(),
        ]));
    }

    public function testProgressEndpointRejectsInvalidCsrf(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$admin, $book, $chapter] = $this->createLibraryFixture($em);
        $client->loginUser($admin, 'admin');

        $client->jsonRequest('POST', '/library/'.$book->getSlug().'/progress', [
            '_token' => 'invalid',
            'chapter' => $chapter->getSlug(),
            'block' => $chapter->getBlocks()[0]['id'],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    /** @return array{AdminUser, LibraryBook, LibraryChapter} */
    private function createLibraryFixture(EntityManagerInterface $em): array
    {
        $suffix = bin2hex(random_bytes(5));
        $admin = (new AdminUser())
            ->setEmail('library-'.$suffix.'@localhost')
            ->setPassword('unused');
        $book = (new LibraryBook())
            ->setTitle('Тестовая книга')
            ->setAuthor('Тестовый автор')
            ->setSlug('test-book-'.$suffix)
            ->setSourceChecksum(hash('sha256', $suffix))
            ->setTotalCharacters(120)
            ->setFootnotes([]);
        $chapter = (new LibraryChapter())
            ->setTitle('Введение')
            ->setSlug('introduction')
            ->setSortOrder(10)
            ->setBlocks([
                ['id' => 'b-test-1', 'type' => 'paragraph', 'text' => 'Текст для проверки сохранения места чтения.'],
                ['id' => 'b-test-2', 'type' => 'paragraph', 'text' => 'Второй абзац тестовой главы.'],
            ])
            ->setCharacterCount(120);
        $book->addChapter($chapter);

        $em->persist($admin);
        $em->persist($book);
        $em->flush();

        return [$admin, $book, $chapter];
    }
}
