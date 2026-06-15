<?php

namespace App\Enum;

enum ContactType: string
{
    case Telegram = 'telegram';
    case Phone = 'phone';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Telegram => 'Telegram',
            self::Phone => 'Телефон',
            self::Email => 'Email',
        };
    }
}
