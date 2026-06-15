<?php

namespace App\Enum;

enum PaymentOfferStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Ожидает оплаты',
            self::Paid => 'Оплачено',
            self::Cancelled => 'Отменено',
            self::Expired => 'Истекло',
        };
    }
}
