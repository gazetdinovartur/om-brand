<?php

namespace App\Enum;

enum InquiryStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Новая',
            self::InProgress => 'В работе',
            self::Done => 'Завершена',
            self::Archived => 'В архиве',
        };
    }
}
