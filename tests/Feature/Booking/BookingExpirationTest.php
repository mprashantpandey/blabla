<?php

namespace Tests\Feature\Booking;

use Tests\TestCase;
use App\Models\User;
use App\Models\City;
use App\Models\Ride;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

class BookingExpirationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('bookings.enabled', true);
        SystemSetting::set('bookings.seat_hold_minutes', 10);
    }

    public function test_expire_holds_command_expires_old_bookings(): void
    {
        $city = City::factory()->create();
        $driver = User::factory()->create();
        $driverProfile = DriverProfile::factory()->create([
            'user_id' => $driver->id,
            'city_id' => $city->id,
            'status' => 'approved',
        ]);
        $rider = User::factory()->create();
        $ride = Ride::factory()->create([
            'driver_profile_id' => $driverProfile->id,
            'city_id' => $city->id,
            'status' => 'published',
            'seats_available' => 4,
        ]);

        // Create expired booking
        $expiredBooking = Booking::factory()->create([
            'ride_id' => $ride->id,
            'rider_user_id' => $rider->id,
            'driver_profile_id' => $driverProfile->id,
            'city_id' => $city->id,
            'status' => 'requested',
            'hold_expires_at' => now()->subMinutes(5),
            'seats_requested' => 2,
        ]);

        $initialSeats = $ride->seats_available;

        Artisan::call('bookings:expire-holds');

        $expiredBooking->refresh();
        $this->assertEquals('expired', $expiredBooking->status);

        $ride->refresh();
        $this->assertEquals($initialSeats + 2, $ride->seats_available);
    }

    public function test_expire_holds_releases_seats(): void
    {
        $city = City::factory()->create();
        $driver = User::factory()->create();
        $driverProfile = DriverProfile::factory()->create([
            'user_id' => $driver->id,
            'city_id' => $city->id,
            'status' => 'approved',
        ]);
        $rider = User::factory()->create();
        $ride = Ride::factory()->create([
            'driver_profile_id' => $driverProfile->id,
            'city_id' => $city->id,
            'status' => 'published',
            'seats_available' => 2,
        ]);

        $booking = Booking::factory()->create([
            'ride_id' => $ride->id,
            'rider_user_id' => $rider->id,
            'driver_profile_id' => $driverProfile->id,
            'city_id' => $city->id,
            'status' => 'payment_pending',
            'hold_expires_at' => now()->subMinutes(1),
            'seats_requested' => 2,
        ]);

        $ride->seats_available = 0;
        $ride->save();

        Artisan::call('bookings:expire-holds');

        $ride->refresh();
        $this->assertEquals(2, $ride->seats_available);
    }
}
