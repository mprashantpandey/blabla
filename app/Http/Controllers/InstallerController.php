<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
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

        // .env File Status
        $envExists = file_exists(base_path('.env'));
        $envExampleExists = file_exists(base_path('.env.example'));
        $envWritable = $envExists ? is_writable(base_path('.env')) : is_writable(base_path());
        
        $checks['env'] = [
            'name' => '.env File',
            'exists' => $envExists,
            'example_exists' => $envExampleExists,
            'writable' => $envWritable,
            'status' => $envWritable ? 'pass' : 'fail',
            'message' => $envExists 
                ? '.env file exists and is writable' 
                : ($envExampleExists 
                    ? '.env file will be created from .env.example' 
                    : '.env.example not found, will create new .env'),
        ];

        return $checks;
    }

    /**
     * Install the application.
     */
    public function install(Request $request)
    {
        // Prevent re-installation
        if (file_exists(storage_path('installed'))) {
            return response()->json([
                'success' => false,
                'message' => 'Application is already installed.',
            ], 403);
        }

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
            // Step 1: Create .env file if it doesn't exist
            $this->createEnvFile();

            // Step 2: Generate APP_KEY if not set
            $this->generateAppKey();

            // Step 3: Set DB_CONNECTION to mysql immediately to prevent SQLite errors
            $this->updateEnv([
                'DB_CONNECTION' => 'mysql',
            ]);

            // Step 4: Update .env file with provided values
            $this->updateEnv([
                'APP_NAME' => '"' . addslashes($request->app_name) . '"',
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'APP_URL' => $request->app_url,
                'DB_HOST' => $request->db_host,
                'DB_PORT' => $request->db_port,
                'DB_DATABASE' => $request->db_database,
                'DB_USERNAME' => $request->db_username,
                'DB_PASSWORD' => $request->db_password ?? '',
            ]);

            // Step 5: Test database connection (database doesn't need to exist, just connection)
            config([
                'database.connections.mysql.host' => $request->db_host,
                'database.connections.mysql.port' => $request->db_port,
                'database.connections.mysql.database' => $request->db_database,
                'database.connections.mysql.username' => $request->db_username,
                'database.connections.mysql.password' => $request->db_password ?? '',
            ]);

            // Test connection
            try {
                DB::connection('mysql')->getPdo();
            } catch (\Exception $e) {
                // Try to create database if it doesn't exist
                try {
                    $this->createDatabaseIfNotExists($request);
                } catch (\Exception $createError) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Database connection failed. Please ensure the database exists or the user has permission to create databases. Error: ' . $e->getMessage(),
                    ], 500);
                }
            }

            // Step 6: Clear config cache and reload database config
            if (file_exists(base_path('bootstrap/cache/config.php'))) {
                unlink(base_path('bootstrap/cache/config.php'));
            }
            
            // Force MySQL as default connection (prevent SQLite errors)
            config(['database.default' => 'mysql']);
            DB::setDefaultConnection('mysql');

            // Step 7: Run migrations
            // Force MySQL connection (prevent SQLite errors)
            DB::setDefaultConnection('mysql');
            config(['database.default' => 'mysql']);
            
            // For fresh installation, use migrate:fresh to ensure clean state
            // This drops all tables and recreates them (safe for fresh install)
            try {
                // Check if any tables exist (MySQL only)
                $tableCount = 0;
                
                try {
                    $tables = DB::connection('mysql')->select("SHOW TABLES");
                    $tableCount = count($tables);
                } catch (\Exception $e) {
                    // If we can't check, assume no tables and proceed with normal migrate
                    $tableCount = 0;
                }
                
                if ($tableCount > 0) {
                    // Tables exist, use fresh migration for clean install
                    Artisan::call('migrate:fresh', ['--force' => true, '--seed' => false, '--database' => 'mysql']);
                } else {
                    // No tables, safe to run normal migration
                    Artisan::call('migrate', ['--force' => true, '--database' => 'mysql']);
                }
            } catch (\Exception $e) {
                // If migration fails, try fresh migration as fallback
                // This ensures clean state for fresh installation
                if (str_contains($e->getMessage(), 'already exists') || (str_contains($e->getMessage(), 'table') && str_contains($e->getMessage(), 'exist'))) {
                    try {
                        Artisan::call('migrate:fresh', ['--force' => true, '--seed' => false, '--database' => 'mysql']);
                    } catch (\Exception $freshError) {
                        throw new \Exception('Migration failed: ' . $freshError->getMessage());
                    }
                } else {
                    throw $e;
                }
            }

            // Step 7: Create roles and permissions
            $this->createRolesAndPermissions();

            // Step 8: Create admin user
            $admin = User::create([
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);

            $admin->assignRole('super_admin');

            // Step 9: Create default settings
            $this->createDefaultSettings($request->app_name);

            // Step 10: Create default cities
            $this->createDefaultCities();

            // Step 11: Create storage link
            try {
                Artisan::call('storage:link', ['--force' => true]);
            } catch (\Exception $e) {
                // Storage link may already exist, continue
            }

            // Step 12: Clear all caches
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');

            // Step 13: Mark as installed
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
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Create .env file from .env.example if it doesn't exist.
     */
    private function createEnvFile(): void
    {
        $envFile = base_path('.env');
        
        if (file_exists($envFile)) {
            return; // .env already exists
        }

        $envExample = base_path('.env.example');
        
        if (file_exists($envExample)) {
            // Copy from .env.example
            copy($envExample, $envFile);
        } else {
            // Create basic .env file with blank MySQL settings
            $defaultEnv = "APP_NAME=Laravel
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=http://localhost

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
";
            file_put_contents($envFile, $defaultEnv);
        }
        
        // Ensure DB_CONNECTION is set to mysql (not sqlite)
        $envContent = file_get_contents($envFile);
        if (preg_match('/^DB_CONNECTION=sqlite/m', $envContent)) {
            $envContent = preg_replace('/^DB_CONNECTION=sqlite/m', 'DB_CONNECTION=mysql', $envContent);
            file_put_contents($envFile, $envContent);
        } elseif (!preg_match('/^DB_CONNECTION=/m', $envContent)) {
            // Add DB_CONNECTION if missing
            $envContent = "DB_CONNECTION=mysql\n" . $envContent;
            file_put_contents($envFile, $envContent);
        }
    }

    /**
     * Generate APP_KEY if not set.
     */
    private function generateAppKey(): void
    {
        $envFile = base_path('.env');
        $envContent = file_get_contents($envFile);

        // Check if APP_KEY is empty or not set
        if (!preg_match('/^APP_KEY=(.+)$/m', $envContent) || preg_match('/^APP_KEY=$/m', $envContent)) {
            // Generate key manually (works without database)
            $key = 'base64:' . base64_encode(random_bytes(32));
            
            // Update .env file with generated key
            if (preg_match('/^APP_KEY=.*$/m', $envContent)) {
                $envContent = preg_replace('/^APP_KEY=.*$/m', "APP_KEY={$key}", $envContent);
            } else {
                $envContent = "APP_KEY={$key}\n" . $envContent;
            }
            
            file_put_contents($envFile, $envContent);
        }
    }

    /**
     * Try to create database if it doesn't exist.
     */
    private function createDatabaseIfNotExists(Request $request): void
    {
        // Connect without database name
        config([
            'database.connections.mysql.host' => $request->db_host,
            'database.connections.mysql.port' => $request->db_port,
            'database.connections.mysql.database' => null,
            'database.connections.mysql.username' => $request->db_username,
            'database.connections.mysql.password' => $request->db_password ?? '',
        ]);

        try {
            $pdo = DB::connection('mysql')->getPdo();
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$request->db_database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Reconnect with database
            config([
                'database.connections.mysql.database' => $request->db_database,
            ]);
            DB::purge('mysql');
            DB::connection('mysql')->getPdo();
        } catch (\Exception $e) {
            throw new \Exception('Cannot create database. Please create it manually or ensure the user has CREATE privileges. Error: ' . $e->getMessage());
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
