<?php

namespace App\Twig;

use App\Entity\PaymentOffer;
use App\Service\PaymentOfferService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PaymentAdminExtension extends AbstractExtension
{
    public function __construct(
        private readonly PaymentOfferService $paymentOfferService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('payment_client_url', $this->getClientUrl(...)),
        ];
    }

    public function getClientUrl(PaymentOffer $offer): string
    {
        return $this->paymentOfferService->getClientUrl($offer);
    }
}
