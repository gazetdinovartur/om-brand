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
    case Collaboration = 'collaboration';
    case Question = 'question';
    case Invitation = 'invitation';
    case Other = 'other';

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
            self::Collaboration => 'Сотрудничество',
            self::Question => 'Вопрос',
            self::Invitation => 'Приглашение',
            self::Other => 'Другое',
        };
    }

    /** Типы для формы заказа разработки на лендинге. @return list<self> */
    public static function developmentOrdered(): array
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

    /** Типы для универсальной формы связи. @return list<self> */
    public static function contactOrdered(): array
    {
        return [
            self::Collaboration,
            self::Question,
            self::Invitation,
            self::Other,
        ];
    }

    /** @return list<self> */
    public static function ordered(): array
    {
        return self::developmentOrdered();
    }

    /** Все типы для админки. @return list<self> */
    public static function allOrdered(): array
    {
        return [
            ...self::developmentOrdered(),
            self::Collaboration,
            self::Question,
            self::Invitation,
            self::Other,
        ];
    }
}
