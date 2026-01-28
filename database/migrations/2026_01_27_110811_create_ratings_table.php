<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure referenced tables exist
        if (!Schema::hasTable('bookings') || !Schema::hasTable('rides') || !Schema::hasTable('users')) {
            return;
        }

        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('ride_id');
            $table->unsignedBigInteger('rater_user_id');
            $table->unsignedBigInteger('ratee_user_id');
            $table->enum('role', ['rider_to_driver', 'driver_to_rider'])->index();
            $table->tinyInteger('rating')->unsigned()->index(); // 1-5
            $table->text('comment')->nullable();
            $table->boolean('is_hidden')->default(false)->index();
            $table->timestamp('created_at');

            $table->unique(['booking_id', 'rater_user_id', 'role']);
            $table->index('ratee_user_id');
            $table->index('rating');
        });
        
        // Add foreign key constraints after table is created
        Schema::table('ratings', function (Blueprint $table) {
            $table->foreign('booking_id')
                  ->references('id')
                  ->on('bookings')
                  ->onDelete('cascade');
                  
            $table->foreign('ride_id')
                  ->references('id')
                  ->on('rides')
                  ->onDelete('cascade');
                  
            $table->foreign('rater_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
                  
            $table->foreign('ratee_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
