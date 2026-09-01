<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business', function (Blueprint $table) {
            $table->string('business_type', 50)->nullable()->after('name');
            $table->text('business_type_config')->nullable()->after('business_type');
        });
    }

    public function down(): void
    {
        Schema::table('business', function (Blueprint $table) {
            $table->dropColumn(['business_type', 'business_type_config']);
        });
    }
};
