<?php

namespace App\Enum;

enum ContentBlockType: string
{
    case Hero = 'hero';
    case Text = 'text';
    case List = 'list';
    case Cards = 'cards';
    case Steps = 'steps';
    case Form = 'form';
    case Footer = 'footer';

    public function label(): string
    {
        return match ($this) {
            self::Hero => 'Hero',
            self::Text => 'Текст',
            self::List => 'Список',
            self::Cards => 'Карточки',
            self::Steps => 'Шаги',
            self::Form => 'Форма',
            self::Footer => 'Футер',
        };
    }
}
