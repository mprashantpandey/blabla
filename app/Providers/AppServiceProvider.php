<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prevent SQLite from being used - force MySQL
        if (config('database.default') === 'sqlite') {
            config(['database.default' => 'mysql']);
        }
        
        // Ensure session connection uses MySQL
        if (config('session.driver') === 'database' && !config('session.connection')) {
            config(['session.connection' => 'mysql']);
        }
    }
}
