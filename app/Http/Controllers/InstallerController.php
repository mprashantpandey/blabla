<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class InstallerController extends Controller
{
    /**
     * Check if installation is needed.
     */
    public function check()
    {
        $installed = file_exists(storage_path('installed'));

        if ($installed) {
            return response()->json([
                'installed' => true,
                'message' => 'Application is already installed.',
            ], 403);
        }

        try {
            return response()->json([
                'installed' => false,
                'pre_checks' => $this->runPreInstallChecks(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'installed' => false,
                'pre_checks' => [],
                'error' => 'Failed to run pre-install checks: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run pre-installation checks.
     */
    private function runPreInstallChecks(): array
    {
        $checks = [];

        // PHP Version
        $phpVersion = PHP_VERSION;
        $minVersion = '8.2.0';
        $checks['php_version'] = [
            'name' => 'PHP Version',
            'required' => $minVersion,
            'current' => $phpVersion,
            'status' => version_compare($phpVersion, $minVersion, '>=') ? 'pass' : 'fail',
        ];

        // Required Extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'json', 'curl', 'fileinfo'];
        $missingExtensions = [];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missingExtensions[] = $ext;
            }
        }
        $checks['extensions'] = [
            'name' => 'Required PHP Extensions',
            'required' => implode(', ', $requiredExtensions),
            'missing' => $missingExtensions,
            'status' => empty($missingExtensions) ? 'pass' : 'fail',
        ];

        // Storage Permissions
        $storageWritable = is_writable(storage_path());
        $bootstrapCacheWritable = is_writable(base_path('bootstrap/cache'));
        $checks['permissions'] = [
            'name' => 'Storage Permissions',
            'storage_writable' => $storageWritable,
            'bootstrap_cache_writable' => $bootstrapCacheWritable,
            'status' => ($storageWritable && $bootstrapCacheWritable) ? 'pass' : 'fail',
        ];

        // .env Writable
        $envWritable = is_writable(base_path('.env')) || (file_exists(base_path('.env.example')) && is_writable(base_path()));
        $checks['env'] = [
            'name' => '.env File',
            'writable' => $envWritable,
            'status' => $envWritable ? 'pass' : 'fail',
        ];

        return $checks;
    }

    /**
     * Install the application.
     */
    public function install(Request $request)
    {
        $request->validate([
            'db_host' => 'required|string',
            'db_port' => 'required|integer',
            'db_database' => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'nullable|string',
            'admin_name' => 'required|string',
            'admin_email' => 'required|email',
            'admin_password' => 'required|string|min:8',
            'app_name' => 'required|string',
            'app_url' => 'required|url',
        ]);

        try {
            // Update .env file
            $this->updateEnv([
                'DB_HOST' => $request->db_host,
                'DB_PORT' => $request->db_port,
                'DB_DATABASE' => $request->db_database,
                'DB_USERNAME' => $request->db_username,
                'DB_PASSWORD' => $request->db_password ?? '',
                'APP_NAME' => $request->app_name,
                'APP_URL' => $request->app_url,
            ]);

            // Test database connection
            config([
                'database.connections.mysql.host' => $request->db_host,
                'database.connections.mysql.port' => $request->db_port,
                'database.connections.mysql.database' => $request->db_database,
                'database.connections.mysql.username' => $request->db_username,
                'database.connections.mysql.password' => $request->db_password ?? '',
            ]);

            DB::connection('mysql')->getPdo();

            // Run migrations
            Artisan::call('migrate', ['--force' => true]);

            // Create roles and permissions
            $this->createRolesAndPermissions();

            // Create admin user
            $admin = User::create([
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);

            $admin->assignRole('super_admin');

            // Create default settings
            $this->createDefaultSettings($request->app_name);

            // Create default cities
            $this->createDefaultCities();

            // Create storage link
            try {
                Artisan::call('storage:link', ['--force' => true]);
            } catch (\Exception $e) {
                // Storage link may already exist, continue
            }

            // Mark as installed
            file_put_contents(storage_path('installed'), now()->toDateTimeString());

            return response()->json([
                'success' => true,
                'message' => 'Installation completed successfully',
                'checklist' => [
                    'admin_user' => true,
                    'cities_seeded' => City::count() > 0,
                    'settings_initialized' => SystemSetting::count() > 0,
                    'storage_linked' => file_exists(public_path('storage')),
                    'cron_reminder' => 'Add cron job: * * * * * cd ' . base_path() . ' && php artisan schedule:run >> /dev/null 2>&1',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Installation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check database connection.
     */
    private function checkDatabase(): bool
    {
        try {
            DB::connection('mysql')->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update .env file.
     */
    private function updateEnv(array $data): void
    {
        $envFile = base_path('.env');
        $envContent = file_get_contents($envFile);

        foreach ($data as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$value}";
            
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$replacement}";
            }
        }

        file_put_contents($envFile, $envContent);
    }

    /**
     * Create roles and permissions.
     */
    private function createRolesAndPermissions(): void
    {
        // Create permissions
        $permissions = [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'drivers.view',
            'drivers.approve',
            'drivers.reject',
            'rides.view',
            'rides.manage',
            'bookings.view',
            'bookings.manage',
            'payouts.view',
            'payouts.approve',
            'settings.manage',
            'cities.manage',
            'tickets.manage',
            'reports.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $cityAdmin = Role::firstOrCreate(['name' => 'city_admin']);
        $supportStaff = Role::firstOrCreate(['name' => 'support_staff']);

        // Assign all permissions to super admin
        $superAdmin->givePermissionTo(Permission::all());

        // Assign limited permissions to city admin
        $cityAdmin->givePermissionTo([
            'users.view',
            'drivers.view',
            'drivers.approve',
            'drivers.reject',
            'rides.view',
            'bookings.view',
            'payouts.view',
            'tickets.manage',
        ]);

        // Assign support permissions
        $supportStaff->givePermissionTo([
            'users.view',
            'rides.view',
            'bookings.view',
            'tickets.manage',
        ]);
    }

    /**
     * Create default settings.
     */
    private function createDefaultSettings(string $appName): void
    {
        $defaultSettings = [
            // App settings
            ['key' => 'app_name', 'value' => $appName, 'type' => 'string', 'group' => 'app'],
            ['key' => 'app_logo', 'value' => '', 'type' => 'string', 'group' => 'app'],
            ['key' => 'currency', 'value' => 'USD', 'type' => 'string', 'group' => 'app'],
            ['key' => 'platform_commission', 'value' => '10', 'type' => 'integer', 'group' => 'app'],
            
            // Payment settings
            ['key' => 'razorpay_enabled', 'value' => 'false', 'type' => 'boolean', 'group' => 'payment'],
            ['key' => 'razorpay_key', 'value' => '', 'type' => 'string', 'group' => 'payment'],
            ['key' => 'razorpay_secret', 'value' => '', 'type' => 'string', 'group' => 'payment'],
            ['key' => 'stripe_enabled', 'value' => 'false', 'type' => 'boolean', 'group' => 'payment'],
            ['key' => 'stripe_key', 'value' => '', 'type' => 'string', 'group' => 'payment'],
            ['key' => 'stripe_secret', 'value' => '', 'type' => 'string', 'group' => 'payment'],
            ['key' => 'cash_payment_enabled', 'value' => 'true', 'type' => 'boolean', 'group' => 'payment'],
            
            // Map settings
            ['key' => 'map_provider', 'value' => 'google', 'type' => 'string', 'group' => 'maps'],
            ['key' => 'map_api_key', 'value' => '', 'type' => 'string', 'group' => 'maps'],
            
            // SMS settings
            ['key' => 'sms_provider', 'value' => 'firebase', 'type' => 'string', 'group' => 'sms'],
            ['key' => 'firebase_server_key', 'value' => '', 'type' => 'string', 'group' => 'sms'],
            ['key' => 'msg91_auth_key', 'value' => '', 'type' => 'string', 'group' => 'sms'],
            
            // App version
            ['key' => 'min_app_version', 'value' => '1.0.0', 'type' => 'string', 'group' => 'app'],
            ['key' => 'force_update', 'value' => 'false', 'type' => 'boolean', 'group' => 'app'],
            ['key' => 'maintenance_mode', 'value' => 'false', 'type' => 'boolean', 'group' => 'app'],
            ['key' => 'maintenance_message', 'value' => 'We are currently under maintenance. Please check back later.', 'type' => 'text', 'group' => 'app'],
            
            // Demo mode
            ['key' => 'demo_mode', 'value' => 'false', 'type' => 'boolean', 'group' => 'general'],
        ];

        foreach ($defaultSettings as $setting) {
            SystemSetting::create($setting);
        }
    }

    /**
     * Create default cities.
     */
    private function createDefaultCities(): void
    {
        $cities = [
            ['name' => 'New York', 'slug' => 'new-york', 'state' => 'NY', 'country' => 'US', 'timezone' => 'America/New_York'],
            ['name' => 'Los Angeles', 'slug' => 'los-angeles', 'state' => 'CA', 'country' => 'US', 'timezone' => 'America/Los_Angeles'],
            ['name' => 'Chicago', 'slug' => 'chicago', 'state' => 'IL', 'country' => 'US', 'timezone' => 'America/Chicago'],
        ];

        foreach ($cities as $city) {
            City::create($city);
        }
    }
}
