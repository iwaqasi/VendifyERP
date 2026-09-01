<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Staff (for Saloon, Repair, Clinic)
        Schema::create('pos_staff', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('specialization')->nullable();
            $table->string('color', 7)->default('#00BCD4');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['business_id', 'is_active']);
        });

        Schema::create('pos_staff_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->tinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->unique(['staff_id', 'day_of_week']);
        });

        // Appointments (for Saloon & Clinic)
        Schema::create('pos_appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id')->nullable();
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('service_name');
            $table->decimal('service_price', 10, 3)->default(0);
            $table->integer('service_duration_minutes')->default(30);
            $table->dateTime('appointment_start');
            $table->dateTime('appointment_end');
            $table->string('status')->default('scheduled'); // scheduled, confirmed, checked_in, in_progress, completed, cancelled, no_show
            $table->text('notes')->nullable();
            $table->unsignedInteger('sell_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'status']);
            $table->index(['staff_id', 'appointment_start']);
        });

        // Active service timer sessions
        Schema::create('pos_service_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('staff_id');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->integer('duration_seconds')->default(0);
            $table->string('status')->default('active'); // active, paused, completed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_service_sessions');
        Schema::dropIfExists('pos_appointments');
        Schema::dropIfExists('pos_staff_schedules');
        Schema::dropIfExists('pos_staff');
    }
};
