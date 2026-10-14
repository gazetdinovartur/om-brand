<?php

namespace App\Infrastructure\Yookassa\Dto;

readonly class CreatePaymentResult
{
    public function __construct(
        public string $paymentId,
        public string $gatewayUrl,
    ) {
    }
}
