<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\LibraryBook;
use App\Entity\LibraryChapter;
use App\Entity\ReadingBookmark;
use App\Entity\ReadingNote;
use App\Entity\ReadingQuote;
use App\Entity\SiteUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Firewall context is `app` (shared by main/admin). loginUser must use that context
 * key — `main` writes `_security_main` and does not survive the next request.
 */
final class AccountAccessTest extends WebTestCase
{
    public function testGuestIsSentToLibraryLoginWithAccountTarget(): void
    {
        $client = static::createClient();
        $client->request('GET', '/account');

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/library/login', $location);
        self::assertStringContainsString('_target_path=', $location);
        self::assertStringContainsString('/account', $location);
    }

    public function testLegacyLibraryAccountRedirectsToAccountKeepingTab(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->createSiteUser($em);
        $client->loginUser($user, 'app');

        $client->request('GET', '/library/account?tab=keys');

        self::assertResponseStatusCodeSame(301);
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertSame('/account', parse_url($location, \PHP_URL_PATH) ?: $location);
        self::assertStringContainsString('tab=keys', $location);
    }

    public function testAccountDefaultsToPracticeTab(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->createSiteUser($em);
        $client->loginUser($user, 'app');

        $crawler = $client->request('GET', '/account');

        self::assertResponseIsSuccessful();
        self::assertSame('noindex, nofollow, noarchive', $client->getResponse()->headers->get('X-Robots-Tag'));
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertSelectorTextContains('h1', 'Своё место');
        self::assertSelectorExists('.library-tabs__link.is-active[href*="tab=practice"]');
        self::assertSelectorExists('#library-practice-title');
        self::assertSame('Практика', trim($crawler->filter('.library-tabs__link.is-active')->text()));
    }

    public function testAccountReadingTabUsesMarksAccordionWithShortPlaceLinks(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->createSiteUser($em);
        [$book, $chapter] = $this->createBookChapter($em);

        $em->persist(
            (new ReadingBookmark())
                ->setSiteUser($user)
                ->setBook($book)
                ->setChapter($chapter)
                ->setBlockId('b-acc-1')
                ->setExcerpt('Закладка для аккордеона'),
        );
        $em->persist(
            (new ReadingQuote())
                ->setSiteUser($user)
                ->setBook($book)
                ->setChapter($chapter)
                ->setBlockId('b-acc-1')
                ->setStartOffset(0)
                ->setEndOffset(12)
                ->setExcerpt('Цитата для внимания'),
        );
        $em->persist(
            (new ReadingNote())
                ->setSiteUser($user)
                ->setBook($book)
                ->setChapter($chapter)
                ->setBlockId('b-acc-1')
                ->setBody('Заметка на первом плане'),
        );
        $em->flush();

        $client->loginUser($user, 'app');
        $crawler = $client->request('GET', '/account?tab=reading');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.library-tabs__link.is-active[href*="tab=reading"]');
        self::assertSelectorExists('.library-account__marks details.library-account__panel');
        self::assertCount(3, $crawler->filter('.library-account__panel'));
        $bodies = $crawler->filter('.library-account__mark-body')->each(
            static fn ($node): string => trim($node->text()),
        );
        self::assertContains('Цитата для внимания', $bodies);
        self::assertContains('Заметка на первом плане', $bodies);
        $placeText = trim($crawler->filter('.library-account__mark-place')->first()->text());
        self::assertSame($book->getTitle(), $placeText);
        self::assertStringNotContainsString('·', $placeText);
    }

    public function testAccountKeysTabAndInvalidTabFallback(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->createSiteUser($em);
        $client->loginUser($user, 'app');

        $client->request('GET', '/account?tab=keys');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.library-tabs__link.is-active[href*="tab=keys"]');
        self::assertSelectorExists('[data-passkey-register-button]');
        self::assertSelectorTextContains('[data-passkey-register-button]', 'Добавить ключ на этом устройстве');

        $client->request('GET', '/account?tab=unknown');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.library-tabs__link.is-active[href*="tab=practice"]');
        self::assertSelectorExists('#library-practice-title');
    }

    private function createSiteUser(EntityManagerInterface $em): SiteUser
    {
        $user = (new SiteUser())
            ->setEmail('account-'.bin2hex(random_bytes(4)).'@localhost')
            ->setDisplayName('Account Tester');
        $em->persist($user);
        $em->flush();

        return $user;
    }

    /** @return array{LibraryBook, LibraryChapter} */
    private function createBookChapter(EntityManagerInterface $em): array
    {
        $suffix = bin2hex(random_bytes(4));
        $book = (new LibraryBook())
            ->setTitle('Книга места '.$suffix)
            ->setAuthor('Автор')
            ->setSlug('account-book-'.$suffix)
            ->setSourceChecksum(hash('sha256', $suffix))
            ->setTotalCharacters(40)
            ->setFootnotes([]);
        $chapter = (new LibraryChapter())
            ->setTitle('Глава')
            ->setSlug('chapter-'.$suffix)
            ->setSortOrder(1)
            ->setBlocks([
                ['id' => 'b-acc-1', 'type' => 'paragraph', 'text' => 'Текст для пометок в своём месте.'],
            ])
            ->setCharacterCount(40);
        $book->addChapter($chapter);
        $em->persist($book);
        $em->flush();

        return [$book, $chapter];
    }
}
