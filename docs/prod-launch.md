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

- [ ] `/` — дом, комнаты; кейсы — «скоро», если черновики
- [ ] `/dev`, `/contact`, `/cases`, `/chronicle`
- [ ] `/admin/login` — вход
- [ ] Картинки хроники (Instagram галереи, VK)
- [ ] Лайки / шаринг `/p/…`
- [ ] Форма заявки → Telegram / email
- [ ] Email/push подписка и notify — см. [notify-smoke-checklist.md](notify-smoke-checklist.md)
- [ ] `view-source`: canonical `https://arturlun.ru`, CSS/JS с `?v=20260723a`
- [ ] `/robots.txt`, `/sitemap.xml`
- [ ] В админке опубликовать нужные кейсы вручную

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
