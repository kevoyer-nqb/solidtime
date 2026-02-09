# Teams & Groups Architecture Blueprint

**Feature ID**: 10-teams-groups
**Date**: 2026-02-06
**Status**: Ready for Implementation
**Estimated Effort**: 120 hours (60 SP)
**PRD Reference**: `.features/10-teams-groups/PRD.md`
**Shared Foundations**: `.features/SHARED-FOUNDATIONS.md`

---

## Executive Summary

This document provides the complete technical architecture for the Teams & Groups feature, which adds sub-organizational team scoping to Solidtime. The feature enables organizations to create teams (departments/working groups), assign members/projects/clients to teams, and apply team-based visibility filtering.

**Key Architectural Decisions**:
1. **Team Model** is `App\Models\Team`, distinct from Jetstream's Team (which maps to `Organization`)
2. **Feature Flag Approach**: `enable_team_scoping` boolean on `organizations` controls whether filtering is applied
3. **Query-Level Scoping**: `TeamScopeService` applies WHERE clauses to existing queries
4. **Per-Request Cache**: Team IDs cached per request for performance (AMD-09)
5. **Backward Compatible**: Migration creates "Default" team and assigns all existing data
6. **Admin/Owner Bypass**: Team scoping never applies to Admin/Owner roles (AMD-08)

---

## Table of Contents

1. [Data Model](#1-data-model)
2. [TeamScopeService](#2-teamscopeservice--query-filtering-layer)
3. [API Contract](#3-api-contract)
4. [Controller Implementation](#4-controller-implementation)
5. [TeamService](#5-teamservice--business-logic)
6. [Data Migration](#6-data-migration--default-team-creation)
7. [TimeEntryFilter Changes](#7-timeentryfilter-modification)
8. [Permission Matrix](#8-permission-registration-modular-pattern)
9. [Controller Modifications for Scoping](#9-controller-modifications-for-team-scoping)
10. [Organization Settings](#10-organization-settings--feature-flag-toggle)
11. [Feature Flag Behavior](#11-feature-flag-behavior)
12. [Frontend Architecture](#12-frontend-architecture)
13. [Task Scoping Through Projects](#13-task-scoping-through-projects)
14. [Performance Considerations](#14-performance-considerations-amd-09)
15. [Jetstream Naming Strategy](#15-jetstream-naming-strategy-amd-13)
16. [File Manifest](#16-file-manifest)
17. [Build Sequence](#17-build-sequence--phased-implementation)
18. [Critical Details](#18-critical-details)
19. [Success Criteria](#19-success-criteria)
20. [Implementation Notes](#20-implementation-notes)

---

## 1. Data Model

### 1.1 New Models

#### Team Model
**File**: `app/Models/Team.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\CustomAuditable;
use App\Models\Concerns\HasUuids;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Note: This is solidtime's Team (sub-org group), not Jetstream's Team (which maps to Organization).
 *
 * @property string $id
 * @property string $name
 * @property string $color
 * @property string $organization_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Collection<int, TeamMember> $teamMembers
 * @property-read Collection<int, Member> $members
 * @property-read Collection<int, TeamProject> $teamProjects
 * @property-read Collection<int, Project> $projects
 * @property-read Collection<int, TeamClient> $teamClients
 * @property-read Collection<int, Client> $clients
 *
 * @method static TeamFactory factory()
 */
class Team extends Model implements AuditableContract
{
    use CustomAuditable;
    /** @use HasFactory<TeamFactory> */
    use HasFactory;
    use HasUuids;

    protected $casts = [
        'name' => 'string',
        'color' => 'string',
    ];

    protected $fillable = [];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return HasMany<TeamMember, $this>
     */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'team_id');
    }

    /**
     * @return BelongsToMany<Member, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'team_members', 'team_id', 'member_id')
            ->withTimestamps();
    }

    /**
     * @return HasMany<TeamProject, $this>
     */
    public function teamProjects(): HasMany
    {
        return $this->hasMany(TeamProject::class, 'team_id');
    }

    /**
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'team_projects', 'team_id', 'project_id')
            ->withTimestamps();
    }

    /**
     * @return HasMany<TeamClient, $this>
     */
    public function teamClients(): HasMany
    {
        return $this->hasMany(TeamClient::class, 'team_id');
    }

    /**
     * @return BelongsToMany<Client, $this>
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'team_clients', 'team_id', 'client_id')
            ->withTimestamps();
    }
}
```

#### TeamMember Pivot Model
**File**: `app/Models/TeamMember.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\CustomAuditable;
use App\Models\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property string $id
 * @property string $team_id
 * @property string $member_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Member $member
 */
class TeamMember extends Model implements AuditableContract
{
    use CustomAuditable;
    use HasUuids;

    protected $fillable = [];

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}
```

#### TeamProject Pivot Model
**File**: `app/Models/TeamProject.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\CustomAuditable;
use App\Models\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property string $id
 * @property string $team_id
 * @property string $project_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Project $project
 */
class TeamProject extends Model implements AuditableContract
{
    use CustomAuditable;
    use HasUuids;

    protected $fillable = [];

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
```

#### TeamClient Pivot Model
**File**: `app/Models/TeamClient.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\CustomAuditable;
use App\Models\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property string $id
 * @property string $team_id
 * @property string $client_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Client $client
 */
class TeamClient extends Model implements AuditableContract
{
    use CustomAuditable;
    use HasUuids;

    protected $fillable = [];

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
```

### 1.2 Database Schema

#### Migration 1: Feature Flag
**File**: `database/migrations/2026_03_10_000000_add_team_scoping_flag_to_organizations.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->boolean('enable_team_scoping')
                ->default(false)
                ->after('prevent_overlapping_time_entries')
                ->comment('When enabled, projects/clients/time entries are filtered by team membership.');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('enable_team_scoping');
        });
    }
};
```

#### Migration 2: Teams Table
**File**: `database/migrations/2026_03_10_000001_create_teams_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('color', 16)->default('#3B82F6');
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
```

#### Migration 3: TeamMembers Table
**File**: `database/migrations/2026_03_10_000002_create_team_members_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('team_id');
            $table->uuid('member_id');
            $table->foreign('team_id')
                ->references('id')
                ->on('teams')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('member_id')
                ->references('id')
                ->on('members')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['team_id', 'member_id']);
            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
```

#### Migration 4: TeamProjects Table
**File**: `database/migrations/2026_03_10_000003_create_team_projects_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_projects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('team_id');
            $table->uuid('project_id');
            $table->foreign('team_id')
                ->references('id')
                ->on('teams')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['team_id', 'project_id']);
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_projects');
    }
};
```

#### Migration 5: TeamClients Table
**File**: `database/migrations/2026_03_10_000004_create_team_clients_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_clients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('team_id');
            $table->uuid('client_id');
            $table->foreign('team_id')
                ->references('id')
                ->on('teams')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['team_id', 'client_id']);
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_clients');
    }
};
```

### 1.3 Relationships in Existing Models

#### Organization Model
**File**: `app/Models/Organization.php`

Add to PHPDoc:
```php
 * @property bool $enable_team_scoping
 * @property-read Collection<int, Team> $teams
```

Add to `$casts`:
```php
'enable_team_scoping' => 'boolean',
```

Add method:
```php
/**
 * @return HasMany<Team, $this>
 */
public function teams(): HasMany
{
    return $this->hasMany(Team::class, 'organization_id');
}
```

#### Member Model
**File**: `app/Models/Member.php`

Add to PHPDoc:
```php
 * @property-read Collection<int, TeamMember> $teamMembers
 * @property-read Collection<int, Team> $teams
```

Add methods:
```php
/**
 * @return HasMany<TeamMember, $this>
 */
public function teamMembers(): HasMany
{
    return $this->hasMany(TeamMember::class, 'member_id');
}

/**
 * @return BelongsToMany<Team, $this>
 */
public function teams(): BelongsToMany
{
    return $this->belongsToMany(Team::class, 'team_members', 'member_id', 'team_id')
        ->withTimestamps();
}
```

#### Project Model
**File**: `app/Models/Project.php`

Add to PHPDoc:
```php
 * @property-read Collection<int, TeamProject> $teamProjects
 * @property-read Collection<int, Team> $teams
```

Add methods:
```php
/**
 * @return HasMany<TeamProject, $this>
 */
public function teamProjects(): HasMany
{
    return $this->hasMany(TeamProject::class, 'project_id');
}

/**
 * @return BelongsToMany<Team, $this>
 */
public function teams(): BelongsToMany
{
    return $this->belongsToMany(Team::class, 'team_projects', 'project_id', 'team_id')
        ->withTimestamps();
}
```

#### Client Model
**File**: `app/Models/Client.php`

Add to PHPDoc:
```php
 * @property-read Collection<int, TeamClient> $teamClients
 * @property-read Collection<int, Team> $teams
```

Add methods:
```php
/**
 * @return HasMany<TeamClient, $this>
 */
public function teamClients(): HasMany
{
    return $this->hasMany(TeamClient::class, 'client_id');
}

/**
 * @return BelongsToMany<Team, $this>
 */
public function teams(): BelongsToMany
{
    return $this->belongsToMany(Team::class, 'team_clients', 'client_id', 'team_id')
        ->withTimestamps();
}
```

---

## 2. TeamScopeService -- Query Filtering Layer

**File**: `app/Service/TeamScopeService.php`

**Responsibilities**:
- Determine if team scoping is enabled for an organization
- Retrieve current user's team IDs with per-request caching (AMD-09)
- Apply team-based WHERE clauses to project, client, and time entry queries
- Bypass scoping for Admin/Owner roles

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Enums\Role;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TeamScopeService
{
    /**
     * Per-request cache of team IDs keyed by member ID.
     * Cleared between HTTP requests (service is not a singleton).
     *
     * @var array<string, array<string>>
     */
    private array $teamIdCache = [];

    /**
     * Check if team scoping is enabled for the organization.
     */
    public function isEnabled(Organization $organization): bool
    {
        return $organization->enable_team_scoping;
    }

    /**
     * Check if the given member's role should bypass team scoping.
     * Admin and Owner roles always see all data regardless of team assignments.
     */
    public function shouldBypassScoping(Member $member): bool
    {
        return in_array($member->role, [
            Role::Owner->value,
            Role::Admin->value,
        ], true);
    }

    /**
     * Get team IDs for a member (cached per request).
     *
     * @return array<string>
     */
    public function getTeamIdsForMember(Member $member): array
    {
        $cacheKey = $member->id;

        if (! isset($this->teamIdCache[$cacheKey])) {
            $this->teamIdCache[$cacheKey] = $member->teamMembers()
                ->pluck('team_id')
                ->toArray();
        }

        return $this->teamIdCache[$cacheKey];
    }

    /**
     * Resolve the Member for a User within an Organization.
     * Returns null if user is not a member.
     */
    private function resolveMember(Organization $organization, User $user): ?Member
    {
        /** @var Member|null $member */
        $member = $organization->members()
            ->where('user_id', $user->id)
            ->first();

        return $member;
    }

    /**
     * Apply team scoping to a Project query.
     *
     * @param  Builder<\App\Models\Project>  $query
     * @return Builder<\App\Models\Project>
     */
    public function applyProjectScope(Builder $query, Organization $organization, User $user): Builder
    {
        if (! $this->isEnabled($organization)) {
            return $query;
        }

        $member = $this->resolveMember($organization, $user);

        if ($member === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->shouldBypassScoping($member)) {
            return $query;
        }

        $teamIds = $this->getTeamIdsForMember($member);

        if (empty($teamIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('teamProjects', function (Builder $q) use ($teamIds): void {
            $q->whereIn('team_id', $teamIds);
        });
    }

    /**
     * Apply team scoping to a Client query.
     *
     * @param  Builder<\App\Models\Client>  $query
     * @return Builder<\App\Models\Client>
     */
    public function applyClientScope(Builder $query, Organization $organization, User $user): Builder
    {
        if (! $this->isEnabled($organization)) {
            return $query;
        }

        $member = $this->resolveMember($organization, $user);

        if ($member === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->shouldBypassScoping($member)) {
            return $query;
        }

        $teamIds = $this->getTeamIdsForMember($member);

        if (empty($teamIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('teamClients', function (Builder $q) use ($teamIds): void {
            $q->whereIn('team_id', $teamIds);
        });
    }

    /**
     * Apply team scoping to a TimeEntry query via project association.
     * Time entries are scoped through their project's team assignments.
     *
     * @param  Builder<\App\Models\TimeEntry>  $query
     * @return Builder<\App\Models\TimeEntry>
     */
    public function applyTimeEntryScope(Builder $query, Organization $organization, User $user): Builder
    {
        if (! $this->isEnabled($organization)) {
            return $query;
        }

        $member = $this->resolveMember($organization, $user);

        if ($member === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->shouldBypassScoping($member)) {
            return $query;
        }

        $teamIds = $this->getTeamIdsForMember($member);

        if (empty($teamIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('project.teamProjects', function (Builder $q) use ($teamIds): void {
            $q->whereIn('team_id', $teamIds);
        });
    }

    /**
     * Clear the per-request cache (useful for testing).
     */
    public function clearCache(): void
    {
        $this->teamIdCache = [];
    }
}
```

**Integration Points** (existing controllers to modify):
- `ProjectController::index()` -- inject after `->whereBelongsTo($organization, 'organization')`
- `ClientController::index()` -- inject after `->whereBelongsTo($organization, 'organization')`
- `TimeEntryController` -- within the internal query building logic
- `ChartController` methods -- all chart data queries

**Modification Example (ProjectController::index)**:
```php
// BEFORE (current code at app/Http/Controllers/Api/V1/ProjectController.php line 44)
$projectsQuery = Project::query()
    ->whereBelongsTo($organization, 'organization');

if (! $canViewAllProjects) {
    $projectsQuery->visibleByEmployee($user);
}

// AFTER
$projectsQuery = Project::query()
    ->whereBelongsTo($organization, 'organization');

// Apply team scoping (if enabled, per SF-07)
$projectsQuery = $teamScopeService->applyProjectScope(
    $projectsQuery,
    $organization,
    $user
);

if (! $canViewAllProjects) {
    $projectsQuery->visibleByEmployee($user);
}
```

---

## 3. API Contract

### 3.1 Team CRUD Endpoints

**Route Prefix**: `/api/v1/organizations/{organization}/teams`
**Route Name Prefix**: `api.v1.teams.`

```php
// routes/api.php additions
Route::name('teams.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/teams', [TeamController::class, 'index'])->name('index');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('show');
    Route::post('/teams', [TeamController::class, 'store'])
        ->name('store')
        ->middleware('check-organization-blocked');
    Route::put('/teams/{team}', [TeamController::class, 'update'])
        ->name('update')
        ->middleware('check-organization-blocked');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('destroy');

    // Member assignment
    Route::get('/teams/{team}/members', [TeamController::class, 'listMembers'])->name('members.index');
    Route::post('/teams/{team}/members', [TeamController::class, 'addMembers'])
        ->name('members.store')
        ->middleware('check-organization-blocked');
    Route::delete('/teams/{team}/members/{member}', [TeamController::class, 'removeMember'])
        ->name('members.destroy');

    // Project assignment
    Route::post('/teams/{team}/projects', [TeamController::class, 'addProjects'])
        ->name('projects.store')
        ->middleware('check-organization-blocked');
    Route::delete('/teams/{team}/projects/{project}', [TeamController::class, 'removeProject'])
        ->name('projects.destroy');

    // Client assignment
    Route::post('/teams/{team}/clients', [TeamController::class, 'addClients'])
        ->name('clients.store')
        ->middleware('check-organization-blocked');
    Route::delete('/teams/{team}/clients/{client}', [TeamController::class, 'removeClient'])
        ->name('clients.destroy');
});
```

### 3.2 Request Validation Classes

**Files to Create**:
- `app/Http/Requests/V1/Team/TeamIndexRequest.php`
- `app/Http/Requests/V1/Team/TeamStoreRequest.php`
- `app/Http/Requests/V1/Team/TeamUpdateRequest.php`
- `app/Http/Requests/V1/Team/TeamAddMembersRequest.php`
- `app/Http/Requests/V1/Team/TeamAddProjectsRequest.php`
- `app/Http/Requests/V1/Team/TeamAddClientsRequest.php`

**TeamStoreRequest** (representative example):
```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Team;

use App\Http\Requests\V1\BaseFormRequest;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Korridor\LaravelModelValidationRules\Rules\UniqueEloquent;

/**
 * @property Organization $organization Organization from route model binding
 */
class TeamStoreRequest extends BaseFormRequest
{
    /**
     * @return array<string, array<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:1',
                'max:255',
                UniqueEloquent::make(Team::class, 'name', function (Builder $builder): Builder {
                    /** @var Builder<Team> $builder */
                    return $builder->where('organization_id', '=', $this->organization->id);
                })->withCustomTranslation('validation.team_name_already_exists'),
            ],
            'color' => [
                'nullable',
                'string',
                'max:16',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
        ];
    }

    public function getColor(): string
    {
        return $this->input('color', '#3B82F6');
    }
}
```

**TeamAddMembersRequest** (representative example):
```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Team;

use App\Http\Requests\V1\BaseFormRequest;
use App\Models\Member;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Korridor\LaravelModelValidationRules\Rules\ExistsEloquent;

/**
 * @property Organization $organization Organization from route model binding
 */
class TeamAddMembersRequest extends BaseFormRequest
{
    /**
     * @return array<string, array<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'member_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'member_ids.*' => [
                'required',
                'uuid',
                ExistsEloquent::make(Member::class, null, function (Builder $builder): Builder {
                    /** @var Builder<Member> $builder */
                    return $builder->where('organization_id', '=', $this->organization->id);
                }),
            ],
        ];
    }
}
```

### 3.3 API Resources

**TeamResource**:
```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Team;

use App\Http\Resources\V1\BaseResource;
use App\Models\Team;
use Illuminate\Http\Request;

/**
 * @property Team $resource
 */
class TeamResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'color' => $this->resource->color,
            'organization_id' => $this->resource->organization_id,
            'members_count' => $this->when(
                $this->resource->relationLoaded('teamMembers'),
                fn () => $this->resource->teamMembers->count()
            ),
            'projects_count' => $this->when(
                $this->resource->relationLoaded('teamProjects'),
                fn () => $this->resource->teamProjects->count()
            ),
            'clients_count' => $this->when(
                $this->resource->relationLoaded('teamClients'),
                fn () => $this->resource->teamClients->count()
            ),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
```

**TeamCollection** (`app/Http/Resources/V1/Team/TeamCollection.php`): Standard paginated resource collection wrapping `TeamResource`.

---

## 4. Controller Implementation

**File**: `app/Http/Controllers/Api/V1/TeamController.php`

Extends `App\Http\Controllers\Api\V1\Controller` (which injects `PermissionStore` and inherits `$this->user()`, `$this->member()` from the grandparent `App\Http\Controllers\Controller`).

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\EntityStillInUseApiException;
use App\Http\Requests\V1\Team\TeamAddClientsRequest;
use App\Http\Requests\V1\Team\TeamAddMembersRequest;
use App\Http\Requests\V1\Team\TeamAddProjectsRequest;
use App\Http\Requests\V1\Team\TeamIndexRequest;
use App\Http\Requests\V1\Team\TeamStoreRequest;
use App\Http\Requests\V1\Team\TeamUpdateRequest;
use App\Http\Resources\V1\Team\TeamCollection;
use App\Http\Resources\V1\Team\TeamResource;
use App\Models\Client;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use App\Service\TeamService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamController extends Controller
{
    /**
     * Validate team belongs to organization.
     *
     * @throws AuthorizationException
     */
    protected function checkPermission(Organization $organization, string $permission, ?Team $team = null): void
    {
        parent::checkPermission($organization, $permission);
        if ($team !== null && $team->organization_id !== $organization->id) {
            throw new AuthorizationException('Team does not belong to organization');
        }
    }

    /**
     * List teams in organization.
     *
     * @operationId getTeams
     */
    public function index(Organization $organization, TeamIndexRequest $request): JsonResource
    {
        $this->checkPermission($organization, 'teams:view');
        $canViewAll = $this->hasPermission($organization, 'teams:view:all');

        $teamsQuery = Team::query()
            ->whereBelongsTo($organization, 'organization')
            ->withCount(['teamMembers', 'teamProjects', 'teamClients']);

        // If not admin/owner, only show teams the user belongs to
        if (! $canViewAll) {
            $member = $this->member($organization);
            $teamsQuery->whereHas('teamMembers', function ($q) use ($member): void {
                $q->where('member_id', $member->id);
            });
        }

        $teams = $teamsQuery->paginate(config('app.pagination_per_page_default'));

        return new TeamCollection($teams);
    }

    /**
     * Get team details.
     *
     * @operationId getTeam
     */
    public function show(Organization $organization, Team $team): JsonResource
    {
        $this->checkPermission($organization, 'teams:view', $team);

        $team->loadCount(['teamMembers', 'teamProjects', 'teamClients']);

        return new TeamResource($team);
    }

    /**
     * Create team.
     *
     * @operationId createTeam
     */
    public function store(
        Organization $organization,
        TeamStoreRequest $request,
        TeamService $teamService
    ): JsonResource {
        $this->checkPermission($organization, 'teams:create');

        $team = $teamService->createTeam(
            $organization,
            $request->input('name'),
            $request->getColor()
        );

        return new TeamResource($team);
    }

    /**
     * Update team.
     *
     * @operationId updateTeam
     */
    public function update(
        Organization $organization,
        Team $team,
        TeamUpdateRequest $request,
        TeamService $teamService
    ): JsonResource {
        $this->checkPermission($organization, 'teams:update', $team);

        $team = $teamService->updateTeam(
            $team,
            $request->input('name'),
            $request->getColor()
        );

        return new TeamResource($team);
    }

    /**
     * Delete team.
     *
     * @operationId deleteTeam
     */
    public function destroy(
        Organization $organization,
        Team $team,
        TeamService $teamService
    ): JsonResponse {
        $this->checkPermission($organization, 'teams:delete', $team);

        $teamService->deleteTeam($team);

        return response()->json(null, 204);
    }

    /**
     * List team members.
     *
     * @operationId getTeamMembers
     */
    public function listMembers(Organization $organization, Team $team): JsonResponse
    {
        $this->checkPermission($organization, 'teams:view', $team);

        $members = $team->members()
            ->with('user')
            ->get();

        return response()->json([
            'data' => $members->map(fn ($member) => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role' => $member->role,
            ]),
        ]);
    }

    /**
     * Add members to team.
     *
     * @operationId addTeamMembers
     */
    public function addMembers(
        Organization $organization,
        Team $team,
        TeamAddMembersRequest $request,
        TeamService $teamService
    ): JsonResponse {
        $this->checkPermission($organization, 'teams:update:members', $team);

        $teamService->addMembers($team, $request->input('member_ids'));

        return response()->json(null, 204);
    }

    /**
     * Remove member from team.
     *
     * @operationId removeTeamMember
     */
    public function removeMember(
        Organization $organization,
        Team $team,
        Member $member,
        TeamService $teamService
    ): JsonResponse {
        $this->checkPermission($organization, 'teams:update:members', $team);

        if ($member->organization_id !== $organization->id) {
            throw new AuthorizationException('Member does not belong to organization');
        }

        $teamService->removeMember($team, $member);

        return response()->json(null, 204);
    }

    /**
     * Add projects to team.
     *
     * @operationId addTeamProjects
     */
    public function addProjects(
        Organization $organization,
        Team $team,
        TeamAddProjectsRequest $request,
        TeamService $teamService
    ): JsonResponse {
        $this->checkPermission($organization, 'teams:update:projects', $team);

        $teamService->addProjects($team, $request->input('project_ids'));

        return response()->json(null, 204);
    }

    /**
     * Remove project from team.
     *
     * @operationId removeTeamProject
     */
    public function removeProject(
        Organization $organization,
        Team $team,
        Project $project,
        TeamService $teamService
    ): JsonResponse {
        $this->checkPermission($organization, 'teams:update:projects', $team);

        if ($project->organization_id !== $organization->id) {
            throw new AuthorizationException('Project does not belong to organization');
        }

        $teamService->removeProject($team, $project);

        return response()->json(null, 204);
    }

    /**
     * Add clients to team.
     *
     * @operationId addTeamClients
     */
    public function addClients(
        Organization $organization,
        Team $team,
        TeamAddClientsRequest $request,
        TeamService $teamService
    ): JsonResponse {
        $this->checkPermission($organization, 'teams:update:clients', $team);

        $teamService->addClients($team, $request->input('client_ids'));

        return response()->json(null, 204);
    }

    /**
     * Remove client from team.
     *
     * @operationId removeTeamClient
     */
    public function removeClient(
        Organization $organization,
        Team $team,
        Client $client,
        TeamService $teamService
    ): JsonResponse {
        $this->checkPermission($organization, 'teams:update:clients', $team);

        if ($client->organization_id !== $organization->id) {
            throw new AuthorizationException('Client does not belong to organization');
        }

        $teamService->removeClient($team, $client);

        return response()->json(null, 204);
    }
}
```

---

## 5. TeamService -- Business Logic

**File**: `app/Service/TeamService.php`

Stateless service class, injected into controller methods via parameter type-hints (matching existing service pattern, e.g. `BillableRateService`).

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Exceptions\Api\EntityStillInUseApiException;
use App\Models\Client;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use App\Models\TeamClient;
use App\Models\TeamMember;
use App\Models\TeamProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamService
{
    public function createTeam(Organization $organization, string $name, string $color): Team
    {
        $team = new Team;
        $team->name = $name;
        $team->color = $color;
        $team->organization()->associate($organization);
        $team->save();

        return $team;
    }

    public function updateTeam(Team $team, string $name, string $color): Team
    {
        $team->name = $name;
        $team->color = $color;
        $team->save();

        return $team;
    }

    /**
     * Delete a team. Throws if team still has members, projects, or clients assigned.
     *
     * @throws EntityStillInUseApiException
     */
    public function deleteTeam(Team $team): void
    {
        if ($team->teamMembers()->exists()) {
            throw new EntityStillInUseApiException('team', 'team_member');
        }
        if ($team->teamProjects()->exists()) {
            throw new EntityStillInUseApiException('team', 'team_project');
        }
        if ($team->teamClients()->exists()) {
            throw new EntityStillInUseApiException('team', 'team_client');
        }

        $team->delete();
    }

    /**
     * Add members to a team (idempotent -- duplicates are ignored).
     *
     * @param  array<string>  $memberIds
     *
     * @throws ValidationException
     */
    public function addMembers(Team $team, array $memberIds): void
    {
        $organization = $team->organization;

        // Validate all members belong to the organization
        $validMemberIds = Member::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $memberIds)
            ->pluck('id')
            ->toArray();

        if (count($validMemberIds) !== count($memberIds)) {
            throw ValidationException::withMessages([
                'member_ids' => ['One or more member IDs are invalid for this organization.'],
            ]);
        }

        // Idempotent insert -- ignore duplicates via unique constraint
        $inserts = collect($validMemberIds)->map(fn ($memberId) => [
            'id' => Str::uuid()->toString(),
            'team_id' => $team->id,
            'member_id' => $memberId,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        DB::table('team_members')->insertOrIgnore($inserts);
    }

    /**
     * Remove a member from a team.
     * Validates the "must belong to at least one team" constraint when scoping is enabled.
     *
     * @throws ValidationException
     */
    public function removeMember(Team $team, Member $member): void
    {
        // Check: if team scoping is enabled, member must belong to at least one other team
        $organization = $team->organization;
        if ($organization->enable_team_scoping) {
            $otherTeamCount = $member->teamMembers()
                ->where('team_id', '!=', $team->id)
                ->count();

            if ($otherTeamCount === 0) {
                throw ValidationException::withMessages([
                    'member_id' => ['Member must belong to at least one team when team scoping is enabled.'],
                ]);
            }
        }

        TeamMember::query()
            ->where('team_id', $team->id)
            ->where('member_id', $member->id)
            ->delete();
    }

    /**
     * Add projects to a team (idempotent).
     *
     * @param  array<string>  $projectIds
     *
     * @throws ValidationException
     */
    public function addProjects(Team $team, array $projectIds): void
    {
        $organization = $team->organization;

        $validProjectIds = Project::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $projectIds)
            ->pluck('id')
            ->toArray();

        if (count($validProjectIds) !== count($projectIds)) {
            throw ValidationException::withMessages([
                'project_ids' => ['One or more project IDs are invalid for this organization.'],
            ]);
        }

        $inserts = collect($validProjectIds)->map(fn ($projectId) => [
            'id' => Str::uuid()->toString(),
            'team_id' => $team->id,
            'project_id' => $projectId,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        DB::table('team_projects')->insertOrIgnore($inserts);
    }

    public function removeProject(Team $team, Project $project): void
    {
        TeamProject::query()
            ->where('team_id', $team->id)
            ->where('project_id', $project->id)
            ->delete();
    }

    /**
     * Add clients to a team (idempotent).
     *
     * @param  array<string>  $clientIds
     *
     * @throws ValidationException
     */
    public function addClients(Team $team, array $clientIds): void
    {
        $organization = $team->organization;

        $validClientIds = Client::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $clientIds)
            ->pluck('id')
            ->toArray();

        if (count($validClientIds) !== count($clientIds)) {
            throw ValidationException::withMessages([
                'client_ids' => ['One or more client IDs are invalid for this organization.'],
            ]);
        }

        $inserts = collect($validClientIds)->map(fn ($clientId) => [
            'id' => Str::uuid()->toString(),
            'team_id' => $team->id,
            'client_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        DB::table('team_clients')->insertOrIgnore($inserts);
    }

    public function removeClient(Team $team, Client $client): void
    {
        TeamClient::query()
            ->where('team_id', $team->id)
            ->where('client_id', $client->id)
            ->delete();
    }
}
```

---

## 6. Data Migration -- Default Team Creation

**File**: `database/migrations/2026_03_10_100001_seed_default_teams_for_existing_organizations.php`

This migration creates a "Default" team for every existing organization, then assigns all existing members, projects, and clients to it. Uses chunked processing per AMD-09 performance guidance.

```php
<?php

declare(strict_types=1);

use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Process organizations in chunks for performance (AMD-09)
        Organization::query()->chunkById(100, function ($organizations): void {
            foreach ($organizations as $organization) {
                // Idempotent: skip if organization already has a "Default" team
                $existingTeam = DB::table('teams')
                    ->where('organization_id', $organization->id)
                    ->where('name', 'Default')
                    ->exists();

                if ($existingTeam) {
                    continue;
                }

                $teamId = Str::uuid()->toString();

                // Create Default team
                DB::table('teams')->insert([
                    'id' => $teamId,
                    'name' => 'Default',
                    'color' => '#3B82F6',
                    'organization_id' => $organization->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Assign all members to Default team
                $members = DB::table('members')
                    ->where('organization_id', $organization->id)
                    ->pluck('id');

                if ($members->isNotEmpty()) {
                    $teamMembers = $members->map(fn ($memberId) => [
                        'id' => Str::uuid()->toString(),
                        'team_id' => $teamId,
                        'member_id' => $memberId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->toArray();
                    DB::table('team_members')->insert($teamMembers);
                }

                // Assign all projects to Default team
                $projects = DB::table('projects')
                    ->where('organization_id', $organization->id)
                    ->pluck('id');

                if ($projects->isNotEmpty()) {
                    $teamProjects = $projects->map(fn ($projectId) => [
                        'id' => Str::uuid()->toString(),
                        'team_id' => $teamId,
                        'project_id' => $projectId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->toArray();
                    DB::table('team_projects')->insert($teamProjects);
                }

                // Assign all clients to Default team
                $clients = DB::table('clients')
                    ->where('organization_id', $organization->id)
                    ->pluck('id');

                if ($clients->isNotEmpty()) {
                    $teamClients = $clients->map(fn ($clientId) => [
                        'id' => Str::uuid()->toString(),
                        'team_id' => $teamId,
                        'client_id' => $clientId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->toArray();
                    DB::table('team_clients')->insert($teamClients);
                }
            }
        });
    }

    public function down(): void
    {
        // Delete all Default teams -- cascading deletes handle pivot records
        DB::table('teams')->where('name', 'Default')->delete();
    }
};
```

---

## 7. TimeEntryFilter Modification

**File**: `app/Service/TimeEntryFilter.php`

The existing `TimeEntryFilter` uses a builder pattern. Add the following property and method:

```php
/**
 * @var array<string>|null
 */
protected ?array $teamIds = null;

/**
 * Filter time entries by team assignment (via project's team_projects pivot).
 * Per AMD-10: applies a subquery where the entry's project_id is in a project
 * assigned to any of the specified teams.
 *
 * @param  array<string>|null  $teamIds
 */
public function addTeamIdsFilter(?array $teamIds): self
{
    if ($teamIds === null) {
        return $this;
    }

    $this->teamIds = $teamIds;

    $this->builder->whereHas('project.teamProjects', function (Builder $q) use ($teamIds): void {
        $q->whereIn('team_id', $teamIds);
    });

    return $this;
}
```

**Integration**: Modify `TimeEntryIndexRequest` and `TimeEntryAggregateRequest` to accept a `team_ids[]` query parameter and call `addTeamIdsFilter()` in the corresponding controller methods.

**TimeEntryIndexRequest addition**:
```php
'team_ids' => [
    'nullable',
    'array',
],
'team_ids.*' => [
    'uuid',
],
```

**TimeEntryController modification** (within query building):
```php
$filter->addTeamIdsFilter($request->input('team_ids'));
```

---

## 8. Permission Registration (Modular Pattern)

**File**: `app/Permissions/TeamPermissions.php`

Per SF-08, permissions are registered via a modular pattern instead of directly modifying `JetstreamServiceProvider`.

```php
<?php

declare(strict_types=1);

namespace App\Permissions;

use App\Enums\Role;
use Laravel\Jetstream\Jetstream;

class TeamPermissions
{
    /**
     * Register team-related permissions for all roles.
     *
     * Permissions follow SF-02 naming convention ({entity}:{action}:{scope}).
     * Corrected per AMD-02:
     *   - teams:update-members -> teams:update:members
     *   - teams:update-projects -> teams:update:projects
     *   - teams:update-clients -> teams:update:clients
     */
    public static function register(): void
    {
        // Owner role -- all team permissions
        $ownerRole = Jetstream::findRole(Role::Owner->value);
        if ($ownerRole !== null) {
            $ownerRole->permissions = array_merge($ownerRole->permissions, [
                'teams:view',
                'teams:view:all',
                'teams:create',
                'teams:update',
                'teams:delete',
                'teams:update:members',
                'teams:update:projects',
                'teams:update:clients',
            ]);
        }

        // Admin role -- all team permissions
        $adminRole = Jetstream::findRole(Role::Admin->value);
        if ($adminRole !== null) {
            $adminRole->permissions = array_merge($adminRole->permissions, [
                'teams:view',
                'teams:view:all',
                'teams:create',
                'teams:update',
                'teams:delete',
                'teams:update:members',
                'teams:update:projects',
                'teams:update:clients',
            ]);
        }

        // Manager role -- view only (AMD-08: intentional limitation)
        $managerRole = Jetstream::findRole(Role::Manager->value);
        if ($managerRole !== null) {
            $managerRole->permissions = array_merge($managerRole->permissions, [
                'teams:view',
            ]);
        }

        // Employee role -- view only
        $employeeRole = Jetstream::findRole(Role::Employee->value);
        if ($employeeRole !== null) {
            $employeeRole->permissions = array_merge($employeeRole->permissions, [
                'teams:view',
            ]);
        }
    }
}
```

**Modify** `app/Providers/JetstreamServiceProvider.php` -- add at end of `configurePermissions()`:
```php
// Feature permissions (modular pattern per SF-08)
\App\Permissions\TeamPermissions::register();
```

### Permission Matrix

| Permission | Owner | Admin | Manager | Employee | Placeholder |
|---|:---:|:---:|:---:|:---:|:---:|
| `teams:view` | Y | Y | Y | Y | - |
| `teams:view:all` | Y | Y | - | - | - |
| `teams:create` | Y | Y | - | - | - |
| `teams:update` | Y | Y | - | - | - |
| `teams:delete` | Y | Y | - | - | - |
| `teams:update:members` | Y | Y | - | - | - |
| `teams:update:projects` | Y | Y | - | - | - |
| `teams:update:clients` | Y | Y | - | - | - |

**AMD-08 Note**: Managers only receive `teams:view` (not `teams:view:all`) by design. A manager who needs cross-team visibility should be promoted to Admin or assigned to multiple teams.

---

## 9. Controller Modifications for Team Scoping

### 9.1 ProjectController

**File**: `app/Http/Controllers/Api/V1/ProjectController.php`
**Method**: `index()` (currently at line 44)

```php
public function index(
    Organization $organization,
    ProjectIndexRequest $request,
    TeamScopeService $teamScopeService
): ProjectCollection {
    $this->checkPermission($organization, 'projects:view');
    $canViewAllProjects = $this->hasPermission($organization, 'projects:view:all');
    $user = $this->user();

    $projectsQuery = Project::query()
        ->whereBelongsTo($organization, 'organization');

    // Apply team scoping if enabled (per SF-07)
    $projectsQuery = $teamScopeService->applyProjectScope(
        $projectsQuery,
        $organization,
        $user
    );

    if (! $canViewAllProjects) {
        $projectsQuery->visibleByEmployee($user);
    }

    // ... rest unchanged
}
```

### 9.2 ClientController

**File**: `app/Http/Controllers/Api/V1/ClientController.php`
**Method**: `index()` (currently at line 38)

```php
public function index(
    Organization $organization,
    ClientIndexRequest $request,
    TeamScopeService $teamScopeService
): ClientCollection {
    $this->checkPermission($organization, 'clients:view');
    $canViewAllClients = $this->hasPermission($organization, 'clients:view:all');
    $user = $this->user();

    $clientsQuery = Client::query()
        ->whereBelongsTo($organization, 'organization')
        ->orderBy('created_at', 'desc');

    // Apply team scoping if enabled
    $clientsQuery = $teamScopeService->applyClientScope(
        $clientsQuery,
        $organization,
        $user
    );

    if (! $canViewAllClients) {
        $clientsQuery->visibleByEmployee($user);
    }

    // ... rest unchanged
}
```

### 9.3 TimeEntryController

**File**: `app/Http/Controllers/Api/V1/TimeEntryController.php`

Modify the internal query building logic (within `getTimeEntriesQuery()` or equivalent private method) to inject `TeamScopeService::applyTimeEntryScope()`. The `TeamScopeService` should be injected as a controller method parameter.

### 9.4 ChartController

**File**: `app/Http/Controllers/Api/V1/ChartController.php`

For each chart method (`weeklyProjectOverview`, `latestTasks`, `lastSevenDays`), pass `TeamScopeService` to the `DashboardService` methods and apply scoping within those methods for the underlying project and time entry queries.

---

## 10. Organization Settings -- Feature Flag Toggle

### Backend Modifications

**Modify** `app/Http/Requests/V1/Organization/OrganizationUpdateRequest.php`:

Add validation rule:
```php
'enable_team_scoping' => [
    'nullable',
    'boolean',
],
```

Add getter:
```php
public function getEnableTeamScoping(): ?bool
{
    return $this->has('enable_team_scoping') ? $this->boolean('enable_team_scoping') : null;
}
```

**Modify** `app/Http/Controllers/Api/V1/OrganizationController.php` `update()` method:
```php
if ($request->getEnableTeamScoping() !== null) {
    $organization->enable_team_scoping = $request->getEnableTeamScoping();
}
```

**Modify** `app/Http/Resources/V1/Organization/OrganizationResource.php` `toArray()`:
```php
'enable_team_scoping' => $this->resource->enable_team_scoping,
```

---

## 11. Feature Flag Behavior

### When `enable_team_scoping = false` (Default)
- Teams can be created and managed via the API and UI
- Members, projects, and clients can be assigned to teams
- **No filtering is applied** -- all members see all data as today
- This allows gradual setup before enforcement
- The "must belong to at least one team" constraint is **not enforced**

### When `enable_team_scoping = true`
- `TeamScopeService` applies WHERE clauses to all scoped queries
- Employees see only projects/clients from their assigned teams
- Managers see data from their assigned teams only (AMD-08)
- Time entries are filtered by their project's team assignment
- **Admin/Owner bypass** -- they always see all data regardless
- The "must belong to at least one team" constraint **is enforced**

### Auto-Assignment Logic (New Member Invitation -- AMD-06)

**Modify** `app/Actions/Jetstream/AddOrganizationMember.php`:

```php
// At end of the add() method, after member creation:
if ($team->enable_team_scoping) {
    // Note: $team here is the Jetstream Team, which is the Organization
    $defaultTeam = \App\Models\Team::query()
        ->where('organization_id', $team->id)
        ->where('name', 'Default')
        ->first();

    if ($defaultTeam !== null) {
        $member = \App\Models\Member::query()
            ->where('organization_id', $team->id)
            ->where('user_id', $user->id)
            ->first();

        if ($member !== null) {
            app(\App\Service\TeamService::class)->addMembers($defaultTeam, [$member->id]);
        }
    }
}
```

---

## 12. Frontend Architecture

### 12.1 TypeScript Types

**File**: `resources/js/types/team.d.ts`

```typescript
export interface Team {
    id: string;
    name: string;
    color: string;
    organization_id: string;
    members_count?: number;
    projects_count?: number;
    clients_count?: number;
    created_at: string;
    updated_at: string;
}

export interface TeamMember {
    id: string;
    user_id: string;
    name: string;
    email: string;
    role: string;
}

export interface TeamCreatePayload {
    name: string;
    color?: string;
}

export interface TeamUpdatePayload {
    name: string;
    color?: string;
}

export interface TeamAddMembersPayload {
    member_ids: string[];
}

export interface TeamAddProjectsPayload {
    project_ids: string[];
}

export interface TeamAddClientsPayload {
    client_ids: string[];
}
```

### 12.2 Pinia Store

**File**: `resources/js/utils/useTeam.ts`

```typescript
import { defineStore } from 'pinia';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { api } from '@/api';
import { getCurrentOrganizationId } from '@/utils/useUser';
import type {
    Team,
    TeamCreatePayload,
    TeamUpdatePayload,
    TeamAddMembersPayload,
    TeamAddProjectsPayload,
    TeamAddClientsPayload,
} from '@/types/team';

export const useTeamStore = defineStore('team', () => {
    const queryClient = useQueryClient();

    /** Fetch all teams for the current organization */
    const {
        data: teams,
        isLoading,
        error,
    } = useQuery({
        queryKey: ['teams', getCurrentOrganizationId()],
        queryFn: async () => {
            const orgId = getCurrentOrganizationId();
            const response = await api.get<{ data: Team[] }>(
                `/v1/organizations/${orgId}/teams`
            );
            return response.data;
        },
    });

    /** Create a new team */
    const createTeam = useMutation({
        mutationFn: async (data: TeamCreatePayload) => {
            const orgId = getCurrentOrganizationId();
            return api.post(`/v1/organizations/${orgId}/teams`, data);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['teams'] });
        },
    });

    /** Update an existing team */
    const updateTeam = useMutation({
        mutationFn: async ({
            teamId,
            data,
        }: {
            teamId: string;
            data: TeamUpdatePayload;
        }) => {
            const orgId = getCurrentOrganizationId();
            return api.put(
                `/v1/organizations/${orgId}/teams/${teamId}`,
                data
            );
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['teams'] });
        },
    });

    /** Delete a team */
    const deleteTeam = useMutation({
        mutationFn: async (teamId: string) => {
            const orgId = getCurrentOrganizationId();
            return api.delete(`/v1/organizations/${orgId}/teams/${teamId}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['teams'] });
        },
    });

    /** Add members to a team */
    const addMembers = useMutation({
        mutationFn: async ({
            teamId,
            memberIds,
        }: {
            teamId: string;
            memberIds: string[];
        }) => {
            const orgId = getCurrentOrganizationId();
            return api.post(
                `/v1/organizations/${orgId}/teams/${teamId}/members`,
                { member_ids: memberIds } satisfies TeamAddMembersPayload
            );
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['teams'] });
        },
    });

    /** Remove a member from a team */
    const removeMember = useMutation({
        mutationFn: async ({
            teamId,
            memberId,
        }: {
            teamId: string;
            memberId: string;
        }) => {
            const orgId = getCurrentOrganizationId();
            return api.delete(
                `/v1/organizations/${orgId}/teams/${teamId}/members/${memberId}`
            );
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['teams'] });
        },
    });

    /** Add projects to a team */
    const addProjects = useMutation({
        mutationFn: async ({
            teamId,
            projectIds,
        }: {
            teamId: string;
            projectIds: string[];
        }) => {
            const orgId = getCurrentOrganizationId();
            return api.post(
                `/v1/organizations/${orgId}/teams/${teamId}/projects`,
                { project_ids: projectIds } satisfies TeamAddProjectsPayload
            );
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['teams'] });
        },
    });

    /** Remove a project from a team */
    const removeProject = useMutation({
        mutationFn: async ({
            teamId,
            projectId,
        }: {
            teamId: string;
            projectId: string;
        }) => {
            const orgId = getCurrentOrganizationId();
            return api.delete(
                `/v1/organizations/${orgId}/teams/${teamId}/projects/${projectId}`
            );
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['teams'] });
        },
    });

    /** Add clients to a team */
    const addClients = useMutation({
        mutationFn: async ({
            teamId,
            clientIds,
        }: {
            teamId: string;
            clientIds: string[];
        }) => {
            const orgId = getCurrentOrganizationId();
            return api.post(
                `/v1/organizations/${orgId}/teams/${teamId}/clients`,
                { client_ids: clientIds } satisfies TeamAddClientsPayload
            );
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['teams'] });
        },
    });

    /** Remove a client from a team */
    const removeClient = useMutation({
        mutationFn: async ({
            teamId,
            clientId,
        }: {
            teamId: string;
            clientId: string;
        }) => {
            const orgId = getCurrentOrganizationId();
            return api.delete(
                `/v1/organizations/${orgId}/teams/${teamId}/clients/${clientId}`
            );
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['teams'] });
        },
    });

    return {
        teams,
        isLoading,
        error,
        createTeam,
        updateTeam,
        deleteTeam,
        addMembers,
        removeMember,
        addProjects,
        removeProject,
        addClients,
        removeClient,
    };
});
```

### 12.3 UI Components

**Files to create** in `resources/js/packages/ui/src/Team/`:

| Component | Purpose |
|---|---|
| `TeamList.vue` | Paginated team list with color badges and counts |
| `TeamCard.vue` | Individual team display card with member/project/client counts |
| `TeamDetailTabs.vue` | Tabbed interface (Members / Projects / Clients) for team detail view |
| `TeamMemberList.vue` | Members tab: list members, add/remove with search |
| `TeamProjectList.vue` | Projects tab: list assigned projects, add/remove |
| `TeamClientList.vue` | Clients tab: list assigned clients, add/remove |
| `TeamCreateModal.vue` | Modal form for team creation (name + color picker) |
| `TeamEditModal.vue` | Modal form for team editing |

### 12.4 Team Management Page

**File**: `resources/js/Pages/Teams.vue`

Inertia page using `AppLayout`. Renders `TeamList` with create button. Clicking a team opens detail view with `TeamDetailTabs`.

### 12.5 Navigation Integration

**File**: `resources/js/Layouts/AppLayout.vue`

Add navigation item in the "Manage" section (after Members/Clients):

```vue
<NavigationSidebarItem
    v-if="can('teams:view')"
    :href="route('teams.index', { organization: currentOrganization.id })"
    :active="route().current('teams.*')"
>
    <template #icon>
        <UserGroupIcon class="w-5 h-5" />
    </template>
    Teams
</NavigationSidebarItem>
```

Import:
```typescript
import { UserGroupIcon } from '@heroicons/vue/20/solid';
```

### 12.6 Organization Settings Toggle

Add toggle in the Organization Settings page:

```vue
<SettingsToggle
    v-model="form.enable_team_scoping"
    label="Enable Team Scoping"
    description="When enabled, members only see projects, clients, and time entries
                 for their assigned teams. Admins and Owners always see all data."
/>
```

---

## 13. Task Scoping Through Projects

**Per AMD-07**: Tasks inherit team scoping through their parent Project. No separate `team_tasks` pivot table is needed.

**Implementation in TaskController**:
```php
$tasksQuery = Task::query()
    ->whereHas('project', function (Builder $q) use ($organization, $user, $teamScopeService): void {
        $projectQuery = $q->whereBelongsTo($organization, 'organization');
        $teamScopeService->applyProjectScope($projectQuery, $organization, $user);
    });
```

**Visibility Matrix**:
> Tasks are team-scoped implicitly via their parent project's team assignments. When a project is assigned to teams, all tasks within that project are visible to those teams.

---

## 14. Performance Considerations (AMD-09)

### Indexes

All pivot tables have composite indexes (covered by the unique constraint) and single-column indexes for reverse lookups:

| Table | Index | Purpose |
|---|---|---|
| `team_members` | `UNIQUE(team_id, member_id)` | Prevent duplicates + fast team-to-member lookup |
| `team_members` | `INDEX(member_id)` | Fast member-to-team lookup |
| `team_projects` | `UNIQUE(team_id, project_id)` | Prevent duplicates + fast team-to-project lookup |
| `team_projects` | `INDEX(project_id)` | Fast project-to-team lookup |
| `team_clients` | `UNIQUE(team_id, client_id)` | Prevent duplicates + fast team-to-client lookup |
| `team_clients` | `INDEX(client_id)` | Fast client-to-team lookup |

### Per-Request Cache

`TeamScopeService::$teamIdCache` stores team IDs keyed by member ID for the duration of a single HTTP request. The service is not registered as a singleton, so the cache is naturally cleared between requests. For testing, `clearCache()` is available.

### Benchmarking Target

- Less than 10% additional latency for team-scoped queries compared to non-scoped queries
- Must benchmark with 100+ teams and 1000+ projects during QA phase (TEAM-024)
- Run `EXPLAIN ANALYZE` on team-scoped queries to verify index usage

### Migration Performance

- Data migration processes organizations in chunks of 100
- Each organization's members/projects/clients are bulk-inserted
- Migration is idempotent (skips orgs with existing "Default" team)

---

## 15. Jetstream Naming Strategy (AMD-13)

**Challenge**: Jetstream's `Team` concept maps to solidtime's `Organization`. The new `Team` model introduces a naming collision at the concept level.

**Resolution**:
1. Solidtime's Team model lives at `App\Models\Team` -- no PHP namespace collision
2. Jetstream's team model is accessed via `Jetstream::teamModel()` which returns `Organization::class`
3. The `teams` database table does not conflict (Jetstream uses `organizations`)
4. A PHPDoc comment at the top of `Team.php` clarifies the distinction:
   ```php
   /**
    * Note: This is solidtime's Team (sub-org group),
    * not Jetstream's Team (which maps to Organization).
    */
   ```
5. In `AddOrganizationMember.php`, the Jetstream `$team` parameter is actually an Organization -- use `\App\Models\Team` with full namespace to avoid confusion

---

## 16. File Manifest

### New Files to Create (40 total)

#### Migrations (6)
1. `database/migrations/2026_03_10_000000_add_team_scoping_flag_to_organizations.php`
2. `database/migrations/2026_03_10_000001_create_teams_table.php`
3. `database/migrations/2026_03_10_000002_create_team_members_table.php`
4. `database/migrations/2026_03_10_000003_create_team_projects_table.php`
5. `database/migrations/2026_03_10_000004_create_team_clients_table.php`
6. `database/migrations/2026_03_10_100001_seed_default_teams_for_existing_organizations.php`

#### Models (4)
7. `app/Models/Team.php`
8. `app/Models/TeamMember.php`
9. `app/Models/TeamProject.php`
10. `app/Models/TeamClient.php`

#### Factories (4)
11. `database/factories/TeamFactory.php`
12. `database/factories/TeamMemberFactory.php`
13. `database/factories/TeamProjectFactory.php`
14. `database/factories/TeamClientFactory.php`

#### Services (2)
15. `app/Service/TeamService.php`
16. `app/Service/TeamScopeService.php`

#### Permissions (1)
17. `app/Permissions/TeamPermissions.php`

#### Controllers (1)
18. `app/Http/Controllers/Api/V1/TeamController.php`

#### Requests (6)
19. `app/Http/Requests/V1/Team/TeamIndexRequest.php`
20. `app/Http/Requests/V1/Team/TeamStoreRequest.php`
21. `app/Http/Requests/V1/Team/TeamUpdateRequest.php`
22. `app/Http/Requests/V1/Team/TeamAddMembersRequest.php`
23. `app/Http/Requests/V1/Team/TeamAddProjectsRequest.php`
24. `app/Http/Requests/V1/Team/TeamAddClientsRequest.php`

#### Resources (2)
25. `app/Http/Resources/V1/Team/TeamResource.php`
26. `app/Http/Resources/V1/Team/TeamCollection.php`

#### Frontend Page (1)
27. `resources/js/Pages/Teams.vue`

#### Frontend Components (7)
28. `resources/js/packages/ui/src/Team/TeamList.vue`
29. `resources/js/packages/ui/src/Team/TeamCard.vue`
30. `resources/js/packages/ui/src/Team/TeamDetailTabs.vue`
31. `resources/js/packages/ui/src/Team/TeamMemberList.vue`
32. `resources/js/packages/ui/src/Team/TeamProjectList.vue`
33. `resources/js/packages/ui/src/Team/TeamClientList.vue`
34. `resources/js/packages/ui/src/Team/TeamCreateModal.vue`

#### Pinia Store (1)
35. `resources/js/utils/useTeam.ts`

#### TypeScript Types (1)
36. `resources/js/types/team.d.ts`

#### Tests (4)
37. `tests/Unit/Service/TeamServiceTest.php`
38. `tests/Unit/Service/TeamScopeServiceTest.php`
39. `tests/Unit/Endpoint/Api/V1/TeamEndpointTest.php`
40. `tests/Feature/TeamScopingTest.php`

### Files to Modify (20 total)

#### Models (4)
1. `app/Models/Organization.php` -- Add `teams()` relationship, `enable_team_scoping` cast, PHPDoc
2. `app/Models/Member.php` -- Add `teamMembers()` and `teams()` relationships, PHPDoc
3. `app/Models/Project.php` -- Add `teamProjects()` and `teams()` relationships, PHPDoc
4. `app/Models/Client.php` -- Add `teamClients()` and `teams()` relationships, PHPDoc

#### Controllers (5)
5. `app/Http/Controllers/Api/V1/ProjectController.php` -- Inject `TeamScopeService` in `index()`
6. `app/Http/Controllers/Api/V1/ClientController.php` -- Inject `TeamScopeService` in `index()`
7. `app/Http/Controllers/Api/V1/TimeEntryController.php` -- Inject `TeamScopeService` in query building
8. `app/Http/Controllers/Api/V1/ChartController.php` -- Pass `TeamScopeService` to dashboard service
9. `app/Http/Controllers/Api/V1/OrganizationController.php` -- Add `enable_team_scoping` update logic

#### Services (1)
10. `app/Service/TimeEntryFilter.php` -- Add `addTeamIdsFilter()` method

#### Providers (1)
11. `app/Providers/JetstreamServiceProvider.php` -- Add `TeamPermissions::register()` call

#### Requests (3)
12. `app/Http/Requests/V1/Organization/OrganizationUpdateRequest.php` -- Add `enable_team_scoping` validation
13. `app/Http/Requests/V1/TimeEntry/TimeEntryIndexRequest.php` -- Add `team_ids[]` parameter
14. `app/Http/Requests/V1/TimeEntry/TimeEntryAggregateRequest.php` -- Add `team_ids[]` parameter

#### Resources (1)
15. `app/Http/Resources/V1/Organization/OrganizationResource.php` -- Expose `enable_team_scoping`

#### Routes (2)
16. `routes/api.php` -- Add team routes group
17. `routes/web.php` -- Add Teams page route (Inertia)

#### Frontend (2)
18. `resources/js/Layouts/AppLayout.vue` -- Add Teams navigation item
19. Organization Settings page -- Add team scoping toggle

#### Actions (1)
20. `app/Actions/Jetstream/AddOrganizationMember.php` -- Add auto-assignment to Default team

---

## 17. Build Sequence -- Phased Implementation

### Phase 1: Foundation (Week 1-2)
- [ ] TEAM-001: Create all 6 migrations (feature flag, tables, data migration)
- [ ] TEAM-002: Run migrations on test database, verify schema
- [ ] TEAM-003: Create all 4 models (Team, TeamMember, TeamProject, TeamClient)
- [ ] TEAM-004: Create all 4 factories
- [ ] TEAM-005: Add relationships to existing models (Organization, Member, Project, Client)
- [ ] TEAM-006: Run `composer analyse` and fix PHPStan errors
- [ ] TEAM-007: Create `TeamPermissions` class
- [ ] TEAM-008: Modify `JetstreamServiceProvider` to register permissions

### Phase 2: Business Logic (Week 2-3)
- [ ] TEAM-009: Create `TeamService` class with all CRUD methods
- [ ] TEAM-010: Create `TeamScopeService` class with query filtering logic
- [ ] TEAM-011: Write unit tests for `TeamService`
- [ ] TEAM-012: Write unit tests for `TeamScopeService`

### Phase 3: API Layer (Week 3-4)
- [ ] TEAM-013: Create all 6 Request validation classes
- [ ] TEAM-014: Create `TeamResource` and `TeamCollection`
- [ ] TEAM-015: Create `TeamController` with all endpoints
- [ ] TEAM-016: Add routes to `routes/api.php`
- [ ] TEAM-017: Modify `TimeEntryFilter` to add `addTeamIdsFilter()` method
- [ ] TEAM-018: Write endpoint tests for `TeamController`

### Phase 4: Query Integration (Week 4-5)
- [ ] TEAM-019: Modify `ProjectController::index()` to inject `TeamScopeService`
- [ ] TEAM-020: Modify `ClientController::index()` to inject `TeamScopeService`
- [ ] TEAM-021: Modify `TimeEntryController` query logic to inject `TeamScopeService`
- [ ] TEAM-022: Modify `ChartController` methods to support team scoping
- [ ] TEAM-023: Write integration tests for team-scoped queries
- [ ] TEAM-024: Benchmark query performance (100 teams, 1000 projects)

### Phase 5: Organization Settings (Week 5)
- [ ] TEAM-025: Modify `OrganizationUpdateRequest` to support `enable_team_scoping`
- [ ] TEAM-026: Modify `OrganizationController::update()` to handle flag
- [ ] TEAM-027: Modify `OrganizationResource` to expose flag
- [ ] TEAM-028: Add auto-assignment logic in `AddOrganizationMember` action

### Phase 6: Frontend (Week 5-7)
- [ ] TEAM-029: Create TypeScript types in `team.d.ts`
- [ ] TEAM-030: Create Pinia store `useTeam.ts`
- [ ] TEAM-031: Create all 7 UI components (TeamList, TeamCard, etc.)
- [ ] TEAM-032: Create Teams page (`Pages/Teams.vue`)
- [ ] TEAM-033: Add navigation item in `AppLayout.vue`
- [ ] TEAM-034: Add team scoping toggle in Organization Settings page
- [ ] TEAM-035: Write component tests with Vitest
- [ ] TEAM-036: Write E2E tests with Playwright

### Phase 7: Documentation and Polishing (Week 7)
- [ ] TEAM-037: Update OpenAPI spec with team endpoints
- [ ] TEAM-038: Regenerate TypeScript API client
- [ ] TEAM-039: Add JSDoc comments to Pinia store
- [ ] TEAM-040: Run full test suite (`composer test`, `npm test`, `npm run e2e`)
- [ ] TEAM-041: Run `composer fix && composer analyse`
- [ ] TEAM-042: Run `npm run lint:fix && npm run format`

---

## 18. Critical Details

### Error Handling
- **422 Validation Errors**: Duplicate team names, invalid member/project/client IDs, removing member from last team
- **403 Authorization**: Non-admin attempts CRUD, member not in org, team not in org
- **409 Conflict**: Deleting team with assignments (`EntityStillInUseApiException`)
- **404 Not Found**: Invalid team/member/project/client UUIDs

### State Management
- Pinia store caches team list per organization
- `@tanstack/vue-query` handles cache invalidation on mutations
- `TeamScopeService` has per-request cache for team IDs (cleared between requests)

### Testing Strategy
- **Unit Tests** (`tests/Unit/Service/`): `TeamService`, `TeamScopeService` -- all business logic methods
- **Endpoint Tests** (`tests/Unit/Endpoint/Api/V1/`): All `TeamController` methods with permission checks, extending `ApiEndpointTestAbstract`
- **Integration Tests** (`tests/Feature/`): Team scoping applied to ProjectController, ClientController, TimeEntryController
- **E2E Tests** (`e2e/`): Playwright tests for Teams page, team assignment flows

### Security
- All team operations require appropriate permissions via `checkPermission()`
- `TeamScopeService` bypasses scoping for Admin/Owner roles
- Team IDs in API filters are validated against organization ownership
- Cross-org leakage prevented via `organization_id` checks on all entities
- All operations are auditable via `CustomAuditable` trait

### Cross-Feature Integration (AMD-12)
- **Feature 07 (PTO)**: PTO list endpoints should accept `team_ids` filter when team scoping is enabled
- **Feature 08 (Scheduling)**: Scheduling and assignment views should support team-based filtering
- **Feature 09 (Reporting)**: Report endpoints should accept `team_ids` filter; profitability/utilization reports should support team-level aggregation

---

## 19. Success Criteria

- [ ] All migrations run successfully on existing database
- [ ] Default team created for all existing organizations (idempotent)
- [ ] Team CRUD works via API and UI
- [ ] Team scoping applies correctly when flag is enabled
- [ ] Admin/Owner always see all data regardless of scoping flag
- [ ] Employees and Managers see only their team's data when scoping is enabled
- [ ] TimeEntryFilter accepts `team_ids` parameter and filters correctly
- [ ] Organization settings toggle controls scoping behavior
- [ ] New member auto-assigned to Default team when scoping is enabled (AMD-06)
- [ ] Tasks inherit team scoping from parent project (AMD-07)
- [ ] All tests pass (unit, endpoint, integration, E2E)
- [ ] PHPStan analysis passes (`composer analyse`)
- [ ] ESLint + Prettier pass on frontend code (`npm run lint:fix && npm run format`)
- [ ] Performance benchmarks meet less than 10% latency target (AMD-09)

---

## 20. Implementation Notes

1. **Order of Operations**: Complete Phase 1 (migrations) first. Existing data must have Default teams before any query modifications are applied.

2. **Feature Flag Testing**: Test both states (`enable_team_scoping = false` and `true`) thoroughly. The flag-off state must be fully backward-compatible with zero behavior changes.

3. **Migration Rollback**: Test the rollback path. Cascading deletes on foreign keys should clean up all pivot records when Default teams are deleted in `down()`.

4. **IDE Confusion**: Use full namespace `App\Models\Team` when referencing the model in code that also touches Jetstream concepts (e.g., `AddOrganizationMember`). The Jetstream `$team` parameter in that action is actually an Organization.

5. **Permission Caching**: `PermissionStore` caches permissions per user+org. Clear cache in tests when modifying roles.

6. **Query Performance**: Run `EXPLAIN ANALYZE` on team-scoped queries to verify indexes are being used. The unique constraints on pivot tables double as composite indexes.

7. **Frontend State**: Use `@tanstack/vue-query` for server state management. Avoid duplicating team data in multiple Pinia stores.

8. **Audit Trail**: All team operations are auditable via `CustomAuditable` trait. Verify audit logs capture team creation, member/project/client assignments, and deletions.

9. **Task Scoping**: Tasks inherit team scoping from their parent project (AMD-07). No separate task-team relationship is needed, which avoids an additional pivot table and simplifies the data model.

10. **Manager Visibility**: Managers only get `teams:view`, not `teams:view:all` (AMD-08). This is intentional to prevent "manager creep" where managers gradually gain org-wide visibility.

11. **TEAM-013 Effort**: Per AMD-11, `TeamScopeService` integration (TEAM-010 + TEAM-019 through TEAM-022) is cross-cutting and realistically requires 10-12 hours rather than the original 8-hour estimate.

---

**End of Architecture Blueprint**

This document provides a complete, actionable architecture for the Teams & Groups feature. All architectural decisions are based on actual codebase patterns discovered during analysis. Implementation can proceed immediately following the phased build sequence.
