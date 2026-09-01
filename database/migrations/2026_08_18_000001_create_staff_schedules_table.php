<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('staff_schedules', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->integer('business_id')->unsigned();
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            
            // Weekly schedule - each day has start_time, end_time, or is_off
            // Using time columns for simplicity (HH:MM format)
            $table->boolean('sat_is_off')->default(false);
            $table->time('sat_start_time')->nullable();
            $table->time('sat_end_time')->nullable();
            
            $table->boolean('sun_is_off')->default(false);
            $table->time('sun_start_time')->nullable();
            $table->time('sun_end_time')->nullable();
            
            $table->boolean('mon_is_off')->default(false);
            $table->time('mon_start_time')->nullable();
            $table->time('mon_end_time')->nullable();
            
            $table->boolean('tue_is_off')->default(false);
            $table->time('tue_start_time')->nullable();
            $table->time('tue_end_time')->nullable();
            
            $table->boolean('wed_is_off')->default(false);
            $table->time('wed_start_time')->nullable();
            $table->time('wed_end_time')->nullable();
            
            $table->boolean('thu_is_off')->default(false);
            $table->time('thu_start_time')->nullable();
            $table->time('thu_end_time')->nullable();
            
            $table->boolean('fri_is_off')->default(false);
            $table->time('fri_start_time')->nullable();
            $table->time('fri_end_time')->nullable();
            
            $table->timestamps();
            
            // Unique constraint - one schedule per staff per business
            $table->unique(['user_id', 'business_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('staff_schedules');
    }
};
