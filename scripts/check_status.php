<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Product;
use App\Category;
use App\Contact;
use App\Transaction;

$businessId = 5;

echo "=== BUSINESS 5 STATUS ===\n";
echo "Categories: " . Category::where('business_id', $businessId)->count() . "\n";
echo "Suppliers: " . Contact::where('business_id', $businessId)->where('type', 'supplier')->count() . "\n";
echo "Customers: " . Contact::where('business_id', $businessId)->where('type', 'customer')->count() . "\n";
echo "Opening Balances: " . Transaction::where('business_id', $businessId)->where('type', 'opening_balance')->count() . "\n";
echo "Products: " . Product::where('business_id', $businessId)->count() . "\n";
echo "Opening Stock Transactions: " . Transaction::where('business_id', $businessId)->where('type', 'opening_stock')->count() . "\n";
