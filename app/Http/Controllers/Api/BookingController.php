<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Booking\CreateBookingRequest;
use App\Models\Booking;
use App\Models\Ride;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends BaseController
{
    protected BookingService $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * Create a booking request.
     */
    public function store(CreateBookingRequest $request): JsonResponse
    {
        $user = $request->user();
        $ride = Ride::findOrFail($request->ride_id);

        try {
            $booking = $this->bookingService->createBooking(
                $ride,
                $user,
                $request->seats,
                $request->payment_method
            );

            return $this->success($this->formatBooking($booking, true), 'Booking request created successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Failed to create booking: ' . $e->getMessage());
        }
    }

    /**
     * Get user's bookings.
     */
    public function myBookings(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = $user->bookingsAsRider()->with(['ride', 'driverProfile.user', 'payment']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $bookings = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        $data = $bookings->map(fn ($booking) => $this->formatBooking($booking));

        return $this->paginated($bookings->setCollection(collect($data)), 'Bookings retrieved successfully');
    }

    /**
     * Get booking details.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $booking = Booking::with(['ride', 'driverProfile.user', 'payment', 'events'])->find($id);

        if (!$booking) {
            return $this->error('Booking not found', [], 404);
        }

        // Check ownership
        if ($booking->rider_user_id !== $user->id) {
            return $this->error('Unauthorized', [], 403);
        }

        return $this->success($this->formatBooking($booking, true), 'Booking retrieved successfully');
    }

    /**
     * Cancel booking.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $booking = Booking::find($id);

        if (!$booking) {
            return $this->error('Booking not found', [], 404);
        }

        if ($booking->rider_user_id !== $user->id) {
            return $this->error('Unauthorized', [], 403);
        }

        try {
            $this->bookingService->cancelBooking($booking, $user, $request->reason);

            return $this->success($this->formatBooking($booking->fresh(), true), 'Booking cancelled successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Failed to cancel booking: ' . $e->getMessage());
        }
    }

    /**
     * Format booking for response.
     */
    protected function formatBooking(Booking $booking, bool $detailed = false): array
    {
        $data = [
            'id' => $booking->id,
            'status' => $booking->status,
            'seats_requested' => $booking->seats_requested,
            'price_per_seat' => (float) $booking->price_per_seat,
            'subtotal' => (float) $booking->subtotal,
            'total_amount' => (float) $booking->total_amount,
            'payment_method' => $booking->payment_method,
            'payment_status' => $booking->payment_status,
            'hold_expires_at' => $booking->hold_expires_at?->toDateTimeString(),
            'ride' => [
                'id' => $booking->ride->id,
                'origin' => [
                    'name' => $booking->ride->origin_name,
                    'lat' => (float) $booking->ride->origin_lat,
                    'lng' => (float) $booking->ride->origin_lng,
                ],
                'destination' => [
                    'name' => $booking->ride->destination_name,
                    'lat' => (float) $booking->ride->destination_lat,
                    'lng' => (float) $booking->ride->destination_lng,
                ],
                'departure_at' => $booking->ride->departure_at->toDateTimeString(),
            ],
            'driver' => [
                'id' => $booking->driverProfile->user->id,
                'name' => $booking->driverProfile->user->name,
            ],
        ];

        if ($detailed) {
            $data['commission_amount'] = (float) $booking->commission_amount;
            $data['accepted_at'] = $booking->accepted_at?->toDateTimeString();
            $data['confirmed_at'] = $booking->confirmed_at?->toDateTimeString();
            $data['cancelled_at'] = $booking->cancelled_at?->toDateTimeString();
            $data['cancel_reason'] = $booking->cancel_reason;
            $data['payment'] = $booking->payment ? [
                'provider' => $booking->payment->provider,
                'status' => $booking->payment->status,
            ] : null;
            $data['events'] = $booking->events->map(fn ($event) => [
                'event' => $event->event,
                'performed_by' => $event->performer ? $event->performer->name : 'System',
                'created_at' => $event->created_at->toDateTimeString(),
            ]);
        }

        return $data;
    }
}
