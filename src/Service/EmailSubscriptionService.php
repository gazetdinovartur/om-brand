<?php

namespace App\Service;

use App\Entity\EmailSubscriber;
use App\Entity\SiteSettings;
use App\Enum\EmailSubscriberStatus;
use App\Repository\EmailSubscriberRepository;
use App\Repository\SiteSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class EmailSubscriptionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EmailSubscriberRepository $subscribers,
        private readonly SiteSettingsRepository $settingsRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
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
     * @return array{ok: bool, message: string, status?: string}
     */
    public function requestSubscribe(string $email): array
    {
        $email = mb_strtolower(trim($email));
        if ('' === $email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Укажите корректный email.'];
        }

        if (!$this->isMailConfigured()) {
            return ['ok' => false, 'message' => 'Подписка по email временно недоступна.'];
        }

        $subscriber = $this->subscribers->findOneByEmail($email);

        if (null !== $subscriber && $subscriber->isConfirmed()) {
            return [
                'ok' => true,
                'message' => 'Этот адрес уже подписан.',
                'status' => EmailSubscriberStatus::Confirmed->value,
            ];
        }

        if (null === $subscriber) {
            $subscriber = new EmailSubscriber($email);
            $this->em->persist($subscriber);
        } else {
            $subscriber->markPendingForConfirm();
        }

        $this->em->flush();

        if (!$this->sendConfirmEmail($subscriber)) {
            return ['ok' => false, 'message' => 'Не удалось отправить письмо. Попробуйте позже.'];
        }

        return [
            'ok' => true,
            'message' => 'Проверьте почту — подтвердите подписку.',
            'status' => EmailSubscriberStatus::Pending->value,
        ];
    }

    public function confirm(string $token): ?EmailSubscriber
    {
        if ('' === $token || 64 !== strlen($token)) {
            return null;
        }

        $subscriber = $this->subscribers->findOneByConfirmToken($token);
        if (null === $subscriber) {
            return null;
        }

        if (!$subscriber->isConfirmed()) {
            $subscriber->confirm();
            $this->em->flush();
        }

        return $subscriber;
    }

    public function unsubscribe(string $token): ?EmailSubscriber
    {
        if ('' === $token || 64 !== strlen($token)) {
            return null;
        }

        $subscriber = $this->subscribers->findOneByUnsubscribeToken($token);
        if (null === $subscriber) {
            return null;
        }

        if (EmailSubscriberStatus::Unsubscribed !== $subscriber->getStatus()) {
            $subscriber->unsubscribe();
            $this->em->flush();
        }

        return $subscriber;
    }

    private function sendConfirmEmail(EmailSubscriber $subscriber): bool
    {
        $settings = $this->settingsRepository->getSettings();
        $confirmUrl = $this->urlGenerator->generate(
            'web_email_subscribe_confirm',
            ['token' => $subscriber->getConfirmToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $context = [
            'confirmUrl' => $confirmUrl,
            'siteName' => trim((string) $settings->getName()) ?: $this->siteDomain(),
            'siteDomain' => $this->siteDomain(),
        ];

        return $this->sendTemplated(
            $settings,
            $subscriber->getEmail(),
            sprintf('Подтвердите подписку — %s', $context['siteName']),
            'emails/subscribe_confirm.html.twig',
            'emails/subscribe_confirm.txt.twig',
            $context,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function sendTemplated(
        SiteSettings $settings,
        string $to,
        string $subject,
        string $htmlTemplate,
        string $textTemplate,
        array $context,
    ): bool {
        if (!$this->isMailConfigured()) {
            return false;
        }

        $senderName = trim((string) $settings->getName());
        $from = '' !== $senderName
            ? new Address($this->mailFrom, $senderName)
            : new Address($this->mailFrom);

        try {
            $this->mailer->send(
                (new Email())
                    ->from($from)
                    ->sender($this->mailFrom)
                    ->to($to)
                    ->subject($subject)
                    ->text($this->twig->render($textTemplate, $context))
                    ->html($this->twig->render($htmlTemplate, $context)),
            );

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send subscription email.', [
                'to' => $to,
                'template' => $htmlTemplate,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function siteDomain(): string
    {
        if ('' !== $this->siteUrl) {
            $host = parse_url($this->siteUrl, PHP_URL_HOST);
            if (is_string($host) && '' !== $host) {
                return $host;
            }
        }

        return 'сайт';
    }
}
