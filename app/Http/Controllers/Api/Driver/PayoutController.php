<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PayoutController extends Controller
{
    protected PayoutService $payoutService;

    public function __construct(PayoutService $payoutService)
    {
        $this->payoutService = $payoutService;
    }

    /**
     * Request a payout.
     */
    public function request(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:bank,razorpay,stripe,cash,manual',
        ]);

        $user = Auth::user();
        $driverProfile = $user->driverProfile;

        if (!$driverProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        try {
            $payout = $this->payoutService->requestPayout(
                $driverProfile,
                $request->amount,
                $request->method
            );

            return response()->json([
                'success' => true,
                'message' => 'Payout request submitted successfully',
                'data' => [
                    'payout' => [
                        'id' => $payout->id,
                        'amount' => (float) $payout->amount,
                        'method' => $payout->method,
                        'status' => $payout->status,
                        'requested_at' => $payout->requested_at,
                    ],
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
