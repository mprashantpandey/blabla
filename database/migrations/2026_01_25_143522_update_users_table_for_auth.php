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
        Schema::table('users', function (Blueprint $table) {
            $table->string('country_code', 5)->nullable()->after('phone');
            $table->enum('status', ['active', 'banned'])->default('active')->after('is_active');
            $table->string('auth_provider', 20)->default('email')->after('status'); // email, phone, google, apple
            $table->timestamp('last_login_at')->nullable()->after('phone_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['country_code', 'status', 'auth_provider', 'last_login_at']);
        });
    }
};
