<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('conversations')) {
            // Ensure referenced tables exist
            if (!Schema::hasTable('bookings') || !Schema::hasTable('rides') || !Schema::hasTable('users')) {
                return;
            }

            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('booking_id')->unique();
                $table->unsignedBigInteger('ride_id');
                $table->unsignedBigInteger('rider_user_id');
                $table->unsignedBigInteger('driver_user_id');
                $table->enum('status', ['active', 'closed'])->default('active')->index();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();

                $table->index('ride_id');
                $table->index('rider_user_id');
                $table->index('driver_user_id');
                // status index already created on column definition
            });
            
            // Add foreign key constraints after table is created
            Schema::table('conversations', function (Blueprint $table) {
                $table->foreign('booking_id')
                      ->references('id')
                      ->on('bookings')
                      ->onDelete('cascade');
                      
                $table->foreign('ride_id')
                      ->references('id')
                      ->on('rides')
                      ->onDelete('cascade');
                      
                $table->foreign('rider_user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');
                      
                $table->foreign('driver_user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
