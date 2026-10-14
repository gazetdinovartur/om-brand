<?php

namespace App\Service;

use App\Entity\PaymentOffer;
use App\Entity\SiteSettings;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/** Письмо после успешной оплаты: ссылка в комнату практики. */
final class PracticePaidMailNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
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

    public function isConfigured(): bool
    {
        return '' !== $this->mailerDsn
            && !str_starts_with($this->mailerDsn, 'null://')
            && '' !== trim($this->mailFrom);
    }

    public function sendRoomAccess(PaymentOffer $offer, SiteSettings $settings): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $inquiry = $offer->getInquiry();
        $email = mb_strtolower(trim((string) ($inquiry?->getContact() ?? $offer->getSiteUser()?->getEmail() ?? '')));
        if ('' === $email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('Practice paid email skipped: no recipient.', [
                'offer_id' => $offer->getId(),
            ]);

            return false;
        }

        $siteDomain = $this->siteDomain();
        $siteName = trim((string) $settings->getName()) ?: $siteDomain;
        $roomUrl = $this->urlGenerator->generate('web_practice_room', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $loginUrl = $this->urlGenerator->generate(
            'web_library_login',
            ['_target_path' => $this->urlGenerator->generate('web_practice_room')],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $accountUrl = $this->urlGenerator->generate(
            'web_account',
            ['tab' => 'practice'],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $senderName = trim((string) $settings->getName());
        $from = '' !== $senderName
            ? new Address($this->mailFrom, $senderName)
            : new Address($this->mailFrom);

        $context = [
            'inquiry' => $inquiry,
            'offer' => $offer,
            'roomUrl' => $roomUrl,
            'loginUrl' => $loginUrl,
            'accountUrl' => $accountUrl,
            'siteDomain' => $siteDomain,
            'siteName' => $siteName,
            'amountRubles' => (int) round($offer->getAmountRubles()),
        ];

        try {
            $this->mailer->send(
                (new Email())
                    ->from($from)
                    ->replyTo(new Address($this->mailFrom, $senderName ?: $siteDomain))
                    ->sender($this->mailFrom)
                    ->to($email)
                    ->subject('Оплата получена · доступ к комнате практики')
                    ->text($this->twig->render('emails/practice_paid.txt.twig', $context))
                    ->html($this->twig->render('emails/practice_paid.html.twig', $context)),
            );

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send practice paid email.', [
                'offer_id' => $offer->getId(),
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function siteDomain(): string
    {
        if ('' !== $this->siteUrl) {
            $host = parse_url($this->siteUrl, PHP_URL_HOST);
            if (\is_string($host) && '' !== $host) {
                return $host;
            }
        }

        return 'сайт';
    }
}
