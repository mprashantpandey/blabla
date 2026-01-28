<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\SystemSetting;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

class SupportService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Create a new support ticket.
     */
    public function createTicket(
        User $user,
        string $subject,
        string $message,
        ?int $bookingId = null,
        ?int $cityId = null,
        string $priority = 'medium'
    ): SupportTicket {
        if (!SystemSetting::get('support.enabled', true)) {
            throw new \Exception('Support system is disabled.');
        }

        // Auto-assign city if enabled and not provided
        if (!$cityId && SystemSetting::get('support.auto_assign_city', true)) {
            $cityId = $user->city_id;
        }

        // Get default priority if not provided
        if ($priority === 'medium') {
            $priority = SystemSetting::get('support.default_priority', 'medium');
        }

        DB::beginTransaction();
        try {
            $ticket = SupportTicket::create([
                'user_id' => $user->id,
                'city_id' => $cityId,
                'booking_id' => $bookingId,
                'subject' => $subject,
                'status' => 'open',
                'priority' => $priority,
            ]);

            // Create initial message
            SupportMessage::create([
                'support_ticket_id' => $ticket->id,
                'sender_type' => 'user',
                'sender_user_id' => $user->id,
                'message' => $message,
                'created_at' => now(),
            ]);

            DB::commit();

            // Notify admins
            $this->notifyAdminsNewTicket($ticket);

            return $ticket;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Add a reply to a ticket.
     */
    public function addReply(
        SupportTicket $ticket,
        User $sender,
        string $message,
        bool $isAdmin = false
    ): SupportMessage {
        DB::beginTransaction();
        try {
            $supportMessage = SupportMessage::create([
                'support_ticket_id' => $ticket->id,
                'sender_type' => $isAdmin ? 'admin' : 'user',
                'sender_user_id' => $sender->id,
                'message' => $message,
                'created_at' => now(),
            ]);

            // Update ticket status if needed
            if ($isAdmin && $ticket->status === 'open') {
                $ticket->status = 'in_progress';
                $ticket->save();
            }

            DB::commit();

            // Notify the other party
            if ($isAdmin) {
                $this->notifyUserReply($ticket, $supportMessage);
            } else {
                $this->notifyAdminsReply($ticket, $supportMessage);
            }

            return $supportMessage;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update ticket status.
     */
    public function updateStatus(SupportTicket $ticket, string $status): void
    {
        $allowedStatuses = ['open', 'in_progress', 'resolved', 'closed'];
        if (!in_array($status, $allowedStatuses)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        $ticket->status = $status;
        $ticket->save();

        // Notify user if status changed to resolved or closed
        if (in_array($status, ['resolved', 'closed'])) {
            $this->notificationService->sendToUser(
                $ticket->user,
                'Support Ticket Updated',
                "Your support ticket '{$ticket->subject}' has been marked as {$status}.",
                ['type' => 'support_ticket_status', 'ticket_id' => $ticket->id, 'status' => $status]
            );
        }
    }

    /**
     * Notify admins about new ticket.
     */
    protected function notifyAdminsNewTicket(SupportTicket $ticket): void
    {
        $admins = User::role(['super_admin', 'city_admin', 'support_staff'])->get();

        foreach ($admins as $admin) {
            // City admins only get notified for their cities
            if ($admin->hasRole('city_admin') && !$admin->hasRole('super_admin')) {
                if ($ticket->city_id) {
                    $assignedCityIds = \App\Models\CityAdminAssignment::where('user_id', $admin->id)
                        ->where('is_active', true)
                        ->pluck('city_id');
                    if (!in_array($ticket->city_id, $assignedCityIds->toArray())) {
                        continue;
                    }
                } else {
                    continue; // City admin can't see tickets without city
                }
            }

            $this->notificationService->sendToUser(
                $admin,
                'New Support Ticket',
                "New support ticket: {$ticket->subject}",
                ['type' => 'support_ticket_new', 'ticket_id' => $ticket->id]
            );
        }
    }

    /**
     * Notify user about admin reply.
     */
    protected function notifyUserReply(SupportTicket $ticket, SupportMessage $message): void
    {
        $this->notificationService->sendToUser(
            $ticket->user,
            'Support Ticket Reply',
            "You have a new reply on ticket: {$ticket->subject}",
            ['type' => 'support_ticket_reply', 'ticket_id' => $ticket->id, 'message_id' => $message->id]
        );
    }

    /**
     * Notify admins about user reply.
     */
    protected function notifyAdminsReply(SupportTicket $ticket, SupportMessage $message): void
    {
        $admins = User::role(['super_admin', 'city_admin', 'support_staff'])->get();

        foreach ($admins as $admin) {
            // City admins only get notified for their cities
            if ($admin->hasRole('city_admin') && !$admin->hasRole('super_admin')) {
                if ($ticket->city_id) {
                    $assignedCityIds = \App\Models\CityAdminAssignment::where('user_id', $admin->id)
                        ->where('is_active', true)
                        ->pluck('city_id');
                    if (!in_array($ticket->city_id, $assignedCityIds->toArray())) {
                        continue;
                    }
                } else {
                    continue;
                }
            }

            $this->notificationService->sendToUser(
                $admin,
                'Support Ticket Reply',
                "New reply on ticket: {$ticket->subject}",
                ['type' => 'support_ticket_reply', 'ticket_id' => $ticket->id, 'message_id' => $message->id]
            );
        }
    }
}

