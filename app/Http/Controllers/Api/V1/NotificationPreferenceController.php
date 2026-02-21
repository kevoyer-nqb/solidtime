<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationType;
use App\Http\Requests\V1\Notification\NotificationPreferenceUpdateRequest;
use App\Models\Member;
use App\Models\NotificationPreference;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationPreferenceController extends Controller
{
    /**
     * List all notification preferences for the current member.
     * Returns all notification types with the current preference for each.
     */
    public function index(Organization $organization): JsonResponse
    {
        $this->checkPermission($organization, 'notification-preferences:manage:own');

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $member = Member::query()
            ->where('user_id', $user->getKey())
            ->where('organization_id', $organization->getKey())
            ->firstOrFail();

        $preferences = NotificationPreference::query()
            ->where('member_id', $member->getKey())
            ->get()
            ->keyBy('notification_type');

        $result = [];
        foreach (NotificationType::cases() as $type) {
            $preference = $preferences->get($type->value);
            $result[] = [
                'type' => $type->value,
                'label' => $type->label(),
                'category' => $type->category(),
                'email_enabled' => $preference !== null
                    ? $preference->email_enabled
                    : $type->isDefaultEnabled(),
            ];
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Update a notification preference for the current member.
     */
    public function update(NotificationPreferenceUpdateRequest $request, Organization $organization): JsonResponse
    {
        $this->checkPermission($organization, 'notification-preferences:manage:own');

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $member = Member::query()
            ->where('user_id', $user->getKey())
            ->where('organization_id', $organization->getKey())
            ->firstOrFail();

        $validated = $request->validated();

        NotificationPreference::query()->updateOrCreate(
            [
                'member_id' => $member->getKey(),
                'notification_type' => $validated['notification_type'],
            ],
            [
                'email_enabled' => $validated['email_enabled'],
            ]
        );

        return response()->json(['success' => true]);
    }
}
