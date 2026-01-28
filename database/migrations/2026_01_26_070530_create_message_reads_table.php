<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure messages and users tables exist before creating foreign keys
        if (!Schema::hasTable('messages') || !Schema::hasTable('users')) {
            // If referenced tables don't exist, skip this migration
            return;
        }

        Schema::create('message_reads', function (Blueprint $table) {
            $table->id();
            
            // Create columns first
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('read_at');

            $table->unique(['message_id', 'user_id']);
            $table->index('user_id');
            $table->index('read_at');
        });
        
        // Add foreign key constraints after table is created
        Schema::table('message_reads', function (Blueprint $table) {
            $table->foreign('message_id')
                  ->references('id')
                  ->on('messages')
                  ->onDelete('cascade');
                  
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reads');
    }
};
