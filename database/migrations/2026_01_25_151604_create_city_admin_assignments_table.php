<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('city_admin_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('city_id')->constrained()->onDelete('cascade');
            $table->enum('role_scope', ['city_admin', 'support_staff'])->default('city_admin');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['user_id', 'city_id']);
            $table->index('user_id');
            $table->index('city_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_admin_assignments');
    }
};
