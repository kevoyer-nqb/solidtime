<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Member;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Organization $organization;

    public function __construct(Organization $organization)
    {
        $this->organization = $organization;
    }

    /**
     * Get the notification's delivery channels.
     * Respects per-member notification_preferences.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable instanceof Member) {
            $preferences = $notifiable->notification_preferences;
            $type = static::class;

            if ($this->isEmailEnabled($preferences, $type)) {
                $channels[] = 'mail';
            }
        } else {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the array representation for the database notification.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(object $notifiable): array;

    /**
     * Get the mail representation of the notification.
     */
    abstract public function toMail(object $notifiable): MailMessage;

    /**
     * Check if email is enabled for this notification type based on member preferences.
     *
     * @param  array<string, mixed>|null  $preferences
     */
    protected function isEmailEnabled(?array $preferences, string $notificationType): bool
    {
        if ($preferences === null) {
            return true;
        }

        if (isset($preferences['email_enabled']) && $preferences['email_enabled'] === false) {
            return false;
        }

        $shortType = class_basename($notificationType);
        if (isset($preferences['types'][$shortType]['email']) && $preferences['types'][$shortType]['email'] === false) {
            return false;
        }

        return true;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }
}
