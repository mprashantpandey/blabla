<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingModerationController extends BaseController
{
    protected BookingService $bookingService;
    protected PaymentService $paymentService;

    public function __construct(BookingService $bookingService, PaymentService $paymentService)
    {
        $this->bookingService = $bookingService;
        $this->paymentService = $paymentService;
    }

    /**
     * Get bookings list.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = Booking::with(['ride', 'rider', 'driverProfile.user', 'payment']);

        // City Admin scope
        if ($user->hasRole('City Admin') && !$user->hasRole('Super Admin')) {
            $assignedCityIds = $user->assignedCities()->pluck('cities.id');
            $query->whereIn('city_id', $assignedCityIds);
        }

        // Filters
        if ($request->has('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('provider')) {
            $query->whereHas('payment', function ($q) use ($request) {
                $q->where('provider', $request->provider);
            });
        }

        $bookings = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        $data = $bookings->map(fn ($booking) => [
            'id' => $booking->id,
            'status' => $booking->status,
            'rider' => [
                'id' => $booking->rider->id,
                'name' => $booking->rider->name,
            ],
            'driver' => [
                'id' => $booking->driverProfile->user->id,
                'name' => $booking->driverProfile->user->name,
            ],
            'ride' => [
                'id' => $booking->ride->id,
                'origin' => $booking->ride->origin_name,
                'destination' => $booking->ride->destination_name,
            ],
            'seats_requested' => $booking->seats_requested,
            'total_amount' => (float) $booking->total_amount,
            'payment_method' => $booking->payment_method,
            'payment_status' => $booking->payment_status,
            'created_at' => $booking->created_at->toDateTimeString(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bookings retrieved successfully',
            'data' => $data,
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    /**
     * Get booking details.
     */
    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        $booking = Booking::with(['ride', 'rider', 'driverProfile.user', 'payment', 'events.performer'])->find($id);

        if (!$booking) {
            return $this->error('Booking not found', [], 404);
        }

        // City Admin scope check
        if ($user->hasRole('city_admin') && !$user->hasRole('super_admin')) {
            $assignedCityIds = \App\Models\CityAdminAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->pluck('city_id');
            if (!$assignedCityIds->contains($booking->city_id)) {
                return $this->error('Access denied', [], 403);
            }
        }

        return $this->success([
            'id' => $booking->id,
            'status' => $booking->status,
            'rider' => [
                'id' => $booking->rider->id,
                'name' => $booking->rider->name,
                'email' => $booking->rider->email,
                'phone' => $booking->rider->phone,
            ],
            'driver' => [
                'id' => $booking->driverProfile->user->id,
                'name' => $booking->driverProfile->user->name,
            ],
            'ride' => [
                'id' => $booking->ride->id,
                'origin' => $booking->ride->origin_name,
                'destination' => $booking->ride->destination_name,
                'departure_at' => $booking->ride->departure_at->toDateTimeString(),
            ],
            'seats_requested' => $booking->seats_requested,
            'price_per_seat' => (float) $booking->price_per_seat,
            'subtotal' => (float) $booking->subtotal,
            'commission_amount' => (float) $booking->commission_amount,
            'total_amount' => (float) $booking->total_amount,
            'payment_method' => $booking->payment_method,
            'payment_status' => $booking->payment_status,
            'payment' => $booking->payment ? [
                'provider' => $booking->payment->provider,
                'status' => $booking->payment->status,
                'amount' => (float) $booking->payment->amount,
            ] : null,
            'events' => $booking->events->map(fn ($event) => [
                'event' => $event->event,
                'performed_by' => $event->performer ? $event->performer->name : 'System',
                'meta' => $event->meta,
                'created_at' => $event->created_at->toDateTimeString(),
            ]),
        ], 'Booking details retrieved successfully');
    }

    /**
     * Force cancel booking.
     */
    public function forceCancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $booking = Booking::find($id);

        if (!$booking) {
            return $this->error('Booking not found', [], 404);
        }

        try {
            $this->bookingService->cancelBooking($booking, auth()->user(), $request->reason);

            return $this->success(null, 'Booking cancelled successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to cancel booking: ' . $e->getMessage());
        }
    }

    /**
     * Force refund.
     */
    public function forceRefund(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $booking = Booking::find($id);

        if (!$booking) {
            return $this->error('Booking not found', [], 404);
        }

        try {
            $refunded = $this->paymentService->processRefund($booking, $request->reason);

            if ($refunded) {
                $booking->status = 'refunded';
                $booking->refunded_at = now();
                $booking->save();

                return $this->success(null, 'Refund processed successfully');
            }

            return $this->error('Refund could not be processed');
        } catch (\Exception $e) {
            return $this->error('Failed to process refund: ' . $e->getMessage());
        }
    }
}
