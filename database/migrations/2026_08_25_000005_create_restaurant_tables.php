<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_tables', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('location_id')->nullable();
            $table->string('name');
            $table->integer('capacity')->default(4);
            $table->string('status')->default('available'); // available, occupied, reserved, maintenance
            $table->string('zone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['business_id', 'status']);
        });

        Schema::create('pos_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('table_id')->nullable();
            $table->unsignedInteger('contact_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->string('order_number', 50);
            $table->string('order_type')->default('dine_in');
            $table->integer('guest_count')->default(1);
            $table->string('status')->default('pending');
            $table->decimal('subtotal', 10, 3)->default(0);
            $table->decimal('tax_amount', 10, 3)->default(0);
            $table->decimal('discount_amount', 10, 3)->default(0);
            $table->decimal('tip_amount', 10, 3)->default(0);
            $table->decimal('grand_total', 10, 3)->default(0);
            $table->string('payment_status')->default('unpaid');
            $table->decimal('amount_paid', 10, 3)->default(0);
            $table->string('delivery_address')->nullable();
            $table->string('delivery_phone')->nullable();
            $table->string('delivery_notes')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sell_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'status']);
            $table->index(['table_id', 'status']);
            $table->index(['order_number']);
        });

        Schema::create('pos_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedInteger('product_id')->nullable();
            $table->unsignedInteger('variation_id')->nullable();
            $table->string('item_name');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 3)->default(0);
            $table->decimal('discount', 10, 3)->default(0);
            $table->decimal('tax_amount', 10, 3)->default(0);
            $table->decimal('line_total', 10, 3)->default(0);
            $table->string('kot_status')->default('pending');
            $table->dateTime('kot_sent_at')->nullable();
            $table->dateTime('prepared_at')->nullable();
            $table->dateTime('served_at')->nullable();
            $table->integer('course_number')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'kot_status']);
        });

        Schema::create('pos_kot_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('order_item_id');
            $table->string('kot_number', 50);
            $table->string('table_name')->nullable();
            $table->string('order_type');
            $table->string('item_name');
            $table->integer('quantity');
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');
            $table->dateTime('sent_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_kot_items');
        Schema::dropIfExists('pos_order_items');
        Schema::dropIfExists('pos_orders');
        Schema::dropIfExists('pos_tables');
    }
};
