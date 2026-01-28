<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;
use App\Models\SystemSetting;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Firebase\Exception\MessagingException;

class NotificationService
{
    protected ?\Kreait\Firebase\Messaging $messaging = null;

    /**
     * Initialize Firebase Messaging.
     */
    protected function initializeFirebase(): void
    {
        if ($this->messaging !== null) {
            return;
        }

        $pushEnabled = SystemSetting::get('push.enabled', false);
        if (!$pushEnabled) {
            return;
        }

        $projectId = SystemSetting::get('firebase.project_id');
        $serviceAccountJson = SystemSetting::get('firebase.service_account_json');

        if (!$projectId || !$serviceAccountJson) {
            Log::warning('Firebase not configured: missing project_id or service_account_json');
            return;
        }

        try {
            // Decrypt if encrypted
            try {
                $serviceAccountJson = \Illuminate\Support\Facades\Crypt::decryptString($serviceAccountJson);
            } catch (\Exception $e) {
                // If decryption fails, assume it's not encrypted (backward compatibility)
            }

            $serviceAccount = json_decode($serviceAccountJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid Firebase service account JSON');
                return;
            }

            $factory = (new Factory)->withServiceAccount($serviceAccount);
            $this->messaging = $factory->createMessaging();
        } catch (\Exception $e) {
            Log::error('Failed to initialize Firebase', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send notification to user.
     */
    public function sendToUser(
        User $user,
        string $title,
        string $body,
        array $data = [],
        bool $saveToDatabase = true
    ): array {
        $results = [
            'database' => false,
            'push' => false,
            'devices_notified' => 0,
        ];

        // Save to database
        if ($saveToDatabase) {
            try {
                $user->notify(new \App\Notifications\GenericNotification($title, $body, $data));
                $results['database'] = true;
            } catch (\Exception $e) {
                Log::error('Failed to save notification to database', ['error' => $e->getMessage()]);
            }
        }

        // Send push notification
        $pushEnabled = SystemSetting::get('push.enabled', false);
        if ($pushEnabled && $this->messaging === null) {
            $this->initializeFirebase();
        }

        if ($pushEnabled && $this->messaging !== null) {
            $devices = UserDevice::where('user_id', $user->id)
                ->whereNotNull('fcm_token')
                ->get();

            foreach ($devices as $device) {
                try {
                    $message = CloudMessage::withTarget('token', $device->fcm_token)
                        ->withNotification(
                            FirebaseNotification::create($title, $body)
                        )
                        ->withData($data);

                    $this->messaging->send($message);
                    $results['devices_notified']++;
                    $results['push'] = true;
                } catch (MessagingException $e) {
                    Log::warning('Failed to send push to device', [
                        'device_id' => $device->id,
                        'error' => $e->getMessage(),
                    ]);

                    // If token is invalid, remove it
                    if (str_contains($e->getMessage(), 'invalid') || str_contains($e->getMessage(), 'not-found')) {
                        $device->update(['fcm_token' => null]);
                    }
                } catch (\Exception $e) {
                    Log::error('Unexpected error sending push', [
                        'device_id' => $device->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $results;
    }

    /**
     * Send notification to multiple users.
     */
    public function sendToUsers(
        array $users,
        string $title,
        string $body,
        array $data = []
    ): array {
        $results = [
            'total' => count($users),
            'success' => 0,
            'failed' => 0,
        ];

        foreach ($users as $user) {
            $result = $this->sendToUser($user, $title, $body, $data);
            if ($result['database'] || $result['push']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Send chat notification.
     */
    public function sendChatNotification(User $receiver, \App\Models\Message $message): array
    {
        // Check if notifications are enabled
        if (!SystemSetting::get('notifications.enabled', true)) {
            return ['database' => false, 'push' => false, 'devices_notified' => 0];
        }

        // Check if chat push is enabled
        $chatPushEnabled = SystemSetting::get('notifications.chat_push', true);
        $dbEnabled = SystemSetting::get('notifications.db_enabled', true);
        $pushEnabled = SystemSetting::get('notifications.push_enabled', true) && $chatPushEnabled;

        // Check quiet hours
        if ($this->isQuietHours()) {
            $pushEnabled = false;
        }

        $sender = $message->sender;
        $title = $sender ? "New message from {$sender->name}" : 'New message';
        $body = strlen($message->body) > 100 ? substr($message->body, 0, 100) . '...' : $message->body;

        $data = [
            'type' => 'chat',
            'conversation_id' => $message->conversation_id,
            'booking_id' => $message->conversation->booking_id,
            'message_id' => $message->id,
        ];

        return $this->sendToUser($receiver, $title, $body, $data, $dbEnabled);
    }

    /**
     * Send booking notification.
     */
    public function sendBookingNotification(string $type, \App\Models\Booking $booking, ?string $customMessage = null): array
    {
        // Check if notifications are enabled
        if (!SystemSetting::get('notifications.enabled', true)) {
            return ['database' => false, 'push' => false, 'devices_notified' => 0];
        }

        // Check if booking push is enabled
        $bookingPushEnabled = SystemSetting::get('notifications.booking_push', true);
        $dbEnabled = SystemSetting::get('notifications.db_enabled', true);
        $pushEnabled = SystemSetting::get('notifications.push_enabled', true) && $bookingPushEnabled;

        // Check quiet hours
        if ($this->isQuietHours()) {
            $pushEnabled = false;
        }

        $title = $this->getBookingNotificationTitle($type);
        $body = $customMessage ?? $this->getBookingNotificationBody($type, $booking);

        $data = [
            'type' => 'booking',
            'booking_id' => $booking->id,
            'status' => $booking->status,
        ];

        // Determine receiver
        $receiver = match ($type) {
            'requested' => $booking->driverProfile->user,
            'accepted', 'rejected', 'cancelled', 'confirmed' => $booking->rider,
            default => null,
        };

        if (!$receiver) {
            return ['database' => false, 'push' => false, 'devices_notified' => 0];
        }

        return $this->sendToUser($receiver, $title, $body, $data, $dbEnabled);
    }

    /**
     * Check if current time is within quiet hours.
     */
    protected function isQuietHours(): bool
    {
        $quietHoursEnabled = SystemSetting::get('notifications.quiet_hours_enabled', false);
        if (!$quietHoursEnabled) {
            return false;
        }

        $start = SystemSetting::get('notifications.quiet_hours_start', '22:00');
        $end = SystemSetting::get('notifications.quiet_hours_end', '08:00');

        $now = now();
        $startTime = \Carbon\Carbon::parse($start);
        $endTime = \Carbon\Carbon::parse($end);

        // Handle overnight quiet hours
        if ($startTime->greaterThan($endTime)) {
            return $now->greaterThanOrEqualTo($startTime) || $now->lessThan($endTime);
        }

        return $now->greaterThanOrEqualTo($startTime) && $now->lessThan($endTime);
    }

    /**
     * Get booking notification title.
     */
    protected function getBookingNotificationTitle(string $type): string
    {
        return match ($type) {
            'requested' => 'New Booking Request',
            'accepted' => 'Booking Accepted',
            'rejected' => 'Booking Rejected',
            'cancelled' => 'Booking Cancelled',
            'confirmed' => 'Booking Confirmed',
            'completed' => 'Ride Completed',
            default => 'Booking Update',
        };
    }

    /**
     * Get booking notification body.
     */
    protected function getBookingNotificationBody(string $type, \App\Models\Booking $booking): string
    {
        return match ($type) {
            'requested' => "You have a new booking request for {$booking->seats_requested} seat(s).",
            'accepted' => 'Your booking request has been accepted by the driver.',
            'rejected' => 'Your booking request has been rejected by the driver.',
            'cancelled' => 'Your booking has been cancelled.',
            'confirmed' => 'Your booking has been confirmed. Payment received.',
            'completed' => 'Your ride has been completed.',
            default => 'Your booking status has been updated.',
        };
    }
}

