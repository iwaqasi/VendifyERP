<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->integer('business_id')->index();
            $table->string('name'); // Cash, Debit Card, Visa, Mastercard, etc.
            $table->string('method_type')->default('cash'); // cash, card, bank_transfer, other
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_auth_code')->default(false); // For card payments
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed default payment methods for all existing businesses
        $businesses = DB::table('business')->pluck('id');
        $defaultMethods = [
            ['name' => 'Cash', 'method_type' => 'cash', 'requires_auth_code' => false, 'sort_order' => 1],
            ['name' => 'Debit Card', 'method_type' => 'card', 'requires_auth_code' => true, 'sort_order' => 2],
            ['name' => 'Credit Card', 'method_type' => 'card', 'requires_auth_code' => true, 'sort_order' => 3],
            ['name' => 'Visa', 'method_type' => 'card', 'requires_auth_code' => true, 'sort_order' => 4],
            ['name' => 'Mastercard', 'method_type' => 'card', 'requires_auth_code' => true, 'sort_order' => 5],
            ['name' => 'Bank Transfer', 'method_type' => 'bank_transfer', 'requires_auth_code' => false, 'sort_order' => 6],
        ];

        foreach ($businesses as $businessId) {
            foreach ($defaultMethods as $method) {
                DB::table('payment_methods')->insert(array_merge($method, [
                    'business_id' => $businessId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
