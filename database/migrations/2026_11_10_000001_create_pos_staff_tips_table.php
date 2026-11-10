<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pos_staff_tips')) {
            Schema::create('pos_staff_tips', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id');
                $table->unsignedBigInteger('staff_id');
                $table->unsignedInteger('transaction_id')->nullable();
                $table->unsignedBigInteger('appointment_id')->nullable();
                $table->unsignedInteger('location_id')->nullable();
                $table->decimal('amount', 10, 3)->default(0);
                $table->string('payment_method', 50)->default('cash');
                $table->string('status', 30)->default('pending'); // 'pending', 'paid_out'
                $table->dateTime('paid_out_at')->nullable();
                $table->unsignedInteger('paid_out_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'staff_id']);
                $table->index(['business_id', 'status']);
                $table->index(['business_id', 'created_at']);
                $table->index('transaction_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_staff_tips');
    }
};

