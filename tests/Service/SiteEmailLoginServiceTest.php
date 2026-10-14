<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\SiteLoginChallenge;
use App\Repository\SiteLoginChallengeRepository;
use App\Repository\SiteSettingsRepository;
use App\Repository\SiteUserRepository;
use App\Service\SiteEmailLoginService;
use App\Service\SiteUserResolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class SiteEmailLoginServiceTest extends KernelTestCase
{
    public function testRequestCodeRejectsInvalidEmail(): void
    {
        $service = $this->serviceWithMailer($this->createMock(MailerInterface::class));

        $result = $service->requestCode('not-an-email');

        self::assertFalse($result['ok']);
        self::assertSame('Укажите корректный email.', $result['message']);
    }

    public function testRequestCodeCreatesChallengeAndSendsMail(): void
    {
        $sent = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (Email $email) use (&$sent): bool {
                $sent = $email;

                return true;
            }));

        $service = $this->serviceWithMailer($mailer);
        $email = 'login-req-'.bin2hex(random_bytes(3)).'@example.test';

        $result = $service->requestCode($email);

        self::assertTrue($result['ok']);
        self::assertStringContainsString('Код отправлен', $result['message']);
        self::assertInstanceOf(Email::class, $sent);
        self::assertSame($email, $sent->getTo()[0]->getAddress());
        self::assertStringContainsString('Код входа', $sent->getSubject());

        $challenge = static::getContainer()->get(SiteLoginChallengeRepository::class)
            ->findLatestActiveForEmail($email);
        self::assertInstanceOf(SiteLoginChallenge::class, $challenge);
    }

    public function testRequestCodeReportsUnavailableWhenMailerNotConfigured(): void
    {
        $service = $this->serviceWithMailer(
            $this->createMock(MailerInterface::class),
            mailerDsn: 'null://null',
            mailFrom: 'noreply@example.test',
        );

        $result = $service->requestCode('someone@example.test');

        self::assertFalse($result['ok']);
        self::assertSame('Вход по email временно недоступен.', $result['message']);
    }

    public function testRequestCodeInvalidatesPreviousOpenChallenge(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send');
        $service = $this->serviceWithMailer($mailer);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $email = 'login-invalidate-'.bin2hex(random_bytes(3)).'@example.test';
        $old = new SiteLoginChallenge($email, hash('sha256', '111111'), new \DateTimeImmutable('+15 minutes'));
        $em->persist($old);
        $em->flush();

        $service->requestCode($email);

        $em->refresh($old);
        self::assertTrue($old->isConsumed());
        self::assertInstanceOf(
            SiteLoginChallenge::class,
            static::getContainer()->get(SiteLoginChallengeRepository::class)->findLatestActiveForEmail($email),
        );
    }

    public function testVerifyCodeCreatesUserAndConsumesChallenge(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $service = $container->get(SiteEmailLoginService::class);

        $email = 'login-verify-'.bin2hex(random_bytes(3)).'@example.test';
        $code = '654321';
        $em->persist(new SiteLoginChallenge($email, hash('sha256', $code), new \DateTimeImmutable('+15 minutes')));
        $em->flush();

        $result = $service->verifyCode($email, $code);

        self::assertTrue($result['ok']);
        self::assertArrayHasKey('user', $result);
        self::assertSame($email, $result['user']->getEmail());
        self::assertNotNull($result['user']->getLastLoginAt());
        self::assertNull(
            $container->get(SiteLoginChallengeRepository::class)->findLatestActiveForEmail($email),
        );
        self::assertNotNull($container->get(SiteUserRepository::class)->findOneByEmail($email));
    }

    public function testVerifyCodeRejectsWrongCodeAndCountsAttempts(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $service = static::getContainer()->get(SiteEmailLoginService::class);

        $email = 'login-wrong-'.bin2hex(random_bytes(3)).'@example.test';
        $challenge = new SiteLoginChallenge($email, hash('sha256', '111111'), new \DateTimeImmutable('+15 minutes'));
        $em->persist($challenge);
        $em->flush();

        $result = $service->verifyCode($email, '000000');

        self::assertFalse($result['ok']);
        self::assertSame('Неверный код.', $result['message']);
        $em->refresh($challenge);
        self::assertSame(1, $challenge->getAttempts());
        self::assertFalse($challenge->isConsumed());
    }

    public function testVerifyCodeLocksAfterMaxAttempts(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $service = static::getContainer()->get(SiteEmailLoginService::class);

        $email = 'login-lock-'.bin2hex(random_bytes(3)).'@example.test';
        $challenge = new SiteLoginChallenge($email, hash('sha256', '222222'), new \DateTimeImmutable('+15 minutes'));
        $em->persist($challenge);
        $em->flush();

        for ($i = 0; $i < 5; ++$i) {
            $service->verifyCode($email, '000000');
        }

        $result = $service->verifyCode($email, '222222');

        self::assertFalse($result['ok']);
        self::assertStringContainsString('Слишком много попыток', $result['message']);
    }

    public function testVerifyCodeRejectsMissingChallenge(): void
    {
        self::bootKernel();
        $service = static::getContainer()->get(SiteEmailLoginService::class);

        $result = $service->verifyCode('missing-'.bin2hex(random_bytes(3)).'@example.test', '123456');

        self::assertFalse($result['ok']);
        self::assertStringContainsString('Код не найден', $result['message']);
    }

    private function serviceWithMailer(
        MailerInterface $mailer,
        string $mailerDsn = 'smtp://localhost',
        string $mailFrom = 'noreply@example.test',
    ): SiteEmailLoginService {
        self::bootKernel();
        $container = static::getContainer();

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('mail-body');

        return new SiteEmailLoginService(
            entityManager: $container->get(EntityManagerInterface::class),
            challenges: $container->get(SiteLoginChallengeRepository::class),
            siteUsers: $container->get(SiteUserResolver::class),
            settingsRepository: $container->get(SiteSettingsRepository::class),
            mailer: $mailer,
            twig: $twig,
            logger: new NullLogger(),
            mailerDsn: $mailerDsn,
            mailFrom: $mailFrom,
            siteUrl: 'https://example.test',
        );
    }
}
