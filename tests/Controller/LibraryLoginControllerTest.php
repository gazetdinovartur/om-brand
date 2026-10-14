<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\SiteLoginChallenge;
use App\Entity\SiteUser;
use App\Repository\SiteUserRepository;
use App\Tests\Support\SiteAuthFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LibraryLoginControllerTest extends WebTestCase
{
    use SiteAuthFixtures;

    public function testLoginPageIsPublicAndShowsEmailForm(): void
    {
        $client = static::createClient();
        $client->request('GET', '/library/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form.library-login__form');
        self::assertSelectorExists('input[name="email"]');
    }

    public function testLoginPreservesSafeTargetPath(): void
    {
        $client = static::createClient();
        $client->request('GET', '/library/login?_target_path=/account%3Ftab%3Dpractice');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_target_path"][value="/account?tab=practice"]');
    }

    public function testLoginRejectsExternalTargetPath(): void
    {
        $client = static::createClient();
        $client->request('GET', '/library/login?_target_path=https://evil.example/phish');

        self::assertResponseIsSuccessful();
        $crawler = $client->getCrawler();
        $target = $crawler->filter('input[name="_target_path"]')->attr('value');
        self::assertSame('/library', $target);
    }

    public function testEmailVerifyLogsInAndRedirectsToTarget(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->loginViaEmailCode($client, $em, targetPath: '/account?tab=practice');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#library-account-title', 'Своё место');
        self::assertSelectorExists('.library-tabs__link.is-active');
        self::assertStringContainsString('Практика', (string) $client->getResponse()->getContent());
        self::assertInstanceOf(SiteUser::class, $user);
    }

    public function testEmailVerifyCreatesSiteUserOnFirstLogin(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $email = 'first-login-'.bin2hex(random_bytes(3)).'@example.test';

        self::assertNull($em->getRepository(SiteUser::class)->findOneBy(['email' => $email]));

        $this->loginViaEmailCode($client, $em, email: $email, targetPath: '/library');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        $user = static::getContainer()->get(SiteUserRepository::class)->findOneByEmail($email);
        self::assertInstanceOf(SiteUser::class, $user);
        self::assertNotNull($user->getLastLoginAt());
    }

    public function testEmailVerifyRejectsWrongCode(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $email = 'wrong-code-'.bin2hex(random_bytes(3)).'@example.test';
        $em->persist(new SiteLoginChallenge($email, hash('sha256', '123456'), new \DateTimeImmutable('+15 minutes')));
        $em->flush();

        $client->request('POST', '/library/login', [
            'action' => 'email_verify',
            'email' => $email,
            'code' => '000000',
            '_target_path' => '/library',
        ], server: ['REMOTE_ADDR' => $this->uniqueClientIp()]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Неверный код', (string) $client->getResponse()->getContent());
    }

    public function testTelegramUnavailableRedirectsWithError(): void
    {
        $client = static::createClient();
        $client->request('GET', '/library/login/telegram');

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/library/login', $location);
        self::assertStringContainsString('telegram_unavailable', $location);
    }

    public function testAuthenticatedUserIsRedirectedAwayFromLogin(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->loginViaEmailCode($client, $em, targetPath: '/library');
        $client->followRedirect();

        $client->request('GET', '/library/login');

        self::assertResponseRedirects('/library');
    }

    public function testAccountRedirectFromLibraryAccountPreservesQuery(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->loginViaEmailCode($client, $em, targetPath: '/account');
        $client->followRedirect();

        $client->request('GET', '/library/account?tab=keys');

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/account', $location);
        self::assertStringContainsString('tab=keys', $location);
    }

    public function testGuestIsRedirectedFromAccountToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/account?tab=keys');

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/library/login', $location);
        self::assertStringContainsString('_target_path=', $location);
    }
}
