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
        Schema::table('business', function (Blueprint $table) {
            if (!Schema::hasColumn('business', 'kuwait_cr_number')) {
                $table->string('kuwait_cr_number', 100)->nullable()->after('tax_number_2')->comment('Kuwait Commercial Registration Number');
            }
            if (!Schema::hasColumn('business', 'kuwait_moci_license')) {
                $table->string('kuwait_moci_license', 100)->nullable()->after('kuwait_cr_number')->comment('Ministry of Commerce and Industry License Number');
            }
            if (!Schema::hasColumn('business', 'kuwait_paci_number')) {
                $table->string('kuwait_paci_number', 100)->nullable()->after('kuwait_moci_license')->comment('Public Authority for Civil Information (PACI) ID');
            }
            if (!Schema::hasColumn('business', 'enable_xbrl_reporting')) {
                $table->boolean('enable_xbrl_reporting')->default(1)->after('kuwait_paci_number')->comment('Enable Kuwait MOCI IFRS XBRL Financial Audit Module');
            }
            if (!Schema::hasColumn('business', 'enable_kuwait_vat_ready')) {
                $table->boolean('enable_kuwait_vat_ready')->default(1)->after('enable_xbrl_reporting')->comment('Enable Kuwait 2027 5% VAT & GCC TLV QR Readiness');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business', function (Blueprint $table) {
            $table->dropColumn([
                'kuwait_cr_number',
                'kuwait_moci_license',
                'kuwait_paci_number',
                'enable_xbrl_reporting',
                'enable_kuwait_vat_ready',
            ]);
        });
    }
};
