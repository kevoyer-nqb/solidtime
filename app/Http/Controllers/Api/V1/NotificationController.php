<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Member;
use App\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * List notifications for the current member (paginated).
     *
     * @throws AuthorizationException
     */
    public function index(Organization $organization, Request $request): JsonResponse
    {
        $this->checkPermission($organization, 'notifications:view');
        $member = $this->member($organization);

        $notifications = $member->notifications()
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', '15'));

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Get count of unread notifications.
     *
     * @throws AuthorizationException
     */
    public function unreadCount(Organization $organization): JsonResponse
    {
        $this->checkPermission($organization, 'notifications:view');
        $member = $this->member($organization);

        return response()->json([
            'data' => [
                'unread_count' => $member->unreadNotifications()->count(),
            ],
        ]);
    }

    /**
     * Mark a single notification as read.
     *
     * @throws AuthorizationException
     */
    public function markAsRead(Organization $organization, string $notificationId): JsonResponse
    {
        $this->checkPermission($organization, 'notifications:view');
        $member = $this->member($organization);

        /** @var DatabaseNotification|null $notification */
        $notification = $member->notifications()->where('id', $notificationId)->first();

        if ($notification === null) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'data' => $notification,
        ]);
    }

    /**
     * Mark all notifications as read.
     *
     * @throws AuthorizationException
     */
    public function markAllAsRead(Organization $organization): JsonResponse
    {
        $this->checkPermission($organization, 'notifications:view');
        $member = $this->member($organization);

        $member->unreadNotifications->markAsRead();

        return response()->json([
            'data' => [
                'message' => 'All notifications marked as read.',
            ],
        ]);
    }
}
