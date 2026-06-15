# Разработка

## Структура проекта

```
├── config/                 # Symfony-конфигурация
├── docker/                 # nginx, php Dockerfile
├── docs/                   # документация
├── migrations/             # Doctrine migrations
├── public/
│   ├── css/site.css        # стили лендинга
│   ├── css/admin-custom.css
│   └── js/site.js
├── src/
│   ├── Admin/              # EasyAdmin CRUD
│   ├── Command/            # app:seed, app:content:sync
│   ├── Content/            # LandingContent — эталон текстов
│   ├── Controller/
│   ├── Entity/
│   ├── Enum/
│   ├── Form/
│   ├── Repository/
│   └── Service/
└── templates/
    ├── admin/
    └── web/
```

## Docker

```bash
docker compose up -d          # поднять
docker compose down           # остановить
docker compose logs -f php    # логи PHP
```

| Сервис | Порт | Назначение |
|--------|------|------------|
| nginx | 8085 | HTTP |
| mysql | 33085 | БД |
| php | — | Symfony CLI внутри контейнера |

Команды Symfony выполнять через `docker compose exec php php bin/console …`.

## Переменные окружения

Файл `.env` (локальные секреты — в `.env.local`):

| Переменная | Назначение |
|------------|------------|
| `DATABASE_URL` | Подключение к MySQL |
| `ADMIN_EMAIL` | Email админа при seed |
| `ADMIN_PASSWORD` | Пароль админа при seed |
| `TELEGRAM_BOT_TOKEN` | Бот для уведомлений о заявках |
| `TELEGRAM_CHAT_ID` | Chat ID для уведомлений |
| `DEFAULT_URI` | Базовый URL (ссылки в CLI) |

Если Telegram не настроен — заявки сохраняются в БД, уведомления пропускаются.

## Маршруты

| URL | Контроллер | Описание |
|-----|------------|----------|
| `/` | `HomeController` | Лендинг + форма |
| `/admin` | EasyAdmin | Панель управления |
| `/oplata/{token}` | `PaymentController` | Страница оплаты по ссылке |

## Админка

Разделы EasyAdmin:

| Раздел | Сущность | Заметки |
|--------|----------|---------|
| Настройки сайта | `SiteSettings` | Одна запись: имя, tagline, аватар, ссылки |
| Блоки контента | `ContentBlock` | Тексты лендинга по slug |
| Заявки | `Inquiry` | Входящие обращения + вложения |
| Кейсы | `CaseStudy` | Портфолио (когда появятся) |
| Оплата | `PaymentOffer` | Ссылки СБП / оплаты |
| Пользователи | `AdminUser` | Доступ в админку |

## Сервисы

| Класс | Роль |
|-------|------|
| `InquiryService` | Создание заявки, файл, Telegram |
| `TelegramNotifier` | Отправка в Telegram |
| `InquiryAttachmentStorage` | Загрузки в `public/uploads/inquiries/` |
| `PaymentOfferService` | Генерация токена оплаты |
| `LandingContentProvider` | Блоки контента для фронта |

## Частые команды

```bash
# Миграции
docker compose exec php php bin/console doctrine:migrations:migrate

# Контент (см. docs/content-and-seed.md)
docker compose exec php php bin/console app:seed
docker compose exec php php bin/console app:content:sync

# Новая миграция после изменения Entity
docker compose exec php php bin/console make:migration
docker compose exec php php bin/console doctrine:migrations:migrate

# Список маршрутов
docker compose exec php php bin/console debug:router
```

## Загрузки

| Тип | Путь |
|-----|------|
| Аватар | `public/uploads/avatars/` |
| Вложения заявок | `public/uploads/inquiries/` |
| Обложки кейсов | `public/uploads/` |

Параметр `app.uploads_directory` — в `config/services.yaml`.

## Локальная разработка без Docker

Возможна, если PHP 8.4+ и MySQL на `127.0.0.1:33085`.  
`DATABASE_URL` в `.env` уже указывает на локальный порт Docker MySQL.

```bash
composer install
php bin/console doctrine:migrations:migrate
php bin/console app:seed
symfony server:start   # или свой nginx/php-fpm
```
