<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Api\BaseController;
use App\Models\Booking;
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
     * Get driver's bookings.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return $this->error('Driver profile not found');
        }

        $query = $profile->bookings()->with(['ride', 'rider', 'payment']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date')) {
            $query->whereHas('ride', function ($q) use ($request) {
                $q->whereDate('departure_at', $request->date);
            });
        }

        $bookings = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        $formattedBookings = $bookings->getCollection()->map(fn ($booking) => $this->formatBooking($booking));
        
        return response()->json([
            'success' => true,
            'message' => 'Bookings retrieved successfully',
            'data' => $formattedBookings->values()->all(),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    /**
     * Accept booking.
     */
    public function accept(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $booking = Booking::find($id);

        if (!$booking) {
            return $this->error('Booking not found', [], 404);
        }

        try {
            $this->bookingService->acceptBooking($booking, $user);

            return $this->success($this->formatBooking($booking->fresh(), true), 'Booking accepted successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Failed to accept booking: ' . $e->getMessage());
        }
    }

    /**
     * Reject booking.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $booking = Booking::find($id);

        if (!$booking) {
            return $this->error('Booking not found', [], 404);
        }

        try {
            $this->bookingService->rejectBooking($booking, $user, $request->reason);

            return $this->success($this->formatBooking($booking->fresh(), true), 'Booking rejected successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Failed to reject booking: ' . $e->getMessage());
        }
    }

    /**
     * Cancel booking (driver).
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
            'total_amount' => (float) $booking->total_amount,
            'payment_method' => $booking->payment_method,
            'payment_status' => $booking->payment_status,
            'rider' => [
                'id' => $booking->rider->id,
                'name' => $booking->rider->name,
                'phone' => $booking->rider->phone,
            ],
            'ride' => [
                'id' => $booking->ride->id,
                'origin_name' => $booking->ride->origin_name,
                'destination_name' => $booking->ride->destination_name,
                'departure_at' => $booking->ride->departure_at->toDateTimeString(),
            ],
        ];

        if ($detailed) {
            $data['accepted_at'] = $booking->accepted_at?->toDateTimeString();
            $data['rejected_at'] = $booking->rejected_at?->toDateTimeString();
            $data['confirmed_at'] = $booking->confirmed_at?->toDateTimeString();
            $data['cancelled_at'] = $booking->cancelled_at?->toDateTimeString();
            $data['cancel_reason'] = $booking->cancel_reason;
        }

        return $data;
    }
}
