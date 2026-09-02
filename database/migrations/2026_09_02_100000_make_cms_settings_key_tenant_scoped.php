<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * cms_settings.key had a GLOBAL unique index, which made it impossible for
 * more than one business to store the same setting key (e.g. 'hero_title').
 * This makes CMS settings per-tenant: unique (business_id, key) instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_settings')) {
            return;
        }

        if (! Schema::hasColumn('cms_settings', 'business_id')) {
            Schema::table('cms_settings', function (Blueprint $table) {
                $table->unsignedInteger('business_id')->default(0)->after('id');
                $table->index('business_id');
            });
        }

        // Drop every UNIQUE index that covers the `key` column alone,
        // regardless of what the index is named.
        $uniqueIndexes = DB::select(
            "SELECT DISTINCT s.INDEX_NAME FROM information_schema.STATISTICS s
             WHERE s.TABLE_SCHEMA = DATABASE()
               AND s.TABLE_NAME = 'cms_settings'
               AND s.COLUMN_NAME = 'key'
               AND s.NON_UNIQUE = 0"
        );

        foreach ($uniqueIndexes as $index) {
            DB::statement("ALTER TABLE `cms_settings` DROP INDEX `{$index->INDEX_NAME}`");
        }

        Schema::table('cms_settings', function (Blueprint $table) {
            $table->unique(['business_id', 'key'], 'cms_settings_business_key_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cms_settings')) {
            return;
        }

        Schema::table('cms_settings', function (Blueprint $table) {
            $table->dropUnique('cms_settings_business_key_unique');
        });

        try {
            Schema::table('cms_settings', function (Blueprint $table) {
                $table->unique('key');
            });
        } catch (\Throwable $e) {
            // Duplicate keys exist across tenants — global uniqueness cannot be restored.
        }
    }
};
