#!/usr/bin/env bash
#
# Деплой Symfony-проекта на VPS / shared-хостинг (NetAngels и аналоги).
# Запускать из корня репозитория по SSH, после настройки .env.
#
# Первый деплой:   ./deploy-script.sh --first
# Обновление:     ./deploy-script.sh
# + sync текстов: ./deploy-script.sh --sync-content
# + сброс css/js: ./deploy-script.sh --bust-assets
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

PHP="${PHP_BIN:-php}"
COMPOSER="${COMPOSER_BIN:-composer}"
CONSOLE="$PHP bin/console"

FIRST_DEPLOY=0
SYNC_CONTENT=0
SKIP_GIT=0
SKIP_COMPOSER=0
BUST_ASSETS=0

usage() {
    cat <<'EOF'
Использование: ./deploy-script.sh [опции]

Опции:
  --first          Первый деплой: seed (настройки, блоки, админ)
  --sync-content   Перезаписать блоки из LandingContent.php
  --bust-assets    Сбросить кэш css/js: bump ASSETS_VERSION в .env
  --skip-git       Не выполнять git pull
  --skip-composer  Не выполнять composer install
  -h, --help       Эта справка

Перед первым запуском вручную:
  1. PHP ≥ 8.4, document root → public/
  2. MySQL: БД и пользователь созданы
  3. cp .env.example .env — заполнить prod-переменные (только .env, без .env.local)
  4. git clone / загрузка кода на сервер

Маршруты после деплоя: / (дом), /dev--null, /contact, /cases, /chronicle, /admin
Тексты дома: src/Content/HouseContent.php (без sync).
Блоки лендинга: LandingContent.php → --sync-content при необходимости.
Лайки после импорта VK: php bin/console app:chronicle:seed-likes --env=prod

Кеш Symfony (var/cache) очищается и прогревается при каждом деплое.
Браузерный кэш статитки (css/js ?v=): ./deploy-script.sh --bust-assets
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --first) FIRST_DEPLOY=1 ;;
        --sync-content) SYNC_CONTENT=1 ;;
        --bust-assets) BUST_ASSETS=1 ;;
        --skip-git) SKIP_GIT=1 ;;
        --skip-composer) SKIP_COMPOSER=1 ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Неизвестная опция: $1" >&2; usage; exit 1 ;;
    esac
    shift
done

log() { echo "→ $*"; }
fail() { echo "✗ $*" >&2; exit 1; }

bump_assets_version() {
    local new_ver old_ver tmp
    new_ver="$(date -u +%Y%m%d%H%M)"
    tmp="$(mktemp)"
    if grep -qE '^ASSETS_VERSION=' .env; then
        old_ver="$(grep -E '^ASSETS_VERSION=' .env | head -n1 | cut -d= -f2-)"
        awk -v ver="$new_ver" '
            BEGIN { done = 0 }
            /^ASSETS_VERSION=/ {
                if (!done) { print "ASSETS_VERSION=" ver; done = 1; next }
            }
            { print }
            END { if (!done) print "ASSETS_VERSION=" ver }
        ' .env > "$tmp"
        mv "$tmp" .env
        log "ASSETS_VERSION: ${old_ver} → ${new_ver}"
    else
        printf '\nASSETS_VERSION=%s\n' "$new_ver" >> .env
        log "ASSETS_VERSION: (не было) → ${new_ver}"
    fi
}

# --- проверки ---

[[ -f bin/console ]] || fail "Запустите скрипт из корня проекта (где bin/console)."

if [[ ! -f .env ]]; then
    fail "Нет .env. Скопируйте: cp .env.example .env и задайте prod-переменные."
fi

if ! command -v "$PHP" &>/dev/null; then
    fail "PHP не найден ($PHP). Укажите: PHP_BIN=/path/to/php ./deploy-script.sh"
fi

PHP_VERSION="$("$PHP" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
PHP_MAJOR="${PHP_VERSION%%.*}"
PHP_MINOR="${PHP_VERSION#*.}"
if (( PHP_MAJOR < 8 )) || (( PHP_MAJOR == 8 && PHP_MINOR < 4 )); then
    fail "Нужен PHP ≥ 8.4, сейчас: $PHP_VERSION"
fi

log "PHP $PHP_VERSION"

# --- обновление кода ---

if [[ $SKIP_GIT -eq 0 ]] && [[ -d .git ]]; then
    log "git pull"
    git pull --ff-only
elif [[ $SKIP_GIT -eq 0 ]] && [[ ! -d .git ]]; then
    log "Пропуск git pull (нет .git). Используйте --skip-git, если так и задумано."
fi

# --- зависимости ---

if [[ $SKIP_COMPOSER -eq 0 ]]; then
    if ! command -v "$COMPOSER" &>/dev/null; then
        fail "Composer не найден. Установите или: COMPOSER_BIN=/path/to/composer ./deploy-script.sh"
    fi
    log "composer install --no-dev --optimize-autoloader"
    "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction
fi

# --- каталоги и права ---

log "Каталоги для cache, log и uploads"
mkdir -p var/cache var/log var/private/uploads
mkdir -p \
    public/uploads/avatars \
    public/uploads/cases \
    public/uploads/cases/gallery \
    public/uploads/cases/audio \
    public/uploads/chronicle/covers \
    public/uploads/chronicle/inline \
    public/uploads/chronicle/gallery

chmod -R ug+rwx var/ 2>/dev/null || true
chmod -R ug+rwx public/uploads/ 2>/dev/null || true

# --- база данных ---

log "Миграции БД"
$CONSOLE doctrine:migrations:migrate --no-interaction --env=prod

if [[ $FIRST_DEPLOY -eq 1 ]]; then
    log "Первый деплой: app:seed"
    $CONSOLE app:seed --env=prod
elif [[ $SYNC_CONTENT -eq 1 ]]; then
    log "Синхронизация контента из LandingContent.php"
    $CONSOLE app:content:sync --env=prod
fi

# --- браузерный кэш css/js ---

if [[ $BUST_ASSETS -eq 1 ]]; then
    bump_assets_version
fi

# --- кеш Symfony ---

log "Очистка и прогрев кеша (prod)"
$CONSOLE cache:clear --env=prod --no-warmup
$CONSOLE cache:warmup --env=prod

echo ""
echo "✓ Деплой завершён."
echo "  Проверьте: HTTPS, / , /dev--null , /contact , /cases , /chronicle ,"
echo "  /admin/login , форма заявки, var/log/prod.log при ошибках."
echo "  После импорта VK / фиксации лайков: php bin/console app:chronicle:seed-likes --env=prod"
if [[ $BUST_ASSETS -eq 1 ]]; then
    echo "  ASSETS_VERSION обновлён — у посетителей подтянутся новые css/js."
fi
