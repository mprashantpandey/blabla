<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure rides table exists before creating foreign key
        if (!Schema::hasTable('rides')) {
            // If rides table doesn't exist, create it first (shouldn't happen, but safety check)
            return;
        }

        Schema::create('ride_views', function (Blueprint $table) {
            $table->id();
            
            // Create columns first
            $table->unsignedBigInteger('ride_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->timestamp('viewed_at');
            $table->timestamps();

            // Add foreign key constraints after table is created
            $table->foreign('ride_id')
                  ->references('id')
                  ->on('rides')
                  ->onDelete('cascade');
                  
            // Only add user foreign key if users table exists
            if (Schema::hasTable('users')) {
                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');
            }
            
            // Only add city foreign key if cities table exists
            if (Schema::hasTable('cities')) {
                $table->foreign('city_id')
                      ->references('id')
                      ->on('cities')
                      ->onDelete('set null');
            }

            $table->index('ride_id');
            $table->index('user_id');
            $table->index('city_id');
            $table->index('viewed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_views');
    }
};
