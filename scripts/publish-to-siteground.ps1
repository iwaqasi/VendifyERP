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
    [string]$KeyPath = "C:\Users\vikki\Desktop\private_key.ppk",
    [string]$RemoteDir = "~/www/erp.arksoftsolutions.com/public_html",
    [switch]$SkipTests = $false,
    [switch]$SkipGitPush = $false
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Resolve-Path "$PSScriptRoot\.."
Set-Location $ProjectRoot

Write-Host "================================================================" -ForegroundColor Cyan
Write-Host "  VendifyERP — Automated Production Publish to SiteGround" -ForegroundColor Cyan
Write-Host "================================================================" -ForegroundColor Cyan
Write-Host "Target: $SshUser@$SshHost:$SshPort ($RemoteDir)" -ForegroundColor Gray
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

# 2. Check Flutter Web POS build
Write-Host "`n[2/5] Checking Flutter Web POS build..." -ForegroundColor Yellow
$PosMainJs = "$ProjectRoot\public\vendify-pos\main.dart.js"
if (-not (Test-Path $PosMainJs)) {
    Write-Host "Compiling Flutter POS Web release..." -ForegroundColor Yellow
    Set-Location "$ProjectRoot\vendify_pos"
    flutter build web --release --base-href /vendify-pos/
    Set-Location $ProjectRoot
    Copy-Item "$ProjectRoot\vendify_pos\build\web\*" "$ProjectRoot\public\vendify-pos\" -Recurse -Force
}
Write-Host "✓ Web POS release ready." -ForegroundColor Green

# 3. Commit and push changes if any
if (-not $SkipGitPush) {
    Write-Host "`n[3/5] Verifying git status and pushing..." -ForegroundColor Yellow
    $Status = git status --porcelain
    if ($Status) {
        Write-Host "Working tree has uncommitted changes. Committing..." -ForegroundColor Yellow
        git add app/ config/ database/ migrations/ resources/ routes/ public/vendify-pos/ scripts/
        git commit -m "chore(deploy): prepare production release $(Get-Date -Format 'yyyy-MM-dd HH:mm')"
    }
    git push origin main
    Write-Host "✓ Pushed to origin/main." -ForegroundColor Green
}

# 4. Execute Remote Commands via SSH
Write-Host "`n[4/5] Connecting to SiteGround via SSH to execute deployment..." -ForegroundColor Yellow

$RemoteCommands = @"
set -e
echo '=== Starting Remote Deployment ==='
cd $RemoteDir || cd ~/www/erp.arksoftsolutions.com || cd ~/public_html

# Auto-detect PHP 8.2 or 8.3 on SiteGround
PHP_BIN="php"
for p in /usr/local/bin/php8.3 /usr/local/bin/php8.2 /usr/local/bin/php8.1; do
    if [ -x "\$p" ]; then
        PHP_BIN="\$p"
        break
    fi
done
echo "Using PHP: \$PHP_BIN"

# Enable maintenance mode
\$PHP_BIN artisan down --render="errors::503" 2>/dev/null || \$PHP_BIN artisan down || true

# Pull latest commits
echo '→ Pulling latest code...'
git fetch origin main
git reset --hard origin/main

# Production Composer Install
echo '→ Installing production dependencies...'
composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader 2>&1 | tail -2

# Run Migrations
echo '→ Running database migrations...'
\$PHP_BIN artisan migrate --force

# Clear and rebuild caches
echo '→ Optimizing production caches...'
\$PHP_BIN artisan config:clear
\$PHP_BIN artisan route:clear
\$PHP_BIN artisan view:clear
\$PHP_BIN artisan cache:clear

\$PHP_BIN artisan config:cache
\$PHP_BIN artisan route:cache
\$PHP_BIN artisan view:cache
\$PHP_BIN artisan package:discover --ansi

# Restart queue workers if any
\$PHP_BIN artisan queue:restart 2>/dev/null || true

# Fix permissions
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Disable maintenance mode
\$PHP_BIN artisan up

echo '=== Remote Deployment Succeeded! ==='
"@

# Execute SSH
& ssh -i $KeyPath -p $SshPort -o StrictHostKeyChecking=accept-new $SshUser@$SshHost $RemoteCommands

if ($LASTEXITCODE -ne 0) {
    Write-Warning "SSH execution encountered an error or passphrase requirement."
} else {
    Write-Host "✓ Remote deployment commands executed successfully." -ForegroundColor Green
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
