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
        
        // If app is not installed, use file sessions to avoid database errors
        $installed = file_exists(storage_path('installed'));
        if (!$installed && config('session.driver') === 'database') {
            config(['session.driver' => 'file']);
        }
        
        // Ensure session connection uses MySQL if using database driver
        if (config('session.driver') === 'database') {
            $sessionConnection = config('session.connection');
            if (!$sessionConnection || $sessionConnection === 'sqlite') {
                config(['session.connection' => 'mysql']);
            }
        }
    }
}
