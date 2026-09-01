<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_insurance_providers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->decimal('coverage_percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['business_id', 'is_active']);
        });

        Schema::create('pos_patient_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('business_id');
            $table->date('date_of_birth')->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->text('allergies')->nullable();
            $table->text('medical_history')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->unsignedBigInteger('insurance_provider_id')->nullable();
            $table->string('insurance_id')->nullable();
            $table->date('insurance_expiry')->nullable();
            $table->timestamps();
        });

        Schema::create('pos_doctors', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->string('name');
            $table->string('specialization');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('license_number')->nullable();
            $table->string('color', 7)->default('#00BCD4');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['business_id', 'is_active']);
        });

        Schema::create('pos_doctor_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_id');
            $table->tinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('slot_duration_minutes')->default(30);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->unique(['doctor_id', 'day_of_week']);
        });

        Schema::create('pos_patient_appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id')->nullable();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->string('patient_name');
            $table->string('patient_phone')->nullable();
            $table->string('appointment_type');
            $table->text('reason')->nullable();
            $table->dateTime('appointment_start');
            $table->dateTime('appointment_end');
            $table->string('status')->default('scheduled');
            $table->boolean('has_insurance')->default(false);
            $table->unsignedBigInteger('insurance_provider_id')->nullable();
            $table->string('insurance_id')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('prescription')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sell_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'status']);
            $table->index(['doctor_id', 'appointment_start']);
            $table->index(['contact_id']);
        });

        Schema::create('pos_patient_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id');
            $table->string('package_name');
            $table->integer('total_sessions')->default(1);
            $table->integer('sessions_used')->default(0);
            $table->decimal('total_price', 10, 3)->default(0);
            $table->decimal('price_per_session', 10, 3)->default(0);
            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['contact_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_patient_packages');
        Schema::dropIfExists('pos_patient_appointments');
        Schema::dropIfExists('pos_doctor_schedules');
        Schema::dropIfExists('pos_doctors');
        Schema::dropIfExists('pos_patient_profiles');
        Schema::dropIfExists('pos_insurance_providers');
    }
};
