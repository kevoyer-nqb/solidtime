<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

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

        $perPage = min(max((int) $request->query('per_page', '15'), 1), 100);

        $notifications = $member->notifications()
            ->orderByDesc('created_at')
            ->paginate($perPage);

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
    public function markAsRead(Organization $organization, string $notification): JsonResponse
    {
        $this->checkPermission($organization, 'notifications:view');
        $member = $this->member($organization);

        /** @var DatabaseNotification|null $dbNotification */
        $dbNotification = $member->notifications()->where('id', $notification)->first();

        if ($dbNotification === null) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $dbNotification->markAsRead();

        return response()->json([
            'data' => $dbNotification,
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

        $member->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'data' => [
                'message' => 'All notifications marked as read.',
            ],
        ]);
    }
}
