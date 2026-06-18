<?php

namespace App\Service;

use App\Entity\Inquiry;
use App\Entity\SiteSettings;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class InquiryMailNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly InquiryAdminUrlGenerator $inquiryAdminUrlGenerator,
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

    public function notifyNewInquiry(Inquiry $inquiry, SiteSettings $settings): bool
    {
        $recipient = $settings->getNotificationEmail();
        if (!$this->isConfigured() || null === $recipient || '' === trim($recipient)) {
            return false;
        }

        $inquiryId = $inquiry->getId();
        if (null === $inquiryId) {
            return false;
        }

        $adminUrl = $this->inquiryAdminUrlGenerator->detailUrl($inquiryId);
        $siteDomain = $this->siteDomain();
        $messageText = $this->messageText($inquiry);

        $templateContext = [
            'inquiry' => $inquiry,
            'adminUrl' => $adminUrl,
            'siteDomain' => $siteDomain,
            'messageText' => $messageText,
        ];

        $senderName = trim((string) $settings->getName());
        $from = '' !== $senderName
            ? new Address($this->mailFrom, $senderName)
            : new Address($this->mailFrom);

        try {
            $this->mailer->send(
                (new Email())
                    ->from($from)
                    ->replyTo(new Address($this->mailFrom, $senderName ?: $siteDomain))
                    ->sender($this->mailFrom)
                    ->to($recipient)
                    ->subject(sprintf('Заявка с %s — %s', $siteDomain, $inquiry->getName()))
                    ->text($this->twig->render('emails/inquiry_notification.txt.twig', $templateContext))
                    ->html($this->twig->render('emails/inquiry_notification.html.twig', $templateContext)),
            );

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send inquiry email.', [
                'inquiry_id' => $inquiryId,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function messageText(Inquiry $inquiry): string
    {
        $message = trim($inquiry->getMessage());

        return '' !== $message ? $message : '— без описания';
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
