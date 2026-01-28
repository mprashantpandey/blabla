<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->unique()->constrained()->onDelete('cascade');
                $table->foreignId('ride_id')->constrained()->onDelete('cascade');
                $table->foreignId('rider_user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('driver_user_id')->constrained('users')->onDelete('cascade');
                $table->enum('status', ['active', 'closed'])->default('active')->index();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();

                $table->index('ride_id');
                $table->index('rider_user_id');
                $table->index('driver_user_id');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
