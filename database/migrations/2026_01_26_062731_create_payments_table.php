<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure bookings table exists
        if (!Schema::hasTable('bookings')) {
            return;
        }

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->enum('provider', ['razorpay', 'stripe', 'cash']);
            $table->string('provider_payment_id')->nullable();
            $table->string('provider_order_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency_code', 3)->default('USD');
            $table->enum('status', ['initiated', 'pending', 'paid', 'failed', 'refunded'])->default('initiated');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('booking_id');
            $table->index('provider');
            $table->index('status');
            $table->index('provider_payment_id');
        });
        
        // Add foreign key constraint after table is created
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('booking_id')
                  ->references('id')
                  ->on('bookings')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
