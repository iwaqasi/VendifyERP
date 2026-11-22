<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Product;
use App\Variation;
use App\VariationLocationDetails;
use App\Category;
use App\Contact;
use App\Transaction;

$businessId = 5;

echo "=== PRODUCTS CHECK ===\n";
$products = Product::where('business_id', $businessId)->with(['variations', 'category'])->get();
foreach ($products as $p) {
    $v = $p->variations->first();
    $vld = VariationLocationDetails::where('variation_id', $v->id)->where('location_id', 5)->first();
    $catName = $p->category ? $p->category->name : 'None';
    echo "ID: {$p->id} | SKU: {$p->sku} | Name: {$p->name} | Cat: {$catName} | Cost: {$v->default_purchase_price} | Sell: {$v->default_sell_price} | Stock: " . ($vld ? $vld->qty_available : 'N/A') . " | Ar: {$p->name_ar}\n";
}

echo "\n=== CUSTOMERS WITH OPENING BALANCE CHECK ===\n";
$obTransactions = Transaction::where('business_id', $businessId)->where('type', 'opening_balance')->with('contact')->get();
echo "Found " . $obTransactions->count() . " opening balance transactions:\n";
foreach ($obTransactions->take(5) as $ob) {
    $c = $ob->contact;
    echo "Cust: " . ($c ? $c->name : 'Unknown') . " (" . ($c ? $c->contact_id : '') . ") | Bal: " . $ob->final_total . " | Status: " . $ob->payment_status . "\n";
}

echo "\n=== SUPPLIERS CHECK ===\n";
$suppliers = Contact::where('business_id', $businessId)->where('type', 'supplier')->get();
echo "Total suppliers: " . $suppliers->count() . "\n";
foreach ($suppliers->take(5) as $s) {
    echo "Supplier: {$s->name} | Business: {$s->supplier_business_name} | Phone: {$s->mobile}\n";
}
