#!/usr/bin/env bash
# =============================================================================
# Package VendifyERP for Upload to SiteGround
# =============================================================================
# Creates a zip file you can upload via SiteGround File Manager or FTP.
#
# Usage:
#   bash scripts/package-for-upload.sh
#
# Output: vendify-deploy.zip (in project root)
#
# Then upload to SiteGround:
#   1. Site Tools → Site → File Manager
#   2. Navigate to your home directory
#   3. Upload vendify-deploy.zip
#   4. Extract it
#   5. Run the deploy script via SSH (if available) or the terminal in Site Tools
# =============================================================================

set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_DIR"

OUTPUT="vendify-deploy.zip"

echo "Packaging VendifyERP for deployment..."

# Clean previous package
rm -f "$OUTPUT"

# Create zip with only what's needed for production
# Excludes: .git, node_modules, vendor (will be reinstalled), build dirs, tests
zip -r "$OUTPUT" \
    app/ \
    bootstrap/ \
    config/ \
    database/ \
    modules/ \
    public/ \
    resources/ \
    routes/ \
    scripts/ \
    storage/ \
    vendor/ \
    artisan \
    composer.json \
    composer.lock \
    deploy.sh \
    .env.example \
    -x "*.git*" \
    -x "*/node_modules/*" \
    -x "*/tests/*" \
    -x "*/.idea/*" \
    -x "*/.dart_tool/*" \
    -x "vendify_pos/build/*" \
    -x "vendify_cms/build/*" \
    -x "public/vendify-pos/*" \
    -x "public/vendify-cms/*" \
    2>&1 | tail -3

SIZE=$(du -h "$OUTPUT" | cut -f1)
echo ""
echo "✓ Created: $OUTPUT ($SIZE)"
echo ""
echo "Upload steps:"
echo "  1. Go to SiteGround → Site Tools → Site → File Manager"
echo "  2. Navigate to: /home/youruser/VendifyERP"
echo "  3. Upload $OUTPUT"
echo "  4. Right-click → Extract"
echo "  5. After extraction, run: bash scripts/siteground-deploy.sh"
