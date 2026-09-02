#!/usr/bin/env bash
# =============================================================================
# VendifyERP Production Deployment Script
# =============================================================================
# Usage:
#   ./deploy.sh              # Full deploy (code + migrations + cache + Flutter)
#   ./deploy.sh --no-flutter # Skip Flutter builds (backend-only changes)
#   ./deploy.sh --migrate    # Only run migrations + cache clear
# =============================================================================

set -euo pipefail

# ─── Configuration ────────────────────────────────────────────────────────────
APP_DIR="$(cd "$(dirname "$0")" && pwd)"
PHP_BIN="${PHP_BIN:-php}"
ARTISAN="$PHP_BIN artisan"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log()   { echo -e "${BLUE}[$(date '+%H:%M:%S')]${NC} $1"; }
ok()    { echo -e "${GREEN}[✓]${NC} $1"; }
warn()  { echo -e "${YELLOW}[!]${NC} $1"; }
fail()  { echo -e "${RED}[✗]${NC} $1"; exit 1; }

# ─── Parse Arguments ──────────────────────────────────────────────────────────
SKIP_FLUTTER=false
MIGRATE_ONLY=false

for arg in "$@"; do
    case $arg in
        --no-flutter)  SKIP_FLUTTER=true ;;
        --migrate)     MIGRATE_ONLY=true ;;
        --help|-h)
            echo "Usage: ./deploy.sh [--no-flutter] [--migrate]"
            echo "  --no-flutter  Skip Flutter web builds (backend-only changes)"
            echo "  --migrate     Only run migrations + cache clear"
            exit 0
            ;;
    esac
done

# ─── Pre-flight Checks ───────────────────────────────────────────────────────
log "Starting deployment..."

cd "$APP_DIR"

# Check PHP
command -v "$PHP_BIN" >/dev/null 2>&1 || fail "PHP not found. Set PHP_BIN or install PHP."

# Check artisan exists
[ -f "artisan" ] || fail "artisan not found. Are you in the Laravel root?"

# Check .env exists
[ -f ".env" ] || fail ".env not found. Copy .env.example to .env and configure it."

echo ""

# ─── Step 1: Maintenance Mode ────────────────────────────────────────────────
if [ "$MIGRATE_ONLY" = false ]; then
    log "Enabling maintenance mode..."
    $ARTISAN down --render="errors::503" 2>/dev/null || $ARTISAN down
    ok "Maintenance mode enabled"
fi

# ─── Step 2: Pull Latest Code ────────────────────────────────────────────────
if [ "$MIGRATE_ONLY" = false ]; then
    log "Pulling latest code from origin/main..."
    git fetch origin main
    LOCAL=$(git rev-parse HEAD)
    REMOTE=$(git rev-parse origin/main)

    if [ "$LOCAL" = "$REMOTE" ]; then
        ok "Already up to date"
    else
        git pull origin main
        ok "Updated to $(git log --oneline -1)"
    fi
    echo ""
fi

# ─── Step 3: Install Dependencies ────────────────────────────────────────────
if [ "$MIGRATE_ONLY" = false ]; then
    log "Installing composer dependencies..."
    composer install --prefer-dist --no-interaction --no-dev --no-scripts --no-progress 2>&1 | tail -1
    ok "Composer dependencies installed"
    echo ""
fi

# ─── Step 4: Run Migrations ──────────────────────────────────────────────────
log "Running database migrations..."
$ARTISAN migrate --force 2>&1 | tail -3
ok "Migrations complete"
echo ""

# ─── Step 5: Clear & Rebuild Caches ──────────────────────────────────────────
log "Clearing and rebuilding caches..."

$ARTISAN config:clear
ok "Config cache cleared"

$ARTISAN route:clear
ok "Route cache cleared"

$ARTISAN view:clear
ok "View cache cleared"

$ARTISAN cache:clear
ok "Application cache cleared"

# Rebuild caches for production performance
$ARTISAN config:cache
ok "Config cached"

$ARTISAN route:cache
ok "Routes cached"

$ARTISAN view:cache
ok "Views compiled"

echo ""

# ─── Step 6: Fix Permissions ─────────────────────────────────────────────────
log "Setting file permissions..."
chmod -R 775 storage 2>/dev/null || true
chmod -R 775 bootstrap/cache 2>/dev/null || true
chmod -R 775 public/uploads 2>/dev/null || true
# Ensure .env is not world-readable
chmod 640 .env 2>/dev/null || true
ok "Permissions set"
echo ""

# ─── Step 7: Discover Packages ────────────────────────────────────────────────
log "Discovering packages..."
$ARTISAN package:discover --ansi
ok "Packages discovered"
echo ""

# ─── Step 8: Build Flutter Apps (optional) ────────────────────────────────────
if [ "$SKIP_FLUTTER" = false ] && [ "$MIGRATE_ONLY" = false ]; then
    log "Building Flutter web apps..."

    # Check if Flutter is available
    if command -v flutter >/dev/null 2>&1; then
        # Build POS
        log "  Building VendifyPOS..."
        cd vendify_pos
        flutter pub get --quiet
        flutter build web --release --dart-define=API_BASE_URL=https://erp.arksoftsolutions.com/api 2>&1 | tail -1
        rm -rf ../public/vendify-pos
        cp -r build/web ../public/vendify-pos
        cd ..
        ok "VendifyPOS built and deployed to public/vendify-pos/"

        # Build CMS
        log "  Building VendifyCMS..."
        cd vendify_cms
        flutter pub get --quiet
        flutter build web --release 2>&1 | tail -1
        rm -rf ../public/vendify-cms
        cp -r build/web ../public/vendify-cms
        cd ..
        ok "VendifyCMS built and deployed to public/vendify-cms/"

        echo ""
    else
        warn "Flutter not found — skipping web builds."
        warn "Install Flutter or build manually before deploying."
        echo ""
    fi
fi

# ─── Step 9: Restart Queue Workers ───────────────────────────────────────────
if [ "$MIGRATE_ONLY" = false ]; then
    log "Restarting queue workers..."
    $ARTISAN queue:restart 2>/dev/null && ok "Queue workers restarted" || warn "Queue restart skipped (no workers running)"
    echo ""
fi

# ─── Step 10: Disable Maintenance Mode ───────────────────────────────────────
if [ "$MIGRATE_ONLY" = false ]; then
    log "Disabling maintenance mode..."
    $ARTISAN up
    ok "Site is live!"
fi

echo ""
echo -e "${GREEN}══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  Deployment complete! 🚀${NC}"
echo -e "${GREEN}  $(date '+%Y-%m-%d %H:%M:%S')${NC}"
echo -e "${GREEN}══════════════════════════════════════════════════════════════${NC}"
