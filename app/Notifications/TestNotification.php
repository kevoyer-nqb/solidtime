<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;

class TestNotification extends BaseNotification
{
    public function __construct(
        private readonly string $organizationId,
        private readonly string $title = 'Test Notification',
        private readonly string $body = 'This is a test notification.',
        private readonly ?string $actionUrl = null,
    ) {}

    public function getNotificationType(): NotificationType
    {
        return NotificationType::Test;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getActionUrl(): ?string
    {
        return $this->actionUrl;
    }
}
