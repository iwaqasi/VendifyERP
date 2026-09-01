<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Banners / Hero Sliders
        Schema::create('cms_banners', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('title');
            $table->string('title_ar')->nullable();
            $table->text('subtitle')->nullable();
            $table->text('subtitle_ar')->nullable();
            $table->string('image')->nullable();
            $table->string('link')->nullable();
            $table->string('button_text')->nullable();
            $table->string('button_text_ar')->nullable();
            $table->enum('position', ['hero', 'promo', 'footer'])->default('hero');
            $table->tinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Sections / Blocks for homepage builder
        Schema::create('cms_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('section_type'); // featured_products, categories, testimonials, custom_html, banner, etc.
            $table->string('title')->nullable();
            $table->string('title_ar')->nullable();
            $table->json('settings')->nullable(); // type-specific settings
            $table->tinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Website design settings (colors, fonts, etc.)
        Schema::create('cms_design_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->unique();
            $table->string('primary_color')->default('#00BCD4');
            $table->string('secondary_color')->default('#1a237e');
            $table->string('accent_color')->default('#ff5722');
            $table->string('background_color')->default('#ffffff');
            $table->string('text_color')->default('#333333');
            $table->string('header_bg_color')->default('#1a237e');
            $table->string('footer_bg_color')->default('#1a237e');
            $table->string('font_family')->default('Inter');
            $table->string('heading_font')->default('Inter');
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->string('hero_image')->nullable();
            $table->string('hero_title')->nullable();
            $table->string('hero_title_ar')->nullable();
            $table->text('hero_subtitle')->nullable();
            $table->text('hero_subtitle_ar')->nullable();
            $table->text('hero_cta_text')->nullable();
            $table->string('hero_cta_link')->nullable();
            $table->string('footer_text')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->json('social_links')->nullable();
            $table->json('seo_settings')->nullable();
            $table->json('custom_css')->nullable();
            $table->json('custom_scripts')->nullable();
            $table->timestamps();
        });

        // Testimonials / Reviews
        Schema::create('cms_testimonials', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('customer_name');
            $table->string('customer_image')->nullable();
            $table->tinyInteger('rating')->default(5);
            $table->text('review');
            $table->text('review_ar')->nullable();
            $table->tinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // FAQ
        Schema::create('cms_faqs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('question');
            $table->string('question_ar')->nullable();
            $table->text('answer');
            $table->text('answer_ar')->nullable();
            $table->string('category')->nullable();
            $table->tinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_faqs');
        Schema::dropIfExists('cms_testimonials');
        Schema::dropIfExists('cms_design_settings');
        Schema::dropIfExists('cms_sections');
        Schema::dropIfExists('cms_banners');
    }
};
