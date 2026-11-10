#!/bin/bash
# =============================================================================
# VendifyERP - Deployment Zip Creator for SiteGround
# Creates a production-ready zip package excluding dev/test files
# =============================================================================

set -e

echo "================================================"
echo "  VendifyERP Deployment Package Builder"
echo "================================================"
echo ""

# Configuration
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUTPUT_FILE="$PROJECT_ROOT/vendify-deploy-$(date +%Y%m%d-%H%M%S).zip"
TEMP_DIR="$PROJECT_ROOT/.deploy-temp"

# Clean up any previous build
rm -rf "$TEMP_DIR"
mkdir -p "$TEMP_DIR"

echo "[1/4] Preparing production composer dependencies..."
cd "$PROJECT_ROOT"

# Install production-only composer dependencies
composer install \
    --prefer-dist \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --no-autoloader \
    2>/dev/null || echo "  ⚠️  Composer install skipped (vendor will be included as-is)"

# Generate optimized autoloader
composer dump-autoload --optimize --no-dev 2>/dev/null || true

echo "[2/4] Copying production files..."

# List of directories/files to INCLUDE (everything else excluded)
INCLUDE_DIRS=(
    "app"
    "bootstrap"
    "config"
    "custom_views"
    "database"
    "lang"
    "Modules"
    "public"
    "resources"
    "routes"
    "storage"
    "vendor"
)

INCLUDE_FILES=(
    ".env.example"
    ".htaccess"
    "artisan"
    "composer.json"
    "composer.lock"
    "modules_statuses.json"
    "readme.md"
)

# Copy included directories
for dir in "${INCLUDE_DIRS[@]}"; do
    if [ -d "$PROJECT_ROOT/$dir" ]; then
        echo "  📁 $dir/"
        cp -r "$PROJECT_ROOT/$dir" "$TEMP_DIR/$dir"
    fi
done

# Copy included files
for file in "${INCLUDE_FILES[@]}"; do
    if [ -f "$PROJECT_ROOT/$file" ]; then
        echo "  📄 $file"
        cp "$PROJECT_ROOT/$file" "$TEMP_DIR/$file"
    fi
done

echo "[3/4] Cleaning up unnecessary files..."

# Remove development files from vendor
find "$TEMP_DIR/vendor" -name "*.md" -type f 2>/dev/null | xargs rm -f
find "$TEMP_DIR/vendor" -name "*.txt" -type f 2>/dev/null | xargs rm -f
find "$TEMP_DIR/vendor" -name "*.xml" -type f 2>/dev/null | xargs rm -f
find "$TEMP_DIR/vendor" -name "*.dist" -type f 2>/dev/null | xargs rm -f
find "$TEMP_DIR/vendor" -name "*.neon" -type f 2>/dev/null | xargs rm -f
find "$TEMP_DIR/vendor" -name ".gitignore" -type f 2>/dev/null | xargs rm -f
find "$TEMP_DIR/vendor" -name ".gitattributes" -type f 2>/dev/null | xargs rm -f
find "$TEMP_DIR/vendor" -name "LICENSE*" -type f 2>/dev/null | xargs rm -f
find "$TEMP_DIR/vendor" -name "CHANGELOG*" -type f 2>/dev/null | xargs rm -f
find "$TEMP_DIR/vendor" -name "*.yml" -type f 2>/dev/null | xargs rm -f
find "$TEMP_DIR/vendor" -name "*.yaml" -type f 2>/dev/null | xargs rm -f
find "$TEMP_DIR/vendor" -name "*.xml.dist" -type f 2>/dev/null | xargs rm -f

# Remove test directories from vendor
find "$TEMP_DIR/vendor" -type d -name "tests" 2>/dev/null | xargs rm -rf
find "$TEMP_DIR/vendor" -type d -name "test" 2>/dev/null | xargs rm -rf
find "$TEMP_DIR/vendor" -type d -name "Test" 2>/dev/null | xargs rm -rf
find "$TEMP_DIR/vendor" -type d -name "phpunit" 2>/dev/null | xargs rm -rf

# Remove Laravel cache and log files
rm -rf "$TEMP_DIR/storage/logs"/*
rm -rf "$TEMP_DIR/storage/framework/cache"/*
rm -rf "$TEMP_DIR/storage/framework/sessions"/*
rm -rf "$TEMP_DIR/storage/framework/views"/*

# Ensure storage directories exist with .gitkeep
mkdir -p "$TEMP_DIR/storage/app/public"
mkdir -p "$TEMP_DIR/storage/framework/cache/data"
mkdir -p "$TEMP_DIR/storage/framework/sessions"
mkdir -p "$TEMP_DIR/storage/framework/views"
mkdir -p "$TEMP_DIR/storage/logs"
touch "$TEMP_DIR/storage/app/public/.gitkeep"
touch "$TEMP_DIR/storage/framework/cache/.gitkeep"
touch "$TEMP_DIR/storage/logs/.gitkeep"

# Remove any .DS_Store files
find "$TEMP_DIR" -name ".DS_Store" -type f 2>/dev/null | xargs rm -f

# Remove .env if accidentally copied
rm -f "$TEMP_DIR/.env"

echo "[4/4] Creating zip package..."

# Create the zip
cd "$TEMP_DIR"
zip -r "$OUTPUT_FILE" . -x "*.DS_Store" -x "*/.DS_Store" 2>/dev/null

# Clean up temp directory
rm -rf "$TEMP_DIR"

# Calculate size
ZIP_SIZE=$(du -h "$OUTPUT_FILE" | cut -f1)

echo ""
echo "================================================"
echo "  ✅ Deployment package created!"
echo "================================================"
echo ""
echo "  📦 File: $(basename $OUTPUT_FILE)"
echo "  📊 Size: $ZIP_SIZE"
echo "  📍 Path: $OUTPUT_FILE"
echo ""
echo "================================================"
echo "  Deployment Instructions for SiteGround"
echo "================================================"
echo ""
echo "  1. Go to Site Tools > Site > File Manager"
echo "  2. Navigate to public_html/"
echo "  3. Delete existing files (BACKUP .env first!)"
echo "  4. Upload: $(basename $OUTPUT_FILE)"
echo "  5. Right-click > Extract"
echo "  6. Delete the zip file"
echo "  7. Upload your .env file (from backup)"
echo "  8. Run: php artisan migrate"
echo "  9. Run: php artisan view:clear && php artisan config:clear"
echo ""
echo "  ⚠️  IMPORTANT: Backup your .env file before uploading!"
echo ""
