<?php

namespace App\Service;

use App\Entity\Inquiry;
use Psr\Log\LoggerInterface;

final class InquiryNotifier
{
    public function __construct(
        private readonly TelegramNotifier $telegramNotifier,
        private readonly InquiryMailNotifier $mailNotifier,
        private readonly PublicSiteContext $siteContext,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notifyNewInquiry(Inquiry $inquiry): void
    {
        $settings = $this->siteContext->getSettings();
        $telegramSent = $this->telegramNotifier->notifyNewInquiry($inquiry);

        if (!$telegramSent && !$this->mailNotifier->notifyNewInquiry($inquiry, $settings)) {
            $this->logger->warning('Inquiry saved but no notification channel delivered the message.', [
                'inquiry_id' => $inquiry->getId(),
            ]);
        }
    }
}
