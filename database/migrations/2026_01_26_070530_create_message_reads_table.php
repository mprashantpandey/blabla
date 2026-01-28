<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure messages and users tables exist before creating foreign keys
        if (!Schema::hasTable('messages')) {
            // If messages table doesn't exist, skip this migration
            return;
        }

        Schema::create('message_reads', function (Blueprint $table) {
            $table->id();
            
            // Create columns first
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('read_at');

            // Add foreign key constraints after table is created
            $table->foreign('message_id')
                  ->references('id')
                  ->on('messages')
                  ->onDelete('cascade');
                  
            // Only add user foreign key if users table exists
            if (Schema::hasTable('users')) {
                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');
            }

            $table->unique(['message_id', 'user_id']);
            $table->index('user_id');
            $table->index('read_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reads');
    }
};
