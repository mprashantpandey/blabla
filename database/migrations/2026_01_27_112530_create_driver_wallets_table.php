<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->unique()->constrained()->onDelete('cascade');
            $table->decimal('balance', 12, 2)->default(0)->index();
            $table->decimal('lifetime_earned', 12, 2)->default(0);
            $table->decimal('lifetime_withdrawn', 12, 2)->default(0);
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();

            $table->index('balance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_wallets');
    }
};
