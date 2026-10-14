<?php

namespace App\Practice;

/**
 * Форматы участия в регулярной практике.
 */
enum PracticeFormat: string
{
    case First = 'first';
    case Single = 'single';
    case Cycle = 'cycle';

    public function label(): string
    {
        return match ($this) {
            self::First => 'Первая встреча — вход свободный',
            self::Single => 'Разовая встреча — 222 ₽',
            self::Cycle => 'Цикл из 4 встреч — 555 ₽',
        };
    }

    public function paymentTitle(): ?string
    {
        return match ($this) {
            self::First => null,
            self::Single => 'Регулярная практика — разовая встреча',
            self::Cycle => 'Регулярная практика — цикл из 4 встреч',
        };
    }

    /** Сумма в копейках; null — без оплаты. */
    public function amountKopecks(): ?int
    {
        return match ($this) {
            self::First => null,
            self::Single => 222_00,
            self::Cycle => 555_00,
        };
    }

    public function requiresPayment(): bool
    {
        return null !== $this->amountKopecks();
    }

    /** Сколько встреч даёт формат при оплате / входе. */
    public function sessionsCount(): int
    {
        return match ($this) {
            self::First, self::Single => 1,
            self::Cycle => 4,
        };
    }
}
