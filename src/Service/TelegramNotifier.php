<?php

namespace App\Service;

use App\Entity\Inquiry;
use App\Entity\PaymentOffer;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramNotifier
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(TELEGRAM_BOT_TOKEN)%')]
        private readonly ?string $botToken = null,
        #[Autowire('%env(TELEGRAM_CHAT_ID)%')]
        private readonly ?string $chatId = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== ($this->botToken ?? '') && '' !== ($this->chatId ?? '');
    }

    public function notifyNewInquiry(Inquiry $inquiry): void
    {
        if (!$this->isConfigured()) {
            $this->logger->info('Telegram not configured, skipping inquiry notification.', [
                'inquiry_id' => $inquiry->getId(),
            ]);

            return;
        }

        $adminUrl = $this->urlGenerator->generate(
            'admin',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        ).'?crudAction=detail&crudControllerFqcn=App%5CAdmin%5CInquiryCrudController&entityId='.$inquiry->getId();

        $attachmentLine = $inquiry->hasAttachment()
            ? "\n📎 Файл: ".$inquiry->getAttachmentOriginalName()
            : '';

        $text = sprintf(
            "🆕 Новая заявка #%d\n\n👤 %s\n📬 %s: %s\n🏷 %s\n\n%s%s\n\n→ %s",
            $inquiry->getId(),
            $inquiry->getName(),
            $inquiry->getContactType()->label(),
            $inquiry->getContact(),
            $inquiry->getInquiryType()->label(),
            $inquiry->getMessage(),
            $attachmentLine,
            $adminUrl,
        );

        $this->sendMessage($text);
    }

    public function notifyPaymentOffer(PaymentOffer $offer, string $paymentUrl): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        $inquiry = $offer->getInquiry();
        $text = sprintf(
            "💳 Ссылка на оплату создана\n\nЗаявка #%d · %s\n%s — %s ₽\n\n→ %s",
            $inquiry?->getId(),
            $inquiry?->getName(),
            $offer->getTitle(),
            number_format($offer->getAmountRubles(), 0, ',', ' '),
            $paymentUrl,
        );

        $this->sendMessage($text);
    }

    private function sendMessage(string $text): void
    {
        try {
            $this->httpClient->request('POST', sprintf('https://api.telegram.org/bot%s/sendMessage', $this->botToken ?? ''), [
                'json' => [
                    'chat_id' => $this->chatId ?? '',
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ],
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send Telegram message.', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
