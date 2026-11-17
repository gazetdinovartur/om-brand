<?php

namespace App\Content;

/**
 * Страница «Регуляция и регулярность» — тексты для согласования и публикации.
 */
final class PracticeContent
{
    public static function pageTitle(): string
    {
        return 'Регуляция и регулярность';
    }

    public static function metaDescription(): string
    {
        return 'Онлайн-встречи, где мы вместе направляем внимание: устойчивый ритм регуляции нервной системы и культура отслеживания себя';
    }

    /** @return list<string> */
    public static function metaKeywords(): array
    {
        return [
            'регуляция',
            'регулярность',
            'практика внимания',
            'сканирование тела',
            'соматические практики',
            'медитация онлайн',
            'Артур Газетдинов',
        ];
    }

    public static function heroLead(): string
    {
        return 'Практика внимания и тела вместе. Онлайн';
    }

    public static function heroBody(): string
    {
        return 'Время, чтобы жить себя';
    }

    public static function heroShare(): string
    {
        return '';
    }

    public static function whyTitle(): string
    {
        return 'Зачем';
    }

    public static function whyLead(): string
    {
        return 'Цель встреч — создать устойчивый паттерн регуляции нервной системы и культуру отслеживания себя';
    }

    public static function whyEffect(): string
    {
        return 'Из этого часто вырастает ощущение большей безопасности: повышается доверие к себе и к миру';
    }

    public static function forWhomTitle(): string
    {
        return 'Для кого';
    }

    /**
     * Признаки, по которым человек может себя узнать.
     *
     * @return list<string>
     */
    public static function forWhom(): array
    {
        return [
            'когда сложно замедлиться или невозможно остановиться',
            'когда есть сжатие и сгущение — будто перегородка или стекло отделяет от себя и от того, что происходит',
            'когда рядом оттенки осуждения или стыда',
            'когда в горле ощущение кома или в затылке — жёсткость',
        ];
    }

    /**
     * Пары «я держу — ты действуешь».
     *
     * @return list<array{me: string, you: string}>
     */
    public static function whyRoles(): array
    {
        return [
            [
                'me' => 'Я помогаю организовать время для себя',
                'you' => 'Ты приходишь и берёшь это время — без задачи «успеть», просто быть с собой',
            ],
            [
                'me' => 'Я держу регулярность и ритм',
                'you' => 'Ты выбираешь, насколько в него встраиваться: прийти, пропустить, вернуться',
            ],
            [
                'me' => 'Я создаю контейнер — времени и процесса',
                'you' => 'Ты внутри него свободен замечать то, что важно именно тебе, и решать, насколько глубоко идти',
            ],
        ];
    }

    public static function howTitle(): string
    {
        return 'Как устроено';
    }

    public static function howBody(): string
    {
        return 'Тридцать минут вместе. Трансляция на сайте. Обратная связь — в чате на сайте';
    }

    public static function howNote(): string
    {
        return 'Не нужно ничего особенного готовить. Достаточно прийти и быть с тем, что есть сейчас';
    }

    public static function howFeedback(): string
    {
        return 'Я ориентируюсь на ваши процессы и на обратную связь в чате. Так вы можете влиять на тему следующей практики';
    }

    public static function practicesTitle(): string
    {
        return 'Что бывает на встречах';
    }

    public static function practicesLead(): string
    {
        return 'На встречах могут быть разные входы в одно и то же — внимание к телу, дыханию, движению, письму';
    }

    /** @return list<string> */
    public static function practices(): array
    {
        return [
            'сканирование тела',
            'дыхание 4–7–8 и квадратное',
            'письменная практика',
            'соматическая медитация',
            'ходьба, прогулка, деконцентрация',
            'тряска',
        ];
    }

    public static function mapTitle(): string
    {
        return 'Карта и местность';
    }

    public static function mapBody(): string
    {
        return 'Одна из практик сканирования тела. Карта — это образ тела, к которому мы обращаемся: вслух называем части и размещаем в них внимание. Местность — то, что в действительности происходит прямо сейчас. Не нужно ничего визуализировать. Скорее — замечать, что уже есть, и относиться к себе доброжелательно';
    }

    public static function joinTitle(): string
    {
        return 'Участие';
    }

    /**
     * @return list<array{title: string, text: string, price: string}>
     */
    public static function pricing(): array
    {
        return [
            [
                'title' => 'Первая встреча',
                'text' => 'чтобы попробовать формат и понять, подходит ли ритм',
                'price' => 'вход свободный',
            ],
            [
                'title' => 'Разово',
                'text' => 'один шанс — одна встреча',
                'price' => '222 ₽',
            ],
            [
                'title' => 'Цикл из 4 встреч',
                'text' => 'усиление через ритм',
                'price' => '555 ₽',
            ],
        ];
    }

    public static function pricingNote(): string
    {
        return 'Стоимость символическая: она отмечает участие и помогает держать ритм';
    }

    public static function scheduleTitle(): string
    {
        return 'Расписание';
    }

    /** Пустой список: нет live/scheduled сессий в админке. */
    public static function scheduleBody(): string
    {
        return 'Ближайшие встречи появятся здесь, когда будет назначена сессия.';
    }

    public static function enterTitle(): string
    {
        return 'Как войти';
    }

    public static function enterBody(): string
    {
        return 'Оставь заявку с email. Сразу создаётся заказ в твоём личном кабинете. Для платного формата в письме будет ссылка на оплату (ЮKassa). После оплаты откроется комната практики — трансляция и чат';
    }

    public static function enterAccount(): string
    {
        return 'В письме — вход в Своё место. Там вкладка Практика: заказы, оплата и доступ в комнату';
    }

    public static function aboutTitle(): string
    {
        return 'Обо мне';
    }

    public static function aboutLead(): string
    {
        return 'Исследую внимание через тело и движение. Практикую и передаю то, что помогает быть автором своей жизни и проживать её в контексте большего';
    }

    /** @return list<string> */
    public static function aboutFacts(): array
    {
        return [
            'с 2015–2019 практиковал хатха-йогу, из них три года аштанга-виньясу',
            'с 2020 танцую контактную импровизацию и нахожусь в поле соматических и письменных практик; проживаю свой дизайн',
            'с 2021 нахожусь в личной терапии',
            'с 2022 — медитирую и исследую голос',
            '2023–2024 — слушатель программы «Соматическая психология» с Александрой Безродновой и Энни Брук',
            'с 2024 — практикую деконцентрацию и колесо года, продолжаю движение',
        ];
    }

    public static function aboutLikes(): string
    {
        return 'Из любимого сейчас: ходьба по городу, лесу и горам; плавание и пение. В последнее время — воркаут: брусья и турнички';
    }

    public static function inviteTitle(): string
    {
        return 'Приглашение';
    }

    public static function inviteBody(): string
    {
        return 'Если откликается — приходи на первую встречу. Она бесплатная: можно попробовать формат и понять, подходит ли ритм';
    }

    public static function formTitle(): string
    {
        return 'Заявка';
    }

    public static function formLead(): string
    {
        return 'Имя, email и формат участия. На email придёт письмо — оплата (если нужна) и вход в своё место';
    }

    public static function roomLabel(): string
    {
        return 'Практика';
    }

    public static function roomInvite(): string
    {
        return 'Регуляция и регулярность — встречи внимания';
    }

    public static function navLabel(): string
    {
        return 'Практика';
    }
}
