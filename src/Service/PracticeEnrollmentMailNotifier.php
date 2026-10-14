<?php

namespace App\Service;

use App\Entity\Inquiry;
use App\Entity\PaymentOffer;
use App\Entity\SiteSettings;
use App\Practice\PracticeFormat;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/** Письмо заявителю: оплата (если нужна) и вход в «Своё место». */
final class PracticeEnrollmentMailNotifier
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

    public function send(
        Inquiry $inquiry,
        PracticeFormat $format,
        string $recipientEmail,
        SiteSettings $settings,
        ?PaymentOffer $offer = null,
    ): bool {
        if (!$this->isConfigured()) {
            $this->logger->info('Practice enrollment email skipped: mailer not configured.', [
                'inquiry_id' => $inquiry->getId(),
            ]);

            return false;
        }

        $recipientEmail = mb_strtolower(trim($recipientEmail));
        if ('' === $recipientEmail || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $siteDomain = $this->siteDomain();
        $siteName = trim((string) $settings->getName()) ?: $siteDomain;
        $accountUrl = $this->urlGenerator->generate(
            'web_account',
            ['tab' => 'practice'],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $loginUrl = $this->urlGenerator->generate(
            'web_library_login',
            ['_target_path' => $this->urlGenerator->generate('web_account', ['tab' => 'practice'])],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $paymentUrl = null;
        $amountRubles = null;
        if ($offer instanceof PaymentOffer && $format->requiresPayment()) {
            $paymentUrl = $offer->getPaymentUrl();
            $amountRubles = (int) round($offer->getAmountRubles());
        }

        $senderName = trim((string) $settings->getName());
        $from = '' !== $senderName
            ? new Address($this->mailFrom, $senderName)
            : new Address($this->mailFrom);

        $subject = $format->requiresPayment()
            ? sprintf('Ссылка на оплату · %s', $offer?->getTitle() ?? $format->label())
            : 'Заявка принята · Регуляция и регулярность';

        $context = [
            'inquiry' => $inquiry,
            'offer' => $offer,
            'format' => $format,
            'paymentUrl' => $paymentUrl,
            'amountRubles' => $amountRubles,
            'loginUrl' => $loginUrl,
            'accountUrl' => $accountUrl,
            'siteDomain' => $siteDomain,
            'siteName' => $siteName,
        ];

        try {
            $this->mailer->send(
                (new Email())
                    ->from($from)
                    ->replyTo(new Address($this->mailFrom, $senderName ?: $siteDomain))
                    ->sender($this->mailFrom)
                    ->to($recipientEmail)
                    ->subject($subject)
                    ->text($this->twig->render('emails/practice_enrollment.txt.twig', $context))
                    ->html($this->twig->render('emails/practice_enrollment.html.twig', $context)),
            );

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send practice enrollment email.', [
                'inquiry_id' => $inquiry->getId(),
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
