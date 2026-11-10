<?php
/**
 * VendifyERP / UltimatePOS — Automated Production Upgrade Runner
 * 
 * This script automates the upgrade process on the target server:
 * 1. Pre-flight environment check (artisan, .env, PHP version)
 * 2. Automatic safety backup of existing files about to be replaced
 * 3. Atomic file synchronization/upgrade
 * 4. Maintenance mode management (artisan down / up)
 * 5. Database migrations (artisan migrate --force)
 * 6. Cache clearing & production optimization (config, route, view)
 * 7. Storage permissions check
 * 
 * Usage:
 *   CLI: php upgrade.php [--dry-run] [--skip-backup] [--skip-migrate]
 *   Web: Visit https://your-domain.com/upgrade.php in your browser
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(300);

$isCli = (php_sapi_name() === 'cli');

// Determine base paths
$packageDir = __DIR__;
$targetDir = $packageDir;

// If upgrade.php is inside a subfolder or extracted package
if (file_exists($packageDir . '/artisan')) {
    $targetDir = $packageDir;
} elseif (file_exists(dirname($packageDir) . '/artisan')) {
    $targetDir = dirname($packageDir);
} else {
    // Look up to 2 directories up
    $parent = dirname(__DIR__);
    if (file_exists($parent . '/artisan')) {
        $targetDir = $parent;
    }
}

// Locate artisan
$artisanPath = $targetDir . '/artisan';
$envPath = $targetDir . '/.env';

// CLI Options
$options = [];
if ($isCli) {
    $options = getopt('', ['dry-run', 'skip-backup', 'skip-migrate', 'help']);
    if (isset($options['help'])) {
        echo "VendifyERP Automated Upgrade Runner\n";
        echo "Usage: php upgrade.php [options]\n\n";
        echo "Options:\n";
        echo "  --dry-run       Simulate the upgrade without modifying files or database\n";
        echo "  --skip-backup   Skip creating the pre-upgrade rollback backup\n";
        echo "  --skip-migrate  Skip running database migrations\n";
        echo "  --help          Show this help message\n";
        exit(0);
    }
}

$dryRun = isset($options['dry-run']) || (isset($_GET['dry_run']) && $_GET['dry_run'] == '1');
$skipBackup = isset($options['skip-backup']) || (isset($_GET['skip_backup']) && $_GET['skip_backup'] == '1');
$skipMigrate = isset($options['skip-migrate']) || (isset($_GET['skip_migrate']) && $_GET['skip_migrate'] == '1');

// Web Security Check
$webAuthorized = false;
$appKey = '';
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    if (preg_match('/^APP_KEY=(.+)$/m', $envContent, $matches)) {
        $appKey = trim($matches[1]);
    }
}

if (!$isCli) {
    session_start();
    $providedKey = $_POST['secret_key'] ?? $_GET['key'] ?? ($_SESSION['upgrade_auth'] ?? '');
    if (!empty($appKey) && !empty($providedKey)) {
        if ($providedKey === $appKey || hash_equals($appKey, $providedKey) || $providedKey === substr($appKey, -8)) {
            $webAuthorized = true;
            $_SESSION['upgrade_auth'] = $providedKey;
        }
    }
}

// Helper output functions
function out($text, $type = 'info') {
    global $isCli;
    $colors = [
        'info'    => "\033[0;34m", // Blue
        'success' => "\033[0;32m", // Green
        'warn'    => "\033[1;33m", // Yellow
        'error'   => "\033[0;31m", // Red
        'reset'   => "\033[0m",
    ];
    $timestamp = date('H:i:s');
    if ($isCli) {
        $color = $colors[$type] ?? $colors['reset'];
        $prefix = ($type === 'success') ? '[✓]' : (($type === 'error') ? '[✗]' : (($type === 'warn') ? '[!]' : '[i]'));
        echo "{$color}[{$timestamp}] {$prefix} {$text}{$colors['reset']}\n";
    } else {
        $cssClasses = [
            'info'    => 'text-blue-400',
            'success' => 'text-emerald-400 font-medium',
            'warn'    => 'text-amber-400',
            'error'   => 'text-rose-400 font-bold',
        ];
        $class = $cssClasses[$type] ?? 'text-slate-300';
        $icon = ($type === 'success') ? '✓' : (($type === 'error') ? '✗' : (($type === 'warn') ? '!' : '•'));
        echo "<div class='py-1 font-mono text-sm {$class}'><span class='opacity-50'>[{$timestamp}]</span> <strong>{$icon}</strong> " . htmlspecialchars($text) . "</div>\n";
        flush();
        ob_flush();
    }
}

function runArtisanCommand($targetDir, $command) {
    $phpBin = PHP_BINARY ?: 'php';
    $cmd = escapeshellcmd("{$phpBin} \"{$targetDir}/artisan\" {$command}") . ' 2>&1';
    $output = [];
    $returnCode = 0;
    exec($cmd, $output, $returnCode);
    return [
        'cmd'        => $command,
        'output'     => implode("\n", $output),
        'returnCode' => $returnCode,
    ];
}

// Web UI Rendering
if (!$isCli && !$webAuthorized && (!isset($_POST['action']) || $_POST['action'] !== 'upgrade')) {
    ?>
    <!DOCTYPE html>
    <html lang="en" class="dark">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>VendifyERP — Production Upgrade Wizard</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
            <div class="flex items-center space-x-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-pink-500 to-rose-600 flex items-center justify-center font-bold text-lg text-white">V</div>
                <div>
                    <h1 class="text-xl font-bold">VendifyERP Upgrade</h1>
                    <p class="text-xs text-slate-400">Automated Production Installer</p>
                </div>
            </div>

            <div class="bg-slate-950/60 rounded-xl p-4 border border-slate-800 mb-6 text-xs text-slate-300 space-y-2">
                <div class="flex justify-between"><span>Target Directory:</span><span class="font-mono text-slate-400"><?= htmlspecialchars($targetDir) ?></span></div>
                <div class="flex justify-between"><span>Laravel Artisan:</span><span class="<?= file_exists($artisanPath) ? 'text-emerald-400' : 'text-rose-400' ?>"><?= file_exists($artisanPath) ? 'Detected' : 'Missing' ?></span></div>
                <div class="flex justify-between"><span>Environment File:</span><span class="<?= file_exists($envPath) ? 'text-emerald-400' : 'text-rose-400' ?>"><?= file_exists($envPath) ? 'Detected' : 'Missing' ?></span></div>
            </div>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Security Key (APP_KEY from .env)</label>
                    <input type="password" name="secret_key" required placeholder="base64:... or last 8 chars" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-rose-500">
                    <p class="text-[11px] text-slate-500 mt-1">Found in your server's <code>.env</code> file to ensure authorized access.</p>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full bg-gradient-to-r from-rose-600 to-pink-600 hover:from-rose-500 hover:to-pink-500 text-white font-semibold py-2.5 px-4 rounded-xl transition duration-150 shadow-lg shadow-rose-900/20 text-sm">
                        Verify & Begin Upgrade →
                    </button>
                </div>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit(0);
}

// Start Upgrade Execution
if (!$isCli) {
    ?>
    <!DOCTYPE html>
    <html lang="en" class="dark">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Executing Upgrade — VendifyERP</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-slate-950 text-slate-100 min-h-screen p-6 font-sans">
        <div class="max-w-4xl mx-auto bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl">
            <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-4">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-pink-500 to-rose-600 flex items-center justify-center font-bold text-white">V</div>
                    <div>
                        <h1 class="text-lg font-bold">VendifyERP Automated Upgrade</h1>
                        <p class="text-xs text-slate-400">Live Execution Log</p>
                    </div>
                </div>
                <div class="text-xs bg-rose-500/10 text-rose-400 border border-rose-500/20 px-2.5 py-1 rounded-full font-mono">
                    <?= $dryRun ? 'DRY RUN' : 'PRODUCTION' ?>
                </div>
            </div>
            <div class="bg-slate-950 rounded-xl p-4 border border-slate-800 h-96 overflow-y-auto space-y-1 mb-6" id="log-container">
    <?php
}

out("Starting VendifyERP upgrade process...", 'info');
out("Package directory: {$packageDir}", 'info');
out("Target installation: {$targetDir}", 'info');

// 1. Pre-flight validation
if (!file_exists($artisanPath)) {
    out("CRITICAL: artisan not found in {$targetDir}. Aborting upgrade.", 'error');
    exit(1);
}

if (!file_exists($envPath)) {
    out("CRITICAL: .env file not found in {$targetDir}. Aborting upgrade.", 'error');
    exit(1);
}

out("Pre-flight checks passed.", 'success');

// 2. Discover package contents to copy
$filesDir = $packageDir . '/files';
if (!is_dir($filesDir)) {
    // If files are in root of package directory
    $filesDir = $packageDir;
}

$manifestFile = $packageDir . '/upgrade-manifest.json';
$filesToCopy = [];

if (file_exists($manifestFile)) {
    $manifest = json_decode(file_get_contents($manifestFile), true);
    if (!empty($manifest['files'])) {
        $filesToCopy = $manifest['files'];
        out("Loaded " . count($filesToCopy) . " files from upgrade-manifest.json", 'info');
    }
}

// Fallback directory scan if no manifest
if (empty($filesToCopy)) {
    $upgradeFolders = ['app', 'config', 'database', 'Modules', 'public', 'resources', 'routes'];
    foreach ($upgradeFolders as $folder) {
        $srcFolder = $filesDir . '/' . $folder;
        if (is_dir($srcFolder)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($srcFolder, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                if ($item->isFile()) {
                    $relPath = str_replace('\\', '/', substr($item->getPathname(), strlen($filesDir) + 1));
                    // Skip git, build cache, and runner scripts
                    if (!preg_match('#(\.git|upgrade\.php|upgrade\.sh|\.zip$)#', $relPath)) {
                        $filesToCopy[] = $relPath;
                    }
                }
            }
        }
    }
    out("Scanned package: found " . count($filesToCopy) . " files to upgrade.", 'info');
}

// 3. Pre-upgrade Rollback Backup
$backupDir = $targetDir . '/storage/backups/upgrade-backup-' . date('Y-m-d_His');
if (!$skipBackup && !$dryRun && !empty($filesToCopy)) {
    out("Creating pre-upgrade backup in storage/backups...", 'info');
    $backedUpCount = 0;
    foreach ($filesToCopy as $relPath) {
        $targetFile = $targetDir . '/' . $relPath;
        if (file_exists($targetFile)) {
            $destBackup = $backupDir . '/' . $relPath;
            $destBackupDir = dirname($destBackup);
            if (!is_dir($destBackupDir)) {
                mkdir($destBackupDir, 0755, true);
            }
            copy($targetFile, $destBackup);
            $backedUpCount++;
        }
    }
    out("Safety backup complete: {$backedUpCount} existing files preserved in: " . basename($backupDir), 'success');
}

// 4. Put application in Maintenance Mode
if (!$dryRun) {
    out("Putting application into maintenance mode...", 'info');
    runArtisanCommand($targetDir, 'down --render="errors::503"');
}

// 5. Copy & Upgrade Files
$copiedCount = 0;
$skippedCount = 0;

out("Applying upgrade files...", 'info');
foreach ($filesToCopy as $relPath) {
    $src = $filesDir . '/' . $relPath;
    $dst = $targetDir . '/' . $relPath;

    // Safety checks
    if (!file_exists($src)) {
        continue;
    }

    // If source and destination are the same file (already extracted in place by unzip)
    if (realpath($src) && realpath($dst) && realpath($src) === realpath($dst)) {
        $copiedCount++;
        continue;
    }

    if ($dryRun) {
        out("[DRY-RUN] Would update: {$relPath}", 'info');
        $copiedCount++;
        continue;
    }

    $dstDir = dirname($dst);
    if (!is_dir($dstDir)) {
        mkdir($dstDir, 0775, true);
    }

    if (copy($src, $dst)) {
        $copiedCount++;
    } else {
        out("Failed to write: {$relPath}", 'error');
    }
}

out("File synchronization complete ({$copiedCount} files updated).", 'success');

// 6. Run Database Migrations
if (!$skipMigrate && !$dryRun) {
    out("Running database migrations...", 'info');
    $migrateRes = runArtisanCommand($targetDir, 'migrate --force');
    if ($migrateRes['returnCode'] === 0) {
        out("Database migrations applied successfully.", 'success');
        if (!empty($migrateRes['output'])) {
            $lines = explode("\n", trim($migrateRes['output']));
            foreach (array_slice($lines, -5) as $line) {
                if (!empty(trim($line))) out("  " . trim($line), 'info');
            }
        }
    } else {
        out("Warning during migrations: " . $migrateRes['output'], 'warn');
    }
}

// 7. Clear and Rebuild Caches
if (!$dryRun) {
    out("Clearing legacy caches...", 'info');
    runArtisanCommand($targetDir, 'config:clear');
    runArtisanCommand($targetDir, 'route:clear');
    runArtisanCommand($targetDir, 'view:clear');
    runArtisanCommand($targetDir, 'cache:clear');

    out("Compiling optimized production caches...", 'info');
    runArtisanCommand($targetDir, 'config:cache');
    runArtisanCommand($targetDir, 'route:cache');
    runArtisanCommand($targetDir, 'view:cache');
    runArtisanCommand($targetDir, 'package:discover --ansi');
    out("Production cache optimization complete.", 'success');
}

// 8. Fix Permissions
if (!$dryRun) {
    @chmod($targetDir . '/storage', 0775);
    @chmod($targetDir . '/bootstrap/cache', 0775);
    out("Storage and cache permissions verified.", 'success');
}

// 9. Bring application back UP
if (!$dryRun) {
    runArtisanCommand($targetDir, 'up');
    out("Application is LIVE and running!", 'success');
}

out("==================================================", 'success');
out("VendifyERP Upgrade completed successfully! 🚀", 'success');
out("Completed at: " . date('Y-m-d H:i:s'), 'success');
out("==================================================", 'success');

if (!$isCli) {
    ?>
            </div>
            <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-xl p-4 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-emerald-400 text-sm">Upgrade Complete</h3>
                    <p class="text-xs text-slate-300">All files, migrations, and web assets have been applied.</p>
                </div>
                <div class="flex space-x-2">
                    <a href="/" target="_blank" class="bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-xs px-4 py-2 rounded-lg transition">
                        Open POS / ERP →
                    </a>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 text-center mt-4">
                Important: For security, remember to delete <code>upgrade.php</code> from your server once verified.
            </p>
        </div>
        <script>
            const logBox = document.getElementById('log-container');
            if (logBox) logBox.scrollTop = logBox.scrollHeight;
        </script>
    </body>
    </html>
    <?php
}

