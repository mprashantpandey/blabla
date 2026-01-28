<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_city_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('city_id')->constrained()->onDelete('cascade');
            $table->timestamp('last_selected_at')->useCurrent();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('city_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_city_preferences');
    }
};
