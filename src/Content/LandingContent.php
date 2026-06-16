<?php

namespace App\Content;

use App\Enum\ContentBlockType;

final class LandingContent
{
    /** Текст в hero — от первого лица, один раз на экране. */
    public static function heroLead(): string
    {
        return 'Занимаюсь разработкой прикладных веб-систем для бизнеса, проектов и частных специалистов';
    }

    /** Кратко для meta description и админки, не дублирует hero. */
    public static function metaTagline(): string
    {
        return 'Прикладные веб-системы для бизнеса, проектов и частных специалистов';
    }

    /** Заголовок вкладки и Open Graph. */
    public static function metaTitle(?string $personName = null): string
    {
        $name = $personName ?? self::personName();

        return sprintf('%s — %s', $name, self::headerSubtitle());
    }

    /** Meta description: чуть информативнее tagline для сниппета. */
    public static function metaDescription(): string
    {
        return self::metaTagline().'. Консультации, проектирование и разработка веб-систем под вашу задачу.';
    }

    /** @return list<string> */
    public static function metaKeywords(): array
    {
        return [
            'разработчик веб-систем',
            'веб-разработка',
            'автоматизация бизнеса',
            'разработка сайта',
            'личный кабинет',
            'интернет-магазин',
            'интеграции',
            'Екатеринбург',
        ];
    }

    /** @return list<string> */
    public static function knowsAbout(): array
    {
        return [
            'Веб-разработка',
            'Прикладные веб-системы',
            'Автоматизация бизнес-процессов',
            'UX проектирование',
            'PHP',
            'Symfony',
        ];
    }

    /** @return list<string> */
    public static function serviceTypes(): array
    {
        return [
            'Разработка веб-систем',
            'Консультации по проектам',
            'Аудит веб-сервисов',
            'Техническое сопровождение',
        ];
    }

    public static function serviceName(?string $personName = null): string
    {
        return sprintf('%s — разработка веб-систем', $personName ?? self::personName());
    }

    public static function areaServed(): string
    {
        return 'RU';
    }

    /** Подпись под именем в шапке сайта. */
    public static function headerSubtitle(): string
    {
        return 'разработчик веб-систем';
    }

    /** Имя на сайте: шапка, hero, title вкладки. */
    public static function personName(): string
    {
        return 'Артур Газетдинов';
    }

    /** Неформальное имя для подписи в футере. */
    public static function alsoKnownAs(): string
    {
        return 'Лун';
    }

    /**
     * @return list<array{href: string, label: string}>
     */
    public static function navigationAnchors(bool $hasCases = false): array
    {
        $items = [
            ['href' => '#approach', 'label' => 'Подход'],
            ['href' => '#services', 'label' => 'Решения'],
            ['href' => '#process', 'label' => 'Процесс'],
            ['href' => '#formats', 'label' => 'Форматы'],
        ];

        if ($hasCases) {
            $items[] = ['href' => '#cases', 'label' => 'Кейсы'];
        }

        $items[] = ['href' => '#contact', 'label' => 'Контакт'];

        return $items;
    }

    /** @return list<array{slug: string, type: ContentBlockType, title: string, subtitle: ?string, body: ?string, items: ?array<int, array<string, string>>, sortOrder: int}> */
    public static function blocks(): array
    {
        return [
            [
                'slug' => 'hero',
                'type' => ContentBlockType::Hero,
                'title' => self::personName(),
                'subtitle' => self::headerSubtitle(),
                'body' => self::heroLead(),
                'items' => null,
                'sortOrder' => 10,
            ],
            [
                'slug' => 'audience',
                'type' => ContentBlockType::Text,
                'title' => 'Для кого',
                'subtitle' => null,
                'body' => 'Для тех, кто занимается своим делом или планирует начать. Для организаторов мероприятий, мастеров, специалистов, предпринимателей, самозанятых и небольших команд. Для тех, кто ценит своё время и внимание. Для тех, кто хочет больше светлых моментов в своём ежедневном труде',
                'items' => null,
                'sortOrder' => 20,
            ],
            [
                'slug' => 'pains',
                'type' => ContentBlockType::List,
                'title' => 'Возможно, вам ко мне, если…',
                'subtitle' => '',
                'body' => null,
                'items' => [
                    ['text' => 'Заявки приходят из разных мест и постоянно теряются'],
                    ['text' => 'Клиенты забывают оплатить, а информация о платежах рассеивается'],
                    ['text' => 'Участники мероприятия постоянно задают одни и те же вопросы'],
                    ['text' => 'Много времени уходит на ручную работу'],
                    ['text' => 'Сотрудники или участники проекта пользуются десятком разных таблиц и чатов'],
                    ['text' => 'Приходится копировать информацию из одного сервиса в другой'],
                    ['text' => 'У вас есть идея проекта, но непонятно, как её реализовать технически'],
                    ['text' => 'Вы хотите освободить время для своей основной работы, передав рутину системе'],
                ],
                'sortOrder' => 30,
            ],
            [
                'slug' => 'specialization',
                'type' => ContentBlockType::Text,
                'title' => 'Мой подход',
                'subtitle' => null,
                'body' => "Сначала я разбираю, как человек проходит путь от задачи до результата. Потом проектирую интерфейс — и только после этого пишу код\n\nТак получается инструмент, которым можно пользоваться с первого раза — без инструкций, обучения и звонков «куда нажать». Даже если вы далеки от IT",
                'items' => null,
                'sortOrder' => 40,
            ],
            [
                'slug' => 'services',
                'type' => ContentBlockType::Cards,
                'title' => 'Что это может быть',
                'subtitle' => null,
                'body' => null,
                'items' => [
                    ['title' => 'Сайт и магазин', 'text' => 'лендинг, витрина или интернет-магазин'],
                    ['title' => 'Заявки и запись', 'text' => 'приём заявок и онлайн-запись'],
                    ['title' => 'Личный кабинет', 'text' => 'для клиентов и сотрудников'],
                    ['title' => 'Обучение', 'text' => 'платформа для курсов и образовательных проектов'],
                    ['title' => 'Мероприятия', 'text' => 'продажа билетов и регистрация участников'],
                    ['title' => 'Интеграции', 'text' => 'платёжные системы, vk/tg боты, ai-ассистенты'],
                    ['title' => 'API', 'text' => 'для мобильного приложения'],
                ],
                'sortOrder' => 50,
            ],
            [
                'slug' => 'philosophy',
                'type' => ContentBlockType::Text,
                'title' => 'Своё решение',
                'subtitle' => null,
                'body' => 'Если готового решения не существует или оно неудобно для вашей работы — разработаем собственное. Индивидуальное, созданное под вашу задачу. В таких проектах сейчас моя главная заинтересованность — найти ваш способ работы и сделать инструмент, который действительно высвобождает внимание',
                'items' => null,
                'sortOrder' => 60,
            ],
            [
                'slug' => 'process',
                'type' => ContentBlockType::Cards,
                'title' => 'Что я предлагаю',
                'subtitle' => null,
                'body' => null,
                'items' => [
                    ['title' => 'Контакт', 'text' => 'Услышу ваши потребности, затруднения и уточню стоящие перед вами задачи'],
                    ['title' => 'Возможности', 'text' => 'Предложу несколько вариантов решения и помогу выбрать тот, который действительно подходит под ваши цели, сроки и ресурсы'],
                    ['title' => 'Реализацию', 'text' => 'Спроектирую и разработаю необходимый инструмент для работы через интернет'],
                    ['title' => 'Поддержку', 'text' => 'Останусь на связи и помогу развивать проект по мере появления новых задач'],
                ],
                'sortOrder' => 70,
            ],
            [
                'slug' => 'work_formats',
                'type' => ContentBlockType::Cards,
                'title' => 'Формы взаимодействия со мной',
                'subtitle' => null,
                'body' => null,
                'items' => [
                    ['title' => 'Консультация', 'text' => 'разовая консультация по проекту или идее'],
                    ['title' => 'Аудит', 'text' => 'существующего сайта или веб-сервиса'],
                    ['title' => 'Проектирование', 'text' => 'решения под вашу задачу'],
                    ['title' => 'Разработка', 'text' => 'нового продукта с нуля'],
                    ['title' => 'Развитие', 'text' => 'доработка и развитие существующих проектов'],
                    ['title' => 'Сопровождение', 'text' => 'поддержка, администрирование и техническое сопровождение'],
                    ['title' => 'В команде', 'text' => 'работа напрямую с заказчиком или в составе небольшой команды'],
                ],
                'sortOrder' => 80,
            ],
            [
                'slug' => 'form_intro',
                'type' => ContentBlockType::Form,
                'title' => 'Расскажите о задаче',
                'subtitle' => 'Можно просто прийти с задачей, идеей или затруднением',
                'body' => 'Если вы не уверены, нужен ли вам сайт, CRM, бот или что-то ещё — это нормально. Вместе разберёмся, что именно происходит сейчас, какие есть варианты решения и нужен ли вообще новый инструмент',
                'items' => null,
                'sortOrder' => 90,
            ],
            [
                'slug' => 'footer_legal',
                'type' => ContentBlockType::Footer,
                'title' => 'Как работаю',
                'subtitle' => null,
                'body' => 'Работаю как самозанятый. После оплаты формирую чек через «Мой налог». При необходимости можем зафиксировать договорённости в договоре или техническом задании.',
                'items' => null,
                'sortOrder' => 100,
            ],
            [
                'slug' => 'footer_excludes',
                'type' => ContentBlockType::Footer,
                'title' => 'Что не предлагаю',
                'subtitle' => null,
                'body' => null,
                'items' => [
                    ['text' => 'продвижение, реклама и маркетинг'],
                    ['text' => 'SEO и закупка рекламы'],
                    ['text' => 'брендинг и разработка фирменного стиля'],
                    ['text' => 'профессиональный дизайн и создание визуальной айдентики'],
                    ['text' => 'фото- и видеопроизводство'],
                    ['text' => 'решение юридических и бухгалтерских вопросов'],
                    ['text' => 'написание бизнес-планов и финансовых моделей'],
                ],
                'sortOrder' => 110,
            ],
        ];
    }
}
