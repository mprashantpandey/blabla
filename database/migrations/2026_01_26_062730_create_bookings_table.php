<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_id')->constrained()->onDelete('cascade');
            $table->foreignId('rider_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('driver_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('city_id')->constrained()->onDelete('cascade');
            $table->enum('status', [
                'requested',
                'accepted',
                'rejected',
                'payment_pending',
                'confirmed',
                'cancelled',
                'completed',
                'expired',
                'refunded'
            ])->default('requested');
            $table->integer('seats_requested');
            $table->decimal('price_per_seat', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->string('commission_type')->nullable(); // snapshot
            $table->decimal('commission_value', 10, 2)->nullable(); // snapshot
            $table->decimal('commission_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->enum('payment_method', ['cash', 'razorpay', 'stripe'])->nullable();
            $table->enum('payment_status', ['unpaid', 'pending', 'paid', 'failed', 'refunded'])->default('unpaid');
            $table->timestamp('hold_expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index('ride_id');
            $table->index('rider_user_id');
            $table->index('driver_profile_id');
            $table->index('city_id');
            $table->index('status');
            $table->index('hold_expires_at');
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
