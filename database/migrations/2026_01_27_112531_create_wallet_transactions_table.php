<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure referenced tables exist
        if (!Schema::hasTable('driver_wallets')) {
            return;
        }

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('driver_wallet_id');
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->enum('type', ['earning', 'commission', 'refund', 'adjustment', 'payout'])->index();
            $table->decimal('amount', 12, 2);
            $table->enum('direction', ['credit', 'debit'])->index();
            $table->string('description');
            $table->json('meta')->nullable();
            $table->timestamp('created_at');

            $table->index('driver_wallet_id');
            $table->index('booking_id');
            $table->index('type');
            $table->index('created_at');
        });
        
        // Add foreign key constraints after table is created
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreign('driver_wallet_id')
                  ->references('id')
                  ->on('driver_wallets')
                  ->onDelete('cascade');
                  
            // Only add booking foreign key if bookings table exists
            if (Schema::hasTable('bookings')) {
                $table->foreign('booking_id')
                      ->references('id')
                      ->on('bookings')
                      ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
