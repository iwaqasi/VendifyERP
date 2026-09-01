<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business', function (Blueprint $table) {
            $table->string('cashier_pin', 10)->nullable()->after('currency_id');
        });

        // Set default PIN for existing business
        DB::table('business')->update(['cashier_pin' => '1234']);
    }

    public function down(): void
    {
        Schema::table('business', function (Blueprint $table) {
            $table->dropColumn('cashier_pin');
        });
    }
};
