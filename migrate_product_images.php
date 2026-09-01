<?php
/**
 * VendifyERP - Migrate Product Images to Per-Business Folders
 * 
 * Moves images from: public/uploads/img/{filename}
 * To:                public/uploads/img/{business_id}/{filename}
 * 
 * Usage: php migrate_product_images.php
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$basePath = public_path('uploads/img');
$moved = 0;
$skipped = 0;
$errors = 0;

echo "=== Product Image Migration to Per-Business Folders ===\n\n";

// Get all products with images
$products = DB::table('products')
    ->whereNotNull('image')
    ->select('id', 'business_id', 'image')
    ->get();

echo "Found " . $products->count() . " products with images.\n\n";

// Create business directories first
$businessIds = $products->pluck('business_id')->unique();
foreach ($businessIds as $bid) {
    $bizDir = $basePath . '/' . $bid;
    if (!is_dir($bizDir)) {
        mkdir($bizDir, 0755, true);
        echo "Created directory: uploads/img/$bid/\n";
    }
}

echo "\nMoving images...\n\n";

foreach ($products as $product) {
    $source = $basePath . '/' . $product->image;
    $dest = $basePath . '/' . $product->business_id . '/' . $product->image;
    
    if (!file_exists($source)) {
        // Check if already in business folder
        if (file_exists($dest)) {
            $skipped++;
            continue;
        }
        echo "  SKIP: Image not found for product #{$product->id}: {$product->image}\n";
        $skipped++;
        continue;
    }
    
    // Don't move if already in the right place
    if (dirname($source) === dirname($dest)) {
        $skipped++;
        continue;
    }
    
    // Check if destination already exists (from another product in same business)
    if (file_exists($dest)) {
        // Files might be the same name but different products
        // Compare file sizes to determine if it's the same file
        if (filesize($source) === filesize($dest)) {
            // Same file already there, just skip
            $skipped++;
            continue;
        }
        // Different file with same name - rename to avoid collision
        $ext = pathinfo($product->image, PATHINFO_EXTENSION);
        $name = pathinfo($product->image, PATHINFO_FILENAME);
        $newName = $name . '_' . $product->id . '.' . $ext;
        $dest = $basePath . '/' . $product->business_id . '/' . $newName;
        
        // Update database with new name
        DB::table('products')->where('id', $product->id)->update(['image' => $newName]);
    }
    
    if (rename($source, $dest)) {
        $moved++;
        if ($moved % 50 === 0) {
            echo "  ... moved $moved images so far\n";
        }
    } else {
        echo "  ERROR: Failed to move {$product->image} for product #{$product->id}\n";
        $errors++;
    }
}

echo "\n=== Migration Complete ===\n";
echo "  Moved: $moved images\n";
echo "  Skipped: $skipped (already in place or not found)\n";
echo "  Errors: $errors\n";

// Verify the structure
echo "\n=== Directory Structure ===\n";
$dirs = glob($basePath . '/*', GLOB_ONLYDIR);
foreach ($dirs as $dir) {
    $name = basename($dir);
    $count = count(glob($dir . '/*'));
    echo "  uploads/img/$name/ — $count files\n";
}
