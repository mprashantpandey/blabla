<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Submit a report.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'booking_id' => 'nullable|exists:bookings,id',
            'reported_user_id' => 'required|exists:users,id',
            'type' => 'required|in:user,ride,message',
            'reason' => 'required|in:spam,harassment,fraud,unsafe,other',
            'comment' => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();

        try {
            $report = $this->reportService->submitReport(
                $user,
                $request->reported_user_id,
                $request->type,
                $request->reason,
                $request->comment,
                $request->booking_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Report submitted successfully. Our team will review it.',
                'data' => [
                    'report_id' => $report->id,
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
