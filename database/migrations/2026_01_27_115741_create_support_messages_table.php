<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure referenced tables exist
        if (!Schema::hasTable('support_tickets') || !Schema::hasTable('users')) {
            return;
        }

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('support_ticket_id');
            $table->enum('sender_type', ['user', 'admin']);
            $table->unsignedBigInteger('sender_user_id')->nullable();
            $table->text('message');
            $table->timestamp('created_at');

            $table->index('support_ticket_id');
        });
        
        // Add foreign key constraints after table is created
        Schema::table('support_messages', function (Blueprint $table) {
            $table->foreign('support_ticket_id')
                  ->references('id')
                  ->on('support_tickets')
                  ->onDelete('cascade');
                  
            $table->foreign('sender_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
