<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo cities
        $cities = [
            [
                'name' => 'New York',
                'slug' => 'new-york',
                'state' => 'NY',
                'country' => 'US',
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'timezone' => 'America/New_York',
                'currency' => 'USD',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Los Angeles',
                'slug' => 'los-angeles',
                'state' => 'CA',
                'country' => 'US',
                'latitude' => 34.0522,
                'longitude' => -118.2437,
                'timezone' => 'America/Los_Angeles',
                'currency' => 'USD',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Chicago',
                'slug' => 'chicago',
                'state' => 'IL',
                'country' => 'US',
                'latitude' => 41.8781,
                'longitude' => -87.6298,
                'timezone' => 'America/Chicago',
                'currency' => 'USD',
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($cities as $cityData) {
            City::firstOrCreate(['slug' => $cityData['slug']], $cityData);
        }

        // Create demo admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@blabla.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
                'city_id' => City::where('slug', 'new-york')->first()?->id,
            ]
        );
        $admin->assignRole('super_admin');

        // Create demo city admin
        $cityAdmin = User::firstOrCreate(
            ['email' => 'cityadmin@blabla.com'],
            [
                'name' => 'City Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
                'city_id' => City::where('slug', 'new-york')->first()?->id,
            ]
        );
        $cityAdmin->assignRole('city_admin');

        // Create demo users
        $demoUsers = [
            [
                'name' => 'John Rider',
                'email' => 'rider@blabla.com',
                'phone' => '+1234567890',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
                'is_active' => true,
                'city_id' => City::where('slug', 'new-york')->first()?->id,
            ],
            [
                'name' => 'Jane Driver',
                'email' => 'driver@blabla.com',
                'phone' => '+1234567891',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
                'is_active' => true,
                'is_driver' => true,
                'city_id' => City::where('slug', 'new-york')->first()?->id,
            ],
        ];

        foreach ($demoUsers as $userData) {
            User::firstOrCreate(['email' => $userData['email']], $userData);
        }

        // Create default system settings if they don't exist
        $defaultSettings = [
            ['key' => 'app_name', 'value' => 'BlaBla', 'type' => 'string', 'group' => 'app'],
            ['key' => 'currency', 'value' => 'USD', 'type' => 'string', 'group' => 'app'],
            ['key' => 'platform_commission', 'value' => '10', 'type' => 'integer', 'group' => 'app'],
            ['key' => 'cash_payment_enabled', 'value' => 'true', 'type' => 'boolean', 'group' => 'payment'],
            ['key' => 'map_provider', 'value' => 'google', 'type' => 'string', 'group' => 'maps'],
            ['key' => 'min_app_version', 'value' => '1.0.0', 'type' => 'string', 'group' => 'app'],
            ['key' => 'maintenance_mode', 'value' => 'false', 'type' => 'boolean', 'group' => 'app'],
            ['key' => 'demo_mode', 'value' => 'false', 'type' => 'boolean', 'group' => 'general'],
        ];

        foreach ($defaultSettings as $setting) {
            SystemSetting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
