<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$b = App\Business::find(5);
if (!$b) {
    echo "Business 5 not found\n";
    exit;
}

echo "Business ID: " . $b->id . " Name: " . $b->name . "\n";
$u = App\User::where('username', 'sunbeam_admin')->first();
if ($u) {
    if (!Illuminate\Support\Facades\Hash::check('Admin@1234', $u->password)) {
        $u->password = Illuminate\Support\Facades\Hash::make('Admin@1234');
        $u->save();
        echo "Password updated to Admin@1234 for sunbeam_admin\n";
    } else {
        echo "Password already matches Admin@1234 for sunbeam_admin\n";
    }
}

$locations = App\BusinessLocation::where('business_id', 5)->get(['id', 'name', 'location_id']);
echo "Locations: " . json_encode($locations, JSON_PRETTY_PRINT) . "\n";

foreach (Illuminate\Support\Facades\DB::select('SHOW COLUMNS FROM products') as $c) {
    if (in_array($c->Field, ['name', 'name_ar', 'sku', 'product_description', 'description_ar'])) {
        echo $c->Field . ': ' . $c->Type . "\n";
    }
}
