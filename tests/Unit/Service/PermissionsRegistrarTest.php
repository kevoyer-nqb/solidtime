<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Enums\Role;
use App\Permissions\CorePermissions;
use App\Permissions\NotificationPermissions;
use App\Permissions\PermissionsProvider;
use App\Permissions\PermissionsRegistrar;
use Laravel\Jetstream\Jetstream;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(PermissionsRegistrar::class)]
class PermissionsRegistrarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset Jetstream permissions and roles before each test
        Jetstream::$permissions = [];
        Jetstream::$roles = [];
    }

    public function test_registrar_collects_permissions_from_all_providers(): void
    {
        // Arrange
        $registrar = new PermissionsRegistrar();
        $registrar
            ->register(CorePermissions::class)
            ->register(NotificationPermissions::class);

        // Act
        $registrar->boot();

        // Assert
        $corePerms = (new CorePermissions())->permissions();
        $notifPerms = (new NotificationPermissions())->permissions();
        $allExpected = array_values(array_unique(array_merge($corePerms, $notifPerms)));

        $this->assertEqualsCanonicalizing($allExpected, Jetstream::$permissions);
    }

    public function test_registrar_merges_roles_correctly(): void
    {
        // Arrange
        $registrar = new PermissionsRegistrar();
        $registrar
            ->register(CorePermissions::class)
            ->register(NotificationPermissions::class);

        // Act
        $registrar->boot();

        // Assert - Owner should have all core + notification permissions
        $ownerRole = Jetstream::findRole(Role::Owner->value);
        $this->assertNotNull($ownerRole);
        $this->assertContains('charts:view:own', $ownerRole->permissions);
        $this->assertContains('notifications:view:own', $ownerRole->permissions);
        $this->assertContains('notification-preferences:manage:own', $ownerRole->permissions);
        $this->assertContains('billing', $ownerRole->permissions);

        // Employee should have limited core + notification permissions
        $employeeRole = Jetstream::findRole(Role::Employee->value);
        $this->assertNotNull($employeeRole);
        $this->assertContains('charts:view:own', $employeeRole->permissions);
        $this->assertContains('notifications:view:own', $employeeRole->permissions);
        $this->assertNotContains('billing', $employeeRole->permissions);
        $this->assertNotContains('organizations:delete', $employeeRole->permissions);

        // Placeholder should have no permissions
        $placeholderRole = Jetstream::findRole(Role::Placeholder->value);
        $this->assertNotNull($placeholderRole);
        $this->assertEmpty($placeholderRole->permissions);
    }

    public function test_wildcard_expands_to_all_provider_permissions(): void
    {
        // Arrange - Create a provider that uses wildcard
        $testProvider = new class implements PermissionsProvider
        {
            public function permissions(): array
            {
                return ['test:action:one', 'test:action:two', 'test:action:three'];
            }

            public function roles(): array
            {
                return [
                    'owner' => ['*'],
                    'employee' => ['test:action:one'],
                ];
            }
        };

        // We need to register the anonymous class; create a named wrapper
        $providerClass = get_class($testProvider);

        $registrar = new PermissionsRegistrar();
        $registrar->register($providerClass);
        $registrar->boot();

        // Assert - Owner got all 3 permissions via wildcard
        $ownerRole = Jetstream::findRole('owner');
        $this->assertNotNull($ownerRole);
        $this->assertContains('test:action:one', $ownerRole->permissions);
        $this->assertContains('test:action:two', $ownerRole->permissions);
        $this->assertContains('test:action:three', $ownerRole->permissions);

        // Employee got only the one specified
        $employeeRole = Jetstream::findRole('employee');
        $this->assertNotNull($employeeRole);
        $this->assertContains('test:action:one', $employeeRole->permissions);
        $this->assertNotContains('test:action:two', $employeeRole->permissions);
    }

    public function test_existing_permissions_unchanged_after_refactor(): void
    {
        // Arrange - Expected owner permissions (snapshot from original JetstreamServiceProvider)
        $expectedOwnerPermissions = [
            'charts:view:own',
            'charts:view:all',
            'projects:view',
            'projects:view:all',
            'projects:create',
            'projects:update',
            'projects:delete',
            'project-members:view',
            'project-members:create',
            'project-members:update',
            'project-members:delete',
            'tasks:view',
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
            'time-entries:view:own',
            'time-entries:create:own',
            'time-entries:update:own',
            'time-entries:delete:own',
            'tags:view',
            'tags:create',
            'tags:update',
            'tags:delete',
            'clients:view',
            'clients:view:all',
            'clients:create',
            'clients:update',
            'clients:delete',
            'organizations:view',
            'organizations:update',
            'organizations:delete',
            'import',
            'export',
            'invitations:view',
            'invitations:create',
            'invitations:resend',
            'invitations:remove',
            'members:view',
            'members:invite-placeholder',
            'members:change-ownership',
            'members:make-placeholder',
            'members:merge-into',
            'members:update',
            'members:delete',
            'billing',
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
            'notifications:view:own',
            'notification-preferences:manage:own',
        ];

        $expectedEmployeePermissions = [
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
            'notifications:view:own',
            'notification-preferences:manage:own',
        ];

        // Act - Boot the registrar as JetstreamServiceProvider does
        $registrar = new PermissionsRegistrar();
        $registrar
            ->register(CorePermissions::class)
            ->register(NotificationPermissions::class);
        $registrar->boot();

        // Assert
        $ownerRole = Jetstream::findRole(Role::Owner->value);
        $this->assertEqualsCanonicalizing($expectedOwnerPermissions, $ownerRole->permissions);

        $employeeRole = Jetstream::findRole(Role::Employee->value);
        $this->assertEqualsCanonicalizing($expectedEmployeePermissions, $employeeRole->permissions);

        $placeholderRole = Jetstream::findRole(Role::Placeholder->value);
        $this->assertEmpty($placeholderRole->permissions);
    }
}
