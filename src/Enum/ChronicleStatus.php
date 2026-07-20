<?php

namespace App\Enum;

enum ChronicleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Published => 'Опубликован',
            self::Scheduled => 'Запланирован',
            self::Archived => 'В архиве',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'chronicle-badge--draft',
            self::Published => 'chronicle-badge--published',
            self::Scheduled => 'chronicle-badge--scheduled',
            self::Archived => 'chronicle-badge--archived',
        };
    }
}
