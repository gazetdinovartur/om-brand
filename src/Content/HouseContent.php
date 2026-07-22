<?php

namespace App\Content;

/**
 * Сайт-экосистема: навигация, комнаты, копирайт связи и SEO главной.
 */
final class HouseContent
{
    public static function heroGreeting(): string
    {
        return 'Привет, меня зовут Артур';
    }

    public static function heroLead(): string
    {
        return 'Сейчас ты здесь - на месте входа в мою цифровую экосистему. Так я организую среду в интернете и приглашаю тебя. К соавторству, партнёрству, сотрудничеству. Или просто побыть - сделать выдох и вдох';
    }

    public static function metaTitle(?string $personName = null): string
    {
        $name = $personName ?? LandingContent::personName();

        return sprintf('%s — цифровая экосистема', $name);
    }

    public static function metaDescription(): string
    {
        return 'Цифровая экосистема Артура Газетдинова: разработка, кейсы, хроника, лаборатория, музыка и связь. Приглашение к соавторству, партнёрству, сотрудничеству — или просто побыть здесь.';
    }

    /** @return list<string> */
    public static function metaKeywords(): array
    {
        return [
            'Артур Газетдинов',
            'Лун',
            'личный бренд',
            'сайт-экосистема',
            'соавторство',
            'партнёрство',
            'сотрудничество',
            'разработка веб-систем',
            'кейсы',
            'хроника',
            'sacred geometry lab',
        ];
    }

    public static function contactPageTitle(): string
    {
        return 'Связь';
    }

    public static function contactPageLead(): string
    {
        return 'Можно написать сюда — о соавторстве, партнёрстве, сотрудничестве, вопросе или просто чтобы выйти на связь. Если нужна разработка системы — удобнее форма на странице разработки, но и здесь можно начать.';
    }

    public static function contactFormTitle(): string
    {
        return 'Напишите мне';
    }

    public static function contactFormSubtitle(): string
    {
        return 'Соавторство, партнёрство, сотрудничество, вопрос — или просто поздороваться';
    }

    public static function contactHowTitle(): string
    {
        return 'Как связаться';
    }

    public static function contactHowBody(): string
    {
        return 'Форма ниже — общий порог. Telegram — если удобнее коротко и быстро. Для заказа разработки можно сразу зайти в мастерскую /dev--null.';
    }

    /**
     * Пункты меню внутренних страниц. Routes resolved in TwigSiteGlobalsProvider.
     *
     * @return list<array{route: string, label: string}>
     */
    public static function navigationItems(): array
    {
        return [
            ['route' => 'web_dev_landing', 'label' => 'Разработка'],
            ['route' => 'web_cases', 'label' => 'Кейсы'],
            ['route' => 'web_chronicle', 'label' => 'Хроника', 'persistFilters' => true],
            ['route' => 'web_contact', 'label' => 'Связь'],
        ];
    }

    /**
     * Комнаты экосистемы — пороги входа.
     *
     * @return list<array{
     *     id: string,
     *     label: string,
     *     invite: string,
     *     route?: string,
     *     routeParams?: array<string, string>,
     *     external?: string
     * }>
     */
    public static function mapRooms(): array
    {
        return [
            [
                'id' => 'dev',
                'label' => 'Разработка',
                'invite' => 'Мастерская, где собираю системы',
                'route' => 'web_dev_landing',
            ],
            [
                'id' => 'cases',
                'label' => 'Кейсы',
                'invite' => 'Истории проектов — как думал и чем закончилось',
                'route' => 'web_cases',
            ],
            [
                'id' => 'chronicle',
                'label' => 'Хроника',
                'invite' => 'Тексты и фото по эпохам',
                'route' => 'web_chronicle',
            ],
            [
                'id' => 'lab',
                'label' => 'Лаборатория',
                'invite' => 'Приоткрываю, когда готово',
                'external' => 'https://lab.arturlun.ru',
            ],
            [
                'id' => 'music',
                'label' => 'Музыка',
                'invite' => 'Другой дом — music.arturlun',
                'external' => 'https://music.arturlun.ru',
            ],
            [
                'id' => 'contact',
                'label' => 'Связь',
                'invite' => 'Порог, за которым разговор',
                'route' => 'web_contact',
            ],
        ];
    }
}
