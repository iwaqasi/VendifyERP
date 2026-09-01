<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CMS Pages (About, Contact, Services, etc.)
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('title_ar')->nullable();
            $table->text('content')->nullable();
            $table->text('content_ar')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('featured_image')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('is_homepage')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'is_published']);
        });

        // CMS Blog Posts
        Schema::create('cms_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('title_ar')->nullable();
            $table->string('excerpt')->nullable();
            $table->text('content')->nullable();
            $table->text('content_ar')->nullable();
            $table->string('featured_image')->nullable();
            $table->string('author_name')->nullable();
            $table->string('category')->nullable();
            $table->string('tags')->nullable(); // comma separated
            $table->boolean('is_published')->default(true);
            $table->dateTime('published_at')->nullable();
            $table->integer('views_count')->default(0);
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'is_published']);
        });

        // CMS Menus (Navigation)
        Schema::create('cms_menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->string('name'); // e.g., "main", "footer"
            $table->timestamps();
        });

        // CMS Menu Items
        Schema::create('cms_menu_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('menu_id');
            $table->string('label');
            $table->string('label_ar')->nullable();
            $table->string('url')->nullable();
            $table->string('type')->default('custom'); // page, post, category, custom
            $table->unsignedBigInteger('reference_id')->nullable(); // page_id or post_id
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['menu_id', 'sort_order']);
        });

        // CMS Settings (per business)
        Schema::create('cms_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // CMS Media Library
        Schema::create('cms_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type'); // image, video, document
            $table->unsignedInteger('file_size')->nullable();
            $table->string('alt_text')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'file_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_media');
        Schema::dropIfExists('cms_settings');
        Schema::dropIfExists('cms_menu_items');
        Schema::dropIfExists('cms_menus');
        Schema::dropIfExists('cms_posts');
        Schema::dropIfExists('cms_pages');
    }
};
