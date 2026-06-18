# Руководство по проекту

Лендинг личного бренда: Symfony 8, EasyAdmin, MySQL, форма заявок, оплата по ссылке.

---

## 1. Локальная разработка

### Требования

- Docker + Docker Compose
- Git

### Первый запуск

```bash
git clone <repo-url> om-brand && cd om-brand
cp .env.example .env.local
```

В `.env.local` задайте надёжный пароль (seed его проверит):

```env
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=ваш_надёжный_пароль
```

```bash
docker compose up -d --build
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console app:seed
```

| Сервис | URL |
|--------|-----|
| Сайт | http://localhost:8085 |
| Админка | http://localhost:8085/admin |
| MySQL | `127.0.0.1:33085` (user/pass/db: `site`) |

Логин в админку: `ADMIN_EMAIL` / `ADMIN_PASSWORD` из `.env.local`.

### Полезные команды

```bash
docker compose exec php php bin/console app:content:sync   # тексты из кода → БД
docker compose exec php php bin/console app:verify:inquiry # проверка формы
docker compose exec php php bin/console cache:clear
docker compose logs -f php
```

---

## 2. Переменные окружения

Файл `.env.local` (не коммитить). Шаблон: `.env.example`.

| Переменная | Обязательно | Назначение |
|------------|-------------|------------|
| `APP_SECRET` | да | Случайная строка 32+ символов |
| `DATABASE_URL` | да | MySQL DSN |
| `ADMIN_EMAIL` | да | Email админа (seed) |
| `ADMIN_PASSWORD` | да | Пароль админа (не `admin`) |
| `APP_SITE_URL` | prod | `https://ваш-домен.ru` — canonical, OG, ссылки |
| `APP_ENV` | prod | `prod` на сервере |
| `APP_DEBUG` | prod | `0` на сервере |
| `TELEGRAM_BOT_TOKEN` | нет | Уведомления о заявках |
| `TELEGRAM_CHAT_ID` | нет | Chat ID |
| `MAILER_DSN` | нет | Email fallback (`null://null` = выкл) |
| `MAILER_FROM` | prod* | Отправитель, совпадает с логином SMTP (напр. `info@arturlun.ru`) |

В админке → **Настройки сайта** → поле **Email для заявок** — куда слать fallback, если Telegram недоступен.

---

## 3. Контент

**На сайте показывается то, что в базе.** Эталон текстов — в коде.

### Где править

| Задача | Как |
|--------|-----|
| Тексты секций (деплой) | `src/Content/LandingContent.php` → `app:content:sync` |
| Без деплоя | Админка → Блоки контента / Настройки |
| Политика конфиденциальности | `src/Content/LegalContent.php` |
| Вёрстка секций | `templates/web/home/index.html.twig` |
| Стили | `public/css/site.css` |

### Команды

```bash
app:seed          # первый запуск: настройки + блоки + админ
app:content:sync  # перезаписать все блоки из LandingContent.php
```

⚠️ `sync` затирает правки блоков, сделанные в админке.

### Slug-блоки (ключ → секция)

| slug | Секция |
|------|--------|
| `hero` | Hero |
| `audience` | Для кого |
| `pains` | Если знакомо |
| `specialization` | Специализация |
| `services` | Услуги |
| `philosophy` | Своё решение |
| `process` | Процесс |
| `work_formats` | Форматы |
| `form_intro` | Заголовок формы |
| `footer_hr` | Футер «Для HR» |
| `footer_excludes` | Футер «Что не предлагаю» |

---

## 4. Админка

URL: `/admin` · Throttling: 5 попыток входа / 15 мин.

| Раздел | Назначение |
|--------|------------|
| Настройки сайта | Имя, tagline, аватар, ссылки, email заявок |
| Блоки контента | Тексты по slug |
| Кейсы | Портфолио на главной |
| Заявки | Обращения + скачать вложение |
| Оплаты | Ссылки `/oplata/{token}` |
| Админы | Доступ в панель |

**Оплата:** сумма в **рублях** (например, 5000). После сохранения — карточка со **ссылкой для клиента**. **Ссылка СБП** подставляется из шаблона в настройках. Telegram при создании.

**Вложения заявок:** хранятся в `var/private/uploads/`, скачивание только через админку.

---

## 5. Форма заявки

POST `/` → сохранение в БД → Telegram (или email fallback).

Поля: имя, тип контакта, контакт, тип задачи, сообщение, файл (до 5 МБ), согласие на обработку данных.

---

## 6. Защита формы: honeypot и rate limit

Два слоя защиты от спама и ботов.

### Honeypot

Скрытое поле `website`. Человек не видит, бот заполняет → заявка **не создаётся**, но ответ «успех» (чтобы бот не учился).

### Rate limit

Не больше **10 отправок с одного IP за 15 минут**. Ответ 429: «Подождите 15 минут».

### CSRF

Symfony form token — защита от подделки запросов с чужих сайтов.

---

## 7. Деплой на хостинг (NetAngels, VPS, shared)

### Требования сервера

- PHP **≥ 8.4**
- Расширения: `pdo_mysql`, `intl`, `gd`, `zip`, `mbstring`, `xml`, `curl`
- MySQL 8.0
- Composer (через SSH)
- Document root → **`public/`** (не корень репозитория)

### NetAngels (виртуальный хостинг / VPS)

#### 1. Домен и SSL

- Привязать домен в панели NetAngels
- Включить Let's Encrypt (HTTPS)

#### 2. PHP

- Панель → PHP → версия **8.4** (или 8.5)
- Проверить расширения (pdo_mysql, intl, gd, zip)

#### 3. MySQL

- Создать БД и пользователя в панели
- Записать host, database, user, password

#### 4. Document root

Указать каталог **`public`** внутри проекта:

```
/home/u12345/om-brand/public
```

Структура на сервере:

```
om-brand/
├── bin/
├── config/
├── public/          ← document root
├── src/
├── templates/
├── var/             ← права на запись
└── vendor/
```

#### 5. Загрузка кода

**Через SSH (предпочтительно):**

```bash
cd ~
git clone <repo-url> om-brand
cd om-brand
composer install --no-dev --optimize-autoloader
```

**Через FTP:** загрузить файлы, затем `composer install` по SSH.

#### 6. `.env.local` на сервере

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<случайная_строка>
APP_SITE_URL=https://ваш-домен.ru

DATABASE_URL="mysql://USER:PASS@localhost:3306/DBNAME?serverVersion=8.0&charset=utf8mb4"

ADMIN_EMAIL=...
ADMIN_PASSWORD=...   # только для первого seed, потом сменить в админке

TELEGRAM_BOT_TOKEN=...
TELEGRAM_CHAT_ID=...

MAILER_DSN=smtp://info@arturlun.ru:ПАРОЛЬ@mail.netangels.ru:587
MAILER_FROM=info@arturlun.ru
```

#### 7. База и контент

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed              # первый раз
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

#### 8. Права на запись

```bash
mkdir -p var/private/uploads public/uploads/avatars public/uploads/cases
chmod -R 775 var/
chmod -R 775 public/uploads/
```

На shared-хостинге через панель или `chown` под пользователя веб-сервера.

#### 9. Проверка после деплоя

- [ ] Главная открывается по HTTPS
- [ ] `/admin/login` — вход работает
- [ ] Форма отправляется, заявка в админке
- [ ] Telegram/email уведомление (если настроено)
- [ ] `view-source` — JSON-LD, canonical с prod-доменом
- [ ] `/robots.txt`, `/sitemap.xml`
- [ ] Загрузка аватара в админке

### Обновление (повторный деплой)

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:content:sync    # если меняли LandingContent.php
php bin/console cache:clear --env=prod
```

### VPS с Docker

Можно использовать dev `docker-compose.yml` как основу, но для prod нужны:
- отдельные пароли
- volume для `var/` и uploads
- reverse proxy + SSL
- `APP_ENV=prod`

Отдельного prod compose в репозитории нет — на VPS чаще ставят nginx + php-fpm нативно (как в разделе NetAngels выше).

---

## 8. Бэкапы и проблемы

### Бэкап

```bash
# БД
mysqldump -u USER -p DBNAME > backup.sql

# Файлы
tar -czf uploads.tar.gz public/uploads/ var/private/uploads/
```

### Частые проблемы

| Симптом | Решение |
|---------|---------|
| 500 после деплоя | `var/log/prod.log`, права на `var/`, `cache:clear` |
| Seed не проходит | Надёжный `ADMIN_PASSWORD` в `.env.local` |
| Telegram молчит | Токен, chat_id, бот добавлен в чат; fallback email |
| Старый контент | `cache:clear`, проверить правки в админке vs sync |
| Белая страница | `APP_DEBUG=0` + смотреть `var/log/` |

---

## 9. Структура проекта

```
config/           Symfony-конфигурация
docker/           nginx + PHP (локальная разработка)
docs/guide.md     ← эта инструкция
migrations/       Миграции БД
public/           Document root (css, js, index.php)
src/
  Admin/          EasyAdmin CRUD
  Command/        seed, sync, verify
  Content/        LandingContent, LegalContent
  Controller/     HTTP
  Entity/         Модели
  EventSubscriber/
  Form/
  Service/
templates/web/    Twig-шаблоны
var/              cache, log, private uploads
```

### Маршруты

| URL | Назначение |
|-----|------------|
| `/` | Лендинг + форма |
| `/politika-konfidencialnosti` | Политика |
| `/oplata/{token}` | Оплата (noindex) |
| `/admin` | Админка |
| `/robots.txt`, `/sitemap.xml` | SEO |

---

## 10. Безопасность (кратко)

- CSRF на форме и admin login
- Honeypot + rate limit + CSRF
- Вложения заявок — только через админку
- CSP и security headers на публичных страницах
- Секреты только в `.env.local`

---

*Один файл — весь flow. Вопросы и доработки — в репозиторий или админку.*
