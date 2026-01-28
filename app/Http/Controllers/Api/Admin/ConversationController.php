<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ConversationController extends Controller
{
    /**
     * List conversations (read-only for admin).
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Apply city scoping for city admins
        $query = Conversation::with(['booking', 'rider', 'driver', 'ride']);

        if ($user->hasRole('city_admin') && !$user->hasRole('super_admin')) {
            $cityIds = $user->cities->pluck('id')->toArray();
            $query->whereHas('booking', function ($q) use ($cityIds) {
                $q->whereIn('city_id', $cityIds);
            });
        }

        // Filters
        if ($request->has('city_id')) {
            $query->whereHas('booking', function ($q) use ($request) {
                $q->where('city_id', $request->city_id);
            });
        }

        if ($request->has('booking_id')) {
            $query->where('booking_id', $request->booking_id);
        }

        if ($request->has('user_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('rider_user_id', $request->user_id)
                  ->orWhere('driver_user_id', $request->user_id);
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $conversations = $query->orderBy('last_message_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'conversations' => $conversations->items(),
                'pagination' => [
                    'current_page' => $conversations->currentPage(),
                    'last_page' => $conversations->lastPage(),
                    'per_page' => $conversations->perPage(),
                    'total' => $conversations->total(),
                ],
            ],
        ]);
    }

    /**
     * Get conversation details (read-only).
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        
        $conversation = Conversation::with([
            'booking',
            'rider',
            'driver',
            'ride',
            'messages.sender',
            'messages.reads',
        ])->findOrFail($id);

        // Check city scope for city admins
        if ($user->hasRole('city_admin') && !$user->hasRole('super_admin')) {
            $cityIds = $user->cities->pluck('id')->toArray();
            if (!in_array($conversation->booking->city_id, $cityIds)) {
                abort(403, 'Unauthorized');
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => [
                    'id' => $conversation->id,
                    'booking_id' => $conversation->booking_id,
                    'status' => $conversation->status,
                    'last_message_at' => $conversation->last_message_at,
                    'rider' => [
                        'id' => $conversation->rider->id,
                        'name' => $conversation->rider->name,
                        'phone' => $conversation->rider->phone,
                    ],
                    'driver' => [
                        'id' => $conversation->driver->id,
                        'name' => $conversation->driver->name,
                        'phone' => $conversation->driver->phone,
                    ],
                    'booking' => [
                        'id' => $conversation->booking->id,
                        'status' => $conversation->booking->status,
                    ],
                ],
                'messages' => $conversation->allMessages()->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'sender' => $message->sender ? [
                            'id' => $message->sender->id,
                            'name' => $message->sender->name,
                        ] : null,
                        'message_type' => $message->message_type,
                        'body' => $message->body,
                        'is_system' => $message->isSystem(),
                        'created_at' => $message->created_at,
                        'read_count' => $message->reads->count(),
                    ];
                }),
            ],
        ]);
    }
}
