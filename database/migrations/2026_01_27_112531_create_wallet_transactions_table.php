<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_wallet_id')->constrained()->onDelete('cascade');
            $table->foreignId('booking_id')->nullable()->constrained()->onDelete('set null');
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
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
