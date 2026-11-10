<?php
/**
 * Vendify Unified Version Bumping Utility
 * 
 * Usage:
 *   php scripts/bump-version.php [major|minor|patch|<semver>] [--build=N]
 * 
 * Examples:
 *   php scripts/bump-version.php minor          # 1.0.0+1 -> 1.1.0+2
 *   php scripts/bump-version.php patch          # 1.1.0+2 -> 1.1.1+3
 *   php scripts/bump-version.php major          # 1.1.1+3 -> 2.0.0+4
 *   php scripts/bump-version.php 1.2.0 --build=10
 */

error_reporting(E_ALL);

$projectRoot = dirname(__DIR__);
$pubspecPath = $projectRoot . '/vendify_pos/pubspec.yaml';
$dartConfigPath = $projectRoot . '/vendify_pos/lib/config/api_config.dart';
$authorConfigPath = $projectRoot . '/config/author.php';
$appConfigPath = $projectRoot . '/config/app.php';
$versionJsonPath = $projectRoot . '/public/version.json';
$posVersionJsonPath = $projectRoot . '/public/vendify-pos/version.json';

// 1. Read current version from pubspec.yaml
if (!file_exists($pubspecPath)) {
    echo "\033[0;31m[✗] Error: pubspec.yaml not found at {$pubspecPath}\033[0m\n";
    exit(1);
}

$pubspecContent = file_get_contents($pubspecPath);
if (!preg_match('/^version:\s*([0-9]+)\.([0-9]+)\.([0-9]+)\+([0-9]+)/m', $pubspecContent, $matches)) {
    echo "\033[0;31m[✗] Could not parse version in pubspec.yaml\033[0m\n";
    exit(1);
}

$major = (int)$matches[1];
$minor = (int)$matches[2];
$patch = (int)$matches[3];
$build = (int)$matches[4];

$currentVersion = "{$major}.{$minor}.{$patch}+{$build}";

// 2. Parse command arguments
$bumpType = strtolower($argv[1] ?? 'patch');
$customBuild = null;

foreach ($argv as $arg) {
    if (strpos($arg, '--build=') === 0) {
        $customBuild = (int)substr($arg, 8);
    }
}

if ($bumpType === 'major') {
    $major++;
    $minor = 0;
    $patch = 0;
    $build++;
} elseif ($bumpType === 'minor') {
    $minor++;
    $patch = 0;
    $build++;
} elseif ($bumpType === 'patch') {
    $patch++;
    $build++;
} elseif (preg_match('/^([0-9]+)\.([0-9]+)\.([0-9]+)(?:\+([0-9]+))?$/', $bumpType, $customMatches)) {
    $major = (int)$customMatches[1];
    $minor = (int)$customMatches[2];
    $patch = (int)$customMatches[3];
    $build = isset($customMatches[4]) ? (int)$customMatches[4] : ($build + 1);
} else {
    echo "\033[0;31m[✗] Invalid bump type: '{$bumpType}'. Use 'major', 'minor', 'patch', or a valid semver like '1.2.0'\033[0m\n";
    exit(1);
}

if ($customBuild !== null) {
    $build = $customBuild;
}

$semver = "{$major}.{$minor}.{$patch}";
$fullVersion = "{$semver}+{$build}";
$msixVersion = "{$semver}.0";

echo "\033[0;36m================================================================\033[0m\n";
echo "\033[0;36m  Vendify Unified Version Bump: {$currentVersion} → {$fullVersion}\033[0m\n";
echo "\033[0;36m  Bump Type: {$bumpType} (Semantic Version: {$semver}, Build: {$build})\033[0m\n";
echo "\033[0;36m================================================================\033[0m\n\n";

// 3. Update pubspec.yaml (Controls Web, Android versionName/versionCode, iOS CFBundleShortVersionString/CFBundleVersion)
$updatedPubspec = preg_replace(
    '/^version:\s*.+$/m',
    "version: {$fullVersion}",
    $pubspecContent
);
$updatedPubspec = preg_replace(
    '/^\s*msix_version:\s*.+$/m',
    "  msix_version: {$msixVersion}",
    $updatedPubspec
);
file_put_contents($pubspecPath, $updatedPubspec);
echo "  \033[0;32m✓\033[0m Updated vendify_pos/pubspec.yaml -> version: {$fullVersion}\n";

// 3b. Update vendify_pos/lib/config/api_config.dart
if (file_exists($dartConfigPath)) {
    $dartContent = file_get_contents($dartConfigPath);
    $dartContent = preg_replace(
        "/static const String appVersion = '[^']+';/",
        "static const String appVersion = '{$semver}';",
        $dartContent
    );
    $dartContent = preg_replace(
        "/static const String appBuild = '[^']+';/",
        "static const String appBuild = '{$build}';",
        $dartContent
    );
    file_put_contents($dartConfigPath, $dartContent);
    echo "  \033[0;32m✓\033[0m Updated vendify_pos/lib/config/api_config.dart -> {$semver} (Build {$build})\n";
}

// 4. Update config/author.php
if (file_exists($authorConfigPath)) {
    $authorContent = file_get_contents($authorConfigPath);
    $authorContent = preg_replace(
        "/'app_version'\s*=>\s*'[0-9\.]+'/",
        "'app_version' => '{$semver}'",
        $authorContent
    );
    file_put_contents($authorConfigPath, $authorContent);
    echo "  \033[0;32m✓\033[0m Updated config/author.php -> app_version: {$semver}\n";
}

// 5. Update config/app.php
if (file_exists($appConfigPath)) {
    $appContent = file_get_contents($appConfigPath);
    if (strpos($appContent, "'version'") !== false) {
        $appContent = preg_replace(
            "/'version'\s*=>\s*env\('APP_VERSION',\s*'[^']+'\)/",
            "'version' => env('APP_VERSION', '{$semver}')",
            $appContent
        );
    } else {
        $appContent = preg_replace(
            "/'name'\s*=>\s*env\('APP_NAME',\s*'[^']+'\),/",
            "'name' => env('APP_NAME', 'VendifyERP'),\n\n    'version' => env('APP_VERSION', '{$semver}'),",
            $appContent
        );
    }
    file_put_contents($appConfigPath, $appContent);
    echo "  \033[0;32m✓\033[0m Updated config/app.php -> version: {$semver}\n";
}

// 6. Generate/update public/version.json
$versionPayload = [
    'app_name' => 'vendify_pos',
    'version' => $semver,
    'build_number' => (string)$build,
    'release_date' => date('Y-m-d H:i:s'),
    'apk_url' => 'https://erp.arksoftsolutions.com/download/apk',
    'web_url' => 'https://erp.arksoftsolutions.com/vendify-pos/',
    'release_notes' => "Vendify POS Release v{$semver}",
];
$jsonFormatted = json_encode($versionPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
file_put_contents($versionJsonPath, $jsonFormatted);
echo "  \033[0;32m✓\033[0m Updated public/version.json\n";

if (is_dir(dirname($posVersionJsonPath))) {
    file_put_contents($posVersionJsonPath, $jsonFormatted);
    echo "  \033[0;32m✓\033[0m Updated public/vendify-pos/version.json\n";
}

echo "\n\033[0;32m[✓] All platforms (Web, Android APK, iOS, Laravel Backend) synchronized to v{$semver} (Build {$build})!\033[0m\n\n";

return [
    'version' => $semver,
    'build' => $build,
    'full_version' => $fullVersion,
];
