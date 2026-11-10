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
        if (Schema::hasTable('pos_kot_items')) {
            Schema::table('pos_kot_items', function (Blueprint $table) {
                if (!Schema::hasColumn('pos_kot_items', 'station')) {
                    $table->string('station', 50)->default('kitchen')->after('order_type')->index();
                }
            });
        }

        if (Schema::hasTable('pos_order_items')) {
            Schema::table('pos_order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('pos_order_items', 'station')) {
                    $table->string('station', 50)->default('kitchen')->after('item_name')->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pos_kot_items')) {
            Schema::table('pos_kot_items', function (Blueprint $table) {
                if (Schema::hasColumn('pos_kot_items', 'station')) {
                    $table->dropColumn('station');
                }
            });
        }

        if (Schema::hasTable('pos_order_items')) {
            Schema::table('pos_order_items', function (Blueprint $table) {
                if (Schema::hasColumn('pos_order_items', 'station')) {
                    $table->dropColumn('station');
                }
            });
        }
    }
};

