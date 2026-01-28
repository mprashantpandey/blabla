<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('city_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['draft', 'published', 'cancelled', 'completed'])->default('draft');
            $table->string('origin_name');
            $table->decimal('origin_lat', 10, 8);
            $table->decimal('origin_lng', 11, 8);
            $table->string('destination_name');
            $table->decimal('destination_lat', 10, 8);
            $table->decimal('destination_lng', 11, 8);
            $table->json('waypoints')->nullable();
            $table->text('route_polyline')->nullable();
            $table->dateTime('departure_at');
            $table->dateTime('arrival_estimated_at')->nullable();
            $table->decimal('price_per_seat', 10, 2);
            $table->string('currency_code', 3)->default('USD');
            $table->integer('seats_total');
            $table->integer('seats_available');
            $table->boolean('allow_instant_booking')->default(true);
            $table->text('notes')->nullable();
            $table->json('rules_json')->nullable();
            $table->string('cancellation_policy')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('created_by_ip')->nullable();
            $table->timestamps();

            $table->index('city_id');
            $table->index('status');
            $table->index('departure_at');
            $table->index('driver_profile_id');
            $table->index(['origin_lat', 'origin_lng']);
            $table->index(['destination_lat', 'destination_lng']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};
