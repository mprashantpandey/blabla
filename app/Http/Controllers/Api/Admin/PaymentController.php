<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends BaseController
{
    /**
     * Get payments list.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['booking.rider', 'booking.ride']);

        // Filters
        if ($request->has('provider')) {
            $query->where('provider', $request->provider);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $payments = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        $data = $payments->map(fn ($payment) => [
            'id' => $payment->id,
            'provider' => $payment->provider,
            'status' => $payment->status,
            'amount' => (float) $payment->amount,
            'currency_code' => $payment->currency_code,
            'booking' => [
                'id' => $payment->booking->id,
                'rider' => $payment->booking->rider->name,
                'ride' => $payment->booking->ride->origin_name . ' → ' . $payment->booking->ride->destination_name,
            ],
            'provider_payment_id' => $payment->provider_payment_id,
            'created_at' => $payment->created_at->toDateTimeString(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payments retrieved successfully',
            'data' => $data,
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }
}
