<?php

namespace App\Service;

use App\Entity\Inquiry;
use App\Entity\SiteSettings;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class InquiryMailNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(MAILER_DSN)%')]
        private readonly string $mailerDsn = '',
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->mailerDsn && !str_starts_with($this->mailerDsn, 'null://');
    }

    public function notifyNewInquiry(Inquiry $inquiry, SiteSettings $settings): bool
    {
        $recipient = $settings->getNotificationEmail();
        if (!$this->isConfigured() || null === $recipient || '' === trim($recipient)) {
            return false;
        }

        $adminUrl = $this->urlGenerator->generate(
            'admin',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ).'?crudAction=detail&crudControllerFqcn=App%5CAdmin%5CInquiryCrudController&entityId='.$inquiry->getId();

        $message = trim($inquiry->getMessage());
        $messageLine = '' !== $message ? $message : '— без описания';

        $body = sprintf(
            "Новая заявка #%d\n\nИмя: %s\nКонтакт (%s): %s\nТип запроса: %s\n\n%s%s\n\nОткрыть в админке:\n%s",
            $inquiry->getId(),
            $inquiry->getName(),
            $inquiry->getContactType()->label(),
            $inquiry->getContact(),
            $inquiry->getInquiryType()->label(),
            $messageLine,
            $inquiry->hasAttachment() ? "\n\nВложение: ".$inquiry->getAttachmentOriginalName() : '',
            $adminUrl,
        );

        try {
            $this->mailer->send(
                (new Email())
                    ->from($recipient)
                    ->to($recipient)
                    ->subject(sprintf('Новая заявка #%d · %s', $inquiry->getId(), $inquiry->getName()))
                    ->text($body),
            );

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send inquiry email.', [
                'inquiry_id' => $inquiry->getId(),
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
