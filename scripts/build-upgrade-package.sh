#!/usr/bin/env bash
# =============================================================================
# VendifyERP — Upgrade Package Builder (Bash for Linux/Mac/Git Bash)
# =============================================================================
# Usage:
#   bash scripts/build-upgrade-package.sh [version]
# =============================================================================

set -euo pipefail

VERSION="${1:-1.0.1}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

PHP_BIN="${PHP_BIN:-php}"
$PHP_BIN "$PROJECT_ROOT/scripts/package-upgrade.php" "$VERSION"
