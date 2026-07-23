<?php

declare(strict_types=1);

namespace App\Content;

/**
 * Корпус модульных кейсов — смысловые области, без фрагментов-дублей.
 *
 * Области:
 * 1. Дом и контент
 * 2. Звук и форма
 * 3. Паттерны студии
 * 4. Живые процессы (заказчику)
 *
 * После seed правки — в админке. Перезапись: app:cases:seed --force
 * Удаление устаревших slug: app:cases:seed --purge-obsolete
 */
final class ModularCasesContent
{
    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return array_merge(
            self::areaHomeContent(),
            self::areaSoundForm(),
            self::areaStudioPatterns(),
            self::areaLiveProcesses(),
        );
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['slug'],
            self::all(),
        );
    }

    /**
     * Область 1 — цифровой дом и контент.
     *
     * @return list<array<string, mixed>>
     */
    public static function areaHomeContent(): array
    {
        return [
            self::entry(
                slug: 'chronicle-from-social',
                area: 'дом и контент',
                sortOrder: 110,
                title: 'Хроника: тексты из соцсетей в одном месте',
                summary: 'Telegram, VK, Instagram — годы письма сходятся в одну хронику с эпохами жизни, тегами, лайками и шерингом. Не архив ради архива, а способ увидеть путь.',
                outcomeLine: 'Можно читать себя сквозь годы — по эпохе, теме, каналу — без прыжков между приложениями.',
                domain: 'контент · хроника · импорт',
                role: 'идея, продукт, разработка',
                storyHook: 'Тексты жили в пяти мессенджерах. Хронология — в голове. Нужно было место, где они дышат вместе — и читаются как жизнь, а не как RSS.',
                storyBody: <<<'TEXT'
Я писал годами — в Telegram, VK, Instagram. Разные каналы, разные аудитории, один путь. Чтобы прочитать себя целиком, приходилось прыгать между приложениями и держать карту в памяти. Календарная лента удобна машине: «2023» не говорит, где я жил. А «Рассветная» или «Коммуна» — говорят сразу.

Собрал на своём сайте хронику. Экспорты Telegram (Да и Да, research, culture, mirror, om), VK и Instagram сходятся в один корпус. Эпохи — Щорса, Рассветная, Коммуна — из catalog.json, с вложенностью и авто-присвоением при импорте. Комментарии становятся продолжениями постов. Есть editorial «избранное», фильтры, load more. Лайк без регистрации, шеринг через Web Share, счётчики из старых реакций VK и Telegram — чтобы прошлое не обнулялось. Импорт идемпотентный.

Тексты остаются verbatim — без правок агента. Это не блог с нуля, а сборка уже прожитого: место, тема и отклик в одном дыхании.
TEXT,
                storyOutcome: 'Есть хроника, в которую можно войти с любой эпохи или темы. Архив читается как карта жизни — не как свалка timestamp’ов.',
                isFeatured: true,
                hasDetailPage: true,
                seoTitle: 'Хроника из соцсетей — кейс · Артур Лун',
                seoDescription: 'Как собрал единую хронику из Telegram, VK и Instagram: эпохи жизни, теги, лайки и фильтры на своём сайте.',
            ),
            self::entry(
                slug: 'digital-home-ecosystem',
                area: 'дом и контент',
                sortOrder: 120,
                title: 'Сайт как дом, а не визитка',
                summary: 'Главная — приглашение в экосистему. Кейсы — не список проектов, а истории решений. Ссылка в мессенджере открывается достойно.',
                outcomeLine: '/ — место побыть; /dev--null — мастерская; /cases — как думаю, а не CV.',
                domain: 'продукт · IA · личный бренд',
                role: 'идея, продукт, разработка',
                storyHook: 'Не хотел лендинг-визитку. Хотел цифровой дом — с комнатами разной глубины и историями, которые показывают мышление, а не только стек.',
                storyBody: <<<'TEXT'
Один сайт должен выдерживать две правды: это моё пространство — и сюда можно прийти за разработкой. Без ощущения, что тебя сразу «продают». Список проектов читается как CV; мне нужно было другое — истории решений.

Перекомпоновал архитектуру. Главная — экосистема: разработка, кейсы, хроника, лаборатория, музыка, связь. /dev--null — мастерская для заказчиков. Две оболочки layout, карта комнат, отдельные формы. /cases — модульные истории: что заинтересовало, как собрал, какую задачу решило. Один проект может дать несколько кейсов. OG, sitemap, JSON-LD — чтобы ссылка в Telegram и поиске выглядела как приглашение, а не пустая карточка.

Посетитель сам выбирает глубину. Две аудитории — два порога, один дом.
TEXT,
                storyOutcome: 'Сайт-экосистема: можно побыть, почитать, заказать разработку. Портфолио читается как способ работы — не как список репозиториев.',
                isFeatured: true,
                showOnLanding: true,
                hasDetailPage: true,
                seoTitle: 'Цифровой дом вместо лендинга — кейс · Артур Лун',
                seoDescription: 'Как собрал сайт-экосистему: комнаты, модульные кейсы и нормальные превью ссылок в мессенджерах.',
            ),
        ];
    }

    /**
     * Область 2 — звук, форма, музыка.
     *
     * @return list<array<string, mixed>>
     */
    public static function areaSoundForm(): array
    {
        return [
            self::entry(
                slug: 'voice-to-geometry',
                area: 'звук и форма',
                sortOrder: 210,
                title: 'Звук становится формой — без домыслов',
                summary: 'Голос и ритм в реальном времени становятся геометрией. Один параметр — один слой. Сессию можно сохранить и унести как видео.',
                outcomeLine: 'Человек видит свой процесс формой — и может унести мандалу или видео сессии.',
                domain: 'исследование · звук · геометрия',
                role: 'идея, продукт, разработка',
                storyHook: 'Нужно было место спросить «как ты сейчас?» — и увидеть ответ, а не очередной визуализатор с домыслами.',
                storyBody: <<<'TEXT'
Мне не хватало честной обратной связи от голоса и ритма — без диагноза, без «ИИ додумал», без красивой ерунды поверх тишины. Скриншота сессии мало: хочется унести время, а не кадр.

Собрал lab.arturlun.ru. Голос и музыка становятся геометрией в реальном времени: режимы «момент» и «процесс», калибровка, один аудиопараметр — один слой. Тишина тоже влияет. Узор можно сохранить. Есть экспорт в видео со звуком — артефакт сессии, не статичная картинка.

Сервис не домысливает — только то, что звучит. Можно привести человека и сказать: побудь здесь.
TEXT,
                storyOutcome: 'Слышимое стало видимым. Лаборатория наблюдения, не диагностика — с формой и видео, которые можно унести.',
                showOnLanding: true,
                isFeatured: true,
                hasDetailPage: true,
                seoTitle: 'Звук в геометрию — кейс · Артур Лун',
                seoDescription: 'Как собрал Sacred Geometry Lab: голос становится формой в реальном времени, сессию можно экспортировать в видео.',
            ),
            self::entry(
                slug: 'embeddable-om-player',
                area: 'звук и форма',
                sortOrder: 220,
                title: 'Своя музыкальная площадка и плеер',
                summary: 'Каталог, встраиваемый <om-player>, очередь без обрывов, метаданные трека на виду. Музыка на своём домене — не только в чужом приложении.',
                outcomeLine: 'Слушаешь на своём сайте: трек, альбом, embed — без чужих правил и обрывов при переходах.',
                domain: 'музыка · web component · UX',
                role: 'продукт, разработка',
                storyHook: 'Своя музыка не должна жить только в чужом приложении — с аккаунтом, лентой и обрывом на каждом клике.',
                storyBody: <<<'TEXT'
Музыка — часть практики. Не хотел, чтобы она существовала только внутри чужой площадки. И не хотел виджет, который глохнет, едва переходишь на другую страницу.

Собрал music.arturlun.ru и Web Component <om-player>: каталог, страница альбома, embed в кейсы и хронику, REST API. Очередь с drag-drop, воспроизведение переживает навигацию, Media Session на lock screen. ID3 — composer, lyrics, preview в админке и панель «О треке»: авторство видно слушателю.

Без регистрации для слушателя. Со своей админкой для меня. Правообладатель — автор.
TEXT,
                storyOutcome: 'Музыка снова на своём домене. Площадка ощущается как продукт: слушать, встраивать, видеть, кто написал.',
                showOnLanding: true,
                hasDetailPage: true,
                presentationMode: 'audio',
                omTrackSlug: 'iz-etogo-mesta',
                audioTitle: 'из этого места',
                presentationIntro: 'Можно послушать прямо здесь — тот же OmPlayer, что на music.arturlun.ru',
                presentationDuration: '~2 мин',
                seoTitle: 'OmPlayer — кейс · Артур Лун',
                seoDescription: 'Как собрал музыкальную площадку: embeddable-плеер, очередь без обрывов и метаданные трека на виду.',
            ),
        ];
    }

    /**
     * Область 3 — паттерны студии.
     *
     * @return list<array<string, mixed>>
     */
    public static function areaStudioPatterns(): array
    {
        return [
            self::entry(
                slug: 'premium-admin-pattern',
                area: 'паттерны студии',
                sortOrder: 310,
                title: 'Админка, которой не стыдно пользоваться',
                summary: 'Русский UI, drag-drop, autosave, batch-загрузка каталога. Эталон студии — переносится между проектами.',
                outcomeLine: 'Один раз выстроил качество — om-player, хроника, магазин, меню получают один язык интерфейса.',
                domain: 'UX · админка · Symfony',
                role: 'продукт, разработка',
                storyHook: 'Стандартная админка функциональна, но не радует. А я в ней живу каждый день — и каталог не заживёт, если загрузка мучительна.',
                storyBody: <<<'TEXT'
Админка — не чердак. Это рабочий стол: контент, статусы, загрузки. Если она раздражает, продукт страдает тихо. Девять альбомов вручную или десятки позиций чая по одному файлу — день, который никто не повторит.

Выстроил эталон на EasyAdmin: русский UI, drag-drop, autosave, preview. Batch upload треков и товаров, WebP, dropzone. Сначала — om-player. Потом тот же язык — хроника и кейсы, market-culture, Ganesha.

«Не ниже этого уровня» стало правилом студии. Владелец наполняет сам — без разработчика в цикле на каждую картинку.
TEXT,
                storyOutcome: 'Админкой пользуются. Каталог живёт. Качество переносится в следующий проект, а не изобретается заново.',
                showOnLanding: true,
                hasDetailPage: true,
                seoTitle: 'Premium admin — кейс · Артур Лун',
                seoDescription: 'Как выстроил эталон админки: drag-drop, autosave, batch-загрузка — и перенёс между проектами.',
            ),
            self::entry(
                slug: 'svoe-mesto-oauth',
                area: 'паттерны студии',
                sortOrder: 320,
                title: 'Войти через VK или Google — и сохранить своё',
                summary: 'OAuth без пароля. Гостевой узор или избранное не пропадают после входа — «своё место» как реальная функция.',
                outcomeLine: 'Вернулся через привычный аккаунт — узоры, избранное, заказы на месте.',
                domain: 'auth · UX · OAuth',
                role: 'разработка, продукт',
                storyHook: 'Регистрация с паролем — лишний порог. Люди уже есть в VK и Google — и не должны терять то, что сделали гостем.',
                storyBody: <<<'TEXT'
«Своё место» бессмысленно, если до него — форма с паролем. Нужен вход туда, где человек уже есть, без потери гостевого следа.

В Sacred Geometry Lab: Google OAuth и VK ID (PKCE). Гость создал узор — после входа узор сохраняется (PendingPatternSave). В market-culture — тот же паттерн для Customer: избранное и корзина на OAuth.

Не «залогинился и потерял», а «залогинился — и твоё осталось». Один модуль — два продукта.
TEXT,
                storyOutcome: '«Своё место» — не метафора в UI. Вернулся — всё на месте.',
                hasDetailPage: true,
                seoTitle: 'OAuth «своё место» — кейс · Артур Лун',
                seoDescription: 'Как сделал вход через VK и Google с сохранением узоров и избранного после авторизации.',
            ),
            self::entry(
                slug: 'cursor-studio-workflow',
                area: 'паттерны студии',
                sortOrder: 330,
                title: 'Студия с агентами: не начинать с нуля',
                summary: 'Паттерны om-player, SGL, om-brand, market-culture — в Platform-Core. Cursor rules, эталоны, batch-сессии.',
                outcomeLine: 'Новый проект наследует качество admin, auth, order flow — не изобретает заново.',
                domain: 'AI · workflow · studio',
                role: 'архитектура, продукт',
                storyHook: 'Кропотливо собери — не будем каждый раз начинать с нуля.',
                storyBody: <<<'TEXT'
Каждый репозиторий тащил одни и те же решения заново. Агенты не знали эталонов — и каждый раз рисковали invent the wheel хуже.

В Platform-Core: Cursor rules, subagents, studio-workflow, авторская подпись. «Смотри om-player admin — не ниже этого уровня» стало контекстом, а не устной договорённостью.

Студия — накопленные паттерны. Новый проект стартует с памяти, не с пустоты.
TEXT,
                storyOutcome: 'Качество наследуется. Агент работает в контексте студии — не с чистого листа.',
                hasDetailPage: true,
                seoTitle: 'Студия с агентами — кейс · Артур Лун',
                seoDescription: 'Как выстроил workflow студии с Cursor: правила, эталоны, batch-сессии.',
            ),
            self::entry(
                slug: 'system-audit-honest',
                area: 'паттерны студии',
                sortOrder: 340,
                title: 'Честный аудит вместо нового фреймворка',
                summary: 'Система разрослась. Убрали мёртвый Vue, оставили нужное, Symfony 8, README, тесты. Не переписать всё — стабилизировать.',
                outcomeLine: 'Проект снова предсказуем. Новый человек может въехать.',
                domain: 'архитектура · аудит',
                role: 'разработка, архитектура',
                storyHook: 'Я больше не поклоняюсь инструментам — решаю задачи живых людей.',
                storyBody: <<<'TEXT'
Universal Application Engine разросся. Слои копились. Vue почти не использовался. Нужна была честная картина — не новый культ фреймворка.

Убрал лишнее, оставил WordPress bridge и Bootstrap, навёл README и тесты, поднял Symfony 8. Данные снова ходят предсказуемо.

Меньше слоёв — больше ясности. Аудит вернул контроль, а не прикрыл хаос красивым рефакторингом ради рефакторинга.
TEXT,
                storyOutcome: 'Проект снова можно держать в руках. Без археологии мёртвых слоёв.',
                hasDetailPage: true,
                seoTitle: 'Честный аудит системы — кейс · Артур Лун',
                seoDescription: 'Как стабилизировал разросшийся проект: аудит, Symfony 8, убрали лишние слои.',
            ),
        ];
    }

    /**
     * Область 4 — живые процессы для заказчика.
     *
     * @return list<array<string, mixed>>
     */
    public static function areaLiveProcesses(): array
    {
        return [
            self::entry(
                slug: 'inquiry-to-payment',
                area: 'живые процессы',
                sortOrder: 410,
                title: 'От заявки до оплаты — без чёрных дыр',
                summary: 'Форма с файлом → пуш в Telegram → ссылка СБП. Первый контакт уже с контекстом, счёт — одной ссылкой.',
                outcomeLine: 'Заявка не теряется. Предоплату можно принять с первого проекта — без месяца на эквайринг.',
                domain: 'заявки · Telegram · платежи',
                role: 'разработка, продукт',
                storyHook: 'Заявка бессмысленна, если о ней узнаёшь через час. А «пришлите ТЗ на почту» — лишний круг до разговора.',
                storyBody: <<<'TEXT'
Я живу в Telegram. Если заявка только в админке — я её пропущу. Часто понять задачу можно только по скрину или брифу. На старте эквайринг избыточен — предоплату нужно принять завтра.

Собрал воронку на om-brand: форма с вложением, валидация файла, уведомление в Telegram (email — fallback), PaymentOffer со ссылкой СБП и статусами. Всё в одной админке рядом с заявкой.

Короткий путь: написал → я увидел → выставили счёт → оплатил с телефона. Без чёрной дыры и без месяца на банк.
TEXT,
                storyOutcome: 'Первый контакт уже с телом задачи. Счёт уходит ссылкой. Я на связи в привычном канале.',
                showOnLanding: false,
                hasDetailPage: true,
                seoTitle: 'Заявка и оплата — кейс · Артур Лун',
                seoDescription: 'Как собрал воронку: форма с файлом, Telegram-уведомление и оплата по ссылке СБП.',
            ),
            self::entry(
                slug: 'order-flow-food',
                area: 'живые процессы',
                sortOrder: 420,
                title: 'Заказ еды: сайт и мессенджеры в одном ритме',
                summary: 'Календарь меню → заказ → статус → оплата. Повар узнаёт в Telegram, клиент — в VK. Без лишних кнопок и звонков «ну что там».',
                outcomeLine: 'Клиент заказывает интуитивно. Статус приходит туда, где люди уже живут.',
                domain: 'e-commerce · боты · UX',
                role: 'продукт, разработка',
                storyHook: 'Повар не должен объяснять «как заказать». Клиент не должен звонить «а мой заказ готов?».',
                storyBody: <<<'TEXT'
Заказ ломается на мелочах: лишние шаги, мутный статус, сайт отдельно от мессенджеров. Люди заказывают с телефона и сидят в Telegram и VK.

В Ganesha: календарь меню, заказ, статусы, оплата, uuid-ссылка без логина. Новый заказ — повару в Telegram; статус — клиенту в VK. Тот же order flow ушёл в market-culture для чая и товаров.

Сайт — источник истины. Мессенджеры — интерфейс присутствия. Один паттерн — следующий проект быстрее.
TEXT,
                storyOutcome: 'Заказ встраивается в привычный ритм. Клиент и повар не прыгают между системами.',
                hasDetailPage: true,
                seoTitle: 'Заказ еды и боты — кейс · Артур Лун',
                seoDescription: 'Как собрал заказ еды с уведомлениями в Telegram и VK — для клиента и повара.',
            ),
            self::entry(
                slug: 'sheets-to-schedule',
                area: 'живые процессы',
                sortOrder: 430,
                title: 'Данные фестиваля ходят сами',
                summary: 'Google Sheets → расписание на сайте и в API. CSV регистраций → отчёт кухне. Без ручного копирования и закупок «на глаз».',
                outcomeLine: 'Организатор правит таблицу — сайт и закупка видят актуальное.',
                domain: 'интеграция · события · данные',
                role: 'разработка, архитектура',
                storyHook: 'Когда данные должны ходить между частями, а не копироваться руками — и кормить и сайт, и кухню.',
                storyBody: <<<'TEXT'
На живом фестивале расписание меняется до последнего дня. Кухне нужны объёмы до закупки. Если каждый раз копировать Excel и считать «на глаз» — ошибки и нервы неизбежны.

В Universal Application Engine / Hanuman Fest: CRON из Google Sheets → Symfony → API → WordPress-тема. Из CSV регистраций — отчёт по питанию и объёмам для кухни.

Организатор остаётся в привычной таблице. Разработчик выходит из цикла «скинь актуальный файл». Живое событие — живые данные.
TEXT,
                storyOutcome: 'Одна таблица кормит сайт. Регистрации работают на кухню. Без ручного sync.',
                hasDetailPage: true,
                seoTitle: 'Данные фестиваля — кейс · Артур Лун',
                seoDescription: 'Как автоматизировал расписание из Google Sheets и отчёт кухне из CSV регистраций.',
            ),
            self::entry(
                slug: 'agent-bitrix-publishing',
                area: 'живые процессы',
                sortOrder: 440,
                title: 'Агент публикует — человек утверждает',
                summary: '71 страница в Bitrix: dry-run, публикация, таблица URL и ID. Excel — истина человека, JSON — зеркало, агент — руки API.',
                outcomeLine: 'Массовая публикация без кликанья — с контролем и прозрачным результатом.',
                domain: 'AI · Bitrix · контент-пайплайн',
                role: 'архитектура, разработка',
                storyHook: 'Агенты будут сами работать с API. Excel — источник истины от человека, JSON — зеркало.',
                storyBody: <<<'TEXT'
Семьдесят одна ITS-страница вручную — пытка, не работа редактора. Нужен конвейер: человек утверждает смысл, агент исполняет технику и возвращает отчёт.

Content-pipeline: Excel → JSON, dry-run, create/update в Bitrix, картинки, свойства, расписание. После прогона — таблица: URL, ID, admin link.

Человек — автор плана. Агент — руки API. Редактор не становится оператором CMS.
TEXT,
                storyOutcome: 'B2B-контент масштабируется. Публикация массовая — контроль человеческий.',
                hasDetailPage: true,
                seoTitle: 'Agent-driven Bitrix — кейс · Артур Лун',
                seoDescription: 'Как настроил публикацию в Bitrix через агентов: dry-run, расписание и таблица результатов.',
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function entry(
        string $slug,
        string $area,
        int $sortOrder,
        string $title,
        string $summary,
        string $outcomeLine,
        string $domain,
        string $role,
        string $storyHook,
        string $storyBody,
        string $storyOutcome,
        bool $isPublished = false,
        bool $showOnLanding = false,
        bool $isFeatured = false,
        bool $hasDetailPage = true,
        string $presentationMode = 'none',
        ?string $omTrackSlug = null,
        ?string $audioTitle = null,
        ?string $presentationIntro = null,
        ?string $presentationDuration = null,
        ?string $seoTitle = null,
        ?string $seoDescription = null,
    ): array {
        return [
            'slug' => $slug,
            'area' => $area,
            'wave' => (int) floor($sortOrder / 100),
            'sortOrder' => $sortOrder,
            'title' => $title,
            'summary' => $summary,
            'outcomeLine' => $outcomeLine,
            'domain' => $domain,
            'role' => $role,
            'year' => 2026,
            'storyHook' => $storyHook,
            'storyBody' => trim($storyBody),
            'storyOutcome' => $storyOutcome,
            'isPublished' => $isPublished,
            'showOnLanding' => $showOnLanding,
            'isFeatured' => $isFeatured,
            'hasDetailPage' => $hasDetailPage,
            'presentationMode' => $presentationMode,
            'omTrackSlug' => $omTrackSlug,
            'audioTitle' => $audioTitle,
            'presentationIntro' => $presentationIntro,
            'presentationDuration' => $presentationDuration,
            'seoTitle' => $seoTitle,
            'seoDescription' => $seoDescription,
        ];
    }
}
