<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case Test = 'test';

    public function isDefaultEnabled(): bool
    {
        return match ($this) {
            self::Test => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Test => 'Test Notification',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::Test => 'informational',
        };
    }
}
