<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('flutter_pos')->default(0)->after('order_screen');
            $table->boolean('flutter_cms')->default(0)->after('flutter_pos');
        });

        // Enable POS for the "Unlimited" package (id=2)
        DB::table('packages')->where('id', 2)->update([
            'flutter_pos' => 1,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['flutter_pos', 'flutter_cms']);
        });
    }
};
