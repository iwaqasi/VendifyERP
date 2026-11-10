<?php
/**
 * VendifyERP — Cross-Platform Upgrade Package Builder
 * Generates production-ready ZIP archives with normalized forward-slash paths for Linux & Windows.
 * 
 * Usage:
 *   php scripts/package-upgrade.php [version]
 */

error_reporting(E_ALL);
ini_set('memory_limit', '512M');
set_time_limit(300);

$version = $argv[1] ?? '1.0.1';
$projectRoot = dirname(__DIR__);
chdir($projectRoot);

echo "\033[0;36m================================================================\033[0m\n";
echo "\033[0;36m  VendifyERP — Automated Upgrade Package Builder (v{$version})\033[0m\n";
echo "\033[0;36m================================================================\033[0m\n";
echo "Project Root: {$projectRoot}\n\n";

// 1. Clean stale zip files from public/vendify-pos
$staleZip = $projectRoot . '/public/vendify-pos/vendify-pos.zip';
if (file_exists($staleZip)) {
    unlink($staleZip);
    echo "  • Removed stale zip from public/vendify-pos\n";
}

// 2. Verify Flutter Web POS exists
$posMainJs = $projectRoot . '/public/vendify-pos/main.dart.js';
if (!file_exists($posMainJs)) {
    echo "\033[1;33m[!] Warning: public/vendify-pos/main.dart.js not found. Compiling Flutter POS...\033[0m\n";
    passthru('cd vendify_pos && flutter build web --release --base-href /vendify-pos/');
    passthru("xcopy /E /I /Y vendify_pos\\build\\web public\\vendify-pos");
}

// 3. Setup Output Files
$timestamp = date('Ymd-His');
$zipName = "vendify-upgrade-v{$version}-{$timestamp}.zip";
$latestZip = "vendify-upgrade-latest.zip";

$zipPath = $projectRoot . '/' . $zipName;
$latestPath = $projectRoot . '/' . $latestZip;

if (file_exists($zipPath)) unlink($zipPath);
if (file_exists($latestPath)) unlink($latestPath);

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    echo "\033[0;31m[✗] Failed to create ZIP archive: {$zipPath}\033[0m\n";
    exit(1);
}

echo "[1/4] Scanning and adding upgrade files...\n";

$fileCount = 0;
$manifestList = [];

// Helper to add files with normalized forward slashes
function addFileToZip(ZipArchive $zip, $absPath, $relPath, &$fileCount, &$manifestList) {
    $normRel = str_replace('\\', '/', $relPath);
    $zip->addFile($absPath, $normRel);
    $fileCount++;
    $manifestList[] = [
        'path' => $normRel,
        'size' => filesize($absPath),
        'md5'  => md5_file($absPath),
    ];
}

function scanAndAddDir(ZipArchive $zip, $absDir, $projectRoot, &$fileCount, &$manifestList) {
    if (!is_dir($absDir)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $abs = $item->getPathname();
            // Exclude git, build caches, and test files
            if (preg_match('#(\.git|vendify-pos\.zip|\.dart_tool|node_modules|tests)#', $abs)) {
                continue;
            }
            $rel = substr($abs, strlen($projectRoot) + 1);
            addFileToZip($zip, $abs, $rel, $fileCount, $manifestList);
        }
    }
}

// Directories to include in the upgrade
$directories = [
    'app',
    'config',
    'database/migrations',
    'routes',
    'Modules/Superadmin/Http/Controllers',
    'resources/views/business/partials',
    'resources/views/myfatoorah',
    'resources/views/install',
    'public/vendify-pos',
];

if (is_dir($projectRoot . '/public/vendify-cms')) {
    $directories[] = 'public/vendify-cms';
}

foreach ($directories as $dir) {
    $absDir = $projectRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $dir);
    if (is_dir($absDir)) {
        scanAndAddDir($zip, $absDir, $projectRoot, $fileCount, $manifestList);
        echo "  \033[0;32m✓\033[0m Added directory: {$dir}/\n";
    }
}

echo "\n[2/4] Injecting automated upgrade runners...\n";

// Add upgrade runner scripts
$runnerPhp = $projectRoot . '/scripts/upgrade-runner.php';
$runnerSh = $projectRoot . '/scripts/upgrade.sh';

if (file_exists($runnerPhp)) {
    $zip->addFile($runnerPhp, 'upgrade.php');
}
if (file_exists($runnerSh)) {
    $zip->addFile($runnerSh, 'upgrade.sh');
}

// Add Instructions
$readmeContent = <<<EOF
# VendifyERP Automated Upgrade Package (v{$version})
Generated: {$timestamp}
Total Files: {$fileCount}

## Quick Upgrade Instructions:

### Option A: Via SSH / Terminal (Recommended)
1. Upload this zip file (`{$latestZip}`) to your server root directory.
2. Extract the archive:
   ```bash
   unzip -o {$latestZip}
   ```
3. Run the automated upgrade script:
   ```bash
   php upgrade.php
   ```
   (or `bash upgrade.sh`)

### Option B: Via cPanel / SiteGround File Manager & Web Browser
1. Upload `{$latestZip}` to your website's root folder using File Manager.
2. Right-click and Extract the archive.
3. Open your browser and navigate to:
   `https://your-domain.com/upgrade.php`
4. Enter your `APP_KEY` (from your `.env` file) to authorize the upgrade.
5. Click **Verify & Begin Upgrade**.
6. When complete, delete `upgrade.php` from your server for security.

### What the Automated Script Does:
✓ Creates an automatic safety backup in `storage/backups/` before modifying anything
✓ Puts the app into maintenance mode during upgrade
✓ Copies all updated backend controllers, models, APIs, and views
✓ Updates the Flutter Web POS in `public/vendify-pos/`
✓ Runs database migrations automatically (`php artisan migrate --force`)
✓ Clears old caches and compiles optimized production caches (`config:cache`, `route:cache`, `view:cache`)
✓ Restores normal live mode
EOF;

$zip->addFromString('UPGRADE_README.md', $readmeContent);

// Add Manifest
$manifestData = [
    'version'      => $version,
    'generated_at' => date('Y-m-d H:i:s'),
    'files_count'  => $fileCount,
    'files'        => array_column($manifestList, 'path'),
];
$zip->addFromString('upgrade-manifest.json', json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "[3/4] Finalizing and compressing ZIP...\n";
$zip->close();

copy($zipPath, $latestPath);

$sizeMB = round(filesize($zipPath) / (1024 * 1024), 2);

echo "\n\033[0;32m[4/4] Upgrade Package Created Successfully! 🚀\033[0m\n";
echo "\033[0;36m================================================================\033[0m\n";
echo "  Package: \033[1;37m{$zipName}\033[0m ({$sizeMB} MB)\n";
echo "  Latest:  \033[1;37m{$latestZip}\033[0m ({$sizeMB} MB)\n";
echo "  Files:   \033[1;37m{$fileCount} updated files\033[0m with forward-slash paths\n";
echo "\033[0;36m================================================================\033[0m\n\n";
echo "\033[1;33mHow to upgrade your live server without manual copying:\033[0m\n";
echo "  1. Upload '{$latestZip}' to your server root.\n";
echo "  2. Extract it (via unzip or File Manager).\n";
echo "  3. Run: \033[0;32mphp upgrade.php\033[0m (or open /upgrade.php in browser)\n";
echo "\033[0;36m================================================================\033[0m\n";

