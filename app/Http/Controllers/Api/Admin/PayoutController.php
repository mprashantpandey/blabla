<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
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
     * List payout requests.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = PayoutRequest::with(['driverProfile.user', 'driverProfile.city']);

        // Apply city scoping for city admins
        if ($user->hasRole('city_admin') && !$user->hasRole('super_admin')) {
            $cityIds = $user->cities->pluck('id')->toArray();
            $query->whereHas('driverProfile', function ($q) use ($cityIds) {
                $q->whereIn('city_id', $cityIds);
            });
        }

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('method')) {
            $query->where('method', $request->method);
        }

        if ($request->has('city_id')) {
            $query->whereHas('driverProfile', function ($q) use ($request) {
                $q->where('city_id', $request->city_id);
            });
        }

        $payouts = $query->orderBy('requested_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'payouts' => $payouts->items()->map(function ($payout) {
                    return [
                        'id' => $payout->id,
                        'driver' => [
                            'id' => $payout->driverProfile->user_id,
                            'name' => $payout->driverProfile->user->name,
                        ],
                        'amount' => (float) $payout->amount,
                        'method' => $payout->method,
                        'status' => $payout->status,
                        'payout_reference' => $payout->payout_reference,
                        'requested_at' => $payout->requested_at,
                        'processed_at' => $payout->processed_at,
                    ];
                }),
                'pagination' => [
                    'current_page' => $payouts->currentPage(),
                    'last_page' => $payouts->lastPage(),
                    'per_page' => $payouts->perPage(),
                    'total' => $payouts->total(),
                ],
            ],
        ]);
    }

    /**
     * Approve a payout.
     */
    public function approve(int $id): JsonResponse
    {
        $payout = PayoutRequest::findOrFail($id);
        $admin = Auth::user();

        try {
            $this->payoutService->approvePayout($payout, $admin);

            return response()->json([
                'success' => true,
                'message' => 'Payout approved successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Reject a payout.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $payout = PayoutRequest::findOrFail($id);
        $admin = Auth::user();

        try {
            $this->payoutService->rejectPayout($payout, $admin, $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Payout rejected successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Mark payout as paid.
     */
    public function markPaid(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reference' => 'required|string|max:255',
        ]);

        $payout = PayoutRequest::findOrFail($id);

        try {
            $this->payoutService->markPaid($payout, $request->reference);

            return response()->json([
                'success' => true,
                'message' => 'Payout marked as paid successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
