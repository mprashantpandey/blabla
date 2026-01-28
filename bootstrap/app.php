<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Prevent SQLite from being used - force MySQL before app boots
if (env('DB_CONNECTION') === 'sqlite' || (empty(env('DB_CONNECTION')) && !file_exists(storage_path('installed')))) {
    // If SQLite detected or app not installed, set MySQL as default
    $_ENV['DB_CONNECTION'] = 'mysql';
    putenv('DB_CONNECTION=mysql');
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'device.lastseen' => \App\Http\Middleware\UpdateDeviceLastSeen::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
