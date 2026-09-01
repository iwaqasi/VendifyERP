<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_repair_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->string('ticket_number', 50);
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('device_type');
            $table->string('device_brand');
            $table->string('device_model')->nullable();
            $table->string('device_serial')->nullable();
            $table->text('device_condition')->nullable();
            $table->text('reported_issue');
            $table->text('diagnosis')->nullable();
            $table->text('resolution')->nullable();
            $table->decimal('estimated_cost', 10, 3)->default(0);
            $table->decimal('actual_cost', 10, 3)->default(0);
            $table->decimal('parts_cost', 10, 3)->default(0);
            $table->decimal('labor_cost', 10, 3)->default(0);
            $table->string('status')->default('received');
            $table->string('priority')->default('normal');
            $table->unsignedBigInteger('technician_id')->nullable();
            $table->date('received_date');
            $table->date('estimated_completion')->nullable();
            $table->date('actual_completion')->nullable();
            $table->date('delivered_date')->nullable();
            $table->integer('warranty_days')->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->unsignedInteger('sell_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'ticket_number']);
            $table->index(['business_id', 'status']);
            $table->index(['customer_phone']);
        });

        Schema::create('pos_repair_status_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('repair_ticket_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('notes')->nullable();
            $table->unsignedInteger('changed_by')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['repair_ticket_id', 'changed_at']);
        });

        Schema::create('pos_repair_parts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('repair_ticket_id');
            $table->unsignedInteger('product_id')->nullable();
            $table->string('part_name');
            $table->string('part_sku')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_cost', 10, 3)->default(0);
            $table->decimal('unit_price', 10, 3)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_repair_parts');
        Schema::dropIfExists('pos_repair_status_history');
        Schema::dropIfExists('pos_repair_tickets');
    }
};
