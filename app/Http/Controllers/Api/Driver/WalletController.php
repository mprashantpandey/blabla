<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Get driver wallet.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $driverProfile = $user->driverProfile;

        if (!$driverProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        $wallet = $this->walletService->getOrCreateWallet($driverProfile);

        return response()->json([
            'success' => true,
            'data' => [
                'wallet' => [
                    'id' => $wallet->id,
                    'balance' => (float) $wallet->balance,
                    'lifetime_earned' => (float) $wallet->lifetime_earned,
                    'lifetime_withdrawn' => (float) $wallet->lifetime_withdrawn,
                    'last_updated_at' => $wallet->last_updated_at,
                ],
            ],
        ]);
    }

    /**
     * Get wallet transactions.
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = Auth::user();
        $driverProfile = $user->driverProfile;

        if (!$driverProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        $wallet = $this->walletService->getOrCreateWallet($driverProfile);

        $transactions = $wallet->transactions()
            ->with(['booking:id,ride_id'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions->items()->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'type' => $transaction->type,
                        'amount' => (float) $transaction->amount,
                        'direction' => $transaction->direction,
                        'description' => $transaction->description,
                        'booking_id' => $transaction->booking_id,
                        'created_at' => $transaction->created_at,
                    ];
                }),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ],
            ],
        ]);
    }
}
