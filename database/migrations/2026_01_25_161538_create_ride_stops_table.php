<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['origin', 'waypoint', 'destination']);
            $table->string('name');
            $table->decimal('lat', 10, 8);
            $table->decimal('lng', 11, 8);
            $table->integer('stop_order');
            $table->timestamps();

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
