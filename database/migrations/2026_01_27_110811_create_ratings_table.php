<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('ride_id')->constrained()->onDelete('cascade');
            $table->foreignId('rater_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('ratee_user_id')->constrained('users')->onDelete('cascade');
            $table->enum('role', ['rider_to_driver', 'driver_to_rider'])->index();
            $table->tinyInteger('rating')->unsigned()->index(); // 1-5
            $table->text('comment')->nullable();
            $table->boolean('is_hidden')->default(false)->index();
            $table->timestamp('created_at');

            $table->unique(['booking_id', 'rater_user_id', 'role']);
            $table->index('ratee_user_id');
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
