<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\SupportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SupportController extends Controller
{
    protected SupportService $supportService;

    public function __construct(SupportService $supportService)
    {
        $this->supportService = $supportService;
    }

    /**
     * List support tickets.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = SupportTicket::with(['user', 'city', 'booking']);

        // Apply city scoping for city admins
        if ($user->hasRole('city_admin') && !$user->hasRole('super_admin')) {
            $assignedCityIds = \App\Models\CityAdminAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->pluck('city_id');
            $query->whereIn('city_id', $assignedCityIds);
        }

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        $tickets = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'tickets' => $tickets->items()->map(function ($ticket) {
                    return [
                        'id' => $ticket->id,
                        'subject' => $ticket->subject,
                        'user' => [
                            'id' => $ticket->user->id,
                            'name' => $ticket->user->name,
                            'email' => $ticket->user->email,
                        ],
                        'city' => $ticket->city ? $ticket->city->name : null,
                        'booking_id' => $ticket->booking_id,
                        'status' => $ticket->status,
                        'priority' => $ticket->priority,
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
     * Get ticket details.
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        
        $query = SupportTicket::with(['messages.sender', 'user', 'city', 'booking'])
            ->where('id', $id);

        // Apply city scoping for city admins
        if ($user->hasRole('city_admin') && !$user->hasRole('super_admin')) {
            $assignedCityIds = \App\Models\CityAdminAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->pluck('city_id');
            $query->whereIn('city_id', $assignedCityIds);
        }

        $ticket = $query->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'ticket' => [
                    'id' => $ticket->id,
                    'subject' => $ticket->subject,
                    'user' => [
                        'id' => $ticket->user->id,
                        'name' => $ticket->user->name,
                        'email' => $ticket->user->email,
                    ],
                    'city' => $ticket->city ? $ticket->city->name : null,
                    'booking_id' => $ticket->booking_id,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
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
        
        $query = SupportTicket::where('id', $id);

        // Apply city scoping for city admins
        if ($user->hasRole('city_admin') && !$user->hasRole('super_admin')) {
            $assignedCityIds = \App\Models\CityAdminAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->pluck('city_id');
            $query->whereIn('city_id', $assignedCityIds);
        }

        $ticket = $query->firstOrFail();

        try {
            $message = $this->supportService->addReply($ticket, $user, $request->message, true);

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

    /**
     * Update ticket status.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $user = Auth::user();
        
        $query = SupportTicket::where('id', $id);

        // Apply city scoping for city admins
        if ($user->hasRole('city_admin') && !$user->hasRole('super_admin')) {
            $assignedCityIds = \App\Models\CityAdminAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->pluck('city_id');
            $query->whereIn('city_id', $assignedCityIds);
        }

        $ticket = $query->firstOrFail();

        try {
            $this->supportService->updateStatus($ticket, $request->status);

            return response()->json([
                'success' => true,
                'message' => 'Ticket status updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

