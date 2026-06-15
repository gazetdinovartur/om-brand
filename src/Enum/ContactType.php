<?php

namespace App\Enum;

enum ContactType: string
{
    case Telegram = 'telegram';
    case Vk = 'vk';
    case Phone = 'phone';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Telegram => 'Telegram',
            self::Vk => 'ВКонтакте',
            self::Phone => 'Телефон',
            self::Email => 'Email',
        };
    }
}
