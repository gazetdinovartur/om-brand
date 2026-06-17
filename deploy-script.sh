#!/usr/bin/env bash
#
# Деплой Symfony-проекта на VPS / shared-хостинг (NetAngels и аналоги).
# Запускать из корня репозитория по SSH, после настройки .env.local.
#
# Первый деплой:   ./deploy-script.sh --first
# Обновление:     ./deploy-script.sh
# + sync текстов: ./deploy-script.sh --sync-content
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

usage() {
    cat <<'EOF'
Использование: ./deploy-script.sh [опции]

Опции:
  --first          Первый деплой: seed (настройки, блоки, админ)
  --sync-content   Перезаписать блоки из LandingContent.php
  --skip-git       Не выполнять git pull
  --skip-composer  Не выполнять composer install
  -h, --help       Эта справка

Перед первым запуском вручную:
  1. PHP ≥ 8.4, document root → public/
  2. MySQL: БД и пользователь созданы
  3. cp .env.example .env.local — заполнить prod-переменные
  4. git clone / загрузка кода на сервер
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --first) FIRST_DEPLOY=1 ;;
        --sync-content) SYNC_CONTENT=1 ;;
        --skip-git) SKIP_GIT=1 ;;
        --skip-composer) SKIP_COMPOSER=1 ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Неизвестная опция: $1" >&2; usage; exit 1 ;;
    esac
    shift
done

log() { echo "→ $*"; }
fail() { echo "✗ $*" >&2; exit 1; }

# --- проверки ---

[[ -f bin/console ]] || fail "Запустите скрипт из корня проекта (где bin/console)."

if [[ ! -f .env.local ]]; then
    fail "Нет .env.local. Скопируйте: cp .env.example .env.local и задайте prod-переменные."
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
mkdir -p public/uploads/avatars public/uploads/cases

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

# --- кеш Symfony ---

log "Очистка и прогрев кеша (prod)"
$CONSOLE cache:clear --env=prod --no-warmup
$CONSOLE cache:warmup --env=prod

echo ""
echo "✓ Деплой завершён."
echo "  Проверьте: HTTPS, /admin/login, форма заявки, var/log/prod.log при ошибках."
