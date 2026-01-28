<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use App\Models\SystemSetting;
use App\Models\CronRun;

class SystemHealth extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-heart';
    protected static ?string $navigationLabel = 'System Health';
    protected static ?string $title = 'System Health & Diagnostics';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.system-health';

    public function getHealthChecks(): array
    {
        $checks = [];

        // Application Environment
        $checks[] = [
            'name' => 'Application Environment',
            'value' => config('app.env'),
            'status' => config('app.env') === 'production' ? 'ok' : 'warning',
            'message' => config('app.env') === 'production' ? 'Production environment' : 'Not in production mode',
        ];

        // Debug Mode
        $checks[] = [
            'name' => 'Debug Mode',
            'value' => config('app.debug') ? 'ON' : 'OFF',
            'status' => config('app.debug') ? 'warning' : 'ok',
            'message' => config('app.debug') ? 'Debug mode is enabled. Disable in production.' : 'Debug mode is disabled',
        ];

        // Queue Driver
        $queueDriver = config('queue.default');
        $checks[] = [
            'name' => 'Queue Driver',
            'value' => $queueDriver,
            'status' => in_array($queueDriver, ['database', 'redis']) ? 'ok' : 'warning',
            'message' => in_array($queueDriver, ['database', 'redis']) ? 'Queue driver is configured' : 'Consider using database or redis for production',
        ];

        // Cache Driver
        $cacheDriver = config('cache.default');
        $checks[] = [
            'name' => 'Cache Driver',
            'value' => $cacheDriver,
            'status' => in_array($cacheDriver, ['redis', 'memcached']) ? 'ok' : 'warning',
            'message' => in_array($cacheDriver, ['redis', 'memcached']) ? 'Cache driver is optimized' : 'Consider using redis or memcached for production',
        ];

        // Filesystem Driver
        $filesystemDriver = config('filesystems.default');
        $checks[] = [
            'name' => 'Filesystem Driver',
            'value' => $filesystemDriver,
            'status' => 'ok',
            'message' => $filesystemDriver === 's3' ? 'Using S3 storage' : 'Using local storage',
        ];

        // Storage Writable
        $storageWritable = is_writable(storage_path());
        $checks[] = [
            'name' => 'Storage Writable',
            'value' => $storageWritable ? 'Yes' : 'No',
            'status' => $storageWritable ? 'ok' : 'error',
            'message' => $storageWritable ? 'Storage directory is writable' : 'Storage directory is not writable. Run: chmod -R 775 storage',
        ];

        // Public Storage Linked
        $publicStorageLinked = file_exists(public_path('storage'));
        $checks[] = [
            'name' => 'Public Storage Linked',
            'value' => $publicStorageLinked ? 'Yes' : 'No',
            'status' => $publicStorageLinked ? 'ok' : 'warning',
            'message' => $publicStorageLinked ? 'Storage link exists' : 'Run: php artisan storage:link',
        ];

        // Database Connection
        try {
            DB::connection()->getPdo();
            $dbStatus = 'ok';
            $dbMessage = 'Database connection successful';
        } catch (\Exception $e) {
            $dbStatus = 'error';
            $dbMessage = 'Database connection failed: ' . $e->getMessage();
        }
        $checks[] = [
            'name' => 'Database Connection',
            'value' => $dbStatus === 'ok' ? 'OK' : 'Failed',
            'status' => $dbStatus,
            'message' => $dbMessage,
        ];

        // Last Cron Run
        $lastCronRun = CronRun::orderBy('last_ran_at', 'desc')->first();
        if ($lastCronRun && $lastCronRun->last_ran_at) {
            $minutesAgo = now()->diffInMinutes($lastCronRun->last_ran_at);
            $cronStatus = $minutesAgo <= 5 ? 'ok' : ($minutesAgo <= 15 ? 'warning' : 'error');
            $cronMessage = $minutesAgo <= 5 
                ? "Last cron ran {$minutesAgo} minute(s) ago" 
                : "Last cron ran {$minutesAgo} minute(s) ago. Check cron configuration.";
        } else {
            $cronStatus = 'warning';
            $cronMessage = 'No cron runs recorded. Ensure cron is configured.';
        }
        $checks[] = [
            'name' => 'Last Cron Run',
            'value' => $lastCronRun && $lastCronRun->last_ran_at ? $lastCronRun->last_ran_at->diffForHumans() : 'Never',
            'status' => $cronStatus,
            'message' => $cronMessage,
        ];

        // Push Enabled (check both master push toggle and notifications push toggle)
        $pushMasterEnabled = SystemSetting::get('push.enabled', false);
        $notificationsPushEnabled = SystemSetting::get('notifications.push_enabled', true);
        $pushEnabled = $pushMasterEnabled && $notificationsPushEnabled;
        $checks[] = [
            'name' => 'Push Notifications',
            'value' => $pushEnabled ? 'Enabled' : 'Disabled',
            'status' => $pushEnabled ? 'ok' : 'warning',
            'message' => $pushEnabled 
                ? 'Push notifications are enabled' 
                : 'Push notifications are disabled. Check Firebase Settings and Notification Settings.',
        ];

        // Payment Gateways
        $razorpayEnabled = SystemSetting::get('payments.razorpay_enabled', false);
        $stripeEnabled = SystemSetting::get('payments.stripe_enabled', false);
        $cashEnabled = SystemSetting::get('payments.method_cash_enabled', true);
        $paymentStatus = ($razorpayEnabled || $stripeEnabled || $cashEnabled) ? 'ok' : 'warning';
        $paymentMessage = [];
        if ($razorpayEnabled) $paymentMessage[] = 'Razorpay';
        if ($stripeEnabled) $paymentMessage[] = 'Stripe';
        if ($cashEnabled) $paymentMessage[] = 'Cash';
        $checks[] = [
            'name' => 'Payment Gateways',
            'value' => implode(', ', $paymentMessage ?: ['None']),
            'status' => $paymentStatus,
            'message' => $paymentMessage ? 'Payment methods configured: ' . implode(', ', $paymentMessage) : 'No payment methods enabled',
        ];

        return $checks;
    }
}
