# =============================================================================
# VendifyERP — Upgrade Package Builder (PowerShell for Windows)
# =============================================================================
# Usage:
#   powershell -ExecutionPolicy Bypass -File scripts/build-upgrade-package.ps1 [version]
# =============================================================================

param (
    [string]$Version = "1.0.1"
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Resolve-Path "$PSScriptRoot\.."
Set-Location $ProjectRoot

# Invoke cross-platform PHP packager
php "$ProjectRoot\scripts\package-upgrade.php" $Version
