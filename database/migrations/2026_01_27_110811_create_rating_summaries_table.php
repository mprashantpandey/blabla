<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['driver', 'rider'])->index();
            $table->decimal('avg_rating', 3, 2)->default(0)->index();
            $table->integer('total_ratings')->default(0)->index();
            $table->integer('total_trips')->default(0)->index();
            $table->timestamp('updated_at');

            $table->unique(['user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_summaries');
    }
};
