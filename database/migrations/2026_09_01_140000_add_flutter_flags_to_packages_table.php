<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The VerifyPosLicense middleware and PackagesController both use
 * packages.flutter_pos / packages.flutter_cms, but no migration ever
 * created these columns — the license check could therefore never pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (! Schema::hasColumn('packages', 'flutter_pos')) {
                $table->boolean('flutter_pos')->default(0)->after('custom_permissions');
            }
            if (! Schema::hasColumn('packages', 'flutter_cms')) {
                $table->boolean('flutter_cms')->default(0)->after('flutter_pos');
            }
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            foreach (['flutter_pos', 'flutter_cms'] as $col) {
                if (Schema::hasColumn('packages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
