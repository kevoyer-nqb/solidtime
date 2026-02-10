<?php

declare(strict_types=1);

namespace App\Service;

use App\Models\User;
use App\Notifications\BaseNotification;

class NotificationService
{
    /**
     * Send a notification to a user.
     * Single entry point for all notification dispatching.
     */
    public function send(User $user, BaseNotification $notification): void
    {
        $user->notify($notification);
    }
}
