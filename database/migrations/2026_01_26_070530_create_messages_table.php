<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('message_type', ['text', 'system'])->default('text')->index();
            $table->text('body');
            $table->json('meta')->nullable();
            $table->boolean('is_deleted')->default(false)->index();
            $table->timestamp('created_at');

            $table->index('conversation_id');
            $table->index('sender_user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
