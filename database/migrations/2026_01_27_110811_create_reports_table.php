<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('reporter_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reported_user_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['user', 'ride', 'message'])->index();
            $table->enum('reason', ['spam', 'harassment', 'fraud', 'unsafe', 'other'])->index();
            $table->text('comment')->nullable();
            $table->enum('status', ['open', 'reviewed', 'action_taken', 'dismissed'])->default('open')->index();
            $table->text('admin_note')->nullable();
            $table->timestamp('created_at');

            $table->index('status');
            $table->index('reported_user_id');
            $table->index('reporter_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
