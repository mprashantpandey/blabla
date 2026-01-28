<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure referenced tables exist
        if (!Schema::hasTable('rides') || !Schema::hasTable('users') || !Schema::hasTable('driver_profiles') || !Schema::hasTable('cities')) {
            return;
        }

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ride_id');
            $table->unsignedBigInteger('rider_user_id');
            $table->unsignedBigInteger('driver_profile_id');
            $table->unsignedBigInteger('city_id');
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
        
        // Add foreign key constraints after table is created
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreign('ride_id')
                  ->references('id')
                  ->on('rides')
                  ->onDelete('cascade');
                  
            $table->foreign('rider_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
                  
            $table->foreign('driver_profile_id')
                  ->references('id')
                  ->on('driver_profiles')
                  ->onDelete('cascade');
                  
            $table->foreign('city_id')
                  ->references('id')
                  ->on('cities')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
