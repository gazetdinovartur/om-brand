#!/usr/bin/env bash
#
# Локальный sync хроники и кейсов на прод (данные не в git).
# Код на сервер — отдельно через git. Запуск с машины разработчика.
#
# Требует в .env:
#   DEPLOY_SSH=c502022@91.201.52.29
#   DEPLOY_REMOTE_ROOT=/home/c502022/arturlun.ru/om-brand
#   DEPLOY_SSH_PORT=22
#   DEPLOY_SSH_IDENTITY=~/.ssh/arturlun_deploy
#
# Использование:
#   ./sync-prod-content.sh              # corpus + media + cases uploads
#   ./sync-prod-content.sh --dry-run
#   ./sync-prod-content.sh --corpus-only
#   ./sync-prod-content.sh --cases-only
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

DRY_RUN=0
CORPUS_ONLY=0
CASES_ONLY=0
SKIP_MEDIA=0

usage() {
    cat <<'EOF'
Использование: ./sync-prod-content.sh [опции]

  --dry-run       Показать rsync без записи
  --corpus-only   Только corpus/ (без content/instagram|vk и cases)
  --cases-only    Только public/uploads/cases/
  --skip-media    Corpus + cases, без content/instagram|vk
  -h, --help      Справка

После rsync на сервере выполните команды из docs/prod-launch.md (§ Синк).
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run) DRY_RUN=1 ;;
        --corpus-only) CORPUS_ONLY=1 ;;
        --cases-only) CASES_ONLY=1 ;;
        --skip-media) SKIP_MEDIA=1 ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Неизвестная опция: $1" >&2; usage; exit 1 ;;
    esac
    shift
done

fail() { echo "✗ $*" >&2; exit 1; }
log() { echo "→ $*"; }

[[ -f .env ]] || fail "Нет .env"

DEPLOY_SSH="$(grep -E '^DEPLOY_SSH=' .env | tail -1 | cut -d= -f2- | tr -d '\r' | sed 's/^["'\'']//;s/["'\'']$//')"
DEPLOY_REMOTE_ROOT="$(grep -E '^DEPLOY_REMOTE_ROOT=' .env | tail -1 | cut -d= -f2- | tr -d '\r' | sed 's/^["'\'']//;s/["'\'']$//')"
DEPLOY_SSH_PORT="$(grep -E '^DEPLOY_SSH_PORT=' .env | tail -1 | cut -d= -f2- | tr -d '\r' | sed 's/^["'\'']//;s/["'\'']$//')"
DEPLOY_SSH_IDENTITY="$(grep -E '^DEPLOY_SSH_IDENTITY=' .env | tail -1 | cut -d= -f2- | tr -d '\r' | sed 's/^["'\'']//;s/["'\'']$//')"

[[ -n "$DEPLOY_SSH" ]] || fail "Задайте DEPLOY_SSH в .env"
[[ -n "$DEPLOY_REMOTE_ROOT" ]] || fail "Задайте DEPLOY_REMOTE_ROOT в .env"

PORT="${DEPLOY_SSH_PORT:-22}"
IDENTITY="${DEPLOY_SSH_IDENTITY/#\~/$HOME}"
[[ -f "$IDENTITY" ]] || fail "Нет ключа: $IDENTITY"

RSYNC_RSH="ssh -p ${PORT} -i ${IDENTITY} -o IdentitiesOnly=yes"
RSYNC_FLAGS=(-avz --progress)
[[ $DRY_RUN -eq 1 ]] && RSYNC_FLAGS+=(--dry-run)

REMOTE="${DEPLOY_SSH}:${DEPLOY_REMOTE_ROOT}"

rsync_to() {
    local src="$1"
    local dest="$2"
    [[ -e "$src" ]] || fail "Нет локально: $src"
    log "rsync $src → $dest"
    rsync "${RSYNC_FLAGS[@]}" -e "$RSYNC_RSH" "$src" "$dest"
}

log "Проверка SSH…"
$RSYNC_RSH "$DEPLOY_SSH" "test -d '$DEPLOY_REMOTE_ROOT' && echo OK_REMOTE || echo NO_REMOTE"

if [[ $CASES_ONLY -eq 1 ]]; then
    $RSYNC_RSH "$DEPLOY_SSH" "mkdir -p '$DEPLOY_REMOTE_ROOT/public/uploads/cases/gallery' '$DEPLOY_REMOTE_ROOT/public/uploads/cases/audio'"
    rsync_to "public/uploads/cases/" "$REMOTE/public/uploads/cases/"
elif [[ $CORPUS_ONLY -eq 1 ]]; then
    [[ -f corpus/chronicle_entries.jsonl ]] || fail "Нет corpus/chronicle_entries.jsonl"
    $RSYNC_RSH "$DEPLOY_SSH" "mkdir -p '$DEPLOY_REMOTE_ROOT/corpus'"
    rsync_to "corpus/" "$REMOTE/corpus/"
else
    [[ -f corpus/chronicle_entries.jsonl ]] || fail "Нет corpus/chronicle_entries.jsonl"
    $RSYNC_RSH "$DEPLOY_SSH" "mkdir -p \
        '$DEPLOY_REMOTE_ROOT/corpus' \
        '$DEPLOY_REMOTE_ROOT/content/instagram' \
        '$DEPLOY_REMOTE_ROOT/content/vk' \
        '$DEPLOY_REMOTE_ROOT/public/uploads/cases/gallery' \
        '$DEPLOY_REMOTE_ROOT/public/uploads/cases/audio' \
        '$DEPLOY_REMOTE_ROOT/public/uploads/chronicle/covers' \
        '$DEPLOY_REMOTE_ROOT/public/uploads/chronicle/inline' \
        '$DEPLOY_REMOTE_ROOT/public/uploads/chronicle/gallery'"

    rsync_to "corpus/" "$REMOTE/corpus/"
    rsync_to "public/uploads/cases/" "$REMOTE/public/uploads/cases/"

    if [[ $SKIP_MEDIA -eq 0 ]]; then
        rsync_to "content/instagram/" "$REMOTE/content/instagram/"
        rsync_to "content/vk/" "$REMOTE/content/vk/"
    else
        log "Пропуск content/instagram и content/vk (--skip-media)"
    fi
fi

echo ""
echo "✓ Sync файлов завершён${DRY_RUN:+ (dry-run)}."
echo ""
echo "Дальше по SSH на проде (см. docs/prod-launch.md):"
echo "  ssh -p $PORT -i $IDENTITY $DEPLOY_SSH"
echo "  cd $DEPLOY_REMOTE_ROOT"
echo "  php bin/console app:chronicle:seed-meta --env=prod"
echo "  php bin/console app:chronicle:import --channel=da-i-da --env=prod"
echo "  php bin/console app:chronicle:import --channel=instagram --env=prod"
echo "  php bin/console app:chronicle:import --channel=vk --env=prod"
echo "  php bin/console app:chronicle:seed-likes --env=prod"
echo "  php bin/console app:cases:seed --env=prod"
echo "  php bin/console cache:clear --env=prod"
