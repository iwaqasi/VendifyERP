#!/usr/bin/env bash
# =============================================================================
# VendifyERP — Automated Shell Upgrade Runner
# =============================================================================
# Usage:
#   bash upgrade.sh [--dry-run] [--skip-backup] [--skip-migrate]
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TARGET_DIR="$SCRIPT_DIR"

if [ -f "$SCRIPT_DIR/artisan" ]; then
    TARGET_DIR="$SCRIPT_DIR"
elif [ -f "$(dirname "$SCRIPT_DIR")/artisan" ]; then
    TARGET_DIR="$(dirname "$SCRIPT_DIR")"
fi

cd "$TARGET_DIR"

PHP_BIN="${PHP_BIN:-php}"
for p in /usr/local/bin/php8.3 /usr/local/bin/php8.2 /usr/local/bin/php8.1 /usr/bin/php; do
    if [ -x "$p" ]; then
        PHP_BIN="$p"
        break
    fi
done

ARTISAN="$PHP_BIN artisan"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log()  { echo -e "${BLUE}[$(date '+%H:%M:%S')] [i]${NC} $1"; }
ok()   { echo -e "${GREEN}[$(date '+%H:%M:%S')] [✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[$(date '+%H:%M:%S')] [!]${NC} $1"; }
fail() { echo -e "${RED}[$(date '+%H:%M:%S')] [✗]${NC} $1"; exit 1; }

DRY_RUN=false
SKIP_BACKUP=false
SKIP_MIGRATE=false

for arg in "$@"; do
    case $arg in
        --dry-run)      DRY_RUN=true ;;
        --skip-backup)  SKIP_BACKUP=true ;;
        --skip-migrate) SKIP_MIGRATE=true ;;
        --help|-h)
            echo "VendifyERP Shell Upgrade Runner"
            echo "Usage: bash upgrade.sh [--dry-run] [--skip-backup] [--skip-migrate]"
            exit 0
            ;;
    esac
done

echo "╔══════════════════════════════════════════════════════════════╗"
echo "║  VendifyERP Automated Production Upgrade Runner              ║"
echo "╚══════════════════════════════════════════════════════════════╝"
log "Target directory: $TARGET_DIR"

[ -f "artisan" ] || fail "artisan not found in $TARGET_DIR."
[ -f ".env" ] || fail ".env not found in $TARGET_DIR."

# Check if upgrade.php exists and run it with PHP for unified engine
if [ -f "$SCRIPT_DIR/upgrade.php" ]; then
    log "Handing over to unified PHP upgrade engine..."
    PHP_ARGS=""
    [ "$DRY_RUN" = true ] && PHP_ARGS="$PHP_ARGS --dry-run"
    [ "$SKIP_BACKUP" = true ] && PHP_ARGS="$PHP_ARGS --skip-backup"
    [ "$SKIP_MIGRATE" = true ] && PHP_ARGS="$PHP_ARGS --skip-migrate"
    
    $PHP_BIN "$SCRIPT_DIR/upgrade.php" $PHP_ARGS
    exit $?
fi

# Fallback direct shell execution if upgrade.php is absent
log "Running direct shell upgrade..."

if [ "$DRY_RUN" = false ]; then
    log "Enabling maintenance mode..."
    $ARTISAN down --render="errors::503" 2>/dev/null || $ARTISAN down || true
fi

# Backup
if [ "$SKIP_BACKUP" = false ] && [ "$DRY_RUN" = false ]; then
    BACKUP_DIR="storage/backups/upgrade-backup-$(date +%Y-%m-%d_%H%M%S)"
    mkdir -p "$BACKUP_DIR"
    log "Preserving existing files in $BACKUP_DIR..."
    [ -d "app" ] && cp -r app "$BACKUP_DIR/" 2>/dev/null || true
    [ -d "config" ] && cp -r config "$BACKUP_DIR/" 2>/dev/null || true
    [ -d "routes" ] && cp -r routes "$BACKUP_DIR/" 2>/dev/null || true
    ok "Rollback backup created in $BACKUP_DIR"
fi

# Migrations
if [ "$SKIP_MIGRATE" = false ] && [ "$DRY_RUN" = false ]; then
    log "Running database migrations..."
    $ARTISAN migrate --force
    ok "Migrations complete"
fi

# Caches
if [ "$DRY_RUN" = false ]; then
    log "Clearing and compiling production caches..."
    $ARTISAN config:clear
    $ARTISAN route:clear
    $ARTISAN view:clear
    $ARTISAN cache:clear
    $ARTISAN config:cache
    $ARTISAN route:cache
    $ARTISAN view:cache
    $ARTISAN package:discover --ansi
    ok "Caches compiled"
    
    chmod -R 775 storage 2>/dev/null || true
    chmod -R 775 bootstrap/cache 2>/dev/null || true
    
    log "Disabling maintenance mode..."
    $ARTISAN up
    ok "Site is live!"
fi

ok "Upgrade completed successfully!"

