<?php

declare(strict_types=1);

namespace App\Enum;

enum PracticeSessionKind: string
{
    case Live = 'live';
    case Speak = 'speak';

    public function label(): string
    {
        return match ($this) {
            self::Live => 'Эфир (Live)',
            self::Speak => 'Встреча (Speak)',
        };
    }
}
