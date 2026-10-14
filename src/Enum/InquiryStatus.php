<?php

namespace App\Enum;

enum InquiryStatus: string
{
    case New = 'new';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Новая',
            self::Done => 'Закрыта',
        };
    }
}
