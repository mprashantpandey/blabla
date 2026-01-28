<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            // Add slug if not exists
            if (!Schema::hasColumn('cities', 'slug')) {
                $table->string('slug')->unique()->after('name');
            }
            
            // Add country if not exists
            if (!Schema::hasColumn('cities', 'country')) {
                $table->string('country')->default('US')->after('slug');
            }
            
            // Add state if not exists
            if (!Schema::hasColumn('cities', 'state')) {
                $table->string('state')->nullable()->after('country');
            }
            
            // Add currency_code
            if (!Schema::hasColumn('cities', 'currency_code')) {
                $table->string('currency_code', 3)->nullable()->after('state');
            }
            
            // Add timezone if not exists
            if (!Schema::hasColumn('cities', 'timezone')) {
                $table->string('timezone')->nullable()->after('currency_code');
            }
            
            // Add default_search_radius_km
            if (!Schema::hasColumn('cities', 'default_search_radius_km')) {
                $table->decimal('default_search_radius_km', 8, 2)->default(30.00)->after('radius');
            }
            
            // Add created_by
            if (!Schema::hasColumn('cities', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->after('is_active');
            }
            
            // Add sort_order if not exists
            if (!Schema::hasColumn('cities', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('is_active');
            }
            
            // Update latitude/longitude precision if needed
            // Ensure indexes
            $table->index('slug');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['sort_order']);
            
            if (Schema::hasColumn('cities', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
            
            if (Schema::hasColumn('cities', 'default_search_radius_km')) {
                $table->dropColumn('default_search_radius_km');
            }
            
            if (Schema::hasColumn('cities', 'currency_code')) {
                $table->dropColumn('currency_code');
            }
        });
    }
};
