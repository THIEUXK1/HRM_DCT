<?php

namespace App\Http\Controllers\Api;

use App\Models\HrNotification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends ApiController
{
    /**
     * GET /notifications
     * Returns the current user's notifications, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = Auth::id();

        $query = HrNotification::forUser($userId)
            ->orderByDesc('created_at');

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        $notifications = $query->limit(50)->get();

        return $this->success([
            'items'        => $notifications,
            'unread_count' => NotificationService::unreadCount($userId),
        ]);
    }

    /**
     * GET /notifications/count
     * Lightweight badge count — called frequently.
     */
    public function count(): JsonResponse
    {
        return $this->success([
            'unread_count' => NotificationService::unreadCount(Auth::id()),
        ]);
    }

    /**
     * POST /notifications/read
     * Mark all or a specific notification as read.
     * Body: { "id": 5 }  — or omit id to mark all as read.
     */
    public function markRead(Request $request): JsonResponse
    {
        $notifId = $request->input('id');
        NotificationService::markRead(Auth::id(), $notifId ? (int) $notifId : null);

        return $this->success(['unread_count' => NotificationService::unreadCount(Auth::id())]);
    }

    /**
     * DELETE /notifications/{id}
     * Remove a notification.
     */
    public function destroy(HrNotification $notification): JsonResponse
    {
        if ($notification->user_id !== Auth::id()) {
            return $this->error('Không có quyền', 403);
        }

        $notification->delete();
        NotificationService::markRead(Auth::id()); // refresh cache

        return $this->noContent();
    }
}
