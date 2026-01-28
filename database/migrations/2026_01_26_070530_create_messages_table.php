<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure conversations and users tables exist
        if (!Schema::hasTable('conversations') || !Schema::hasTable('users')) {
            return;
        }

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('sender_user_id')->nullable();
            $table->enum('message_type', ['text', 'system'])->default('text')->index();
            $table->text('body');
            $table->json('meta')->nullable();
            $table->boolean('is_deleted')->default(false)->index();
            $table->timestamp('created_at');

            $table->index('conversation_id');
            $table->index('sender_user_id');
            $table->index('created_at');
        });
        
        // Add foreign key constraints after table is created
        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('conversation_id')
                  ->references('id')
                  ->on('conversations')
                  ->onDelete('cascade');
                  
            $table->foreign('sender_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
