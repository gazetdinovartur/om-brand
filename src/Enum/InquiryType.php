<?php

namespace App\Enum;

enum InquiryType: string
{
    case Consultation = 'consultation';
    case Audit = 'audit';
    case Design = 'design';
    case Development = 'development';
    case Evolution = 'evolution';
    case Support = 'support';
    case Unsure = 'unsure';

    public function label(): string
    {
        return match ($this) {
            self::Consultation => 'Консультация',
            self::Audit => 'Аудит',
            self::Design => 'Проектирование',
            self::Development => 'Разработка',
            self::Evolution => 'Развитие',
            self::Support => 'Сопровождение',
            self::Unsure => 'Помогите разобраться',
        };
    }

    /** @return list<self> */
    public static function ordered(): array
    {
        return [
            self::Consultation,
            self::Audit,
            self::Design,
            self::Development,
            self::Evolution,
            self::Support,
            self::Unsure,
        ];
    }
}
