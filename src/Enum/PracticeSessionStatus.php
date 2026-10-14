<?php

declare(strict_types=1);

namespace App\Enum;

enum PracticeSessionStatus: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Replay = 'replay';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Запланирована',
            self::Live => 'Эфир',
            self::Replay => 'Запись',
            self::Completed => 'Архив',
        };
    }
}
