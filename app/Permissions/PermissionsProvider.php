<?php

declare(strict_types=1);

namespace App\Permissions;

interface PermissionsProvider
{
    /**
     * Return flat array of permission strings, e.g. ['charts:view:own', 'charts:view:all']
     *
     * @return array<string>
     */
    public function permissions(): array;

    /**
     * Return array keyed by Role enum value, each value is array of permission strings for that role.
     * Use ['*'] to grant all permissions from permissions().
     *
     * @return array<string, array<string>>
     */
    public function roles(): array;
}
