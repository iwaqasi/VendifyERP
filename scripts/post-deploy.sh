#!/usr/bin/env bash
# =============================================================================
# cPanel Post-Deploy Hook
# =============================================================================
# Place this in: /home/youruser/VendifyERP/scripts/post-deploy.sh
# Then in cPanel → Git Version Control → Repository → Deployment →
#   Post-Deployment Hook: /home/youruser/VendifyERP/scripts/post-deploy.sh
#
# Or symlink it:
#   ln -sf /home/youruser/VendifyERP/scripts/post-deploy.sh /home/youruser/VendifyERP/.post-deploy
# =============================================================================

set -euo pipefail

# Auto-detect project root from script location
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
APP_DIR="$(dirname "$SCRIPT_DIR")"

cd "$APP_DIR"

# Detect PHP binary (cPanel often has multiple versions)
PHP_BIN=""
for php in /usr/local/bin/php8.3 /usr/local/bin/php8.2 /usr/local/bin/php8.1 /usr/bin/php; do
    if [ -x "$php" ]; then
        PHP_BIN="$php"
        break
    fi
done
[ -z "$PHP_BIN" ] && PHP_BIN="php"

echo "[$(date)] Post-deploy started (PHP: $PHP_BIN)"

# Install dependencies
composer install --prefer-dist --no-interaction --no-dev --no-scripts --no-progress 2>/dev/null

# Migrations
$PHP_BIN artisan migrate --force 2>/dev/null

# Clear all caches
$PHP_BIN artisan config:clear 2>/dev/null
$PHP_BIN artisan route:clear 2>/dev/null
$PHP_BIN artisan view:clear 2>/dev/null
$PHP_BIN artisan cache:clear 2>/dev/null

# Rebuild production caches
$PHP_BIN artisan config:cache 2>/dev/null
$PHP_BIN artisan route:cache 2>/dev/null
$PHP_BIN artisan view:cache 2>/dev/null

# Fix permissions
chmod -R 775 storage 2>/dev/null
chmod -R 775 bootstrap/cache 2>/dev/null
chmod 640 .env 2>/dev/null

# Package discovery
$PHP_BIN artisan package:discover --ansi 2>/dev/null

echo "[$(date)] Post-deploy complete ✓"
