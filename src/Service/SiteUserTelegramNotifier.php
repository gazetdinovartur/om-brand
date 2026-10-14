<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PaymentOffer;
use App\Entity\SiteUser;
use App\Practice\PracticeFormat;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Личные сообщения пользователю в Telegram (не путать с admin TelegramNotifier).
 */
final class SiteUserTelegramNotifier
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(TELEGRAM_BOT_TOKEN)%')]
        private readonly string $botToken = '',
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->botToken);
    }

    public function notifyEnrollment(
        SiteUser $user,
        PracticeFormat $format,
        ?PaymentOffer $offer = null,
    ): bool {
        $chatId = trim((string) $user->getTelegramId());
        if ('' === $chatId || !$this->isConfigured()) {
            return false;
        }

        $accountUrl = $this->urlGenerator->generate(
            'web_account',
            ['tab' => 'practice'],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        if ($format->requiresPayment() && null !== $offer) {
            $payUrl = trim((string) $offer->getPaymentUrl());
            $text = sprintf(
                "Заявка на практику принята\n%s — %s ₽\n\nОплатить:\n%s\n\nСвоё место:\n%s",
                $offer->getTitle(),
                number_format($offer->getAmountRubles(), 0, ',', ' '),
                '' !== $payUrl ? $payUrl : $accountUrl,
                $accountUrl,
            );
        } else {
            $text = sprintf(
                "Заявка на первую встречу принята.\nДоступ в комнату практики — в Своём месте:\n%s",
                $accountUrl,
            );
        }

        return $this->send($chatId, $text);
    }

    public function notifyPaid(SiteUser $user, PaymentOffer $offer): bool
    {
        $chatId = trim((string) $user->getTelegramId());
        if ('' === $chatId || !$this->isConfigured()) {
            return false;
        }

        $roomUrl = $this->urlGenerator->generate('web_practice_room', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $accountUrl = $this->urlGenerator->generate(
            'web_account',
            ['tab' => 'practice'],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $text = sprintf(
            "Оплата получена\n%s — %s ₽\n\nКомната практики:\n%s\n\nСвоё место:\n%s",
            $offer->getTitle(),
            number_format($offer->getAmountRubles(), 0, ',', ' '),
            $roomUrl,
            $accountUrl,
        );

        return $this->send($chatId, $text);
    }

    private function send(string $chatId, string $text): bool
    {
        try {
            $this->httpClient->request('POST', sprintf('https://api.telegram.org/bot%s/sendMessage', $this->botToken), [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ],
            ]);

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send Telegram DM to site user.', [
                'chat_id' => $chatId,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
