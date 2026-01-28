<?php

namespace Tests\Feature\Booking;

use Tests\TestCase;
use App\Models\User;
use App\Models\City;
use App\Models\Ride;
use App\Models\DriverProfile;
use App\Models\SystemSetting;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BookingCreationTest extends TestCase
{
    use RefreshDatabase;

    protected BookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookingService = app(BookingService::class);

        // Set up system settings
        SystemSetting::set('bookings.enabled', true);
        SystemSetting::set('bookings.require_driver_acceptance_default', true);
        SystemSetting::set('bookings.seat_hold_minutes', 10);
        SystemSetting::set('payments.method_cash_enabled', true);
        SystemSetting::set('business.commission_type', 'percent');
        SystemSetting::set('business.commission_value', 10);
    }

    public function test_rider_can_create_booking_request(): void
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
            'price_per_seat' => 100.00,
        ]);

        $this->actingAs($rider);

        $booking = $this->bookingService->createBooking(
            $ride,
            $rider,
            2,
            'cash'
        );

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'ride_id' => $ride->id,
            'rider_user_id' => $rider->id,
            'status' => 'requested',
            'seats_requested' => 2,
            'payment_method' => 'cash',
        ]);

        $this->assertEquals(2, $booking->seats_requested);
        $this->assertEquals(100.00, $booking->price_per_seat);
        $this->assertEquals(200.00, $booking->subtotal);
    }

    public function test_booking_reserves_seats_atomically(): void
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
            'price_per_seat' => 100.00,
        ]);

        $this->actingAs($rider);

        $booking = $this->bookingService->createBooking(
            $ride,
            $rider,
            2,
            'cash'
        );

        $ride->refresh();
        $this->assertEquals(0, $ride->seats_available);
    }

    public function test_booking_fails_if_not_enough_seats(): void
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
            'seats_available' => 1,
            'price_per_seat' => 100.00,
        ]);

        $this->actingAs($rider);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->bookingService->createBooking(
            $ride,
            $rider,
            2,
            'cash'
        );
    }

    public function test_booking_calculates_commission_correctly(): void
    {
        SystemSetting::set('business.commission_type', 'percent');
        SystemSetting::set('business.commission_value', 15);

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
            'price_per_seat' => 100.00,
        ]);

        $this->actingAs($rider);

        $booking = $this->bookingService->createBooking(
            $ride,
            $rider,
            2,
            'cash'
        );

        $this->assertEquals(200.00, $booking->subtotal);
        $this->assertEquals(30.00, $booking->commission_amount); // 15% of 200
        $this->assertEquals(200.00, $booking->total_amount); // Rider pays subtotal
    }
}
