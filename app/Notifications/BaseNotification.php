<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Member;
use App\Models\NotificationPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract public function getNotificationType(): NotificationType;

    abstract public function getOrganizationId(): string;

    abstract public function getTitle(): string;

    abstract public function getBody(): string;

    abstract public function getActionUrl(): ?string;

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->shouldSendEmail($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Determine if an email should be sent for this notification.
     */
    public function shouldSendEmail(object $notifiable): bool
    {
        $member = Member::query()
            ->where('user_id', $notifiable->getKey())
            ->where('organization_id', $this->getOrganizationId())
            ->first();

        if ($member === null) {
            return $this->getNotificationType()->isDefaultEnabled();
        }

        $preference = NotificationPreference::query()
            ->where('member_id', $member->getKey())
            ->where('notification_type', $this->getNotificationType()->value)
            ->first();

        if ($preference === null) {
            return $this->getNotificationType()->isDefaultEnabled();
        }

        return $preference->email_enabled;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'organization_id' => $this->getOrganizationId(),
            'type' => $this->getNotificationType()->value,
            'title' => $this->getTitle(),
            'body' => $this->getBody(),
            'action_url' => $this->getActionUrl(),
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->getTitle())
            ->markdown('emails.notification', [
                'title' => $this->getTitle(),
                'body' => $this->getBody(),
                'actionUrl' => $this->getActionUrl(),
            ]);

        return $message;
    }
}
