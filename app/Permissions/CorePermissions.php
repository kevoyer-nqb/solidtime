<?php

declare(strict_types=1);

namespace App\Permissions;

use App\Enums\Role;
use Laravel\Jetstream\Jetstream;

/**
 * Core permissions for existing Solidtime features.
 * Extracted from JetstreamServiceProvider to follow the modular pattern (SF-08).
 *
 * Role hierarchy is built compositionally: Employee -> Manager -> Admin -> Owner.
 * Each higher role inherits all permissions from the role below and adds its own.
 */
class CorePermissions
{
    /**
     * @return array<string>
     */
    private static function employeePermissions(): array
    {
        return [
            'charts:view:own',
            'projects:view',
            'tags:view',
            'tasks:view',
            'clients:view',
            'time-entries:view:own',
            'time-entries:create:own',
            'time-entries:update:own',
            'time-entries:delete:own',
            'organizations:view',
            'notifications:view',
        ];
    }

    /**
     * @return array<string>
     */
    private static function managerPermissions(): array
    {
        return array_merge(self::employeePermissions(), [
            'charts:view:all',
            'projects:view:all',
            'projects:create',
            'projects:update',
            'projects:delete',
            'project-members:view',
            'project-members:create',
            'project-members:update',
            'project-members:delete',
            'tasks:view:all',
            'tasks:create',
            'tasks:create:all',
            'tasks:update',
            'tasks:update:all',
            'tasks:delete',
            'tasks:delete:all',
            'time-entries:view:all',
            'time-entries:create:all',
            'time-entries:update:all',
            'time-entries:delete:all',
            'tags:create',
            'tags:update',
            'tags:delete',
            'clients:view:all',
            'clients:create',
            'clients:update',
            'clients:delete',
            'invitations:view',
            'members:view',
            'reports:view',
            'reports:create',
            'reports:update',
            'reports:delete',
            'invoices:view',
            'invoices:create',
            'invoices:update',
            'invoices:download',
            'invoices:delete',
            'invoice-settings:view',
            'invoice-settings:update',
        ]);
    }

    /**
     * @return array<string>
     */
    private static function adminPermissions(): array
    {
        return array_merge(self::managerPermissions(), [
            'organizations:update',
            'import',
            'export',
            'invitations:create',
            'invitations:resend',
            'invitations:remove',
            'members:invite-placeholder',
            'members:make-placeholder',
            'members:merge-into',
            'members:update',
            'members:delete',
            'notifications:manage',
        ]);
    }

    /**
     * @return array<string>
     */
    private static function ownerPermissions(): array
    {
        return array_merge(self::adminPermissions(), [
            'organizations:delete',
            'members:change-ownership',
            'billing',
        ]);
    }

    public static function register(): void
    {
        Jetstream::role(Role::Owner->value, 'Owner', self::ownerPermissions())
            ->description('Owner users can perform any action. There is only one owner per organization.');

        Jetstream::role(Role::Admin->value, 'Administrator', self::adminPermissions())
            ->description('Administrator users can perform any action, except accessing the billing dashboard.');

        Jetstream::role(Role::Manager->value, 'Manager', self::managerPermissions())
            ->description('Managers have full access to all projects, time entries, etc. but cannot manage the organization (add/remove members, edit the organization, etc.).');

        Jetstream::role(Role::Employee->value, 'Employee', self::employeePermissions())
            ->description('Employees have the ability to read, create, and update their own time entries, they can see the projects that they are members of and the clients they are assigned to.');

        Jetstream::role(Role::Placeholder->value, 'Placeholder', [])
            ->description('Placeholders are used for importing data. They cannot log in and have no permissions.');
    }
}
