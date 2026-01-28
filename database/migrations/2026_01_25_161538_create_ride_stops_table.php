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

        Schema::create('ride_stops', function (Blueprint $table) {
            $table->id();
            
            // Create ride_id column first
            $table->unsignedBigInteger('ride_id');
            
            $table->enum('type', ['origin', 'waypoint', 'destination']);
            $table->string('name');
            $table->decimal('lat', 10, 8);
            $table->decimal('lng', 11, 8);
            $table->integer('stop_order');
            $table->timestamps();

            // Add foreign key constraint after table is created
            $table->foreign('ride_id')
                  ->references('id')
                  ->on('rides')
                  ->onDelete('cascade');

            $table->index('ride_id');
            $table->index('type');
            $table->index('stop_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_stops');
    }
};
