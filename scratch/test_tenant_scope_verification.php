<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TENANT SCOPE VERIFICATION ===\n";

// 1. Without authenticated user
$unscopedContacts = \App\Contact::count();
$unscopedProducts = \App\Product::count();
echo "Unscoped count (no auth user): Contacts={$unscopedContacts}, Products={$unscopedProducts}\n";

// 2. Simulate User for Business 1
$user1 = \App\User::where('business_id', 1)->first();
auth()->login($user1);

$b1Contacts = \App\Contact::count();
$b1Products = \App\Product::count();
$b1Locations = \App\BusinessLocation::count();
echo "Business 1 scoped count: Contacts={$b1Contacts}, Products={$b1Products}, Locations={$b1Locations}\n";

// Verify all returned records belong to Business 1
$foreignContacts = \App\Contact::where('business_id', '!=', 1)->count();
$foreignProducts = \App\Product::where('business_id', '!=', 1)->count();
echo "Foreign records in Business 1 query: Contacts={$foreignContacts}, Products={$foreignProducts}\n";

// 3. Test withoutTenantScope
$b1WithoutScope = \App\Contact::withoutTenantScope()->count();
echo "Contact::withoutTenantScope() count: {$b1WithoutScope} (matches unscoped: " . ($b1WithoutScope === $unscopedContacts ? 'YES' : 'NO') . ")\n";

if ($foreignContacts === 0 && $foreignProducts === 0) {
    echo "SUCCESS: Complete tenant isolation verified with 0 cross-tenant data leakage!\n";
} else {
    echo "FAILURE: Cross-tenant data leakage detected!\n";
}

