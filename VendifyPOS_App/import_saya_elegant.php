<?php
/**
 * VendifyERP - Saya Elegant Style Data Importer
 * 
 * This script creates:
 * 1. Business "Saya Elegant Style" (ID: 4)
 * 2. Admin user for the business
 * 3. Categories (Scarves, Earrings, Bracelets, Necklaces, Watches, Rings)
 * 4. Brands (Vestopazzo, Nomination Italy, Vera Luce)
 * 5. Products with prices, descriptions, SKUs
 * 6. Variations and location details
 * 7. Downloads product images from Shopify CDN
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$app = Application::bootstrapInstance();
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$businessId = 4;
$ownerId = null;
$locationId = null;
$createdBy = 1; // Super admin

echo "=== Saya Elegant Style Importer ===\n\n";

// ============================================================
// 1. CREATE BUSINESS
// ============================================================
echo "[1/7] Creating business 'Saya Elegant Style'...\n";

$businessExists = DB::table('business')->where('id', $businessId)->exists();

if ($businessExists) {
    echo "  Business ID $businessId already exists, skipping creation.\n";
} else {
    DB::table('business')->insert([
        'name' => 'Saya Elegant Style',
        'currency_id' => 140, // KWD
        'cashier_pin' => '1234',
        'start_date' => date('Y-m-d'),
        'default_sales_tax' => null,
        'default_profit_percent' => 0,
        'owner_id' => 1,
        'time_zone' => 'Asia/Kuwait',
        'fy_start_month' => 1,
        'accounting_method' => 'fifo',
        'sell_price_tax' => 'includes',
        'currency_symbol_placement' => 'before',
        'date_format' => 'm/d/Y',
        'time_format' => '24',
        'currency_precision' => 3,
        'quantity_precision' => 2,
        'enable_brand' => 1,
        'enable_category' => 1,
        'enable_sub_category' => 1,
        'enable_price_tax' => 1,
        'is_active' => 1,
        'created_by' => $createdBy,
        'enabled_modules' => json_encode(["purchases", "add_sale", "pos_sale", "stock_transfers", "stock_adjustment", "expenses", "account", "service_staff", "booking", "subscription", "types_of_service"]),
        'sku_prefix' => 'SES',
        'ref_no_prefixes' => json_encode([
            "purchase" => "PO",
            "sell_return" => "CN",
            "expense" => "EP",
            "contacts" => "CO",
            "purchase_payment" => "PP",
            "sell_payment" => "SP",
            "stock_transfer" => "ST",
            "stock_adjustment" => "SA",
        ]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $businessId = DB::getPdo()->lastInsertId();
    echo "  Created business ID: $businessId\n";
}

// ============================================================
// 2. CREATE OWNER/ADMIN USER
// ============================================================
echo "\n[2/7] Creating admin user...\n";

$adminEmail = 'saya@elegant.com';
$adminExists = DB::table('users')->where('email', $adminEmail)->where('business_id', $businessId)->exists();

if ($adminExists) {
    $adminUser = DB::table('users')->where('email', $adminEmail)->where('business_id', $businessId)->first();
    $ownerId = $adminUser->id;
    echo "  Admin user already exists (ID: $ownerId), skipping.\n";
} else {
    $userId = DB::table('users')->insertGetId([
        'username' => 'saya_admin',
        'email' => $adminEmail,
        'password' => Hash::make('password'),
        'business_id' => $businessId,
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $ownerId = $userId;
    
    // Update business owner_id
    DB::table('business')->where('id', $businessId)->update(['owner_id' => $ownerId]);
    
    echo "  Created admin user ID: $ownerId\n";
    
    // Create default user role assignment
    $adminRoleId = DB::table('roles')->where('name', 'admin')->where('business_id', $businessId)->value('id');
    if ($adminRoleId) {
        DB::table('role_has_users')->insert([
            'role_id' => $adminRoleId,
            'user_id' => $userId,
        ]);
    }
}

// ============================================================
// 3. CREATE BUSINESS LOCATION
// ============================================================
echo "\n[3/7] Creating business location...\n";

$locationExists = DB::table('business_locations')->where('business_id', $businessId)->exists();

if ($locationExists) {
    $loc = DB::table('business_locations')->where('business_id', $businessId)->first();
    $locationId = $loc->id;
    echo "  Location already exists (ID: $locationId), skipping.\n";
} else {
    $invoiceSchemeId = DB::table('invoice_schemes')->first()->id ?? 1;
    
    $locationId = DB::table('business_locations')->insertGetId([
        'business_id' => $businessId,
        'location_id' => 'SES0001',
        'name' => 'Saya Elegant Style - Main Store',
        'landmark' => '',
        'country' => 'Kuwait',
        'state' => 'Hawalli',
        'city' => 'Hawalli',
        'zip_code' => '000000',
        'invoice_scheme_id' => $invoiceSchemeId,
        'sale_invoice_scheme_id' => $invoiceSchemeId,
        'invoice_layout_id' => 2,
        'sale_invoice_layout_id' => 2,
        'print_receipt_on_invoice' => 1,
        'receipt_printer_type' => 'browser',
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "  Created location ID: $locationId\n";
}

// ============================================================
// 4. CREATE BRANDS
// ============================================================
echo "\n[4/7] Creating brands...\n";

$brands = ['Vestopazzo', 'Nomination Italy', 'Vera Luce Sicily'];
$brandIds = [];

foreach ($brands as $brandName) {
    $existing = DB::table('brands')->where('name', $brandName)->where('business_id', $businessId)->first();
    if ($existing) {
        $brandIds[$brandName] = $existing->id;
        echo "  Brand '$brandName' exists (ID: {$existing->id})\n";
    } else {
        $id = DB::table('brands')->insertGetId([
            'business_id' => $businessId,
            'name' => $brandName,
            'created_by' => $ownerId,
            'use_for_repair' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $brandIds[$brandName] = $id;
        echo "  Created brand '$brandName' (ID: $id)\n";
    }
}

// ============================================================
// 5. CREATE CATEGORIES
// ============================================================
echo "\n[5/7] Creating categories...\n";

$categories = [
    'Scarves' => 'SCARVES',
    'Earrings' => 'Earrings',
    'Bracelets' => 'Bracelets',
    'Necklaces' => 'Necklaces',
    'Watches' => 'Watches',
    'Rings' => 'Rings',
];
$categoryIds = [];

foreach ($categories as $catName => $shortCode) {
    $existing = DB::table('categories')->where('name', $catName)->where('business_id', $businessId)->first();
    if ($existing) {
        $categoryIds[$catName] = $existing->id;
        echo "  Category '$catName' exists (ID: {$existing->id})\n";
    } else {
        $id = DB::table('categories')->insertGetId([
            'name' => $catName,
            'business_id' => $businessId,
            'short_code' => $shortCode,
            'parent_id' => 0,
            'category_type' => 'product',
            'created_by' => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $categoryIds[$catName] = $id;
        echo "  Created category '$catName' (ID: $id)\n";
    }
}

// Map Shopify type tags to our category IDs
$categoryMap = [
    'Scarves' => $categoryIds['Scarves'],
    'Earrings' => $categoryIds['Earrings'],
    'Bracelets' => $categoryIds['Bracelets'],
    'Necklaces' => $categoryIds['Necklaces'],
    'Watches' => $categoryIds['Watches'],
    'Rings' => $categoryIds['Rings'],
];

// ============================================================
// 6. CREATE PRODUCTS
// ============================================================
echo "\n[6/7] Creating products...\n";

$products = [
    // SCARVES
    ['name' => 'GEOMETRIC SCARF', 'sku' => 'SES-SC-001', 'price' => 12.000, 'brand' => 'Vestopazzo', 'category' => 'Scarves', 'stock' => 2, 'sku_ref' => 'E25SI09S',
     'description' => 'The Geometric scarf is made from 100% cotton. Elegant and delicate, this scarf stands out for its precious geometric print. Dimensions: 70 x 180 cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/IMG-9697.png?v=1787306328'],
    
    ['name' => 'SCARF FANTASY - Beige', 'sku' => 'SES-SC-002', 'price' => 7.500, 'brand' => 'Vestopazzo', 'category' => 'Scarves', 'stock' => 1, 'sku_ref' => 'ESIMXS-1',
     'description' => 'The Fantasy scarf is made from 100% cotton. Elegant and delicate, this scarf stands out for its precious multi color fantasy. Color: Beige. Dimensions: 70 x 180 cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/IMG-9701.png?v=1787306774'],
    
    ['name' => 'SCARF FANTASY - Red Blue', 'sku' => 'SES-SC-003', 'price' => 7.500, 'brand' => 'Vestopazzo', 'category' => 'Scarves', 'stock' => 1, 'sku_ref' => 'ESIMXS-2',
     'description' => 'The Fantasy scarf is made from 100% cotton. Color: Red-Blue. Dimensions: 70 x 180 cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/IMG-9704.png?v=1787306977'],
    
    ['name' => 'SCARF FANTASY - Pink', 'sku' => 'SES-SC-004', 'price' => 7.500, 'brand' => 'Vestopazzo', 'category' => 'Scarves', 'stock' => 1, 'sku_ref' => 'ESIMXS-3',
     'description' => 'The Fantasy scarf is made from 100% cotton. Color: Pink. Dimensions: 70 x 180 cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/IMG-9706.png?v=1787307225'],
    
    ['name' => 'SCARF FANTASY - Green', 'sku' => 'SES-SC-005', 'price' => 7.500, 'brand' => 'Vestopazzo', 'category' => 'Scarves', 'stock' => 1, 'sku_ref' => 'ESIMXS-4',
     'description' => 'The Fantasy scarf is made from 100% cotton. Color: Green. Dimensions: 70 x 180 cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/Vestopazzo_ESIMXS_1786174900491.jpg?v=1787265622'],
    
    ['name' => 'SCARF FANTASY - Rose Gold', 'sku' => 'SES-SC-006', 'price' => 7.500, 'brand' => 'Vestopazzo', 'category' => 'Scarves', 'stock' => 1, 'sku_ref' => 'ESIMXS-5',
     'description' => 'The Fantasy scarf is made from 100% cotton. Color: Rose Gold. Dimensions: 70 x 180 cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/Vestopazzo_ESIMXS_1786175105628.jpg?v=1787265695'],
    
    ['name' => 'SCARF FANTASY - White', 'sku' => 'SES-SC-007', 'price' => 7.500, 'brand' => 'Vestopazzo', 'category' => 'Scarves', 'stock' => 1, 'sku_ref' => 'ESIMXS-6',
     'description' => 'The Fantasy scarf is made from 100% cotton. Color: White. Dimensions: 70 x 180 cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/Vestopazzo_ESIMXS_1786175219732.jpg?v=1787265814'],
    
    ['name' => 'SCARF FANTASY - Red', 'sku' => 'SES-SC-008', 'price' => 7.500, 'brand' => 'Vestopazzo', 'category' => 'Scarves', 'stock' => 1, 'sku_ref' => 'ESIMXS-7',
     'description' => 'The Fantasy scarf is made from 100% cotton. Color: Red. Dimensions: 70 x 180 cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/Vestopazzo_ESIMXS_1786175351981.jpg?v=1787265889'],
    
    ['name' => 'SCARF FANTASY - Blue', 'sku' => 'SES-SC-009', 'price' => 7.500, 'brand' => 'Vestopazzo', 'category' => 'Scarves', 'stock' => 1, 'sku_ref' => 'ESIMXS-8',
     'description' => 'The Fantasy scarf is made from 100% cotton. Color: Blue. Dimensions: 70 x 180 cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/Vestopazzo_ESIMXS_1786176520162.jpg?v=1787265956'],
    
    ['name' => 'SCARF FANTASY - Orange', 'sku' => 'SES-SC-010', 'price' => 7.500, 'brand' => 'Vestopazzo', 'category' => 'Scarves', 'stock' => 1, 'sku_ref' => 'ESIMXS-9',
     'description' => 'The Fantasy scarf is made from 100% cotton. Color: Orange. Dimensions: 70 x 180 cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/Vestopazzo_ESIMXS_1786176643513.jpg?v=1787266021'],
    
    ['name' => 'SCARF FANTASY - Navy', 'sku' => 'SES-SC-011', 'price' => 7.500, 'brand' => 'Vestopazzo', 'category' => 'Scarves', 'stock' => 1, 'sku_ref' => 'ESIMXS-10',
     'description' => 'The Fantasy scarf is made from 100% cotton. Color: Navy. Dimensions: 70 x 180 cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/Vestopazzo_ESIMXS_1786178534782.jpg?v=1787266087'],
    
    // EARRINGS (Vestopazzo)
    ['name' => 'BUTTERFLY EARRINGS', 'sku' => 'SES-ER-001', 'price' => 12.000, 'brand' => 'Vestopazzo', 'category' => 'Earrings', 'stock' => 4, 'sku_ref' => 'MP3009S',
     'description' => 'Small hoop earrings with a mother-of-pearl butterfly charm. Hoop diameter: 0.8 cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP3009S_01.webp?v=1786100871'],
    
    ['name' => 'BLUE FISH HOOP EARRINGS', 'sku' => 'SES-ER-002', 'price' => 12.000, 'brand' => 'Vestopazzo', 'category' => 'Earrings', 'stock' => 2, 'sku_ref' => 'MP3008S',
     'description' => 'Small hoop earrings with a blue mother-of-pearl fish charm. Snap closure with pin. Hoop diameter: 0.8 cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP3008S_01.webp?v=1786100870'],
    
    ['name' => 'STAR EARRINGS', 'sku' => 'SES-ER-003', 'price' => 8.500, 'brand' => 'Vestopazzo', 'category' => 'Earrings', 'stock' => 4, 'sku_ref' => 'MP3007S',
     'description' => 'Star-shaped stud earrings with a mother-of-pearl insert on a gold-plated steel base. Pin closure with butterfly.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP3007S_01.webp?v=1786100871'],
    
    ['name' => 'DREAMCATCHER EARRINGS', 'sku' => 'SES-ER-004', 'price' => 12.200, 'brand' => 'Vestopazzo', 'category' => 'Earrings', 'stock' => 3, 'sku_ref' => 'MP3006S',
     'description' => 'Dreamcatcher pendant earrings, composed of a circular mother-of-pearl element with three movable golden leaves. Stud closure.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP3006S_01.webp?v=1786100870'],
    
    ['name' => 'MOGRA MOTHER OF PEARL EARRINGS', 'sku' => 'SES-ER-005', 'price' => 7.500, 'brand' => 'Vestopazzo', 'category' => 'Earrings', 'stock' => 7, 'sku_ref' => 'MP3005S',
     'description' => 'Drop earrings with small white mother-of-pearl spheres arranged in a cluster. Stud closure with butterfly clasp.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP3005S_01.webp?v=1786100870'],
    
    ['name' => 'WHITE FISH HOOP EARRINGS', 'sku' => 'SES-ER-006', 'price' => 12.000, 'brand' => 'Vestopazzo', 'category' => 'Earrings', 'stock' => 3, 'sku_ref' => 'MP3004S',
     'description' => 'Small hoop earrings featuring gold micro spheres and a dangling mother-of-pearl fish charm. Snap closure. Hoop diameter: 1.5 cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP3004S_01.webp?v=1786100869'],
    
    ['name' => 'HEART HOOP EARRINGS', 'sku' => 'SES-ER-007', 'price' => 12.200, 'brand' => 'Vestopazzo', 'category' => 'Earrings', 'stock' => 1, 'sku_ref' => 'MP3003S',
     'description' => 'Small hoop earrings with a mother-of-pearl heart pendant. Stud closure with butterfly clasp. Hoop diameter: 1.5 cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP3003S_01.webp?v=1786100868'],
    
    ['name' => 'SHELL HOOP EARRINGS', 'sku' => 'SES-ER-008', 'price' => 13.500, 'brand' => 'Vestopazzo', 'category' => 'Earrings', 'stock' => 4, 'sku_ref' => 'MP3002S',
     'description' => 'Medium hoop earrings decorated with mother-of-pearl beads and a shell charm. Hook closure. Hoop diameter: 3.5 cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP3002S_01.webp?v=1786100868'],
    
    ['name' => 'MOGRA HOOP EARRINGS', 'sku' => 'SES-ER-009', 'price' => 9.000, 'brand' => 'Vestopazzo', 'category' => 'Earrings', 'stock' => 4, 'sku_ref' => 'MP3001S',
     'description' => 'Medium-small hoop earrings with mogra mother-of-pearl spheres. Hook closure. Hoop diameter: 2.5 cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP3001S_01.webp?v=1786100869'],
    
    ['name' => 'MOTHER-OF-PEARL HEART HOOP EARRINGS', 'sku' => 'SES-ER-010', 'price' => 13.500, 'brand' => 'Vestopazzo', 'category' => 'Earrings', 'stock' => 4, 'sku_ref' => 'MP3000S',
     'description' => 'Large hoop earrings with mother-of-pearl spheres in aquamarine shades and heart pendant. Interlocking clasp. Hoop diameter: 4.5 cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP3000S_01.webp?v=1786100869'],
    
    ['name' => 'OPEN CIRCLE EARRINGS', 'sku' => 'SES-ER-011', 'price' => 7.500, 'brand' => 'Vestopazzo', 'category' => 'Earrings', 'stock' => 3, 'sku_ref' => 'AL17438',
     'description' => 'Bold open hoop earrings handmade from 100% recycled aluminium. Stud fastening with butterfly clasp. Length: 2.8 cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/AL17438_01.webp?v=1786100870'],
    
    ['name' => 'BOUCLES TRIANGLE PLIE', 'sku' => 'SES-ER-012', 'price' => 7.500, 'brand' => 'Vestopazzo', 'category' => 'Earrings', 'stock' => 4, 'sku_ref' => 'AL17387',
     'description' => 'Geometric folded triangle earrings handmade from 100% recycled aluminium. Nickel tested, handcrafted.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/AL17387_01.jpg?v=1786100867'],
    
    // BRACELETS (Vestopazzo)
    ['name' => 'HEART-SHAPED CYLINDER STRETCH BRACELET', 'sku' => 'SES-BR-001', 'price' => 10.150, 'brand' => 'Vestopazzo', 'category' => 'Bracelets', 'stock' => 5, 'sku_ref' => 'MP2007',
     'description' => 'Bracelet composed of small cylindrical mother-of-pearl elements in sand tones with heart-shaped pendant.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP2007_01.webp?v=1786100868'],
    
    ['name' => 'SHELL STRETCH BRACELET', 'sku' => 'SES-BR-002', 'price' => 8.500, 'brand' => 'Vestopazzo', 'category' => 'Bracelets', 'stock' => 4, 'sku_ref' => 'MP2006',
     'description' => 'Stretch bracelet with mother-of-pearl cylinders, gold-plated microspheres and shell charm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP2006_01.webp?v=1786100868'],
    
    ['name' => 'STAR STRETCH BRACELET', 'sku' => 'SES-BR-003', 'price' => 9.000, 'brand' => 'Vestopazzo', 'category' => 'Bracelets', 'stock' => 3, 'sku_ref' => 'MP2004',
     'description' => 'Stretch bracelet with blue mother-of-pearl beads and a star-shaped charm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP2004_01.webp?v=1786100868'],
    
    ['name' => 'HEART STRETCH BRACELET', 'sku' => 'SES-BR-004', 'price' => 9.000, 'brand' => 'Vestopazzo', 'category' => 'Bracelets', 'stock' => 1, 'sku_ref' => 'MP2003',
     'description' => 'Stretch bracelet featuring mother-of-pearl beads and a heart pendant.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP2003_01.webp?v=1786100867'],
    
    ['name' => 'MICRO HEART ELASTIC BRACELET', 'sku' => 'SES-BR-005', 'price' => 9.500, 'brand' => 'Vestopazzo', 'category' => 'Bracelets', 'stock' => 2, 'sku_ref' => 'MP2002',
     'description' => 'Bracelet with raised heart-shaped pendant. Small spheres in milky tones interspersed with gold-plated micro spheres.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP2002_01.webp?v=1786100867'],
    
    ['name' => 'BUTTERFLY STRETCH BRACELET', 'sku' => 'SES-BR-006', 'price' => 9.000, 'brand' => 'Vestopazzo', 'category' => 'Bracelets', 'stock' => 0, 'sku_ref' => 'MP2001',
     'description' => 'Stretch bracelet composed of white mother-of-pearl spheres and a butterfly charm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP2001_01.webp?v=1786100868'],
    
    ['name' => 'SMALL SNAKE BRACELET', 'sku' => 'SES-BR-007', 'price' => 4.500, 'brand' => 'Vestopazzo', 'category' => 'Bracelets', 'stock' => 1, 'sku_ref' => 'DD13530',
     'description' => 'Elastic bracelet with snake skin texture in recycled brass. Small size, handmade, nickel tested.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/DD13530_01.webp?v=1786100870'],
    
    ['name' => 'BRACELET AFRO COINS', 'sku' => 'SES-BR-008', 'price' => 10.150, 'brand' => 'Vestopazzo', 'category' => 'Bracelets', 'stock' => 4, 'sku_ref' => 'DD13519',
     'description' => 'Elastic fringe bracelet with double row of satin-finish coins in recycled brass. Handmade, nickel tested.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/DD13519_01.webp?v=1786100870'],
    
    ['name' => 'HIGH-END ELASTIC ALUMINUM BRACELET', 'sku' => 'SES-BR-009', 'price' => 12.000, 'brand' => 'Vestopazzo', 'category' => 'Bracelets', 'stock' => 2, 'sku_ref' => 'AL01176',
     'description' => '100% recycled aluminum band bracelet in a sculptural cuff shape. Ultralight, nickel tested, handcrafted.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/AL01176_01.webp?v=1786100870'],
    
    ['name' => 'RIGID ALUMINUM BRACELET', 'sku' => 'SES-BR-010', 'price' => 7.500, 'brand' => 'Vestopazzo', 'category' => 'Bracelets', 'stock' => 3, 'sku_ref' => 'AL00195',
     'description' => 'Rigid bangle made from 100% recycled aluminum. Ultralight, nickel tested, handcrafted.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/AL00195_01.jpg?v=1786100869'],
    
    ['name' => 'RIGID BRACELET WITH FLOWER TEXTURE', 'sku' => 'SES-BR-011', 'price' => 6.000, 'brand' => 'Vestopazzo', 'category' => 'Bracelets', 'stock' => 2, 'sku_ref' => 'AL00184',
     'description' => 'Open and adjustable bangle with engraved floral texture. Handmade from 100% recycled aluminum.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/AL00184_01.jpg?v=1786100868'],
    
    // BRACELETS (Nomination Italy)
    ['name' => 'COMPOSABLE ROSE GOLD BRACELET GREEN LOVE', 'sku' => 'SES-BR-012', 'price' => 27.500, 'brand' => 'Nomination Italy', 'category' => 'Bracelets', 'stock' => 1, 'sku_ref' => '330322/10/530301/011',
     'description' => 'Composable rose gold bracelet with green love charm. 13pcs base. Made in Italy.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/IMG-8110.png?v=1784919267'],
    
    ['name' => 'COMPOSABLE ROSE GOLD BRACELET GREEN CLOVER', 'sku' => 'SES-BR-013', 'price' => 31.400, 'brand' => 'Nomination Italy', 'category' => 'Bracelets', 'stock' => 1, 'sku_ref' => '630302/12/530301/011',
     'description' => 'Composable rose gold bracelet with green clover charm. 13pcs base. Made in Italy.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/IMG-8047.png?v=1784844584'],
    
    ['name' => 'EXTENSION XL 4 CZ STONES RED', 'sku' => 'SES-BR-014', 'price' => 200.900, 'brand' => 'Nomination Italy', 'category' => 'Bracelets', 'stock' => 1, 'sku_ref' => '042541/06',
     'description' => 'Nomination Italy Extension XL with 4 red cubic zirconia stones and 18k gold details. Exclusive, made in Italy.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/IMG-7547.png?v=1784146261'],
    
    ['name' => 'XTE BRACELET 18K GOLD 6 STONES TURQUOISE', 'sku' => 'SES-BR-015', 'price' => 235.800, 'brand' => 'Nomination Italy', 'category' => 'Bracelets', 'stock' => 1, 'sku_ref' => '043560/003',
     'description' => 'Nomination Italy XTE bracelet in steel with 18k gold and 6 turquoise cubic zirconia stones.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/IMG-7546.jpg?v=1784145774'],
    
    ['name' => 'XTE BRACELET 18K GOLD 12 STONES CZ PEARL', 'sku' => 'SES-BR-016', 'price' => 267.300, 'brand' => 'Nomination Italy', 'category' => 'Bracelets', 'stock' => 1, 'sku_ref' => '043563/007',
     'description' => 'Nomination Italy XTE bracelet in steel with 18k gold, 12 stones and white CZ pearl.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/A4B82000-64AF-4062-8863-1A22FCAE8323.jpg?v=1784145560'],
    
    ['name' => 'XTE BRACELET 18K GOLD CZ DOTS', 'sku' => 'SES-BR-017', 'price' => 206.600, 'brand' => 'Nomination Italy', 'category' => 'Bracelets', 'stock' => 1, 'sku_ref' => '043563/007',
     'description' => 'Nomination Italy XTE bracelet in stainless steel with 18k gold and cubic zirconia dots.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/C44ABF1B-40F6-44B9-BA48-48B7B15D74D2.png?v=1784145374'],
    
    // BRACELETS (Vera Luce)
    ['name' => 'MATTE MESH STATEMENT CUFF BRACELET GOLD', 'sku' => 'SES-BR-018', 'price' => 12.000, 'brand' => 'Vera Luce Sicily', 'category' => 'Bracelets', 'stock' => 1, 'sku_ref' => 'S322-VL-G',
     'description' => 'Vera Luce Sicily gold matte woven mesh torque cuff bracelet. Slip-on with end caps.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/7BCA472F-53BF-4F41-8E51-79DD508B6BA0.png?v=1784052591'],
    
    ['name' => 'ROMAN NUMERAL MESH CUFF BRACELET GOLD', 'sku' => 'SES-BR-019', 'price' => 13.000, 'brand' => 'Vera Luce Sicily', 'category' => 'Bracelets', 'stock' => 3, 'sku_ref' => 'S290-VL-G',
     'description' => 'Vera Luce Sicily gold wide mesh cuff with Roman numeral crystal endpieces.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/IMG-7424.png?v=1784053267'],
    
    ['name' => 'MALACHITE GREEN CLOVER LINK BRACELET', 'sku' => 'SES-BR-020', 'price' => 12.000, 'brand' => 'Vera Luce Sicily', 'category' => 'Bracelets', 'stock' => 1, 'sku_ref' => 'S015-VL-G',
     'description' => 'Vera Luce Sicily gold chain bracelet with malachite green four-leaf clover motifs.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/75219C3D-B316-4DA5-ACC8-B6096FDFEEC9.png?v=1784053361'],
    
    // NECKLACES (Vestopazzo)
    ['name' => 'HEART SPHERES CHOKER', 'sku' => 'SES-NK-001', 'price' => 22.500, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 1, 'sku_ref' => 'MP1012',
     'description' => 'Choker with mother-of-pearl spheres and stylized heart-shaped front closure in steel.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP1012_01.webp?v=1786100869'],
    
    ['name' => 'STAR NECKLACE', 'sku' => 'SES-NK-002', 'price' => 13.500, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 2, 'sku_ref' => 'MP1010',
     'description' => 'Star necklace with mother-of-pearl spheres and luminous star-shaped charm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP1010_01.webp?v=1786100869'],
    
    ['name' => 'HEART SPHERES NECKLACE', 'sku' => 'SES-NK-003', 'price' => 14.500, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 1, 'sku_ref' => 'MP1009',
     'description' => 'Necklace with gold beads and aquamarine mother-of-pearl heart pendant mosaic.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP1009_01.webp?v=1786100869'],
    
    ['name' => 'AQUAMARINE SPHERES CHOKER', 'sku' => 'SES-NK-004', 'price' => 9.500, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 3, 'sku_ref' => 'MP1008',
     'description' => 'Choker with aquamarine mother-of-pearl spheres and gold-plated steel micro spheres.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP1008_01.webp?v=1786100869'],
    
    ['name' => 'ALTERNATED SPHERES CHOKER', 'sku' => 'SES-NK-005', 'price' => 11.000, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 2, 'sku_ref' => 'MP1007',
     'description' => 'Minimalist choker with alternating mother-of-pearl and gold-finish spheres.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP1007_01.webp?v=1786100869'],
    
    ['name' => 'MOTHER-OF-PEARL MOSAIC HEART PENDANT', 'sku' => 'SES-NK-006', 'price' => 10.150, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 3, 'sku_ref' => 'MP1006',
     'description' => 'Mother-of-pearl mosaic heart pendant necklace with dynamic reflections.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP1006_01.webp?v=1786100868'],
    
    ['name' => 'VINTAGE HEART NECKLACE', 'sku' => 'SES-NK-007', 'price' => 9.000, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 5, 'sku_ref' => 'MP1005',
     'description' => 'Vintage heart necklace with milky mother-of-pearl and gold-plated steel frame.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP1005_01.webp?v=1786100869'],
    
    ['name' => 'MOTHER-OF-PEARL HEART NECKLACE', 'sku' => 'SES-NK-008', 'price' => 9.000, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 3, 'sku_ref' => 'MP1004',
     'description' => 'Heart pendant necklace with iridescent mother-of-pearl and gold-plated steel microspheres.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP1004_01.webp?v=1786100870'],
    
    ['name' => 'DREAMCATCHER NECKLACE', 'sku' => 'SES-NK-009', 'price' => 10.150, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 1, 'sku_ref' => 'MP1003',
     'description' => 'Dreamcatcher pendant necklace with mother-of-pearl disc and three movable leaf charms.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP1003_01.webp?v=1786100869'],
    
    ['name' => 'MICRO CHARMS CHOKER', 'sku' => 'SES-NK-010', 'price' => 10.150, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 2, 'sku_ref' => 'MP1002',
     'description' => 'Choker with alternating mother-of-pearl and steel micro charms. Adjustable clasp.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP1002_01.webp?v=1786100870'],
    
    ['name' => 'WHALE TAIL NECKLACE', 'sku' => 'SES-NK-011', 'price' => 10.150, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 4, 'sku_ref' => 'MP1001',
     'description' => 'Whale tail pendant necklace with mother-of-pearl and gold-plated steel edges.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP1001_01.webp?v=1786100870'],
    
    ['name' => 'MOON NECKLACE', 'sku' => 'SES-NK-012', 'price' => 10.150, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 4, 'sku_ref' => 'MP1000',
     'description' => 'Madreperla moon necklace in mother-of-pearl and gold-finish steel. Contemporary chic.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/MP1000_01.webp?v=1786100869'],
    
    // NECKLACES (Vestopazzo - Brass)
    ['name' => 'BARRETTE NECKLACE', 'sku' => 'SES-NK-013', 'price' => 13.000, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 1, 'sku_ref' => 'DD04055',
     'description' => 'Multi-strand recycled brass necklace with geometric bar accents. 50cm + 6.5cm extender. Nickel tested.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/DD04055_01.webp?v=1786100871'],
    
    ['name' => 'HEART COIN PENDANT', 'sku' => 'SES-NK-014', 'price' => 4.500, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 2, 'sku_ref' => 'DD02059',
     'description' => 'Delicate heart coin pendant necklace handmade from recycled brass. Nickel tested.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/DD02059_01.webp?v=1786100869'],
    
    // NECKLACES (Vestopazzo - Aluminum)
    ['name' => 'MIX CIRCLE PENDANT NECKLACE', 'sku' => 'SES-NK-015', 'price' => 10.500, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 1, 'sku_ref' => 'AL04907',
     'description' => 'Round mix-shape pendant necklace from 100% recycled aluminium on raw silk thread. 50cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/AL04907_01.jpg?v=1786100869'],
    
    ['name' => 'HEART PENDANT ALUMINUM NECKLACE', 'sku' => 'SES-NK-016', 'price' => 10.150, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 2, 'sku_ref' => 'AL04906',
     'description' => 'Heart pendant necklace from 100% recycled aluminium on raw silk thread. 50cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/AL04906_01.jpg?v=1786100869'],
    
    ['name' => '3 RECTANGLE SLIDING PENDANT', 'sku' => 'SES-NK-017', 'price' => 10.150, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 3, 'sku_ref' => 'AL04286',
     'description' => 'Sliding necklace with three rounded rectangular elements in 100% recycled aluminium. Adjustable.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/AL04286_01.webp?v=1786100869'],
    
    ['name' => 'DRAGONFLY PENDANT', 'sku' => 'SES-NK-018', 'price' => 10.150, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 3, 'sku_ref' => 'AL04165',
     'description' => 'Statement dragonfly pendant on adjustable cord, handmade from 100% recycled aluminium.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/AL04165_01.jpg?v=1786100869'],
    
    ['name' => 'NECKLACE 3 STRANDS SPHERES', 'sku' => 'SES-NK-019', 'price' => 34.500, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 1, 'sku_ref' => 'AL02184',
     'description' => 'Three-strand graduated sphere necklace in 100% recycled aluminium on black cord. Adjustable, ultralight.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/AL02184_01.webp?v=1786100872'],
    
    ['name' => 'ALUMINUM OVAL CHAIN NECKLACE', 'sku' => 'SES-NK-020', 'price' => 24.500, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 2, 'sku_ref' => 'AL02053',
     'description' => 'Irregular oval chain necklace handmade from 100% recycled aluminium. Water-resistant.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/AL02053_01.webp?v=1786100869'],
    
    ['name' => 'PLAQUES NECKLACE', 'sku' => 'SES-NK-021', 'price' => 24.000, 'brand' => 'Vestopazzo', 'category' => 'Necklaces', 'stock' => 1, 'sku_ref' => 'AL02020',
     'description' => 'Bold plaques necklace handmade from 100% recycled aluminium. Contemporary, artisanal.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/AL02020_01.jpg?v=1786100869'],
    
    // NECKLACES (Nomination Italy)
    ['name' => 'MOSAICA HEART NECKLACE SILVER', 'sku' => 'SES-NK-022', 'price' => 35.910, 'brand' => 'Nomination Italy', 'category' => 'Necklaces', 'stock' => 1, 'sku_ref' => '241604/010',
     'description' => 'Nomination Italy Mosaica heart pendant necklace in sterling silver with pink cubic zirconia. 40-46cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/BBBDCA74-96FF-467A-9316-BAAEE2344CED.jpg?v=1784379467'],
    
    ['name' => 'MOSAICA HEART NECKLACE ROSE GOLD', 'sku' => 'SES-NK-023', 'price' => 35.910, 'brand' => 'Nomination Italy', 'category' => 'Necklaces', 'stock' => 1, 'sku_ref' => '241604/011',
     'description' => 'Nomination Italy Mosaica heart pendant necklace in rose gold. 40-46cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/B651806C-EE0B-4070-A89F-FF1A87DAD925.jpg?v=1784379467'],
    
    ['name' => 'MOSAICA HEART NECKLACE GOLD', 'sku' => 'SES-NK-024', 'price' => 35.910, 'brand' => 'Nomination Italy', 'category' => 'Necklaces', 'stock' => 1, 'sku_ref' => '241604/012',
     'description' => 'Nomination Italy Mosaica heart pendant necklace in gold. 40-46cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/53F21FA9-86B0-44B3-AF56-94F75FF3326B.jpg?v=1784379467'],
    
    ['name' => 'MOSAICA NECKLACE SILVER WHITE BLUE CZ', 'sku' => 'SES-NK-025', 'price' => 31.220, 'brand' => 'Nomination Italy', 'category' => 'Necklaces', 'stock' => 0, 'sku_ref' => '241605/010',
     'description' => 'Nomination Italy Mosaica necklace in sterling silver with 49 white and blue cubic zirconia. 38-44cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/IMG-7668.jpg?v=1784365822'],
    
    ['name' => 'MOSAICA NECKLACE GOLD WHITE BLUE CZ', 'sku' => 'SES-NK-026', 'price' => 31.220, 'brand' => 'Nomination Italy', 'category' => 'Necklaces', 'stock' => 1, 'sku_ref' => '241605/011',
     'description' => 'Nomination Italy Mosaica necklace in gold with white and blue cubic zirconia. 38-44cm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/IMG-7672.jpg?v=1784366086'],
    
    // NECKLACES (Vera Luce)
    ['name' => 'CLEAR CRYSTAL TUBOGAS CHOKER GOLD', 'sku' => 'SES-NK-027', 'price' => 15.000, 'brand' => 'Vera Luce Sicily', 'category' => 'Necklaces', 'stock' => 4, 'sku_ref' => 'W060-VL-G',
     'description' => 'Vera Luce Sicily gold tubogas collar choker with emerald-cut clear crystal in four-prong setting.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/F20E3B4D-893C-40E7-8935-4EF0FB339001.png?v=1784053779'],
    
    ['name' => 'MATTE MESH COLLAR CHOKER GOLD', 'sku' => 'SES-NK-028', 'price' => 12.000, 'brand' => 'Vera Luce Sicily', 'category' => 'Necklaces', 'stock' => 1, 'sku_ref' => 'S323-VL-G',
     'description' => 'Vera Luce Sicily gold matte woven mesh torque collar choker. Slip-on with end caps.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/26525510-7BC3-4DE9-AFC9-CB013E2F6169.png?v=1784051805'],
    
    ['name' => 'WOVEN MESH COLLAR CHOKER SILVER', 'sku' => 'SES-NK-029', 'price' => 12.000, 'brand' => 'Vera Luce Sicily', 'category' => 'Necklaces', 'stock' => 1, 'sku_ref' => 'S320-VL-S',
     'description' => 'Vera Luce Sicily silver woven mesh torque collar choker. Slip-on with end caps.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/E8F5438B-63EE-447C-A438-21AD8E414605.png?v=1784052746'],
    
    ['name' => 'WOVEN MESH COLLAR CHOKER GOLD', 'sku' => 'SES-NK-030', 'price' => 12.000, 'brand' => 'Vera Luce Sicily', 'category' => 'Necklaces', 'stock' => 1, 'sku_ref' => 'M327-VL-G',
     'description' => 'Vera Luce Sicily gold woven mesh torque collar choker. Slip-on with end caps.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/download_8ff95a9f-a712-4f5a-8e42-afa147766a7a.jpg?v=1783948042'],
    
    // WATCHES (Nomination Italy)
    ['name' => 'PARIS WATCH SUNRAY WHITE', 'sku' => 'SES-WT-001', 'price' => 50.100, 'brand' => 'Nomination Italy', 'category' => 'Watches', 'stock' => 1, 'sku_ref' => '076037/008',
     'description' => 'Stainless steel watch with white Cubic Zirconia. Band width: 0.8cm. Sunray Pink dial. Waterproof: 3 bars.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/FullSizeRender_c42676d0-d21b-4a34-8179-2a8e72339ee8.jpg?v=1786637025'],
    
    ['name' => 'PARIS WATCH STEEL STRAP RECTANGULAR CZ', 'sku' => 'SES-WT-002', 'price' => 78.200, 'brand' => 'Nomination Italy', 'category' => 'Watches', 'stock' => 1, 'sku_ref' => '',
     'description' => 'Stainless steel watch with white Cubic Zirconia. Sunray Silver dial. Waterproof: 3 bars.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/IMG-9368.webp?v=1786636546'],
    
    ['name' => 'PARIS WATCH SUNRAY PINK', 'sku' => 'SES-WT-003', 'price' => 50.100, 'brand' => 'Nomination Italy', 'category' => 'Watches', 'stock' => 1, 'sku_ref' => '076037/014',
     'description' => 'Stainless steel watch with white Cubic Zirconia. Sunray Pink dial with Roman numerals. Waterproof: 3 bars.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/IMG-9367.png?v=1786636239'],
    
    ['name' => 'TIME PARIS WATCH SUNRAY SILVER 076039', 'sku' => 'SES-WT-004', 'price' => 50.100, 'brand' => 'Nomination Italy', 'category' => 'Watches', 'stock' => 2, 'sku_ref' => '076039/017',
     'description' => 'Time watch compatible with Composable links. Stainless steel with Sunray Silver dial. Waterproof: 3 atm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/3A2FEDE9-7519-4DB1-A72A-2EDBA56CB8D6.jpg?v=1786635769'],
    
    ['name' => 'TIME PARIS WATCH SUNRAY SILVER', 'sku' => 'SES-WT-005', 'price' => 50.100, 'brand' => 'Nomination Italy', 'category' => 'Watches', 'stock' => 1, 'sku_ref' => '',
     'description' => 'Time watch compatible with Composable links. Stainless steel with Sunray Silver dial. Waterproof: 3 atm.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/FFB9E66A-7D9E-43C0-A61D-5FB9AA3B640F.jpg?v=1785000841'],
    
    // RINGS (Nomination Italy)
    ['name' => 'BUTTERFLY MEDIUM RINGS STEEL CZ PVD GOLD', 'sku' => 'SES-RG-001', 'price' => 29.300, 'brand' => 'Nomination Italy', 'category' => 'Rings', 'stock' => 1, 'sku_ref' => '027321/012',
     'description' => 'Butterfly medium rings in stainless steel with cubic zirconia and PVD yellow gold finish.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/7BB7A52C-283E-4DEE-9A44-FB2D8897AB47.png?v=1784621403'],
    
    ['name' => 'BUTTERFLY MEDIUM RING SS CZ PVD GOLD', 'sku' => 'SES-RG-002', 'price' => 42.300, 'brand' => 'Nomination Italy', 'category' => 'Rings', 'stock' => 1, 'sku_ref' => '027329/012',
     'description' => 'Butterfly medium ring in stainless steel with cubic zirconia and PVD yellow gold finish.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/IMG-8045.png?v=1784843993'],
    
    // RINGS (Vera Luce)
    ['name' => 'CLEAR CRYSTAL TUBOGAS STRETCH RING GOLD', 'sku' => 'SES-RG-003', 'price' => 10.000, 'brand' => 'Vera Luce Sicily', 'category' => 'Rings', 'stock' => 3, 'sku_ref' => 'RW050-VL-G',
     'description' => 'Vera Luce Sicily gold tubogas stretch ring with emerald-cut clear crystal in four-prong setting.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/27026DF2-F2F7-407B-8548-F2F498F588D6.png?v=1784053485'],
    
    ['name' => 'CHAMPAGNE STONE TUBOGAS STRETCH RING', 'sku' => 'SES-RG-004', 'price' => 10.000, 'brand' => 'Vera Luce Sicily', 'category' => 'Rings', 'stock' => 4, 'sku_ref' => 'RK017-VL-G',
     'description' => 'Vera Luce Sicily gold tubogas stretch ring with emerald-cut champagne crystal.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/6339AC2E-83FD-4C5F-8F78-A483DA13BCA8.png?v=1784053606'],
    
    ['name' => 'EMERALD GREEN TUBOGAS STRETCH RING', 'sku' => 'SES-RG-005', 'price' => 10.000, 'brand' => 'Vera Luce Sicily', 'category' => 'Rings', 'stock' => 7, 'sku_ref' => 'RG010-VL-G',
     'description' => 'Vera Luce Sicily gold tubogas stretch ring with emerald-cut dark green crystal.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/F2C792BD-7C31-4347-B76D-C4471983EA60.png?v=1784054688'],
    
    ['name' => 'BLACK STONE TUBOGAS STRETCH RING', 'sku' => 'SES-RG-006', 'price' => 10.000, 'brand' => 'Vera Luce Sicily', 'category' => 'Rings', 'stock' => 11, 'sku_ref' => 'RB015-VL-G',
     'description' => 'Vera Luce Sicily gold tubogas stretch ring with emerald-cut black crystal.',
     'image' => 'https://cdn.shopify.com/s/files/1/0649/1868/3751/files/8DBCF49F-B2AC-4591-8E6E-712F8C889EEA.png?v=1784054866'],
];

$productsCreated = 0;
$productsSkipped = 0;

foreach ($products as $productData) {
    $existing = DB::table('products')->where('name', $productData['name'])->where('business_id', $businessId)->first();
    
    if ($existing) {
        echo "  Product '{$productData['name']}' exists (ID: {$existing->id}), skipping.\n";
        $productsSkipped++;
        continue;
    }
    
    $categoryId = $categoryMap[$productData['category']] ?? null;
    $brandId = $brandIds[$productData['brand']] ?? null;
    
    $productId = DB::table('products')->insertGetId([
        'name' => $productData['name'],
        'business_id' => $businessId,
        'type' => 'single',
        'category_id' => $categoryId,
        'brand_id' => $brandId,
        'tax' => null,
        'tax_type' => 'exclusive',
        'enable_stock' => 1,
        'is_flexible_price' => 0,
        'alert_quantity' => 1,
        'sku' => $productData['sku'],
        'barcode_type' => 'C128',
        'product_description' => $productData['description'] ?? '',
        'created_by' => $ownerId,
        'not_for_selling' => 0,
        'is_inactive' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    // Create product variation (dummy/default)
    $variationId = DB::table('product_variations')->insertGetId([
        'name' => 'DUMMY',
        'product_id' => $productId,
        'is_dummy' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    // Create variation
    $varId = DB::table('variations')->insertGetId([
        'name' => 'DUMMY',
        'product_id' => $productId,
        'sub_sku' => $productData['sku_ref'] ?? $productData['sku'],
        'product_variation_id' => $variationId,
        'default_purchase_price' => 0,
        'dpp_inc_tax' => 0,
        'profit_percent' => 0,
        'default_sell_price' => $productData['price'],
        'sell_price_inc_tax' => $productData['price'],
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    // Create variation location details
    DB::table('variation_location_details')->insert([
        'product_id' => $productId,
        'product_variation_id' => $variationId,
        'variation_id' => $varId,
        'location_id' => $locationId,
        'qty_available' => $productData['stock'],
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    $productsCreated++;
    echo "  Created product '{$productData['name']}' (ID: $productId, Price: {$productData['price']} KWD)\n";
}

echo "\n[7/7] Summary:\n";
echo "  Business: Saya Elegant Style (ID: $businessId)\n";
echo "  Location: Main Store (ID: $locationId)\n";
echo "  Admin: $adminEmail (ID: $ownerId)\n";
echo "  Brands: " . count($brandIds) . " created\n";
echo "  Categories: " . count($categoryIds) . " created\n";
echo "  Products: $productsCreated created, $productsSkipped skipped\n";
echo "\n=== Import Complete! ===\n";
