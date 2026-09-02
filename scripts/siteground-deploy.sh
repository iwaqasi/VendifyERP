#!/usr/bin/env bash
# =============================================================================
# SiteGround Deployment Script for VendifyERP
# =============================================================================
#
# SiteGround uses Site Tools (not cPanel). This script works with:
#
# Option A: SSH (GrowBig/GoGeek plans)
#   ssh youruser@server
#   cd /home/youruser/VendifyERP
#   bash scripts/siteground-deploy.sh
#
# Option B: Site Tools → Devs → Cron Jobs
#   Set up a cron job to run this script after your Git pull
#
# Option C: Site Tools → Devs → Git
#   SiteGround's Git integration auto-pulls from GitHub.
#   Use this script as a post-pull hook.
# =============================================================================

set -euo pipefail

# ─── Configuration ────────────────────────────────────────────────────────────
# SiteGround paths (adjust if your setup differs)
APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"

# Auto-detect PHP (SiteGround provides multiple versions)
PHP_BIN=""
for php in /usr/local/bin/php8.3 /usr/local/bin/php8.2 /usr/local/bin/php8.1 /usr/bin/php; do
    if [ -x "$php" ]; then
        PHP_BIN="$php"
        break
    fi
done
[ -z "$PHP_BIN" ] && PHP_BIN="php"

cd "$APP_DIR"

echo "╔══════════════════════════════════════════════════╗"
echo "║  VendifyERP Deployment — SiteGround              ║"
echo "║  $(date '+%Y-%m-%d %H:%M:%S')                          ║"
echo "╚══════════════════════════════════════════════════╝"
echo ""

# ─── Step 1: Install Dependencies ────────────────────────────────────────────
echo "→ Installing composer dependencies..."
composer install --prefer-dist --no-interaction --no-dev --no-scripts --no-progress 2>&1 | tail -2
echo "  ✓ Done"
echo ""

# ─── Step 2: Run Migrations ──────────────────────────────────────────────────
echo "→ Running database migrations..."
$PHP_BIN artisan migrate --force 2>&1 | tail -3
echo "  ✓ Done"
echo ""

# ─── Step 3: Clear All Caches ────────────────────────────────────────────────
echo "→ Clearing caches..."
$PHP_BIN artisan config:clear 2>/dev/null && echo "  ✓ Config cleared"
$PHP_BIN artisan route:clear 2>/dev/null && echo "  ✓ Routes cleared"
$PHP_BIN artisan view:clear 2>/dev/null && echo "  ✓ Views cleared"
$PHP_BIN artisan cache:clear 2>/dev/null && echo "  ✓ Cache cleared"
$PHP_BIN artisan event:clear 2>/dev/null && echo "  ✓ Events cleared"
echo ""

# ─── Step 4: Rebuild Production Caches ───────────────────────────────────────
echo "→ Building production caches..."
$PHP_BIN artisan config:cache 2>/dev/null && echo "  ✓ Config cached"
$PHP_BIN artisan route:cache 2>/dev/null && echo "  ✓ Routes cached"
$PHP_BIN artisan view:cache 2>/dev/null && echo "  ✓ Views compiled"
echo ""

# ─── Step 5: Fix Permissions ─────────────────────────────────────────────────
echo "→ Setting permissions..."
chmod -R 775 storage 2>/dev/null || true
chmod -R 775 bootstrap/cache 2>/dev/null || true
chmod 640 .env 2>/dev/null || true
echo "  ✓ Done"
echo ""

# ─── Step 6: Package Discovery ───────────────────────────────────────────────
echo "→ Discovering packages..."
$PHP_BIN artisan package:discover --ansi 2>/dev/null
echo "  ✓ Done"
echo ""

# ─── Step 7: Optimizations ───────────────────────────────────────────────────
echo "→ Running optimizations..."
$PHP_BIN artisan optimize 2>/dev/null
echo "  ✓ Done"
echo ""

echo "╔══════════════════════════════════════════════════╗"
echo "║  Deployment complete! 🚀                         ║"
echo "║  Site: https://erp.arksoftsolutions.com          ║"
echo "╚══════════════════════════════════════════════════╝"
