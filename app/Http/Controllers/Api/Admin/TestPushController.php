<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\TestPushRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;

class TestPushController extends BaseController
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Send test push notification.
     */
    public function send(TestPushRequest $request): JsonResponse
    {
        $user = User::findOrFail($request->user_id);

        $result = $this->notificationService->sendToUser(
            $user,
            $request->title,
            $request->body,
            ['type' => 'test', 'test_id' => uniqid()]
        );

        return $this->success([
            'database_sent' => $result['database'],
            'push_sent' => $result['push'],
            'devices_notified' => $result['devices_notified'],
        ], 'Test notification sent');
    }
}
