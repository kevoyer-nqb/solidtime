<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * List notifications for the current user in the given organization.
     */
    public function index(Organization $organization): JsonResponse
    {
        $this->checkPermission($organization, 'notifications:view:own');

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $notifications = $user->notifications()
            ->whereRaw("data::jsonb->>'organization_id' = ?", [$organization->getKey()])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($notifications);
    }

    /**
     * Get the unread notification count for the current user in the given organization.
     */
    public function unreadCount(Organization $organization): JsonResponse
    {
        $this->checkPermission($organization, 'notifications:view:own');

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $count = $user->unreadNotifications()
            ->whereRaw("data::jsonb->>'organization_id' = ?", [$organization->getKey()])
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Organization $organization, string $notificationId): JsonResponse
    {
        $this->checkPermission($organization, 'notifications:view:own');

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $notification = $user->notifications()->findOrFail($notificationId);
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Mark all unread notifications as read for the current organization.
     */
    public function markAllAsRead(Organization $organization): JsonResponse
    {
        $this->checkPermission($organization, 'notifications:view:own');

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $user->unreadNotifications()
            ->whereRaw("data::jsonb->>'organization_id' = ?", [$organization->getKey()])
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
