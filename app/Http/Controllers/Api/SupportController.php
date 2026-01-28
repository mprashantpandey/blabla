<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SupportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SupportController extends Controller
{
    protected SupportService $supportService;

    public function __construct(SupportService $supportService)
    {
        $this->supportService = $supportService;
    }

    /**
     * Get user's support tickets.
     */
    public function myTickets(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tickets = $user->supportTickets()
            ->with(['city', 'booking'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'tickets' => $tickets->items()->map(function ($ticket) {
                    return [
                        'id' => $ticket->id,
                        'subject' => $ticket->subject,
                        'status' => $ticket->status,
                        'priority' => $ticket->priority,
                        'city' => $ticket->city ? $ticket->city->name : null,
                        'booking_id' => $ticket->booking_id,
                        'created_at' => $ticket->created_at,
                        'updated_at' => $ticket->updated_at,
                    ];
                }),
                'pagination' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'total' => $tickets->total(),
                ],
            ],
        ]);
    }

    /**
     * Create a new support ticket.
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'booking_id' => 'nullable|exists:bookings,id',
            'priority' => 'nullable|in:low,medium,high',
        ]);

        $user = Auth::user();
        $bookingId = $request->booking_id;
        $cityId = null;

        // Validate booking belongs to user if provided
        if ($bookingId) {
            $booking = \App\Models\Booking::where('id', $bookingId)
                ->where('rider_user_id', $user->id)
                ->first();
            
            if (!$booking) {
                throw ValidationException::withMessages(['booking_id' => ['Invalid booking.']]);
            }
            
            $cityId = $booking->city_id;
        } else {
            $cityId = $user->city_id;
        }

        try {
            $ticket = $this->supportService->createTicket(
                $user,
                $request->subject,
                $request->message,
                $bookingId,
                $cityId,
                $request->priority ?? 'medium'
            );

            return response()->json([
                'success' => true,
                'message' => 'Support ticket created successfully',
                'data' => [
                    'ticket' => [
                        'id' => $ticket->id,
                        'subject' => $ticket->subject,
                        'status' => $ticket->status,
                        'priority' => $ticket->priority,
                        'created_at' => $ticket->created_at,
                    ],
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get ticket details.
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        $ticket = \App\Models\SupportTicket::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['messages.sender', 'city', 'booking'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'ticket' => [
                    'id' => $ticket->id,
                    'subject' => $ticket->subject,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'city' => $ticket->city ? $ticket->city->name : null,
                    'booking_id' => $ticket->booking_id,
                    'created_at' => $ticket->created_at,
                    'updated_at' => $ticket->updated_at,
                    'messages' => $ticket->messages->map(function ($message) {
                        return [
                            'id' => $message->id,
                            'sender_type' => $message->sender_type,
                            'sender_name' => $message->sender ? $message->sender->name : 'System',
                            'message' => $message->message,
                            'created_at' => $message->created_at,
                        ];
                    }),
                ],
            ],
        ]);
    }

    /**
     * Reply to a ticket.
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $user = Auth::user();
        $ticket = \App\Models\SupportTicket::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (in_array($ticket->status, ['resolved', 'closed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot reply to a resolved or closed ticket.',
            ], 422);
        }

        try {
            $message = $this->supportService->addReply($ticket, $user, $request->message, false);

            return response()->json([
                'success' => true,
                'message' => 'Reply sent successfully',
                'data' => [
                    'message' => [
                        'id' => $message->id,
                        'sender_type' => $message->sender_type,
                        'message' => $message->message,
                        'created_at' => $message->created_at,
                    ],
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

