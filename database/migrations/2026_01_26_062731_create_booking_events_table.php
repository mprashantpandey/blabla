<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure bookings and users tables exist
        if (!Schema::hasTable('bookings') || !Schema::hasTable('users')) {
            return;
        }

        Schema::create('booking_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->string('event');
            $table->unsignedBigInteger('performed_by_user_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('booking_id');
            $table->index('event');
            $table->index('performed_by_user_id');
        });
        
        // Add foreign key constraints after table is created
        Schema::table('booking_events', function (Blueprint $table) {
            $table->foreign('booking_id')
                  ->references('id')
                  ->on('bookings')
                  ->onDelete('cascade');
                  
            $table->foreign('performed_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_events');
    }
};
