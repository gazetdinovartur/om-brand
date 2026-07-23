# Руководство по проекту

Цифровой дом и лендинг разработчика: Symfony 8, EasyAdmin, MySQL, формы заявок, кейсы, хроника, оплата по ссылке.

---

## 0. Карта сайта

| URL | Роль |
|-----|------|
| `/` | Дом — приветствие и карта комнат экосистемы |
| `/dev--null` | Лендинг разработчика + форма заказа |
| `/contact` | Универсальная связь (соавторство, вопрос, просто написать) |
| `/cases` | Хаб кейсов |
| `/cases/{slug}` | Страница истории кейса |
| `/chronicle` | Хроника (фильтры) |
| `/chronicle/{slug}` | Запись |
| `/p/{hash}` | Короткая ссылка записи |
| `/politika-konfidencialnosti` | Политика |
| `/oplata/{token}` | Оплата (noindex) |
| `/admin` | Админка |
| `/robots.txt`, `/sitemap.xml` | SEO |

Оболочки: `house` (внутренние страницы) и `landing` (`/dev--null` — полная шапка/футер).

---

## 1. Локальная разработка

### Требования

- Docker + Docker Compose
- Git

### Первый запуск

```bash
git clone <repo-url> om-brand && cd om-brand
cp .env.example .env
```

В `.env` задайте надёжный пароль (seed его проверит):

```env
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=ваш_надёжный_пароль
```

```bash
docker compose up -d --build
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console app:seed
docker compose exec php php bin/console app:chronicle:seed-meta
docker compose exec php php bin/console app:chronicle:seed-likes
```

> В Docker `DATABASE_URL` внутри контейнера PHP должен указывать на хост `mysql` (не `127.0.0.1:33085`). Порт `33085` — только с хоста.

| Сервис | URL |
|--------|-----|
| Дом | http://localhost:8085 |
| Разработка | http://localhost:8085/dev--null |
| Связь | http://localhost:8085/contact |
| Кейсы | http://localhost:8085/cases |
| Хроника | http://localhost:8085/chronicle |
| Админка | http://localhost:8085/admin |
| MySQL | `127.0.0.1:33085` (user/pass/db: `site`) |

Логин в админку: `ADMIN_EMAIL` / `ADMIN_PASSWORD` из `.env`.

### Полезные команды

```bash
docker compose exec php php bin/console app:content:sync        # тексты лендинга из кода → БД
docker compose exec php php bin/console app:chronicle:seed-likes # счётчики лайков из импорта
docker compose exec php php bin/console app:verify:inquiry      # проверка формы
docker compose exec php php bin/console cache:clear
docker compose logs -f php
```

---

## 2. Переменные окружения

Файл `.env` (не коммитить). Шаблон: `.env.example`. Без `.env.local` — один файл на окружение.

| Переменная | Обязательно | Назначение |
|------------|-------------|------------|
| `APP_SECRET` | да | Случайная строка 32+ символов |
| `DATABASE_URL` | да | MySQL DSN |
| `ADMIN_EMAIL` | да | Email админа (seed) |
| `ADMIN_PASSWORD` | да | Пароль админа (не `admin`) |
| `APP_SITE_URL` | prod | `https://arturlun.ru` — canonical, OG, ссылки |
| `APP_ENV` | prod | `prod` на сервере |
| `APP_DEBUG` | prod | `0` на сервере |
| `ASSETS_VERSION` | prod | Query `?v=` для css/js — bump при фронт-релизе |
| `TELEGRAM_BOT_TOKEN` | нет | Уведомления о заявках |
| `TELEGRAM_CHAT_ID` | нет | Chat ID |
| `MAILER_DSN` | нет | Email fallback (`null://null` = выкл) |
| `MAILER_FROM` | prod* | Отправитель, совпадает с логином SMTP (напр. `info@arturlun.ru`) |

В админке → **Настройки** → поле **Email для заявок** — куда слать fallback, если Telegram недоступен.

---

## 3. Контент

**Лендинг `/dev--null`:** на сайте показываются блоки из БД. Эталон — в коде.  
**Дом и связь:** тексты только из кода (`HouseContent`), без админки.

### Где править

| Задача | Как |
|--------|-----|
| Тексты секций лендинга | `src/Content/LandingContent.php` → `app:content:sync` |
| Дом, комнаты, `/contact` | `src/Content/HouseContent.php` (деплой кода, sync не нужен) |
| Политика конфиденциальности | `src/Content/LegalContent.php` |
| Имя, аватар, ссылки, email | Админка → **Настройки** (одна запись, без «Создать») |
| Вёрстка дома | `templates/web/home/index.html.twig` |
| Вёрстка лендинга | `templates/web/dev/index.html.twig` |
| Стили | `public/css/site.css` |

### Команды

```bash
app:seed          # первый запуск: настройки + блоки лендинга + админ
app:content:sync  # перезаписать блоки лендинга из LandingContent.php
```

⚠️ `sync` затирает правки блоков в БД. Редактировать блоки в админке больше не нужно — раздел убран из меню; ориентир — файлы в `src/Content/`.

### Slug-блоки лендинга (ключ → секция на `/dev--null`)

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
| `pricing` | Стоимость |
| `form_intro` | Заголовок формы |
| `footer_hr` | Футер «Для HR» |
| `footer_excludes` | Футер «Что не предлагаю» |

Кейсы на лендинге — из БД (флаг «На лендинге»), не из ContentBlock.

---

## 4. Хроника (блог)

Публичная лента текстов и фото по **каналам / эпохам / тегам**: `/chronicle`.  
Карточка записи: `/chronicle/{slug}`, короткая ссылка: `/p/{hash}`.

### Что в репозитории, что только локально

| В git | Только локально (`.gitignore`) |
|-------|--------------------------------|
| `config/content/catalog.json` — эпохи, теги, серии, пути к экспортам | `content/` — ChatExport, VK, Instagram-медиа |
| Код: Entity, Command, Twig, CSS | `corpus/` — `chronicle_entries.jsonl` после сборки |
| Миграции БД | `scripts/` — `corpus_build.py` и пр. |
| | `analysis/` — зеркала эпох, саммари |
| | `public/uploads/chronicle/` — картинки после импорта |

**Не тащите mysqldump с локалки на прод.** Источник правды для записей — corpus + `app:chronicle:import`.

### Каталог

`config/content/catalog.json`:

- **eras** — эпохи с датами и цветами  
- **theme_tags** / **channel_tags** — теги  
- **series** — каналы (Да и Да, VK, Instagram, черновики TG)  
- **channels** — откуда брать тексты + `status` (`published` / `draft`)  
- **external_paths.instagram_exports** — пути к zip экспорта Instagram  

После правок каталога:

```bash
docker compose exec php php bin/console app:chronicle:seed-meta
```

### Сборка корпуса (локально)

Скрипты лежат в `scripts/` (не в git). Нужны экспорты в `content/` и/или zip Instagram.

```bash
# TG + VK → corpus/chronicle_entries.jsonl
python3 scripts/corpus_build.py

# + Instagram (подписи ≥ 80 символов, фото из всех zip экспорта)
python3 scripts/corpus_build.py --instagram
```

Особенности:

- Заголовки собираются из текста (целые фразы, без обрывов на предлогах); посты VK только с картинками → «Пост от 10 мая 2026 года»
- Instagram: медиа из **всех** zip в catalog; посты без картинок в хронику не попадают
- Лайки VK пишутся в блоки `calloutStyle=meta`; на сайте поднимаются в `like_count` и показываются как живые лайки
- Telegram: в meta суммируются **все** реакции волны (`🔥 2 · ❤ 1` → 3)
- Instagram: лайки из zip `past_instagram_insights` → meta `❤ N` (при `--instagram` / `enrich_corpus_likes.py`)
- После миграции / импорта: `php bin/console app:chronicle:seed-likes`

```bash
# обновить meta лайков в уже собранном корпусе (TG + IG), без полной пересборки
python3 scripts/enrich_corpus_likes.py
```

### Импорт в БД (локально)

```bash
docker compose exec php php bin/console app:chronicle:seed-meta
docker compose exec php php bin/console app:chronicle:import --channel=da-i-da
docker compose exec php php bin/console app:chronicle:import --channel=instagram
docker compose exec php php bin/console app:chronicle:import --channel=vk
docker compose exec php php bin/console app:chronicle:seed-likes
# черновики TG (не на сайте, пока draft):
# docker compose exec php php bin/console app:chronicle:import --channel=om --channel=research --channel=culture
```

Импорт идемпотентен по `source_key`. Обновляет title, lede, блоки, медиа.  
**Не трогает** `isFeatured` (♥ в админке).

### Поведение сайта

- В фильтрах только каналы / эпохи / теги, у которых есть **опубликованные** записи  
- Чип «Все теги» / «Все каналы» / «Все эпохи» — сброс фильтра  
- Даты по-русски («10 мая 2026»)  
- Запись без обложки: заголовок на всю ширину  
- Серия — чип с обводкой; эпоха — мягкая заливка; теги — оранжевые пилюли  
- Лайк без регистрации; «Поделиться» — системный share / копирование `/p/…`  
- Фильтры на мобилке запоминаются в `sessionStorage`

Краткий чеклист запуска на https://arturlun.ru/: [prod-launch.md](prod-launch.md). Скрипт rsync: `./sync-prod-content.sh`.

### Перенос хроники на прод (с media)

Код — через `git`. Данные хроники **не в git**. Не делайте полный mysqldump локальной БД на прод.

#### Сколько места (снимок на 2026-07-21)

| Что | Размер | Файлов | Нужно на проде? |
|-----|--------|--------|-----------------|
| `corpus/` (jsonl + manifest) | **~8 МБ** | ~4 | да |
| `content/instagram/` | **~340 МБ** | ~1600 | вариант A |
| `content/vk/` | **~790 МБ** | ~430 | вариант A |
| Итого исходники media | **~1.1 ГБ** | | |
| `public/uploads/chronicle/` после чистого импорта published | **~540 МБ** | ~1400 | да (результат) |
| То же локально сейчас (с «хвостами» от повторных импортов) | ~1.6 ГБ | ~5300 | не копировать как есть |

**Свободно на диске прода закладывайте ~2.5–3 ГБ** на первый прогон (исходники + uploads + запас), потом исходники `content/` можно удалить с сервера.

Опубликовано сейчас: ~300 записей (Да и Да + Instagram + VK).

#### Два рабочих варианта

**Вариант A — предпочтительный: corpus + исходные media + import на проде**

Плюсы: пути картинок создаются на сервере, без таскания раздутого локального `uploads/`.  
Минусы: первый импорт дольше, на сервере временно лежат `content/`.

Локально:

```bash
python3 scripts/corpus_build.py --instagram
```

Залить на сервер (из корня проекта на машине разработчика):

```bash
# подставьте USER, HOST, REMOTE=/path/to/om-brand
rsync -avz --progress corpus/ USER@HOST:REMOTE/corpus/

mkdir -p content/instagram content/vk   # на сервере уже должно быть
rsync -avz --progress content/instagram/ USER@HOST:REMOTE/content/instagram/
rsync -avz --progress content/vk/ USER@HOST:REMOTE/content/vk/
```

На проде:

```bash
cd /path/to/om-brand

mkdir -p \
  corpus \
  content/instagram \
  content/vk \
  public/uploads/chronicle/{covers,inline,gallery}
chmod -R 775 public/uploads/chronicle

php bin/console app:chronicle:seed-meta --env=prod

php bin/console app:chronicle:import --channel=da-i-da --env=prod
php bin/console app:chronicle:import --channel=instagram --env=prod
php bin/console app:chronicle:import --channel=vk --env=prod
php bin/console app:chronicle:seed-likes --env=prod

php bin/console cache:clear --env=prod
```

После успешной проверки картинок на `/chronicle` исходники можно убрать с сервера (uploads остаются):

```bash
# осторожно: только если import уже прошёл
rm -rf content/instagram content/vk
# corpus/ лучше оставить — пригодится для повторного title/text sync
```

Повторное обновление текстов/заголовков без смены картинок: залить новый `corpus/chronicle_entries.jsonl` и снова `app:chronicle:import` (медиа перекопируются в новые UUID — место снова вырастет; периодически чистите сироты в `uploads/chronicle` или заливайте заново в пустую папку).

---

**Вариант B — быстрее по CPU: corpus + уже собранный `uploads/chronicle`**

Имеет смысл, только если на локалке один «чистый» импорт и вы готовы тащить ~0.5–1.6 ГБ готовых файлов. Имена файлов в БД должны совпасть с именами в `uploads/` — значит, либо:

1. вместе с uploads переносится **дамп таблиц хроники** с локалки, либо  
2. на проде после rsync uploads всё равно делается import (тогда файлы в uploads размножатся — не делайте так).

Практичный B:

```bash
# локально: дамп только хроники
docker compose exec mysql mysqldump -usite -psite site \
  chronicle_entry chronicle_block chronicle_block_image \
  chronicle_entry_tag chronicle_era chronicle_series chronicle_tag \
  > /tmp/chronicle.sql

rsync -avz --progress public/uploads/chronicle/ USER@HOST:REMOTE/public/uploads/chronicle/
scp /tmp/chronicle.sql USER@HOST:/tmp/chronicle.sql
```

На проде: импорт SQL в БД сайта, права на `public/uploads/chronicle`, `cache:clear`.  
Код и `catalog` по-прежнему через git + `seed-meta` при смене эпох/тегов.

Вариант A проще сопровождать; B — если import на слабом хостинге слишком долгий.

#### Чеклист после деплоя хроники

- [ ] `/chronicle` открывается, счётчик записей ≈ ожидаемому  
- [ ] Фильтры: Да и Да / ВКонтакте / Instagram  
- [ ] Карточка с галереей Instagram — все фото  
- [ ] VK image-only — заголовок «Пост от …», картинка на месте  
- [ ] Запись без обложки — заголовок на всю ширину  
- [ ] ❤ лайки из VK видны в блоке лайк/шеринг под постом  
- [ ] лайк без регистрации работает; повторное нажатие снимает лайк  
- [ ] на мобилке «Поделиться» открывает системный share; на десктопе копирует короткую ссылку `/p/…`  
- [ ] В админке ♥ избранное сохраняется после повторного import  

Код (фильтры, вёрстка, даты) — обычным `git pull` + `./deploy-script.sh`.

---

## 5. Админка

URL: `/admin` · меню «Навигация» · Throttling: 5 попыток входа / 15 мин.

| Раздел | Назначение |
|--------|------------|
| Настройки | Имя, tagline, аватар, ссылки, email заявок (singleton — сразу edit) |
| Кейсы | Портфолио: лендинг, `/cases`, `/cases/{slug}`; DnD порядка в списке и галереи |
| Хроника | Записи, эпохи, серии, теги; ♥ = избранное |
| Заявки | Обращения + скачать вложение |
| Оплаты | Ссылки `/oplata/{token}` |
| Админы | Доступ в панель |

Тексты лендинга/дома в админке **не правятся** — на дашборде подсказка путей к `LandingContent.php` / `HouseContent.php`.

### Кейсы (форма)

- Вкладки: Основное · История · Медиа · SEO  
- Порядок в списке — drag & drop (поле «Порядок» убрано)  
- Галерея — drag & drop  
- Видео/аудио — поля по типу презентации  
- OG-картинка берётся из обложки автоматически  
- Длительность презентации не используется  

**Оплата:** сумма в **рублях** (например, 5000). После сохранения — карточка со **ссылкой для клиента**. **Ссылка СБП** подставляется из шаблона в настройках. Telegram при создании.

**Вложения заявок:** хранятся в `var/private/uploads/`, скачивание только через админку.

---

## 6. Формы заявок

| Страница | Маршрут | Назначение |
|----------|---------|------------|
| `/dev--null` | POST на себя | Заказ разработки (InquiryForm) |
| `/contact` | POST на себя | Универсальная связь (ContactForm) |

Обе сохраняют заявку в БД → Telegram (или email fallback).

Поля (лендинг): имя, тип контакта, контакт, тип задачи, сообщение, файл (до 5 МБ), согласие.  
Страница связи — упрощённый набор под общий контакт.

---

## 7. Защита формы: honeypot и rate limit

Два слоя защиты от спама и ботов.

### Honeypot

Скрытое поле `website`. Человек не видит, бот заполняет → заявка **не создаётся**, но ответ «успех» (чтобы бот не учился).

### Rate limit

Не больше **10 отправок с одного IP за 15 минут**. Ответ 429: «Подождите 15 минут».

### CSRF

Symfony form token — защита от подделки запросов с чужих сайтов.

---

## 8. Деплой на хостинг (NetAngels, VPS, shared)

### Требования сервера

- PHP **≥ 8.4**
- Расширения: `pdo_mysql`, `intl`, `gd`, `zip`, `mbstring`, `xml`, `curl`
- MySQL 8.0
- Composer (через SSH)
- Document root → **`public/`** (не корень репозитория)

### Быстрый путь (скрипт)

Из корня проекта по SSH, после настройки `.env`:

```bash
# Первый раз
./deploy-script.sh --first

# Обычное обновление
./deploy-script.sh

# + перезаписать блоки лендинга из кода
./deploy-script.sh --sync-content
```

Скрипт делает: `git pull` → `composer install` → каталоги uploads → миграции → (seed / sync) → `cache:clear` + warmup.

После миграции лайков / импорта VK на проде один раз:

```bash
php bin/console app:chronicle:seed-likes --env=prod
```

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

#### 6. `.env` на сервере

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<случайная_строка>
APP_SITE_URL=https://arturlun.ru

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
php bin/console app:seed --env=prod              # первый раз
php bin/console app:chronicle:seed-likes --env=prod  # после миграции лайков / импорта VK
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

Или одной командой: `./deploy-script.sh --first` (см. выше).

#### 8. Права на запись

```bash
mkdir -p var/private/uploads \
  public/uploads/avatars \
  public/uploads/cases \
  public/uploads/cases/gallery \
  public/uploads/cases/audio \
  public/uploads/chronicle/{covers,inline,gallery}
chmod -R 775 var/
chmod -R 775 public/uploads/
```

На shared-хостинге через панель или `chown` под пользователя веб-сервера.

#### 9. Проверка после деплоя

- [ ] `/` — дом (приветствие + комнаты), HTTPS
- [ ] `/dev--null` — лендинг, форма разработки
- [ ] `/contact` — форма связи
- [ ] `/cases`, `/cases/{slug}` — хаб и история
- [ ] `/admin/login` — вход; меню «Навигация»; настройки без «Создать»
- [ ] Кейсы: DnD порядка в списке, форма с вкладками
- [ ] Форма отправляется, заявка в админке
- [ ] Telegram/email уведомление (если настроено)
- [ ] `view-source` — JSON-LD, canonical с prod-доменом
- [ ] `/robots.txt`, `/sitemap.xml` (дом, лендинг, кейсы, хроника, контакт)
- [ ] `/chronicle` — лента, фильтры, лайки, шаринг
- [ ] Загрузка аватара / обложки кейса в админке

### Обновление (повторный деплой)

```bash
./deploy-script.sh
# или вручную:
git pull origin main
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:content:sync --env=prod   # только если меняли LandingContent.php
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

Изменения в `HouseContent.php` применяются сразу после деплоя кода (sync не нужен).

Хронику (тексты/фото) при обновлении корпуса см. [§4 · Перенос хроники на прод](#перенос-хроники-на-прод-с-media) — отдельным rsync + `app:chronicle:import`, не через один только `git pull`.

### VPS с Docker

Можно использовать dev `docker-compose.yml` как основу, но для prod нужны:
- отдельные пароли
- volume для `var/` и uploads
- reverse proxy + SSL
- `APP_ENV=prod`

Отдельного prod compose в репозитории нет — на VPS чаще ставят nginx + php-fpm нативно (как в разделе NetAngels выше).

---

## 9. Бэкапы и проблемы

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
| Seed не проходит | Надёжный `ADMIN_PASSWORD` в `.env` |
| Telegram молчит | Токен, chat_id, бот добавлен в чат; fallback email |
| Старый контент лендинга | `app:content:sync` + `cache:clear` |
| Белая страница | `APP_DEBUG=0` + смотреть `var/log/` |
| Docker: БД недоступна из PHP | В compose `DATABASE_URL` с хостом `mysql`, не `127.0.0.1` |

---

## 10. Структура проекта

```
bin/
config/
  content/catalog.json   ← эпохи, теги, каналы хроники
docker/           nginx + PHP (локальная разработка)
docs/guide.md     ← эта инструкция
migrations/       Миграции БД
public/           Document root (css, js, index.php)
src/
  Admin/          EasyAdmin CRUD
  Command/        seed, sync, chronicle:*, …
  Content/        LandingContent, HouseContent, LegalContent
  Controller/     HTTP (home, dev, contact, cases, chronicle, …)
  Entity/         Модели
  EventSubscriber/
  Form/
  Service/
templates/web/    Twig (house, landing, cases, chronicle, contact)
var/              cache, log, private uploads

# не в git (локально):
content/          экспорты TG/VK/IG
corpus/           chronicle_entries.jsonl
scripts/          corpus_build.py и др.
analysis/         зеркала эпох
```

### Маршруты

См. [§0 · Карта сайта](#0-карта-сайта).

---

## 11. Безопасность (кратко)

- CSRF на формах и admin login
- Honeypot + rate limit + CSRF
- Вложения заявок — только через админку
- CSP и security headers на публичных страницах
- Секреты только в `.env` (файл не в git)

---

## 12. Changelog (2026-07-22)

- Сайт как экосистема: дом `/`, лендинг `/dev--null`, связь `/contact`
- Контент дома — `HouseContent.php`; блоки лендинга убраны из меню админки
- Админка кейсов: вкладки, DnD порядка и галереи, медиа по типу презентации, OG из обложки
- Настройки — singleton (без «Создать»)
- Живые лайки + шаринг; импорт счётчиков VK → `app:chronicle:seed-likes`
- Кейсы на лендинге: компактный список, центрирование с соседними блоками, выделенная секция

---

*Один файл — весь flow. Вопросы и доработки — в репозиторий.*
