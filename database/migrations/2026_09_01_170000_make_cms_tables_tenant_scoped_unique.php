<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 3 (multi-tenant CMS): cms_pages.slug, cms_posts.slug and
 * cms_settings.key were GLOBALLY unique, which made it impossible for two
 * tenants to use the same page slug ("about") or setting key ("hero_title").
 * Scope the uniqueness to each business instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['business_id', 'slug']);
        });

        Schema::table('cms_posts', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['business_id', 'slug']);
        });

        Schema::table('cms_settings', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->unique(['business_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'slug']);
            $table->unique('slug');
        });

        Schema::table('cms_posts', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'slug']);
            $table->unique('slug');
        });

        Schema::table('cms_settings', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'key']);
            $table->unique('key');
        });
    }
};