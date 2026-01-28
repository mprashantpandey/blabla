<?php

namespace Tests\Feature\Payment;

use Tests\TestCase;
use App\Models\User;
use App\Models\City;
use App\Models\Ride;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\SystemSetting;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected BookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookingService = app(BookingService::class);

        SystemSetting::set('bookings.enabled', true);
        SystemSetting::set('payments.method_cash_enabled', true);
        SystemSetting::set('business.commission_type', 'percent');
        SystemSetting::set('business.commission_value', 10);
    }

    public function test_cash_payment_confirms_immediately_after_acceptance(): void
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

        $this->actingAs($rider);

        $booking = $this->bookingService->createBooking(
            $ride,
            $rider,
            2,
            'cash'
        );

        $this->actingAs($driver);
        $this->bookingService->acceptBooking($booking, $driver);

        $booking->refresh();
        $this->assertEquals('confirmed', $booking->status);
        $this->assertEquals('unpaid', $booking->payment_status);
    }

    public function test_booking_can_be_cancelled(): void
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
            'departure_at' => now()->addHours(5), // Future departure
        ]);

        SystemSetting::set('bookings.allow_cancellation', true);
        SystemSetting::set('bookings.cancellation_deadline_hours', 3);

        $this->actingAs($rider);

        $booking = $this->bookingService->createBooking(
            $ride,
            $rider,
            2,
            'cash'
        );

        $initialSeats = $ride->seats_available;

        $this->bookingService->cancelBooking($booking, $rider, 'Changed my mind');

        $booking->refresh();
        $this->assertEquals('cancelled', $booking->status);
        $this->assertEquals('Changed my mind', $booking->cancel_reason);

        $ride->refresh();
        $this->assertEquals($initialSeats + 2, $ride->seats_available);
    }
}
