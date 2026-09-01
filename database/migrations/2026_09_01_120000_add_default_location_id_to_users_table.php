<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('default_location_id')->nullable()->after('business_id');
            $table->foreign('default_location_id')->references('id')->on('business_locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_location_id']);
            $table->dropColumn('default_location_id');
        });
    }
};
