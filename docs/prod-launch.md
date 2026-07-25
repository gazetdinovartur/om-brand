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

DATABASE_URL="mysql://USER:PASS@localhost:3306/DBNAME?serverVersion=8.0&charset=utf8mb4"

ADMIN_EMAIL=...
ADMIN_PASSWORD=...   # только для app:seed, потом сменить в админке

TELEGRAM_BOT_TOKEN=...
TELEGRAM_CHAT_ID=...

# VK кросспост (callback https://arturlun.ru/oauth/vk/callback)
VK_APP_ID=...
VK_APP_SECRET=...   # или VK_SECRET_KEY
VK_SERVICE_TOKEN=...
VK_OWNER_ID=...     # числовой id (см. ниже); после OAuth показывается на экране успеха
VK_CROSSPOST_ENABLED=1
# VK_USER_ACCESS_TOKEN не обязателен вручную — см. «Подключить VK»

MAILER_DSN=smtp://info@arturlun.ru:ПАРОЛЬ@mail.netangels.ru:587
MAILER_FROM=info@arturlun.ru

OM_PLAYER_SCRIPT_URL=https://music.arturlun.ru/build/player/om-player.iife.js
OM_PLAYER_API_BASE=https://music.arturlun.ru/api/v1

# Сброс кэша CSS/JS у вернувшихся посетителей — bump при каждом фронт-релизе
ASSETS_VERSION=20260723a
```

**Откуда взять `VK_USER_ACCESS_TOKEN` и `VK_OWNER_ID`**

1. В `.env` задай `VK_APP_ID` + `VK_APP_SECRET` (или `VK_SECRET_KEY`), `VK_CROSSPOST_ENABLED=1`.
2. Задеплой, зайди в админку под собой.
3. В редакторе хроники нажми **«Подключить VK»** → разреши `wall`, `photos`, `offline`.
4. После редиректа на `/oauth/vk/callback`:
   - токен сохранится в `var/vk/user_access_token` (gitignored) — **в `.env` копировать не обязательно**;
   - на экране будет `user_id: …` — это и есть **`VK_OWNER_ID`** (положительное число страницы arturlun). Пропиши его в `.env`.
5. Альтернатива `VK_OWNER_ID`: страница VK → URL вида `vk.com/id123456` (не короткое имя `arturlun`).

`VK_SERVICE_TOKEN` / service key для постинга на **личную** стену не заменяет user token.

Cron (каждые 15 мин) — отложенная публикация + кросспост + sync стены:

```cron
*/15 * * * * cd /home/c502022/arturlun.ru/om-brand && php bin/console app:chronicle:crosspost-vk --env=prod >/dev/null 2>&1
*/15 * * * * cd /home/c502022/arturlun.ru/om-brand && php bin/console app:chronicle:sync-vk-wall --env=prod >/dev/null 2>&1
```

После деплоя: в админке редактора → «Подключить VK» (scopes wall,photos,offline).

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
- [ ] `/dev--null`, `/contact`, `/cases`, `/chronicle`
- [ ] `/admin/login` — вход
- [ ] Картинки хроники (Instagram галереи, VK)
- [ ] Лайки / шаринг `/p/…`
- [ ] Форма заявки → Telegram / email
- [ ] `view-source`: canonical `https://arturlun.ru`, CSS/JS с `?v=20260723a`
- [ ] `/robots.txt`, `/sitemap.xml`
- [ ] В админке опубликовать нужные кейсы вручную

---

## 5. Повторные релизы

| Что менялось | Действие |
|--------------|----------|
| Код / шаблоны / CSS | `git pull` → `./deploy-script.sh` + bump `ASSETS_VERSION` в `.env` |
| Тексты лендинга | `./deploy-script.sh --sync-content` |
| Корпус хроники | `./sync-prod-content.sh` (или `--corpus-only`) → import на сервере |
| Только кейсы (тексты из кода) | `php bin/console app:cases:seed --env=prod` (± `--force`) |
| Обложки кейсов | `./sync-prod-content.sh --cases-only` |

Не заливай полный mysqldump локальной БД на прод. Хроника = corpus + import.
