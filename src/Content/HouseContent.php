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
        return 'Сейчас ты на входе в мою цифровую экосистему. Так я организую среду в интернете и приглашаю тебя. К соавторству, сотрудничеству или просто побыть - сделать выдох и вдох';
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
        return 'Напишите о сотрудничестве, вопросе, приглашении — или просто поздороваться. Заказ разработки удобнее через форму на странице разработки.';
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
                'invite' => 'Информация обо мне как разработчике web систем',
                'route' => 'web_dev_landing',
            ],
            [
                'id' => 'cases',
                'label' => 'Кейсы',
                'invite' => 'Примеры моих работ в сферах web dev, ux, ia',
                'route' => 'web_cases',
            ],
            [
                'id' => 'chronicle',
                'label' => 'Хроника',
                'invite' => 'Мои тексты и фото из разных эпох',
                'route' => 'web_chronicle',
            ],
            [
                'id' => 'lab',
                'label' => 'Лаборатория',
                'invite' => 'Созерцательная практика со звуком и геометрией',
                'external' => 'https://lab.arturlun.ru',
            ],
            [
                'id' => 'music',
                'label' => 'Музыка',
                'invite' => 'Свободная музыкальная площадка и встраиваемый плеер',
                'external' => 'https://music.arturlun.ru',
            ],
            [
                'id' => 'contact',
                'label' => 'Связь',
                'invite' => 'Здесь можно написать мне',
                'route' => 'web_contact',
            ],
        ];
    }
}
