<?php
/**
 * Sun Beam Hawally Data Importer for VendifyERP / UltimatePOS
 * 
 * Imports:
 * 1. Categories (DCS)
 * 2. Suppliers (Vendors)
 * 3. Customers (with Opening Balance)
 * 4. Products (Inventory with SKU, Names, Categories, Prices, and Stock)
 */

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Business;
use App\BusinessLocation;
use App\User;
use App\Category;
use App\Contact;
use App\Product;
use App\ProductVariation;
use App\Variation;
use App\VariationLocationDetails;
use App\Transaction;
use App\PurchaseLine;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$productUtil = new ProductUtil();
$transactionUtil = new TransactionUtil();

DB::disableQueryLog();

$businessId = 5; // Sun Beam Hawally
$business = Business::find($businessId);
if (!$business) {
    echo "ERROR: Business with ID $businessId not found!\n";
    exit(1);
}

// Verify User
$user = User::where('business_id', $businessId)->where('username', 'sunbeam_admin')->first();
if (!$user) {
    echo "ERROR: User sunbeam_admin not found!\n";
    exit(1);
}
$userId = $user->id;

// Verify Location
$location = BusinessLocation::where('business_id', $businessId)->first();
if (!$location) {
    echo "ERROR: Business Location not found!\n";
    exit(1);
}
$locationId = $location->id;

$limit = null;
if (isset($argv[1]) && is_numeric($argv[1])) {
    $limit = (int)$argv[1];
}

echo "========================================================\n";
echo "Sun Beam Hawally Data Importer\n";
echo "Business: {$business->name} (ID: $businessId)\n";
echo "Location: {$location->name} (ID: $locationId)\n";
echo "User: {$user->username} (ID: $userId)\n";
if ($limit) {
    echo "Mode: TEST MODE (Limit: $limit items)\n";
} else {
    echo "Mode: FULL IMPORT\n";
}
echo "========================================================\n\n";

$dataDir = storage_path('sunbeam_import');

// ==========================================
// 1. IMPORT CATEGORIES
// ==========================================
echo "[1/4] Importing Categories...\n";
$catFile = $dataDir . '/categories.json';
if (!file_exists($catFile)) {
    echo "ERROR: $catFile not found!\n";
    exit(1);
}
$categoriesData = json_decode(file_get_contents($catFile), true);

$categoryLookup = []; // code -> id and name -> id
$catsInserted = 0;
$catsExisting = 0;

foreach ($categoriesData as $catItem) {
    $code = trim($catItem['code']);
    $name = trim($catItem['name']);
    if (empty($name)) $name = $code;

    // Check if category exists
    $cat = Category::where('business_id', $businessId)
        ->where(function($q) use ($code, $name) {
            $q->where('short_code', $code)
              ->orWhere('name', $name);
        })
        ->first();

    if (!$cat) {
        $cat = Category::create([
            'name' => $name,
            'business_id' => $businessId,
            'short_code' => $code,
            'parent_id' => 0,
            'category_type' => 'product',
            'created_by' => $userId
        ]);
        $catsInserted++;
    } else {
        $catsExisting++;
    }

    $categoryLookup[strtoupper($code)] = $cat->id;
    $categoryLookup[strtoupper($name)] = $cat->id;
}
echo "Categories: $catsInserted created, $catsExisting existing. Total mapped: " . count($categoryLookup) . "\n\n";

// ==========================================
// 2. IMPORT VENDORS / SUPPLIERS
// ==========================================
echo "[2/4] Importing Suppliers / Vendors...\n";
$vendFile = $dataDir . '/vendors.json';
$vendorsData = json_decode(file_get_contents($vendFile), true);
$vendorLookup = []; // vend_code -> contact_id
$vendsInserted = 0;
$vendsExisting = 0;

foreach ($vendorsData as $vItem) {
    $vendCode = $vItem['vend_code'];
    $company = trim($vItem['company'] ?? '');
    $first = trim($vItem['first_name'] ?? '');
    $last = trim($vItem['last_name'] ?? '');
    $phone1 = trim($vItem['phone1'] ?? '');
    $phone2 = trim($vItem['phone2'] ?? '');

    $contactId = 'VEND-' . $vendCode;
    $contactName = $company ?: trim("$first $last");
    if (empty($contactName)) $contactName = "Vendor $vendCode";

    $contact = Contact::where('business_id', $businessId)
        ->where(function($q) use ($contactId, $company) {
            $q->where('contact_id', $contactId);
            if (!empty($company)) {
                $q->orWhere('supplier_business_name', $company);
            }
        })
        ->first();

    if (!$contact) {
        $contact = Contact::create([
            'business_id' => $businessId,
            'type' => 'supplier',
            'supplier_business_name' => $company ?: $contactName,
            'name' => $contactName,
            'first_name' => $first,
            'last_name' => $last,
            'contact_id' => $contactId,
            'mobile' => $phone1 ?: $phone2,
            'alternate_number' => $phone2,
            'created_by' => $userId,
            'contact_status' => 'active'
        ]);
        $vendsInserted++;
    } else {
        $vendsExisting++;
    }

    $vendorLookup[$vendCode] = $contact->id;
    if ($company) {
        $vendorLookup[strtoupper($company)] = $contact->id;
    }
}
echo "Suppliers: $vendsInserted created, $vendsExisting existing. Total mapped: " . count($vendorLookup) . "\n\n";

// ==========================================
// 3. IMPORT CUSTOMERS
// ==========================================
echo "[3/4] Importing Customers...\n";
$custFile = $dataDir . '/customers.json';
$customersData = json_decode(file_get_contents($custFile), true);
$custsInserted = 0;
$custsExisting = 0;
$openingBalsCreated = 0;

$transactionDate = $business->start_date ?? date('Y-01-01');

foreach ($customersData as $cItem) {
    $custId = $cItem['cust_id'];
    $company = trim($cItem['company'] ?? '');
    $first = trim($cItem['first_name'] ?? '');
    $last = trim($cItem['last_name'] ?? '');
    $phone = trim($cItem['phone'] ?? '');
    $phone1 = trim($cItem['phone1'] ?? '');
    $phone2 = trim($cItem['phone2'] ?? '');
    $chrgBal = (float)($cItem['chrg_bal'] ?? 0);
    $chrgLimit = (float)($cItem['chrg_limit'] ?? 0);

    $contactId = 'CUST-' . $custId;
    $fullName = trim("$first $last");
    if (empty($fullName)) $fullName = $company ?: "Customer $custId";

    $contact = Contact::where('business_id', $businessId)
        ->where('contact_id', $contactId)
        ->first();

    if (!$contact) {
        $contact = Contact::create([
            'business_id' => $businessId,
            'type' => 'customer',
            'name' => $fullName,
            'first_name' => $first,
            'last_name' => $last,
            'supplier_business_name' => $company,
            'contact_id' => $contactId,
            'mobile' => $phone ?: $phone1 ?: $phone2,
            'alternate_number' => $phone2,
            'credit_limit' => $chrgLimit > 0 ? $chrgLimit : null,
            'created_by' => $userId,
            'contact_status' => 'active'
        ]);
        $custsInserted++;

        if ($chrgBal > 0) {
            $transactionUtil->createOpeningBalanceTransaction($businessId, $contact->id, $chrgBal, $userId, false);
            $openingBalsCreated++;
        }
    } else {
        $custsExisting++;
    }
}
echo "Customers: $custsInserted created, $custsExisting existing. Opening balances created: $openingBalsCreated\n\n";

// ==========================================
// 4. IMPORT INVENTORY & OPENING STOCK
// ==========================================
echo "[4/4] Importing Inventory Products & Stock...\n";
$invFile = $dataDir . '/inventory.json';
$inventoryData = json_decode(file_get_contents($invFile), true);

if ($limit) {
    $inventoryData = array_slice($inventoryData, 0, $limit);
}

$unitId = 4; // Pieces
$prodInserted = 0;
$prodSkipped = 0;
$stockLinesCreated = 0;
$totalQtyAdded = 0;

$existingSkus = Product::where('business_id', $businessId)->pluck('sku')->flip()->toArray();

$chunkSize = 100;
$chunks = array_chunk($inventoryData, $chunkSize);
$totalChunks = count($chunks);

foreach ($chunks as $chunkIndex => $chunk) {
    DB::beginTransaction();
    try {
        foreach ($chunk as $item) {
            $sku = trim($item['sku']);
            $name = trim($item['name']);
            $nameAr = trim($item['name_ar'] ?? '');
            $dcs = strtoupper(trim($item['dcs'] ?? ''));
            $dcsName = strtoupper(trim($item['dcs_name'] ?? ''));
            $qty = (float)($item['qty'] ?? 0);
            $cost = (float)($item['cost'] ?? 0);
            $price = (float)($item['price'] ?? 0);
            $desc1 = trim($item['desc1'] ?? '');
            $desc2 = trim($item['desc2'] ?? '');
            $desc3 = trim($item['desc3'] ?? '');
            $desc4 = trim($item['desc4'] ?? '');

            // Check if product with this SKU already exists in this business
            if (isset($existingSkus[$sku])) {
                $prodSkipped++;
                continue;
            }

            // Resolve Category ID
            $catId = $categoryLookup[$dcs] ?? ($categoryLookup[$dcsName] ?? null);

            // Calculate profit margin
            $profitPercent = 0.0;
            if ($cost > 0 && $price > $cost) {
                $profitPercent = round((($price - $cost) / $cost) * 100, 2);
            }

            $description = trim($desc1 . ($desc2 && $desc2 != $desc1 ? "\n" . $desc2 : '') . ($desc3 ? "\nModel: " . $desc3 : ''));

            // Create Product
            $product = Product::create([
                'name' => mb_substr($name, 0, 190),
                'name_ar' => $nameAr ? mb_substr($nameAr, 0, 190) : null,
                'business_id' => $businessId,
                'type' => 'single',
                'unit_id' => $unitId,
                'brand_id' => null,
                'category_id' => $catId,
                'tax' => null,
                'tax_type' => 'exclusive',
                'enable_stock' => 1,
                'alert_quantity' => 0,
                'sku' => $sku,
                'barcode_type' => 'C128',
                'created_by' => $userId,
                'product_description' => $description,
                'description_ar' => $desc4 ?: $nameAr,
                'is_inactive' => 0,
                'not_for_selling' => 0
            ]);

            // Sync Product Location
            DB::table('product_locations')->insert([
                'product_id' => $product->id,
                'location_id' => $locationId
            ]);

            // Create Product Variation (DUMMY)
            $productVariation = ProductVariation::create([
                'name' => 'DUMMY',
                'product_id' => $product->id,
                'is_dummy' => 1
            ]);

            // Create Variation
            $variation = Variation::create([
                'name' => 'DUMMY',
                'product_id' => $product->id,
                'sub_sku' => $sku,
                'product_variation_id' => $productVariation->id,
                'default_purchase_price' => $cost,
                'dpp_inc_tax' => $cost,
                'profit_percent' => $profitPercent,
                'default_sell_price' => $price,
                'sell_price_inc_tax' => $price
            ]);

            // Create Variation Location Details (always tracks stock)
            VariationLocationDetails::create([
                'product_id' => $product->id,
                'product_variation_id' => $productVariation->id,
                'variation_id' => $variation->id,
                'location_id' => $locationId,
                'qty_available' => $qty
            ]);

            // If quantity is positive, create Opening Stock Transaction & Purchase Line
            if ($qty > 0) {
                $totalBeforeTax = $qty * $cost;
                $transaction = Transaction::create([
                    'type' => 'opening_stock',
                    'opening_stock_product_id' => $product->id,
                    'status' => 'received',
                    'business_id' => $businessId,
                    'transaction_date' => $transactionDate,
                    'total_before_tax' => $totalBeforeTax,
                    'location_id' => $locationId,
                    'final_total' => $totalBeforeTax,
                    'payment_status' => 'paid',
                    'created_by' => $userId
                ]);

                $transaction->purchase_lines()->create([
                    'product_id' => $product->id,
                    'variation_id' => $variation->id,
                    'quantity' => $qty,
                    'pp_without_discount' => $cost,
                    'purchase_price' => $cost,
                    'purchase_price_inc_tax' => $cost,
                    'item_tax' => 0,
                    'tax_id' => null
                ]);

                $stockLinesCreated++;
                $totalQtyAdded += $qty;
            }

            $prodInserted++;
            $existingSkus[$sku] = true;
        }
        DB::commit();
        $processed = ($chunkIndex + 1) * $chunkSize;
        if ($processed > count($inventoryData)) $processed = count($inventoryData);
        echo "  • Progress: $processed / " . count($inventoryData) . " products processed\r";
    } catch (\Exception $e) {
        DB::rollBack();
        echo "\nERROR in chunk $chunkIndex: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
        exit(1);
    }
}

echo "\n\n========================================================\n";
echo "IMPORT SUMMARY\n";
echo "========================================================\n";
echo "Products Created: $prodInserted\n";
echo "Products Skipped: $prodSkipped\n";
echo "Stock Lines Created: $stockLinesCreated\n";
echo "Total Qty Added: $totalQtyAdded\n";
echo "Categories Mapped: " . Category::where('business_id', $businessId)->count() . "\n";
echo "Suppliers Mapped: " . Contact::where('business_id', $businessId)->where('type', 'supplier')->count() . "\n";
echo "Customers Mapped: " . Contact::where('business_id', $businessId)->where('type', 'customer')->count() . "\n";
echo "Total Products in DB for Business $businessId: " . Product::where('business_id', $businessId)->count() . "\n";
echo "========================================================\n";
