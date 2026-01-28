<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InstallerController;

// Check if installed, if not show installer
Route::get('/', function () {
    $installed = file_exists(storage_path('installed'));
    
    if (!$installed) {
        return view('installer');
    }
    
    return view('welcome');
});

// Installer page
Route::get('/install', function () {
    $installed = file_exists(storage_path('installed'));
    
    if ($installed) {
        return redirect('/admin')->with('message', 'Application is already installed.');
    }
    
    return view('installer');
});

// Installer API routes (excluded from CSRF for fresh installation)
Route::prefix('install')->withoutMiddleware(['web'])->group(function () {
    Route::get('/check', [InstallerController::class, 'check']);
    Route::post('/run', [InstallerController::class, 'install']);
});
