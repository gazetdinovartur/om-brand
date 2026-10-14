# Запуск на проде — https://arturlun.ru/

Один env-файл: **`.env`** (без `.env.local`). Код — через git. Document root → `public/`.

Локальные `DEPLOY_*` в `.env` нужны только для `./sync-prod-content.sh` с машины разработчика.

---

## 0. SSH-ключ (один раз)

Публичный ключ на машине разработчика:

```text
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIGw2+hMvQt5XKuWj7cDEn5Xfj88e8XnYBEzLuSfvj6gX arturlun-deploy@arturlun.ru
```

1. NetAngels → SSH-ключи → добавить строку выше.
2. Проверка с Mac:

```bash
ssh arturlun-deploy 'pwd; ls arturlun.ru/om-brand | head'
# или:
ssh -i ~/.ssh/arturlun_deploy -p 22 c502022@91.201.52.29 'pwd'
```

Host `arturlun-deploy` уже в `~/.ssh/config`, ключ: `~/.ssh/arturlun_deploy`.

---

## 1. `.env` на сервере

В корне проекта (`…/om-brand/.env`), не коммитить:

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<случайная_строка_32+>
APP_SITE_URL=https://arturlun.ru
DEFAULT_URI=https://arturlun.ru
WEBAUTHN_RP_ID=arturlun.ru
WEBAUTHN_RP_NAME="Артур · админка"
WEBAUTHN_ALLOWED_ORIGIN=https://arturlun.ru
TELEGRAM_OIDC_CLIENT_ID=...
TELEGRAM_OIDC_CLIENT_SECRET=...
# BotFather → Login Widget: Allowed URL https://arturlun.ru
# Redirect URI: https://arturlun.ru/library/login/telegram/callback
# Library reader: после деплоя — doctrine:migrations:migrate (quotes/notes/PDF fields)

DATABASE_URL="mysql://USER:PASS@localhost:3306/DBNAME?serverVersion=8.0&charset=utf8mb4"

ADMIN_EMAIL=...
ADMIN_PASSWORD=...   # только для app:seed, потом сменить в админке

TELEGRAM_BOT_TOKEN=...
TELEGRAM_CHAT_ID=...
TELEGRAM_CHANNEL_ID=...   # канал для кросспоста хроники (@name или -100…)

# VK кросспост в сообщество
VK_APP_ID=...
VK_APP_SECRET=...   # или VK_SECRET_KEY
VK_SERVICE_TOKEN=...
VK_COMMUNITY_TOKEN=...   # ключ сообщества (Управление → Работа с API)
VK_OWNER_ID=-...         # сообщество: цель кросспоста (минус или короткое имя)
VK_SYNC_OWNER_ID=...     # личная стена → хроника (user id без минуса)
VK_CROSSPOST_ENABLED=1
# User-токен для фото/sync — «Подключить VK» в админке (не обязателен для текста в группу)

MAILER_DSN=smtp://info@arturlun.ru:ПАРОЛЬ@mail.netangels.ru:587
MAILER_FROM=info@arturlun.ru

# Web Push (VAPID) — подписка в браузере + push при новой записи
# php -r 'require "vendor/autoload.php"; print_r(Minishlink\WebPush\VAPID::createVapidKeys());'
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
VAPID_SUBJECT=mailto:info@arturlun.ru

OM_PLAYER_SCRIPT_URL=https://music.arturlun.ru/build/player/om-player.iife.js
OM_PLAYER_API_BASE=https://music.arturlun.ru/api/v1

# Практика + ЮKassa + Kinescope (см. раздел 6 ниже и docs/practice.md)
YOOKASSA_SHOP_ID=
YOOKASSA_SECRET_KEY=
KINESCOPE_WEBHOOK_USER=
KINESCOPE_WEBHOOK_PASSWORD=
# 0 = скрыта с карты/меню, /practice только админ; 1 = публично (+ раскомментировать HouseContent)
PRACTICE_PUBLIC_ENABLED=0

# Сброс кэша CSS/JS у вернувшихся посетителей — bump при каждом фронт-релизе
ASSETS_VERSION=20260723a
```

**VK: сообщество (основной сценарий)**

1. Создай сообщество. Управление → Работа с API → ключ с правом **Стена** → `VK_COMMUNITY_TOKEN`.
2. `VK_OWNER_ID=-<group_id>` (минус обязателен), `VK_CROSSPOST_ENABLED=1`.
3. Чтобы тянуть **личную** стену в хронику — отдельно `VK_SYNC_OWNER_ID=<user_id>` (без минуса) + user-токен.
4. Текст на стену группы уходит community-токеном (`from_group=1`).
5. Для **фото**, правок и импорта стены нужен **user-токен** админа группы / владельца стены:
**User-токен (после смерти Standalone)**

Новые приложения создаются только через [id.vk.ru](https://id.vk.ru/about/business/go). Implicit Flow (`response_type=token` + `wall`) даёт Security Error.

Рабочий путь: `/admin/oauth/vk/start` → VK ID PKCE → callback на `{APP_SITE_URL}/admin/oauth/vk/callback`.

В id.vk.ru для приложения укажи:
- базовый домен (`localhost` или `arturlun.ru`);
- Trusted redirect URI **точно** как `APP_SITE_URL` + `/admin/oauth/vk/callback` (пример локально: `http://localhost:8085/admin/oauth/vk/callback`).

Важно: токен VK ID (`vk2.a.*`) на `api.vk.com` иногда отвечает **1051** — тогда live sync стены недоступен. Запасной путь: экспорт данных VK → corpus/import. Кросспост **текста** в сообщество работает на `VK_COMMUNITY_TOKEN` без user-токена.

В `.env`: `VK_OWNER_ID=-group` (кросспост), `VK_SYNC_OWNER_ID=user_id` (импорт личной стены).


Личная стена через API обычно недоступна без исключительного права от поддержки VK — не целевой режим.

**Telegram-канал**

1. Бот — админ канала. `TELEGRAM_BOT_TOKEN` + `TELEGRAM_CHANNEL_ID` (`@channel` или `-100…`).
2. Чат заявок (`TELEGRAM_CHAT_ID`) — отдельно, не смешивать.
3. В редакторе хроники — галочка «Опубликовать в Telegram-канал».

**VK → хроника (sync)**

Нужны: `VK_CROSSPOST_ENABLED=1`, `VK_SYNC_OWNER_ID` (личный user id) или `VK_OWNER_ID`, user-токен, cron `app:chronicle:sync-vk-wall`, серия `vk-wall` из catalog (опционально). Smoke: `php bin/console app:chronicle:sync-vk-wall --count=20`.

Cron — готовый файл: [`deploy/crontab`](../deploy/crontab).

```bash
crontab /home/c502022/arturlun.ru/om-brand/deploy/crontab
# или: crontab -e  и вставить содержимое файла
```

| Расписание | Команда | Зачем |
|------------|---------|--------|
| каждые 5 мин | `app:chronicle:publish-scheduled` | отложенные посты → published + email/Web Push |
| каждые 15 мин | `app:chronicle:crosspost-vk` | ретраи VK по галочке |
| каждые 15 мин | `app:chronicle:sync-vk-wall` | новые посты со стены VK → хроника |
| каждые 15 мин | `app:chronicle:crosspost-telegram` | ретраи Telegram-канала по галочке |

Логи: `var/log/cron-*.log`. `app:chronicle:notify` — только вручную (повторная рассылка / smoke).

---

## 2. Код (делаешь ты через git)

На сервере, из корня `om-brand`:

```bash
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
# или целиком:
./deploy-script.sh --first          # первый раз (+ app:seed)
./deploy-script.sh                  # обычное обновление
./deploy-script.sh --sync-content   # если меняли LandingContent.php
```

`deploy-script.sh` ждёт `.env`, гоняет миграции и `cache:clear` / warmup.

Права (если ещё не):

```bash
mkdir -p var/cache var/log var/private/uploads \
  public/uploads/{avatars,cases,cases/gallery,cases/audio} \
  public/uploads/chronicle/{covers,inline,gallery}
chmod -R 775 var/ public/uploads/
```

---

## 3. Синк хроники и кейсов (с Mac)

Данные **не в git**. Объём: corpus ~9 МБ + Instagram ~340 МБ + VK ~790 МБ + cases uploads ~1 МБ ≈ **1.1 ГБ**.

На сервере свободно закладывай **~2.5–3 ГБ** на первый прогон.

### 3.1 С Mac (после рабочего SSH)

```bash
cd "/Users/arturlun/projects/ом личный бренд"

# опционально обновить корпус:
# python3 scripts/corpus_build.py --instagram

./sync-prod-content.sh --dry-run   # проверка
./sync-prod-content.sh             # corpus + content/instagram + content/vk + uploads/cases
```

Опции: `--corpus-only`, `--cases-only`, `--skip-media`.

### 3.2 На сервере — импорт

```bash
cd /home/c502022/arturlun.ru/om-brand   # путь из DEPLOY_REMOTE_ROOT

php bin/console app:chronicle:seed-meta --env=prod

php bin/console app:chronicle:import --channel=da-i-da --env=prod
php bin/console app:chronicle:import --channel=instagram --env=prod
php bin/console app:chronicle:import --channel=vk --env=prod

# черновики приватных TG (om / research / culture) + зеркало — только в админке
# медиа: ./sync-prod-content.sh --drafts  (или --corpus-only + --drafts)
php bin/console app:chronicle:import --channel=om --channel=research --channel=culture --env=prod
php bin/console app:chronicle:import --channel=mirror --env=prod

php bin/console app:chronicle:seed-likes --env=prod

# кейсы как черновики (публикуешь сам в админке)
php bin/console app:cases:seed --env=prod

php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

Пока нет опубликованных кейсов, на главной дверка «Кейсы» неактивна: *«Скоро здесь будут кейсы»*.

После проверки картинок на `/chronicle` исходники можно убрать (uploads остаются):

```bash
rm -rf content/instagram content/instagram-heyteaflow content/vk \
  content/ChatExport_2026-07-20_om content/ChatExport_2026-07-20_research \
  content/ChatExport_2026-07-20_culture content/ChatExport_2026-07-20_mirror
# corpus/ лучше оставить — повторный text/title sync без media
```

---

## 4. Чеклист https://arturlun.ru/

- [ ] `/` — дом, комнаты; кейсы — «скоро», если черновики; **практика на карте скрыта**, пока `PRACTICE_PUBLIC_ENABLED=0`
- [ ] `/dev`, `/contact`, `/cases`, `/chronicle`
- [ ] `/practice` — 404 для гостя; у админа (после входа в `/admin`) открывается по прямой ссылке
- [ ] `/admin/login` — вход
- [ ] Картинки хроники (Instagram галереи, VK)
- [ ] Лайки / шаринг `/p/…`
- [ ] Форма заявки → Telegram / email
- [ ] Email/push подписка и notify — см. [notify-smoke-checklist.md](notify-smoke-checklist.md)
- [ ] `view-source`: canonical `https://arturlun.ru`, CSS/JS с актуальным `?v=…`
- [ ] `/robots.txt`, `/sitemap.xml` (практики в sitemap нет, пока не публична)
- [ ] В админке опубликовать нужные кейсы вручную
- [ ] Практика: ЮKassa + Kinescope — см. раздел 6

---

## 5. Повторные релизы

| Что менялось | Действие |
|--------------|----------|
| Код / шаблоны / CSS | `git pull` → `./deploy-script.sh` + bump `ASSETS_VERSION` в `.env` |
| Тексты лендинга | `./deploy-script.sh --sync-content` (только `content_block`; Настройки/кейсы не трогает) |
| Корпус хроники | `./sync-prod-content.sh` (или `--corpus-only`) → import на сервере |
| Только кейсы (первичный импорт) | `php bin/console app:cases:seed --env=prod` (существующие пропускает; `--force` — осознанная перезапись) |
| Обложки кейсов | `./sync-prod-content.sh --cases-only` (rsync без `--delete`; совпадающие имена перезапишет) |

Обычный `./deploy-script.sh` **не** вызывает `app:cases:seed` и **не** перезаписывает правки кейсов/настроек из админки.

Не заливай полный mysqldump локальной БД на прод. Хроника = corpus + import.

---

## 6. Практика на прод: что нужно от тебя

Логика продукта: [practice.md](practice.md). Ниже — чеклист именно **твоих** действий при выкладке этой фичи.

### 6.1 Код и миграция (обычный деплой)

На сервере после `git pull`:

```bash
cd /home/c502022/arturlun.ru/om-brand   # свой путь
./deploy-script.sh
# внутри: composer, doctrine:migrations:migrate, cache:clear
```

Миграция `Version20261014020000` добавляет баланс на `payment_offer` и таблицу `practice_session`. Без неё комната/админка сессий упадут.

Bump `ASSETS_VERSION` в `.env`, если выкатываешь CSS комнаты/кабинета.

### 6.2 Переменные в `.env` на сервере (заполни ты)

| Переменная | Откуда взять | Зачем |
|------------|--------------|--------|
| `YOOKASSA_SHOP_ID` | [ЮKassa](https://yookassa.ru/) → магазин | Создание платежей практики |
| `YOOKASSA_SECRET_KEY` | там же, секретный ключ | API + проверка webhook |
| `KINESCOPE_WEBHOOK_USER` | сам придумаешь при создании webhook в Kinescope | HTTP Basic на `/webhooks/kinescope` |
| `KINESCOPE_WEBHOOK_PASSWORD` | то же | то же |
| `PRACTICE_PUBLIC_ENABLED` | `0` пока дорабатываешь страницу | Скрытие с карты/меню; `/practice` только ROLE_ADMIN |
| `APP_SITE_URL` / `DEFAULT_URI` | `https://arturlun.ru` | return URL ЮKassa, абсолютные ссылки в письмах |
| `MAILER_*` | уже должны быть | письма записи/оплаты/комнаты |
| `TELEGRAM_*` | уже должны быть | уведомления тебе и (если привязан) пользователю |

После правки `.env`: снова `php bin/console cache:clear --env=prod`.

### 6.3 ЮKassa (кабинет ЮKassa)

1. Магазин в режиме, подходящем для живых платежей (или тест — осознанно).
2. **HTTP-уведомления** на URL:

   `https://arturlun.ru/webhooks/yookassa`

   Событие минимум: `payment.succeeded` (наш код ещё сверит платёж через API).
3. В настройках магазина домен return URL должен совпадать с `APP_SITE_URL` (возврат ведёт в `/account?tab=practice&yk_return=1&token=…`).
4. Проверка: тестовая/реальная оплата разовой практики → офер `paid`, письмо про комнату, в админке статус Done у заявки.

### 6.4 Kinescope (кабинет Kinescope)

1. Включи **запись** эфира и чат после стрима:
   - `chat_after_stream` = on  
   - `chat_active` = on (чтобы писать после эфира — обратная связь)
2. Создай **исходящий webhook** (через API, не кабинет):

   ```bash
   php bin/console app:kinescope:setup-webhook
   # на проде APP_SITE_URL=https://arturlun.ru уже в .env
   ```

   URL: `https://arturlun.ru/webhooks/kinescope`, Basic = `KINESCOPE_WEBHOOK_*`, события live/speak/media (см. команду).
3. Для каждой встречи в админке: **Сессии практики** → новая (название, время, Live/Speak) → сохранение **само** создаёт event/room в Kinescope. Live: RTMP в карточке для OBS. Перед эфиром — **В эфир**.
4. После стрима webhook → `replay` + списание. Запасной путь — **«Эфир завершён»**.
5. Домены для embed: разреши `arturlun.ru` в privacy/embed, если ограничил.
6. Speak host JWT: `php bin/console app:kinescope:setup-jwt` + `KINESCOPE_JWT_*` (см. `docs/practice.md`).

### 6.5 Как смотреть страницу, пока она скрыта

1. Войди в `/admin` (админ-сессия общая с сайтом — `context: app`).
2. Открой напрямую: `https://arturlun.ru/practice`
3. Гость и обычный SiteUser без ROLE_ADMIN получат **404**.
4. Комната `/practice/room` и вкладка «Практика» в Своём месте работают у пользователей с оплаченным доступом (для e2e можно оформить заявку под админом или завести тестового пользователя после оплаты).

### 6.6 Когда отдаёшь практику публике

1. В `.env`: `PRACTICE_PUBLIC_ENABLED=1` (или `HouseContent::PRACTICE_PUBLIC_ENABLED = true`).
2. В `HouseContent.php` раскомментируй пункт меню и комнату на карте (TODO-комментарии рядом с практикой).
3. Деплой + cache clear + при желании убрать `Disallow: /practice` из robots.
4. Проверь: `/` показывает дверку, меню содержит «Практика», гость открывает `/practice`, sitemap содержит URL.

### 6.7 Smoke после деплоя практики

- [ ] Миграции applied (`doctrine:migrations:status` — Latest = Current)
- [ ] Админ открывает `/practice`, гость — 404
- [ ] Админка → Сессии практики — CRUD открывается
- [ ] (опционально) тестовая оплата ЮKassa → paid
- [ ] (опционально) тестовый `live.finished` на webhook (curl + Basic) с известным event id → replay + `sessionsLeft` −1
- [ ] Комната показывает embed при live/replay; без них — текст про ближайшую

Пример ручной проверки webhook Kinescope:

```bash
curl -u 'USER:PASS' -X POST 'https://arturlun.ru/webhooks/kinescope' \
  -H 'Content-Type: application/json' \
  -d '{"event":"live.finished","data":{"event_id":"ВАШ_EVENT_ID","video_id":"ВАШ_VIDEO_ID"}}'
```

Ответ ожидается `{"ok":true}`.
