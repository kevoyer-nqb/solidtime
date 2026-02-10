<?php

declare(strict_types=1);

namespace App\Permissions;

use App\Enums\Role;
use Laravel\Jetstream\Jetstream;

class PermissionsRegistrar
{
    /**
     * @var array<class-string<PermissionsProvider>>
     */
    private array $providers = [];

    /**
     * Register a permissions provider class.
     *
     * @param  class-string<PermissionsProvider>  $providerClass
     */
    public function register(string $providerClass): self
    {
        $this->providers[] = $providerClass;

        return $this;
    }

    /**
     * Boot all registered providers: collect permissions, merge roles, register with Jetstream.
     */
    public function boot(): void
    {
        $allPermissions = [];
        /** @var array<string, array<string>> $rolePermissions */
        $rolePermissions = [];

        foreach ($this->providers as $providerClass) {
            /** @var PermissionsProvider $provider */
            $provider = new $providerClass();
            $providerPermissions = $provider->permissions();
            $allPermissions = array_merge($allPermissions, $providerPermissions);

            foreach ($provider->roles() as $roleValue => $permissions) {
                if (! isset($rolePermissions[$roleValue])) {
                    $rolePermissions[$roleValue] = [];
                }

                // Handle wildcard: expand ['*'] to all permissions from this provider
                if ($permissions === ['*']) {
                    $permissions = $providerPermissions;
                }

                $rolePermissions[$roleValue] = array_merge($rolePermissions[$roleValue], $permissions);
            }
        }

        // Deduplicate
        $allPermissions = array_values(array_unique($allPermissions));
        foreach ($rolePermissions as $roleValue => $permissions) {
            $rolePermissions[$roleValue] = array_values(array_unique($permissions));
        }

        // Register all permissions with Jetstream
        Jetstream::permissions($allPermissions);

        // Register each role with merged permissions
        $roleDescriptions = [
            Role::Owner->value => 'Owner users can perform any action. There is only one owner per organization.',
            Role::Admin->value => 'Administrator users can perform any action, except accessing the billing dashboard.',
            Role::Manager->value => 'Managers have full access to all projects, time entries, ect. but cannot manage the organization (add/remove member, edit the organization, ect.).',
            Role::Employee->value => 'Employees have the ability to read, create, and update their own time entries, they can see the projects that they are members of and the clients they are assigned to.',
            Role::Placeholder->value => 'Placeholders are used for importing data. They cannot log in and have no permissions.',
        ];

        foreach (Role::cases() as $role) {
            $permissions = $rolePermissions[$role->value] ?? [];
            $description = $roleDescriptions[$role->value] ?? '';
            Jetstream::role($role->value, ucfirst($role->value), $permissions)
                ->description($description);
        }
    }

    /**
     * Get all registered provider class names.
     *
     * @return array<class-string<PermissionsProvider>>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }
}
