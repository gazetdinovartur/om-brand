<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SiteLoginChallenge;
use App\Entity\SiteUser;
use App\Repository\SiteLoginChallengeRepository;
use App\Repository\SiteSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class SiteEmailLoginService
{
    private const CODE_TTL_MINUTES = 15;
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SiteLoginChallengeRepository $challenges,
        private readonly SiteUserResolver $siteUsers,
        private readonly SiteSettingsRepository $settingsRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(MAILER_DSN)%')]
        private readonly string $mailerDsn = '',
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $mailFrom = '',
        #[Autowire('%env(default:app.site_url:APP_SITE_URL)%')]
        private readonly string $siteUrl = '',
    ) {
    }

    public function isMailConfigured(): bool
    {
        return '' !== $this->mailerDsn
            && !str_starts_with($this->mailerDsn, 'null://')
            && '' !== trim($this->mailFrom);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function requestCode(string $email): array
    {
        $email = mb_strtolower(trim($email));
        if ('' === $email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Укажите корректный email.'];
        }

        if (!$this->isMailConfigured()) {
            return ['ok' => false, 'message' => 'Вход по email временно недоступен.'];
        }

        $code = (string) random_int(100000, 999999);
        $this->challenges->invalidateOpenForEmail($email);

        $challenge = new SiteLoginChallenge(
            $email,
            hash('sha256', $code),
            new \DateTimeImmutable(sprintf('+%d minutes', self::CODE_TTL_MINUTES)),
        );
        $this->entityManager->persist($challenge);
        $this->entityManager->flush();

        if (!$this->sendCodeEmail($email, $code)) {
            return ['ok' => false, 'message' => 'Не удалось отправить письмо. Попробуйте позже.'];
        }

        return [
            'ok' => true,
            'message' => 'Код отправлен на почту. Он действует 15 минут.',
        ];
    }

    /**
     * @return array{ok: bool, message: string, user?: SiteUser}
     */
    public function verifyCode(string $email, string $code): array
    {
        $email = mb_strtolower(trim($email));
        $code = trim($code);
        if ('' === $email || !preg_match('/^\d{6}$/', $code)) {
            return ['ok' => false, 'message' => 'Введите email и шестизначный код.'];
        }

        $challenge = $this->challenges->findLatestActiveForEmail($email);
        if (!$challenge instanceof SiteLoginChallenge) {
            return ['ok' => false, 'message' => 'Код не найден или устарел. Запросите новый.'];
        }

        if ($challenge->getAttempts() >= self::MAX_ATTEMPTS) {
            $challenge->markConsumed();
            $this->entityManager->flush();

            return ['ok' => false, 'message' => 'Слишком много попыток. Запросите новый код.'];
        }

        $challenge->registerAttempt();
        if (!hash_equals($challenge->getCodeHash(), hash('sha256', $code))) {
            $this->entityManager->flush();

            return ['ok' => false, 'message' => 'Неверный код.'];
        }

        $challenge->markConsumed();
        $user = $this->siteUsers->findOrCreateByEmail($email);
        $user->markLoggedIn();
        $this->entityManager->flush();

        return [
            'ok' => true,
            'message' => 'Вход выполнен.',
            'user' => $user,
        ];
    }

    private function sendCodeEmail(string $email, string $code): bool
    {
        try {
            $settings = $this->settingsRepository->getSettings();
            $siteName = $settings->getName() ?: 'Артур';
            $siteDomain = $this->siteDomain();

            $html = $this->twig->render('emails/site_login_code.html.twig', [
                'siteName' => $siteName,
                'siteDomain' => $siteDomain,
                'code' => $code,
                'ttlMinutes' => self::CODE_TTL_MINUTES,
            ]);
            $text = $this->twig->render('emails/site_login_code.txt.twig', [
                'siteName' => $siteName,
                'siteDomain' => $siteDomain,
                'code' => $code,
                'ttlMinutes' => self::CODE_TTL_MINUTES,
            ]);

            $message = (new Email())
                ->from(new Address(trim($this->mailFrom), $siteName))
                ->to($email)
                ->subject(sprintf('Код входа · %s', $siteName))
                ->html($html)
                ->text($text);

            $this->mailer->send($message);

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('Site login email failed: '.$exception->getMessage(), [
                'exception' => $exception,
            ]);

            return false;
        }
    }

    private function siteDomain(): string
    {
        $base = trim($this->siteUrl);
        if ('' === $base) {
            return 'arturlun.ru';
        }
        $host = parse_url($base, \PHP_URL_HOST);

        return \is_string($host) && '' !== $host ? $host : 'arturlun.ru';
    }
}
