<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_verification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained()->onDelete('cascade');
            $table->enum('action', ['submitted', 'approved', 'rejected', 'suspended', 'docs_updated', 'reinstated'])->default('submitted');
            $table->foreignId('performed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('driver_profile_id');
            $table->index('action');
            $table->index('performed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_verification_events');
    }
};
