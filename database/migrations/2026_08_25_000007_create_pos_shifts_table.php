<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedInteger('user_id'); // Cashier who opened the shift
            
            // Shift timing
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->string('status')->default('open'); // open, closed
            
            // Opening cash
            $table->decimal('opening_cash', 10, 3)->default(0);
            
            // Sales totals (calculated when closing)
            $table->decimal('total_sales', 10, 3)->default(0);
            $table->decimal('total_cash_sales', 10, 3)->default(0);
            $table->decimal('total_card_sales', 10, 3)->default(0);
            $table->decimal('total_other_sales', 10, 3)->default(0);
            $table->integer('total_transactions')->default(0);
            $table->decimal('total_tax', 10, 3)->default(0);
            $table->decimal('total_discount', 10, 3)->default(0);
            
            // Refunds
            $table->decimal('total_refunds', 10, 3)->default(0);
            $table->integer('refund_count')->default(0);
            
            // Cash reconciliation
            $table->decimal('expected_cash', 10, 3)->default(0); // opening + cash sales - refunds
            $table->decimal('counted_cash', 10, 3)->nullable(); // actual counted cash
            $table->decimal('cash_difference', 10, 3)->nullable(); // counted - expected
            
            // Notes
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            
            $table->timestamps();
            
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_shifts');
    }
};
