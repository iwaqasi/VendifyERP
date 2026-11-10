# =============================================================================
# VendifyERP — Automated SiteGround Deployment Script (PowerShell)
# =============================================================================
# Usage:
#   powershell -ExecutionPolicy Bypass -File scripts/publish-to-siteground.ps1
# =============================================================================

param (
    [string]$SshHost = "ssh.arksoftsolutions.com",
    [int]$SshPort = 18765,
    [string]$SshUser = "u2391-xuchg8hq4f9u",
    [string]$KeyPath = "C:\Users\vikki\.ssh\siteground_ed25519",
    [string]$RemoteDir = "~/www/erp.arksoftsolutions.com/public_html",
    [switch]$SkipTests = $false,
    [switch]$SkipGitPush = $false,
    [switch]$ForceBuildPos = $false
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Resolve-Path "$PSScriptRoot\.."
Set-Location $ProjectRoot

# Fallback key path if ed25519 not found
if (-not (Test-Path $KeyPath)) {
    $KeyPath = "C:\Users\vikki\Desktop\private_key.ppk"
}

Write-Host "================================================================" -ForegroundColor Cyan
Write-Host "  VendifyERP — Automated Production Publish to SiteGround" -ForegroundColor Cyan
Write-Host "================================================================" -ForegroundColor Cyan
Write-Host "Target: $SshUser@${SshHost}:${SshPort} ($RemoteDir)" -ForegroundColor Gray
Write-Host "Key:    $KeyPath" -ForegroundColor Gray

# 1. Run local tests first
if (-not $SkipTests) {
    Write-Host "`n[1/5] Running local test suite..." -ForegroundColor Yellow
    & vendor/bin/phpunit
    if ($LASTEXITCODE -ne 0) {
        Write-Error "Local test suite failed! Fix failing tests before publishing."
        exit 1
    }
    Write-Host "✓ All tests passed successfully." -ForegroundColor Green
}

# 2. Check and compile Flutter Web POS build
Write-Host "`n[2/5] Checking Flutter Web POS build..." -ForegroundColor Yellow
$PosMainJs = "$ProjectRoot\public\vendify-pos\main.dart.js"
if ($ForceBuildPos -or (-not (Test-Path $PosMainJs))) {
    Write-Host "Compiling Flutter POS Web release..." -ForegroundColor Yellow
    Set-Location "$ProjectRoot\vendify_pos"
    flutter build web --release --base-href /vendify-pos/
    Set-Location $ProjectRoot
    Copy-Item "$ProjectRoot\vendify_pos\build\web\*" "$ProjectRoot\public\vendify-pos\" -Recurse -Force
}
Write-Host "✓ Web POS release ready." -ForegroundColor Green

# 3. Commit and push changes to git
if (-not $SkipGitPush) {
    Write-Host "`n[3/5] Verifying git status and pushing..." -ForegroundColor Yellow
    $Status = git status --porcelain
    if ($Status) {
        Write-Host "Working tree has uncommitted changes. Committing..." -ForegroundColor Yellow
        git add app/ config/ database/ migrations/ resources/ routes/ vendify_pos/ scripts/
        git commit -m "chore(deploy): prepare production release $(Get-Date -Format 'yyyy-MM-dd HH:mm')"
    }
    git push origin main
    Write-Host "✓ Pushed to origin/main." -ForegroundColor Green
}

# 4. Package Upgrade & Deploy to SiteGround
Write-Host "`n[4/5] Packaging upgrade archive & deploying via SSH/SCP..." -ForegroundColor Yellow
& php "$ProjectRoot\scripts\package-upgrade.php"
if ($LASTEXITCODE -ne 0) {
    Write-Error "Failed to build upgrade package."
    exit 1
}

$PackageZip = "$ProjectRoot\vendify-upgrade-latest.zip"
Write-Host "→ Uploading $PackageZip to SiteGround..." -ForegroundColor Yellow
& scp -i $KeyPath -P $SshPort -o StrictHostKeyChecking=accept-new $PackageZip "$SshUser@$SshHost`:~/vendify-upgrade-latest.zip"
if ($LASTEXITCODE -ne 0) {
    Write-Error "SCP upload failed."
    exit 1
}
Write-Host "✓ Upgrade package uploaded." -ForegroundColor Green

# Remote commands: extract package, run migrations, rebuild caches
$RemoteCommands = @"
set -e
echo '=== Extracting Upgrade Package ==='
cd $RemoteDir

PHP_BIN="/usr/local/bin/php83"
if [ ! -x "`$PHP_BIN" ]; then
    PHP_BIN="php"
fi
echo "Using PHP: `$PHP_BIN"

# Extract archive
unzip -o -q ~/vendify-upgrade-latest.zip -d $RemoteDir
echo '✓ Files extracted.'

# Run upgrade runner (runs migrations, clears/rebuilds caches, permissions)
`$PHP_BIN upgrade.php --skip-backup

echo '=== Deployment Finished Successfully! ==='
"@

Write-Host "→ Executing remote upgrade on SiteGround..." -ForegroundColor Yellow
& ssh -i $KeyPath -p $SshPort -o StrictHostKeyChecking=accept-new -o BatchMode=yes "$SshUser@$SshHost" $RemoteCommands
if ($LASTEXITCODE -ne 0) {
    Write-Warning "SSH execution encountered an error."
} else {
    Write-Host "✓ Remote upgrade executed successfully." -ForegroundColor Green
}

# 5. Live Smoke Test
Write-Host "`n[5/5] Performing live health check..." -ForegroundColor Yellow
try {
    $Health = Invoke-RestMethod -Uri "https://erp.arksoftsolutions.com/api/v1/health" -TimeoutSec 10
    if ($Health.status -eq "ok") {
        Write-Host "✓ Site is healthy! (App: $($Health.app_name), Version: $($Health.version))" -ForegroundColor Green
    } else {
        Write-Host "Response: $($Health | ConvertTo-Json -Compress)" -ForegroundColor Yellow
    }
} catch {
    Write-Warning "Health check warning: $($_.Exception.Message)"
}

Write-Host "`n================================================================" -ForegroundColor Cyan
Write-Host "  Publish Workflow Complete! 🚀" -ForegroundColor Green
Write-Host "  URL: https://erp.arksoftsolutions.com" -ForegroundColor White
Write-Host "  POS: https://erp.arksoftsolutions.com/vendify-pos/" -ForegroundColor White
Write-Host "================================================================" -ForegroundColor Cyan
