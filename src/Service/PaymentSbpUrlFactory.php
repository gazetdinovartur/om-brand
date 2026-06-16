<?php

namespace App\Service;

use App\Entity\PaymentOffer;
use App\Repository\SiteSettingsRepository;

final class PaymentSbpUrlFactory
{
    public function __construct(
        private readonly SiteSettingsRepository $siteSettingsRepository,
        private readonly string $envTemplate = '',
    ) {
    }

    public function build(PaymentOffer $offer): ?string
    {
        $template = trim($this->siteSettingsRepository->getSettings()->getSbpPaymentUrlTemplate() ?? '')
            ?: trim($this->envTemplate);

        if ('' === $template) {
            return null;
        }

        return strtr($template, [
            '{amount}' => (string) $offer->getAmount(),
            '{amount_rubles}' => (string) (int) round($offer->getAmountRubles()),
            '{token}' => $offer->getToken(),
            '{title}' => rawurlencode($offer->getTitle()),
            '{id}' => (string) ($offer->getId() ?? ''),
        ]);
    }
}
