<?php

declare(strict_types=1);

namespace App\Permissions;

use App\Enums\Role;

class NotificationPermissions implements PermissionsProvider
{
    /**
     * @return array<string>
     */
    public function permissions(): array
    {
        return [
            'notifications:view:own',
            'notification-preferences:manage:own',
        ];
    }

    /**
     * @return array<string, array<string>>
     */
    public function roles(): array
    {
        return [
            Role::Owner->value => ['*'],
            Role::Admin->value => ['*'],
            Role::Manager->value => ['*'],
            Role::Employee->value => ['*'],
        ];
    }
}
