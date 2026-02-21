# ARCHITECTURE: Kiosk & Clock Mode

**Feature ID**: 06-kiosk-clock-mode  
**Date**: 2026-02-06  
**Status**: Architecture Blueprint  
**PRD Reference**: `/home/keven/Documents/solidtime-analysis/.features/06-kiosk-clock-mode/PRD.md`

---

## Executive Summary

This architecture implements a complete kiosk & clock mode system for Solidtime, enabling shared-device time tracking with PIN/QR authentication, clock in/out/break functionality, and real-time attendance monitoring. The feature introduces a custom authentication guard, dual-hash PIN security per AMD-04, standalone Vue SPA for kiosk terminals, and comprehensive admin controls.

**Key Architectural Decisions**:
- Custom `auth:kiosk` guard for token-based device authentication (separate from Passport/Jetstream)
- Dual-hash PIN design: SHA-256 for uniqueness, bcrypt for verification (AMD-04)
- Standalone Vue SPA for kiosk UI (not Inertia) with direct API calls
- `KioskSession` as state machine tracking clock-in/break/out lifecycle
- Badge QR tokens with configurable TTL for printed badges (AMD-05)
- UTC storage with org timezone display (AMD-07)
- Token-in-URL mitigation via HTTPS enforcement, expiry, and revocation (AMD-08)

---

## 1. Data Model Architecture

### 1.1 Database Schema

#### Table: `kiosks`

```sql
CREATE TABLE kiosks (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(500) NULL,
    mode VARCHAR(20) NOT NULL DEFAULT 'full', -- 'full' or 'punch_only'
    default_project_id UUID NULL,
    is_active BOOLEAN NOT NULL DEFAULT true,
    token_hash VARCHAR(64) NOT NULL UNIQUE, -- SHA-256 of 64-char hex token
    token_expires_at TIMESTAMP NULL, -- Configurable expiry per AMD-08
    last_activity_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    
    CONSTRAINT fk_kiosks_organization FOREIGN KEY (organization_id)
        REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_kiosks_default_project FOREIGN KEY (default_project_id)
        REFERENCES projects(id) ON DELETE SET NULL
);

CREATE INDEX idx_kiosks_organization_id ON kiosks(organization_id);
CREATE INDEX idx_kiosks_token_hash ON kiosks(token_hash); -- Critical for auth lookup
CREATE INDEX idx_kiosks_is_active ON kiosks(is_active) WHERE is_active = true;
```

**Design Rationale**:
- `token_hash` stored as SHA-256 (not bcrypt) for deterministic lookup performance
- `token_expires_at` allows admin-configurable token rotation (AMD-08)
- `last_activity_at` throttled updates (once per minute) to track kiosk health without DB pressure
- `default_project_id` enables punch-only mode with auto-project assignment

---

#### Table: `kiosk_sessions`

```sql
CREATE TABLE kiosk_sessions (
    id UUID PRIMARY KEY,
    kiosk_id UUID NOT NULL,
    member_id UUID NOT NULL,
    organization_id UUID NOT NULL, -- Denormalized for query performance
    status VARCHAR(20) NOT NULL DEFAULT 'clocked_in', -- 'clocked_in', 'on_break', 'clocked_out'
    clock_in_at TIMESTAMP NOT NULL,
    clock_out_at TIMESTAMP NULL,
    on_break_since TIMESTAMP NULL,
    total_break_seconds INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    
    CONSTRAINT fk_kiosk_sessions_kiosk FOREIGN KEY (kiosk_id)
        REFERENCES kiosks(id) ON DELETE CASCADE,
    CONSTRAINT fk_kiosk_sessions_member FOREIGN KEY (member_id)
        REFERENCES members(id) ON DELETE CASCADE,
    CONSTRAINT fk_kiosk_sessions_organization FOREIGN KEY (organization_id)
        REFERENCES organizations(id) ON DELETE CASCADE
);

CREATE INDEX idx_kiosk_sessions_member_id ON kiosk_sessions(member_id);
CREATE INDEX idx_kiosk_sessions_status ON kiosk_sessions(status);
CREATE INDEX idx_kiosk_sessions_organization_status ON kiosk_sessions(organization_id, status);
CREATE INDEX idx_kiosk_sessions_active ON kiosk_sessions(member_id, status) 
    WHERE status IN ('clocked_in', 'on_break'); -- Critical for active session lookup
```

**Design Rationale**:
- `organization_id` denormalized to avoid JOIN on attendance dashboard queries (500+ members)
- `total_break_seconds` accumulated for accurate shift reporting
- Index on `(organization_id, status)` optimizes attendance dashboard summary queries
- Partial index on active sessions (`clocked_in`, `on_break`) for overlap detection

---

#### Table: `kiosk_pin_attempts`

```sql
CREATE TABLE kiosk_pin_attempts (
    id UUID PRIMARY KEY,
    kiosk_id UUID NOT NULL,
    member_id UUID NULL, -- NULL if PIN doesn't match any member
    organization_id UUID NOT NULL,
    pin_entered VARCHAR(4) NOT NULL, -- Stored temporarily for rate limiting
    success BOOLEAN NOT NULL DEFAULT false,
    attempted_at TIMESTAMP NOT NULL,
    ip_address VARCHAR(45) NULL, -- IPv6 support
    
    CONSTRAINT fk_kiosk_pin_attempts_kiosk FOREIGN KEY (kiosk_id)
        REFERENCES kiosks(id) ON DELETE CASCADE,
    CONSTRAINT fk_kiosk_pin_attempts_member FOREIGN KEY (member_id)
        REFERENCES members(id) ON DELETE SET NULL,
    CONSTRAINT fk_kiosk_pin_attempts_organization FOREIGN KEY (organization_id)
        REFERENCES organizations(id) ON DELETE CASCADE
);

CREATE INDEX idx_kiosk_pin_attempts_rate_limit 
    ON kiosk_pin_attempts(kiosk_id, member_id, attempted_at) 
    WHERE success = false; -- Optimizes rate-limit queries
```

**Design Rationale**:
- `pin_entered` stored for forensic analysis (not for verification)
- Rate limiting query: `WHERE kiosk_id = ? AND member_id = ? AND success = false AND attempted_at > now() - interval '15 minutes'`
- Partial index on failed attempts reduces index size
- Auto-cleanup job deletes records older than 30 days

---

#### Migration: `members` Table Additions

```sql
ALTER TABLE members 
ADD COLUMN kiosk_pin_check VARCHAR(64) NULL UNIQUE, -- SHA-256(org_id + ':' + pin) for uniqueness
ADD COLUMN kiosk_pin_hash VARCHAR(255) NULL,        -- bcrypt for verification
ADD COLUMN kiosk_badge_token VARCHAR(64) NULL UNIQUE, -- For printed badge QR codes (AMD-05)
ADD COLUMN kiosk_badge_token_expires_at TIMESTAMP NULL;

CREATE INDEX idx_members_kiosk_pin_check ON members(kiosk_pin_check) WHERE kiosk_pin_check IS NOT NULL;
CREATE INDEX idx_members_kiosk_badge_token ON members(kiosk_badge_token) WHERE kiosk_badge_token IS NOT NULL;
```

**Design Rationale (AMD-04 Dual-Hash PIN)**:
- `kiosk_pin_check`: Deterministic hash (SHA-256) for database-level uniqueness constraint
  - Formula: `SHA-256(organization_id + ':' + pin_plaintext)`
  - Organization ID salt prevents cross-org PIN collision detection
  - NEVER used for authentication
- `kiosk_pin_hash`: Non-deterministic bcrypt for secure verification
  - Used with `Hash::check($pin, $kiosk_pin_hash)` during authentication
  - Cost factor 10 (Laravel default)
- `kiosk_badge_token`: Long-lived QR token for printed badges (AMD-05)
  - 64-character cryptographically random hex
  - Admin-configurable expiry (24h, 7d, 30d, or NULL for no expiry)
  - Admin can revoke at any time

**Security Properties**:
- Uniqueness check happens at application layer AND database layer (constraint)
- Even with SHA-256 hash leaked, attacker cannot authenticate (bcrypt required)
- Org-scoped salt prevents rainbow table attacks across organizations
- Badge tokens are revocable independent of dynamic QR tokens

---

### 1.2 Eloquent Models

#### `Kiosk` Model

**File**: `app/Models/Kiosk.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KioskMode;
use App\Models\Concerns\CustomAuditable;
use App\Models\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string|null $location
 * @property KioskMode $mode
 * @property string|null $default_project_id
 * @property bool $is_active
 * @property string $token_hash
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $last_activity_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Organization $organization
 * @property-read Project|null $defaultProject
 * @property-read Collection<int, KioskSession> $sessions
 */
class Kiosk extends Model implements AuditableContract
{
    use CustomAuditable;
    use HasFactory;
    use HasUuids;

    protected $casts = [
        'mode' => KioskMode::class,
        'is_active' => 'bool',
        'token_expires_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    protected $fillable = [
        'name',
        'location',
        'mode',
        'default_project_id',
        'is_active',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function defaultProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'default_project_id');
    }

    /**
     * @return HasMany<KioskSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(KioskSession::class);
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
```

---

#### `KioskSession` Model

**File**: `app/Models/KioskSession.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KioskSessionStatus;
use App\Models\Concerns\CustomAuditable;
use App\Models\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property string $id
 * @property string $kiosk_id
 * @property string $member_id
 * @property string $organization_id
 * @property KioskSessionStatus $status
 * @property Carbon $clock_in_at
 * @property Carbon|null $clock_out_at
 * @property Carbon|null $on_break_since
 * @property int $total_break_seconds
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Kiosk $kiosk
 * @property-read Member $member
 * @property-read Organization $organization
 */
class KioskSession extends Model implements AuditableContract
{
    use CustomAuditable;
    use HasFactory;
    use HasUuids;

    protected $casts = [
        'status' => KioskSessionStatus::class,
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
        'on_break_since' => 'datetime',
        'total_break_seconds' => 'int',
    ];

    protected $fillable = [
        'status',
        'clock_in_at',
        'clock_out_at',
        'on_break_since',
        'total_break_seconds',
    ];

    /**
     * @return BelongsTo<Kiosk, $this>
     */
    public function kiosk(): BelongsTo
    {
        return $this->belongsTo(Kiosk::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            KioskSessionStatus::ClockedIn,
            KioskSessionStatus::OnBreak,
        ], true);
    }

    public function getTotalWorkSeconds(): int
    {
        if ($this->clock_out_at === null) {
            $totalSeconds = now()->diffInSeconds($this->clock_in_at);
        } else {
            $totalSeconds = $this->clock_out_at->diffInSeconds($this->clock_in_at);
        }

        return max(0, $totalSeconds - $this->total_break_seconds);
    }
}
```

---

#### `KioskPinAttempt` Model

**File**: `app/Models/KioskPinAttempt.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $kiosk_id
 * @property string|null $member_id
 * @property string $organization_id
 * @property string $pin_entered
 * @property bool $success
 * @property Carbon $attempted_at
 * @property string|null $ip_address
 * @property-read Kiosk $kiosk
 * @property-read Member|null $member
 * @property-read Organization $organization
 */
class KioskPinAttempt extends Model
{
    use HasFactory;
    use HasUuids;

    const UPDATED_AT = null; // No updated_at column

    protected $casts = [
        'success' => 'bool',
        'attempted_at' => 'datetime',
    ];

    protected $fillable = [
        'pin_entered',
        'success',
        'attempted_at',
        'ip_address',
    ];

    /**
     * @return BelongsTo<Kiosk, $this>
     */
    public function kiosk(): BelongsTo
    {
        return $this->belongsTo(Kiosk::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
```

---

#### Enums

**File**: `app/Enums/KioskMode.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum KioskMode: string
{
    case Full = 'full';
    case PunchOnly = 'punch_only';
}
```

**File**: `app/Enums/KioskSessionStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum KioskSessionStatus: string
{
    case ClockedIn = 'clocked_in';
    case OnBreak = 'on_break';
    case ClockedOut = 'clocked_out';
}
```

---

### 1.3 Model Relationships

#### `Member` Model Additions

**File**: `app/Models/Member.php` (modifications)

```php
/**
 * @property string|null $kiosk_pin_check
 * @property string|null $kiosk_pin_hash
 * @property string|null $kiosk_badge_token
 * @property Carbon|null $kiosk_badge_token_expires_at
 */

protected $casts = [
    // ... existing casts
    'kiosk_badge_token_expires_at' => 'datetime',
];

protected $hidden = [
    // ... existing hidden
    'kiosk_pin_hash',
    'kiosk_pin_check',
    'kiosk_badge_token',
];

/**
 * @return HasMany<KioskSession, $this>
 */
public function kioskSessions(): HasMany
{
    return $this->hasMany(KioskSession::class);
}

/**
 * @return HasMany<KioskPinAttempt, $this>
 */
public function kioskPinAttempts(): HasMany
{
    return $this->hasMany(KioskPinAttempt::class);
}

public function hasKioskPin(): bool
{
    return $this->kiosk_pin_hash !== null;
}

public function hasValidBadgeToken(): bool
{
    return $this->kiosk_badge_token !== null 
        && ($this->kiosk_badge_token_expires_at === null 
            || $this->kiosk_badge_token_expires_at->isFuture());
}
```

#### `Organization` Model Additions

**File**: `app/Models/Organization.php` (modifications)

```php
/**
 * @return HasMany<Kiosk, $this>
 */
public function kiosks(): HasMany
{
    return $this->hasMany(Kiosk::class);
}
```

---

## 2. Authentication Architecture

### 2.1 Custom Kiosk Guard

The kiosk guard is a token-based authentication mechanism independent of Passport and Jetstream, designed for shared-device scenarios where no user session exists.

#### Guard Registration

**File**: `config/auth.php` (additions)

```php
'guards' => [
    // ... existing guards
    'kiosk' => [
        'driver' => 'kiosk-token',
        'provider' => 'kiosks',
    ],
],

'providers' => [
    // ... existing providers
    'kiosks' => [
        'driver' => 'kiosk-token-provider',
        'model' => App\Models\Kiosk::class,
    ],
],
```

---

#### Kiosk Guard Implementation

**File**: `app/Auth/KioskGuard.php`

```php
<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\Kiosk;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class KioskGuard implements Guard
{
    protected ?Kiosk $kiosk = null;
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function check(): bool
    {
        return $this->kiosk !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?Kiosk
    {
        if ($this->kiosk !== null) {
            return $this->kiosk;
        }

        $token = $this->getTokenFromRequest();
        if ($token === null) {
            return null;
        }

        $tokenHash = hash('sha256', $token);

        // Cache kiosk lookup for 60 seconds to reduce DB load
        $this->kiosk = Cache::remember(
            "kiosk_token:{$tokenHash}",
            60,
            fn() => Kiosk::query()
                ->where('token_hash', $tokenHash)
                ->where('is_active', true)
                ->whereRaw('(token_expires_at IS NULL OR token_expires_at > NOW())')
                ->first()
        );

        if ($this->kiosk !== null) {
            $this->updateLastActivity($this->kiosk);
        }

        return $this->kiosk;
    }

    public function id(): ?string
    {
        return $this->kiosk?->getKey();
    }

    public function validate(array $credentials = []): bool
    {
        return false; // Not used for token-based auth
    }

    public function hasUser(): bool
    {
        return $this->kiosk !== null;
    }

    public function setUser(Authenticatable $user): void
    {
        if ($user instanceof Kiosk) {
            $this->kiosk = $user;
        }
    }

    protected function getTokenFromRequest(): ?string
    {
        $header = $this->request->header('Authorization');
        if ($header === null || ! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return substr($header, 7);
    }

    protected function updateLastActivity(Kiosk $kiosk): void
    {
        // Throttle updates to once per minute to avoid excessive writes
        $cacheKey = "kiosk_activity_updated:{$kiosk->id}";
        if (Cache::has($cacheKey)) {
            return;
        }

        DB::table('kiosks')
            ->where('id', $kiosk->id)
            ->update(['last_activity_at' => now()]);

        Cache::put($cacheKey, true, 60);
    }
}
```

---

#### Kiosk Token Provider

**File**: `app/Auth/KioskTokenProvider.php`

```php
<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\Kiosk;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class KioskTokenProvider implements UserProvider
{
    public function retrieveById($identifier): ?Kiosk
    {
        return Kiosk::query()->find($identifier);
    }

    public function retrieveByToken($identifier, $token): ?Kiosk
    {
        $tokenHash = hash('sha256', $token);
        return Kiosk::query()
            ->where('id', $identifier)
            ->where('token_hash', $tokenHash)
            ->where('is_active', true)
            ->first();
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        // Not applicable for kiosk tokens
    }

    public function retrieveByCredentials(array $credentials): ?Kiosk
    {
        return null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return false;
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // Not applicable
    }
}
```

---

#### Guard Registration in AuthServiceProvider

**File**: `app/Providers/AuthServiceProvider.php` (additions)

```php
use App\Auth\KioskGuard;
use App\Auth\KioskTokenProvider;
use Illuminate\Support\Facades\Auth;

public function boot(): void
{
    // ... existing code

    Auth::provider('kiosk-token-provider', function ($app, array $config) {
        return new KioskTokenProvider();
    });

    Auth::extend('kiosk-token', function ($app, $name, array $config) {
        return new KioskGuard($app['request']);
    });
}
```

---

### 2.2 Kiosk Middleware

**File**: `app/Http/Middleware/AuthenticateKiosk.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateKiosk
{
    /**
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth('kiosk')->check()) {
            throw new AuthenticationException('Unauthenticated.', ['kiosk']);
        }

        return $next($request);
    }
}
```

**File**: `app/Http/Kernel.php` (additions)

```php
protected $middlewareAliases = [
    // ... existing
    'auth.kiosk' => \App\Http\Middleware\AuthenticateKiosk::class,
];
```

---

### 2.3 PIN Authentication Flow

**Dual-Hash Workflow (AMD-04)**:

1. **PIN Setup** (Admin/Member):
   ```php
   // In MemberPinService::setPin()
   $pinPlaintext = $request->input('pin'); // e.g., "1234"
   
   // Step 1: Generate uniqueness-check hash (SHA-256 with org salt)
   $pinCheck = hash('sha256', $member->organization_id . ':' . $pinPlaintext);
   
   // Step 2: Check uniqueness in database
   $duplicate = Member::query()
       ->where('organization_id', $member->organization_id)
       ->where('kiosk_pin_check', $pinCheck)
       ->where('id', '!=', $member->id)
       ->exists();
   
   if ($duplicate) {
       throw new ValidationException('PIN already in use by another member');
   }
   
   // Step 3: Generate bcrypt hash for verification
   $pinHash = Hash::make($pinPlaintext);
   
   // Step 4: Store both hashes
   $member->update([
       'kiosk_pin_check' => $pinCheck,  // For uniqueness enforcement
       'kiosk_pin_hash' => $pinHash,    // For authentication
   ]);
   ```

2. **PIN Verification** (Kiosk):
   ```php
   // In KioskDeviceController::authPin()
   $pinEntered = $request->input('pin'); // e.g., "1234"
   $kiosk = auth('kiosk')->user();
   
   // Step 1: Find member by organization (no PIN-based lookup yet)
   $member = Member::query()
       ->where('organization_id', $kiosk->organization_id)
       ->whereNotNull('kiosk_pin_hash')
       ->get()
       ->first(fn($m) => Hash::check($pinEntered, $m->kiosk_pin_hash));
   
   if ($member === null) {
       // Log failed attempt
       KioskPinAttempt::create([
           'kiosk_id' => $kiosk->id,
           'member_id' => null,
           'organization_id' => $kiosk->organization_id,
           'pin_entered' => $pinEntered,
           'success' => false,
           'attempted_at' => now(),
           'ip_address' => $request->ip(),
       ]);
       
       return response()->json([
           'error' => 'invalid_pin',
           'message' => 'Invalid PIN',
       ], 401);
   }
   
   // Step 2: Check rate limiting
   $failedAttempts = KioskPinAttempt::query()
       ->where('kiosk_id', $kiosk->id)
       ->where('member_id', $member->id)
       ->where('success', false)
       ->where('attempted_at', '>', now()->subMinutes(15))
       ->count();
   
   if ($failedAttempts >= 5) {
       return response()->json([
           'error' => 'locked_out',
           'message' => 'Too many failed attempts',
           'locked_until' => now()->addMinutes(10)->toIso8601String(),
       ], 429);
   }
   
   // Step 3: Log successful attempt
   KioskPinAttempt::create([
       'kiosk_id' => $kiosk->id,
       'member_id' => $member->id,
       'organization_id' => $kiosk->organization_id,
       'pin_entered' => $pinEntered,
       'success' => true,
       'attempted_at' => now(),
       'ip_address' => $request->ip(),
   ]);
   
   // Step 4: Return member info + session status
   return KioskMemberResource::make($member)->additional([
       'session' => $member->kioskSessions()
           ->where('status', '!=', 'clocked_out')
           ->latest()
           ->first(),
   ]);
   ```

**Security Properties**:
- SHA-256 hash never used for authentication (only bcrypt)
- Org-scoped salt prevents cross-org collision detection
- Database constraint enforces PIN uniqueness
- Rate limiting prevents brute-force attacks
- Failed attempts logged for audit

---

### 2.4 QR Authentication Flow

**Dynamic QR (5-minute TTL)**:

```php
// In MemberPinController::generateQrToken()
use Firebase\JWT\JWT;

$payload = [
    'member_id' => $member->id,
    'organization_id' => $member->organization_id,
    'type' => 'dynamic', // vs 'badge'
    'iat' => time(),
    'exp' => time() + 300, // 5 minutes
];

$token = JWT::encode($payload, config('app.key'), 'HS256');

return response()->json([
    'data' => [
        'qr_payload' => $token,
        'expires_at' => now()->addMinutes(5)->toIso8601String(),
    ],
]);
```

**Badge QR (Configurable TTL) - AMD-05**:

```php
// In MemberPinController::generateBadgeToken()
$token = bin2hex(random_bytes(32)); // 64-char hex
$expiresAt = match($request->input('ttl')) {
    '24h' => now()->addHours(24),
    '7d' => now()->addDays(7),
    '30d' => now()->addDays(30),
    'none' => null,
    default => now()->addDays(7),
};

$member->update([
    'kiosk_badge_token' => hash('sha256', $token), // Store hash
    'kiosk_badge_token_expires_at' => $expiresAt,
]);

// Generate JWT with badge token reference
$payload = [
    'badge_token' => $token, // Plaintext token (only shown once)
    'member_id' => $member->id,
    'organization_id' => $member->organization_id,
    'type' => 'badge',
    'iat' => time(),
    'exp' => $expiresAt?->timestamp,
];

$qrPayload = JWT::encode($payload, config('app.key'), 'HS256');

return response()->json([
    'data' => [
        'qr_payload' => $qrPayload,
        'token' => $token, // Show plaintext only once for admin to print
        'expires_at' => $expiresAt?->toIso8601String(),
    ],
]);
```

**QR Verification**:

```php
// In KioskDeviceController::authQr()
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

try {
    $payload = JWT::decode(
        $request->input('qr_payload'),
        new Key(config('app.key'), 'HS256')
    );
} catch (\Exception $e) {
    return response()->json([
        'error' => 'invalid_qr',
        'message' => 'Could not decode QR code',
    ], 401);
}

// Verify organization match
$kiosk = auth('kiosk')->user();
if ($payload->organization_id !== $kiosk->organization_id) {
    return response()->json([
        'error' => 'invalid_qr',
        'message' => 'QR code is for a different organization',
    ], 401);
}

// If badge type, verify badge token
if ($payload->type === 'badge') {
    $member = Member::query()
        ->where('id', $payload->member_id)
        ->where('kiosk_badge_token', hash('sha256', $payload->badge_token))
        ->whereRaw('(kiosk_badge_token_expires_at IS NULL OR kiosk_badge_token_expires_at > NOW())')
        ->first();
} else {
    // Dynamic QR - just verify member exists
    $member = Member::query()->find($payload->member_id);
}

if ($member === null) {
    return response()->json([
        'error' => 'invalid_qr',
        'message' => 'QR code is invalid or expired',
    ], 401);
}

return KioskMemberResource::make($member)->additional([
    'session' => $member->kioskSessions()
        ->where('status', '!=', 'clocked_out')
        ->latest()
        ->first(),
]);
```

---

## 3. API Contract

### 3.1 Admin Kiosk Management API (`auth:api`)

**Base Route**: `/api/v1/organizations/{organization}/kiosks`

#### Create Kiosk

```http
POST /api/v1/organizations/{organization}/kiosks
Authorization: Bearer {passport_token}
Content-Type: application/json

{
  "name": "Reception Tablet",
  "location": "Building A - Floor 1",
  "mode": "punch_only",
  "default_project_id": "uuid-or-null",
  "token_expires_at": "2026-03-06T00:00:00Z" // Optional, defaults to 30 days
}

Response 201:
{
  "data": {
    "id": "uuid",
    "name": "Reception Tablet",
    "location": "Building A - Floor 1",
    "mode": "punch_only",
    "default_project_id": "uuid-or-null",
    "is_active": true,
    "token": "64-char-hex-plaintext-token", // ONLY shown on creation
    "kiosk_url": "https://app.solidtime.io/kiosk/64-char-hex-plaintext-token",
    "token_expires_at": "2026-03-06T00:00:00Z",
    "last_activity_at": null,
    "created_at": "2026-02-06T12:00:00Z",
    "updated_at": "2026-02-06T12:00:00Z"
  }
}
```

**Permission**: `kiosks:create` (Owner, Admin)  
**Middleware**: `auth:api`, `verified`, `check-organization-blocked`

---

#### List Kiosks

```http
GET /api/v1/organizations/{organization}/kiosks
Authorization: Bearer {passport_token}

Response 200:
{
  "data": [
    {
      "id": "uuid",
      "name": "Reception Tablet",
      "location": "Building A - Floor 1",
      "mode": "punch_only",
      "default_project_id": "uuid-or-null",
      "is_active": true,
      // No token in list response
      "token_expires_at": "2026-03-06T00:00:00Z",
      "last_activity_at": "2026-02-06T11:45:00Z",
      "created_at": "2026-02-06T12:00:00Z",
      "updated_at": "2026-02-06T12:00:00Z"
    }
  ]
}
```

**Permission**: `kiosks:view` (Owner, Admin, Manager)

---

#### Update Kiosk

```http
PUT /api/v1/organizations/{organization}/kiosks/{kiosk}
Authorization: Bearer {passport_token}
Content-Type: application/json

{
  "name": "Updated Name",
  "location": "New Location",
  "mode": "full",
  "is_active": false
}

Response 200:
{
  "data": { /* KioskResource without token */ }
}
```

**Permission**: `kiosks:update` (Owner, Admin)  
**Middleware**: `check-organization-blocked`

---

#### Regenerate Token

```http
POST /api/v1/organizations/{organization}/kiosks/{kiosk}/regenerate-token
Authorization: Bearer {passport_token}

Response 200:
{
  "data": {
    "token": "new-64-char-hex-plaintext-token",
    "kiosk_url": "https://app.solidtime.io/kiosk/new-64-char-hex-plaintext-token",
    "token_expires_at": "2026-03-06T00:00:00Z"
  }
}
```

**Permission**: `kiosks:update` (Owner, Admin)  
**Middleware**: `check-organization-blocked`  
**Side Effect**: Invalidates old token immediately, ends all active sessions

---

#### Delete Kiosk

```http
DELETE /api/v1/organizations/{organization}/kiosks/{kiosk}
Authorization: Bearer {passport_token}

Response 204 No Content
```

**Permission**: `kiosks:delete` (Owner, Admin)  
**Middleware**: `check-organization-blocked` (per AMD-06)  
**Side Effect**: Ends all active `KioskSession` records

---

### 3.2 Member PIN/QR Management API (`auth:api`)

**Base Route**: `/api/v1/organizations/{organization}/members/{member}`

#### Set/Update PIN

```http
PUT /api/v1/organizations/{organization}/members/{member}/pin
Authorization: Bearer {passport_token}
Content-Type: application/json

{
  "pin": "1234"
}

Response 200:
{
  "message": "PIN updated successfully"
}

Response 422 (Duplicate):
{
  "message": "The given data was invalid.",
  "errors": {
    "pin": ["This PIN is already in use by another member"]
  }
}
```

**Permission**: Self (`time-entries:create:own`) or Admin (`members:update`)  
**Validation**: Exactly 4 digits, unique within organization  
**Middleware**: `check-organization-blocked`

---

#### Remove PIN

```http
DELETE /api/v1/organizations/{organization}/members/{member}/pin
Authorization: Bearer {passport_token}

Response 200:
{
  "message": "PIN removed successfully"
}
```

---

#### Generate Dynamic QR Token

```http
POST /api/v1/organizations/{organization}/members/{member}/qr-token
Authorization: Bearer {passport_token}

Response 200:
{
  "data": {
    "qr_payload": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_at": "2026-02-06T12:05:00Z"
  }
}
```

**TTL**: 5 minutes (hardcoded for dynamic QR)

---

#### Generate Badge QR Token (AMD-05)

```http
POST /api/v1/organizations/{organization}/members/{member}/badge-token
Authorization: Bearer {passport_token}
Content-Type: application/json

{
  "ttl": "7d" // Options: "24h", "7d", "30d", "none"
}

Response 200:
{
  "data": {
    "qr_payload": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token": "64-char-hex-plaintext-token", // Show once for printing
    "expires_at": "2026-02-13T12:00:00Z" // or null
  }
}
```

**Permission**: `members:update` (Admin only)  
**Middleware**: `check-organization-blocked`

---

#### Revoke Badge Token

```http
DELETE /api/v1/organizations/{organization}/members/{member}/badge-token
Authorization: Bearer {passport_token}

Response 200:
{
  "message": "Badge token revoked"
}
```

---

### 3.3 Kiosk Device API (`auth:kiosk`)

**Base Route**: `/api/v1/kiosk`  
**Middleware**: `auth:kiosk` (all routes)

#### Get Kiosk Status

```http
GET /api/v1/kiosk/status
Authorization: Bearer {kiosk_token}

Response 200:
{
  "data": {
    "kiosk": {
      "id": "uuid",
      "name": "Reception Tablet",
      "mode": "punch_only",
      "organization_name": "Acme Corp",
      "organization_timezone": "America/New_York"
    },
    "server_time": "2026-02-06T12:00:00Z"
  }
}
```

**Purpose**: Health check, kiosk config, server time for clock sync (AMD-07)

---

#### Authenticate by PIN

```http
POST /api/v1/kiosk/auth/pin
Authorization: Bearer {kiosk_token}
Content-Type: application/json

{
  "pin": "1234"
}

Response 200 (Valid):
{
  "data": {
    "member": {
      "id": "uuid",
      "name": "John Doe",
      "profile_photo_url": "https://..."
    },
    "session": { // null if not clocked in
      "id": "uuid",
      "status": "clocked_in",
      "clock_in_at": "2026-02-06T08:00:00Z",
      "on_break_since": null,
      "total_break_seconds": 0,
      "total_work_seconds": 14400 // Calculated
    }
  }
}

Response 401 (Invalid PIN):
{
  "error": "invalid_pin",
  "message": "Invalid PIN",
  "remaining_attempts": 3 // Before lockout
}

Response 429 (Rate Limited):
{
  "error": "locked_out",
  "message": "Too many failed attempts. Try again in 10 minutes.",
  "locked_until": "2026-02-06T12:10:00Z"
}
```

**Rate Limit**: 5 failed attempts per member per 15 minutes → 10-minute lockout

---

#### Authenticate by QR

```http
POST /api/v1/kiosk/auth/qr
Authorization: Bearer {kiosk_token}
Content-Type: application/json

{
  "qr_payload": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}

Response 200:
{
  // Same structure as PIN auth
}

Response 401:
{
  "error": "invalid_qr",
  "message": "QR code is invalid or expired"
}
```

---

#### Clock In

```http
POST /api/v1/kiosk/clock-in
Authorization: Bearer {kiosk_token}
Content-Type: application/json

{
  "member_id": "uuid",
  "project_id": "uuid-or-null", // Only if mode = 'full'
  "task_id": "uuid-or-null",    // Only if mode = 'full'
  "description": "Working on project" // Only if mode = 'full'
}

Response 201:
{
  "data": {
    "session": {
      "id": "uuid",
      "status": "clocked_in",
      "clock_in_at": "2026-02-06T12:00:00Z"
    },
    "time_entry": {
      "id": "uuid",
      "start": "2026-02-06T12:00:00Z",
      "end": null,
      "project_id": "uuid-or-null",
      "task_id": "uuid-or-null",
      "description": "Working on project"
    }
  }
}

Response 409 (Already Clocked In):
{
  "error": "already_clocked_in",
  "message": "You are already clocked in",
  "existing_session": { /* KioskSession */ }
}

Response 409 (Overlapping Entry):
{
  "error": "overlapping_entry",
  "message": "You have an overlapping time entry"
}
```

**Business Logic**:
- Creates `KioskSession` with `status = 'clocked_in'`
- Creates `TimeEntry` with `start = now()`, `end = null`
- If kiosk `mode = 'punch_only'` and `default_project_id` is set, assigns that project
- Respects `organization.prevent_overlapping_time_entries` setting
- Debounce: Reject if member has clocked in within last 5 seconds

---

#### Clock Out

```http
POST /api/v1/kiosk/clock-out
Authorization: Bearer {kiosk_token}
Content-Type: application/json

{
  "member_id": "uuid"
}

Response 200:
{
  "data": {
    "session": {
      "id": "uuid",
      "status": "clocked_out",
      "clock_in_at": "2026-02-06T08:00:00Z",
      "clock_out_at": "2026-02-06T17:00:00Z",
      "total_break_seconds": 3600,
      "total_work_seconds": 28800 // 8 hours worked (minus 1 hour break)
    }
  }
}

Response 409:
{
  "error": "not_clocked_in",
  "message": "You are not currently clocked in"
}
```

**Business Logic**:
- Finds active `KioskSession` for member
- If `status = 'on_break'`, ends break first (accumulate break time)
- Sets `clock_out_at = now()`, `status = 'clocked_out'`
- Sets `end = now()` on active `TimeEntry`
- Dispatches `RecalculateSpentTimeForProject` and `RecalculateSpentTimeForTask` jobs

---

#### Start Break

```http
POST /api/v1/kiosk/break/start
Authorization: Bearer {kiosk_token}
Content-Type: application/json

{
  "member_id": "uuid"
}

Response 200:
{
  "data": {
    "session": {
      "id": "uuid",
      "status": "on_break",
      "on_break_since": "2026-02-06T12:00:00Z"
    }
  }
}

Response 409 (Not Clocked In):
{
  "error": "not_clocked_in",
  "message": "You must clock in before starting a break"
}

Response 409 (Already On Break):
{
  "error": "already_on_break",
  "message": "You are already on break"
}
```

**Business Logic**:
- Sets `status = 'on_break'`, `on_break_since = now()`
- Sets `end = now()` on active `TimeEntry` (pauses work tracking)

---

#### End Break

```http
POST /api/v1/kiosk/break/end
Authorization: Bearer {kiosk_token}
Content-Type: application/json

{
  "member_id": "uuid"
}

Response 200:
{
  "data": {
    "session": {
      "id": "uuid",
      "status": "clocked_in",
      "total_break_seconds": 1800 // 30 minutes accumulated
    },
    "time_entry": {
      "id": "uuid",
      "start": "2026-02-06T12:30:00Z", // New entry after break
      "end": null
    }
  }
}

Response 409:
{
  "error": "not_on_break",
  "message": "You are not currently on break"
}
```

**Business Logic**:
- Calculates break duration: `now() - on_break_since`
- Accumulates into `total_break_seconds += break_duration`
- Sets `status = 'clocked_in'`, `on_break_since = null`
- Creates new `TimeEntry` with `start = now()`, `end = null` (resumes work tracking)

---

#### Get Attendance (Kiosk-Side)

```http
GET /api/v1/kiosk/attendance
Authorization: Bearer {kiosk_token}

Response 200:
{
  "data": {
    "members": [
      {
        "id": "uuid",
        "name": "John Doe",
        "status": "clocked_in",
        "clock_in_at": "2026-02-06T08:00:00Z",
        "on_break_since": null
      },
      {
        "id": "uuid",
        "name": "Jane Smith",
        "status": "on_break",
        "clock_in_at": "2026-02-06T08:30:00Z",
        "on_break_since": "2026-02-06T12:00:00Z"
      }
    ]
  }
}
```

**Purpose**: Display who's currently in/out on the kiosk idle screen

---

### 3.4 Attendance Dashboard API (`auth:api`)

**Base Route**: `/api/v1/organizations/{organization}/attendance`

#### Get Attendance Overview

```http
GET /api/v1/organizations/{organization}/attendance
Authorization: Bearer {passport_token}
Query Parameters:
  - date: "2026-02-06" (default: today)
  - status: "all" | "clocked_in" | "on_break" | "clocked_out" (default: "all")
  - search: "John" (member name search)

Response 200:
{
  "data": {
    "summary": {
      "total_members": 50,
      "clocked_in": 35,
      "on_break": 5,
      "clocked_out": 10
    },
    "members": [
      {
        "member_id": "uuid",
        "name": "John Doe",
        "profile_photo_url": "https://...",
        "status": "clocked_in",
        "clock_in_at": "2026-02-06T08:00:00Z",
        "clock_out_at": null,
        "on_break_since": null,
        "total_work_seconds": 14400,
        "total_break_seconds": 1800,
        "kiosk_name": "Reception Tablet",
        "source": "kiosk" // or "web" or "api"
      }
    ]
  }
}
```

**Permission**: `time-entries:view:all` (Owner, Admin, Manager)  
**Performance**: Single query with eager loading, indexed by `(organization_id, status)`

**Source Detection Logic**:
- If `KioskSession` exists with active status → `source = 'kiosk'`
- Else if running `TimeEntry` exists without kiosk session → check creation context:
  - Created via web SPA → `source = 'web'`
  - Created via API → `source = 'api'`

---

## 4. Service Layer Architecture

### 4.1 KioskService

**File**: `app/Service/KioskService.php`

**Responsibilities**:
- Kiosk CRUD operations
- Token generation and validation
- Session cleanup on kiosk deletion

**Key Methods**:

```php
class KioskService
{
    public function createKiosk(
        Organization $organization,
        array $data
    ): array {
        // Returns ['kiosk' => Kiosk, 'token' => plaintext_token]
        $token = bin2hex(random_bytes(32)); // 64-char hex
        $tokenHash = hash('sha256', $token);
        
        $kiosk = Kiosk::create([
            'organization_id' => $organization->id,
            'name' => $data['name'],
            'location' => $data['location'] ?? null,
            'mode' => $data['mode'] ?? KioskMode::Full,
            'default_project_id' => $data['default_project_id'] ?? null,
            'is_active' => true,
            'token_hash' => $tokenHash,
            'token_expires_at' => $data['token_expires_at'] ?? now()->addDays(30),
        ]);
        
        return [
            'kiosk' => $kiosk,
            'token' => $token, // Return plaintext only once
        ];
    }
    
    public function regenerateToken(Kiosk $kiosk): array {
        // Returns ['token' => plaintext_token]
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        
        DB::transaction(function () use ($kiosk, $tokenHash) {
            // End all active sessions using old token
            KioskSession::query()
                ->where('kiosk_id', $kiosk->id)
                ->whereIn('status', ['clocked_in', 'on_break'])
                ->update(['status' => 'clocked_out', 'clock_out_at' => now()]);
            
            // Update token
            $kiosk->update([
                'token_hash' => $tokenHash,
                'token_expires_at' => now()->addDays(30),
            ]);
            
            // Clear cache
            Cache::forget("kiosk_token:{$kiosk->token_hash}");
        });
        
        return ['token' => $token];
    }
    
    public function deleteKiosk(Kiosk $kiosk): void {
        DB::transaction(function () use ($kiosk) {
            // End all active sessions
            KioskSession::query()
                ->where('kiosk_id', $kiosk->id)
                ->whereIn('status', ['clocked_in', 'on_break'])
                ->update(['status' => 'clocked_out', 'clock_out_at' => now()]);
            
            // Delete kiosk (cascade deletes sessions via FK)
            $kiosk->delete();
            
            // Clear cache
            Cache::forget("kiosk_token:{$kiosk->token_hash}");
        });
    }
}
```

---

### 4.2 KioskSessionService

**File**: `app/Service/KioskSessionService.php`

**Responsibilities**:
- Clock in/out state machine
- Break start/end transitions
- Time entry creation/updates
- Overlap detection
- Debouncing

**Key Methods**:

```php
class KioskSessionService
{
    public function __construct(
        private TimeEntryService $timeEntryService
    ) {}
    
    public function clockIn(
        Kiosk $kiosk,
        Member $member,
        ?string $projectId = null,
        ?string $taskId = null,
        ?string $description = null
    ): KioskSession {
        // Check for existing active session
        $existingSession = KioskSession::query()
            ->where('member_id', $member->id)
            ->whereIn('status', ['clocked_in', 'on_break'])
            ->latest()
            ->first();
        
        if ($existingSession !== null) {
            throw new AlreadyClockedInException($existingSession);
        }
        
        // Debounce: check for recent clock-in within 5 seconds
        $recentSession = KioskSession::query()
            ->where('member_id', $member->id)
            ->where('clock_in_at', '>', now()->subSeconds(5))
            ->exists();
        
        if ($recentSession) {
            throw new DuplicateClockInException;
        }
        
        // Check overlap if org setting enabled
        if ($kiosk->organization->prevent_overlapping_time_entries) {
            $this->assertNoOverlap($member, now(), null);
        }
        
        return DB::transaction(function () use ($kiosk, $member, $projectId, $taskId, $description) {
            // Create kiosk session
            $session = KioskSession::create([
                'kiosk_id' => $kiosk->id,
                'member_id' => $member->id,
                'organization_id' => $kiosk->organization_id,
                'status' => KioskSessionStatus::ClockedIn,
                'clock_in_at' => now(),
            ]);
            
            // Create time entry
            TimeEntry::create([
                'member_id' => $member->id,
                'user_id' => $member->user_id,
                'organization_id' => $kiosk->organization_id,
                'project_id' => $projectId ?? $kiosk->default_project_id,
                'task_id' => $taskId,
                'description' => $description ?? '',
                'start' => now(),
                'end' => null,
                'billable' => true,
                'tags' => [],
            ]);
            
            return $session;
        });
    }
    
    public function clockOut(Member $member): KioskSession {
        $session = KioskSession::query()
            ->where('member_id', $member->id)
            ->whereIn('status', ['clocked_in', 'on_break'])
            ->latest()
            ->firstOrFail();
        
        return DB::transaction(function () use ($session, $member) {
            // If on break, end break first
            if ($session->status === KioskSessionStatus::OnBreak) {
                $this->endBreak($member);
                $session->refresh();
            }
            
            // End time entry
            $activeEntry = TimeEntry::query()
                ->where('member_id', $member->id)
                ->whereNull('end')
                ->latest()
                ->first();
            
            if ($activeEntry !== null) {
                $activeEntry->update(['end' => now()]);
                
                // Dispatch recalculation jobs
                if ($activeEntry->project_id !== null) {
                    RecalculateSpentTimeForProject::dispatch($activeEntry->project);
                }
                if ($activeEntry->task_id !== null) {
                    RecalculateSpentTimeForTask::dispatch($activeEntry->task);
                }
            }
            
            // End session
            $session->update([
                'status' => KioskSessionStatus::ClockedOut,
                'clock_out_at' => now(),
            ]);
            
            return $session;
        });
    }
    
    public function startBreak(Member $member): KioskSession {
        $session = KioskSession::query()
            ->where('member_id', $member->id)
            ->where('status', KioskSessionStatus::ClockedIn)
            ->latest()
            ->firstOrFail();
        
        return DB::transaction(function () use ($session, $member) {
            // End current time entry
            $activeEntry = TimeEntry::query()
                ->where('member_id', $member->id)
                ->whereNull('end')
                ->latest()
                ->first();
            
            if ($activeEntry !== null) {
                $activeEntry->update(['end' => now()]);
            }
            
            // Update session
            $session->update([
                'status' => KioskSessionStatus::OnBreak,
                'on_break_since' => now(),
            ]);
            
            return $session;
        });
    }
    
    public function endBreak(Member $member): KioskSession {
        $session = KioskSession::query()
            ->where('member_id', $member->id)
            ->where('status', KioskSessionStatus::OnBreak)
            ->latest()
            ->firstOrFail();
        
        return DB::transaction(function () use ($session, $member) {
            // Calculate break duration
            $breakDuration = now()->diffInSeconds($session->on_break_since);
            
            // Accumulate break time
            $session->update([
                'status' => KioskSessionStatus::ClockedIn,
                'total_break_seconds' => $session->total_break_seconds + $breakDuration,
                'on_break_since' => null,
            ]);
            
            // Create new time entry to resume work
            $lastEntry = TimeEntry::query()
                ->where('member_id', $member->id)
                ->latest()
                ->first();
            
            TimeEntry::create([
                'member_id' => $member->id,
                'user_id' => $member->user_id,
                'organization_id' => $session->organization_id,
                'project_id' => $lastEntry?->project_id ?? $session->kiosk->default_project_id,
                'task_id' => $lastEntry?->task_id,
                'description' => $lastEntry?->description ?? '',
                'start' => now(),
                'end' => null,
                'billable' => $lastEntry?->billable ?? true,
                'tags' => $lastEntry?->tags ?? [],
            ]);
            
            return $session;
        });
    }
    
    private function assertNoOverlap(Member $member, Carbon $start, ?Carbon $end): void {
        $query = TimeEntry::query()
            ->where('organization_id', $member->organization_id)
            ->where('user_id', $member->user_id)
            ->where(function (Builder $q) use ($start, $end): void {
                $q->where(function (Builder $q2) use ($start): void {
                    $q2->where('end', '>', $start)
                        ->where('start', '<', $start);
                });
                
                if ($end !== null) {
                    $q->orWhere(function (Builder $q4) use ($end): void {
                        $q4->where('start', '<', $end)
                            ->where('end', '>', $end);
                    });
                }
            });
        
        if ($query->exists()) {
            throw new OverlappingTimeEntryApiException;
        }
    }
}
```

---

### 4.3 MemberPinService

**File**: `app/Service/MemberPinService.php`

**Responsibilities**:
- PIN setup/update/removal
- Dual-hash generation (AMD-04)
- Uniqueness validation

**Key Methods**:

```php
class MemberPinService
{
    public function setPin(Member $member, string $pin): void {
        // Validate format
        if (!preg_match('/^\d{4}$/', $pin)) {
            throw new ValidationException('PIN must be exactly 4 digits');
        }
        
        // Generate dual hashes (AMD-04)
        $pinCheck = hash('sha256', $member->organization_id . ':' . $pin);
        $pinHash = Hash::make($pin);
        
        // Check uniqueness
        $duplicate = Member::query()
            ->where('organization_id', $member->organization_id)
            ->where('kiosk_pin_check', $pinCheck)
            ->where('id', '!=', $member->id)
            ->exists();
        
        if ($duplicate) {
            throw new ValidationException('PIN already in use by another member');
        }
        
        // Update member
        $member->update([
            'kiosk_pin_check' => $pinCheck,
            'kiosk_pin_hash' => $pinHash,
        ]);
    }
    
    public function removePin(Member $member): void {
        $member->update([
            'kiosk_pin_check' => null,
            'kiosk_pin_hash' => null,
        ]);
    }
    
    public function verifyPin(Member $member, string $pin): bool {
        if ($member->kiosk_pin_hash === null) {
            return false;
        }
        
        return Hash::check($pin, $member->kiosk_pin_hash);
    }
}
```

---

### 4.4 KioskQrService

**File**: `app/Service/KioskQrService.php`

**Responsibilities**:
- Dynamic QR token generation (5-min TTL)
- Badge QR token generation (configurable TTL, AMD-05)
- JWT encoding/decoding

**Key Methods**:

```php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class KioskQrService
{
    public function generateDynamicQrToken(Member $member): array {
        $payload = [
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'type' => 'dynamic',
            'iat' => time(),
            'exp' => time() + 300, // 5 minutes
        ];
        
        $token = JWT::encode($payload, config('app.key'), 'HS256');
        
        return [
            'qr_payload' => $token,
            'expires_at' => now()->addMinutes(5),
        ];
    }
    
    public function generateBadgeQrToken(Member $member, string $ttl): array {
        $plaintextToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plaintextToken);
        
        $expiresAt = match($ttl) {
            '24h' => now()->addHours(24),
            '7d' => now()->addDays(7),
            '30d' => now()->addDays(30),
            'none' => null,
            default => now()->addDays(7),
        };
        
        $member->update([
            'kiosk_badge_token' => $tokenHash,
            'kiosk_badge_token_expires_at' => $expiresAt,
        ]);
        
        $payload = [
            'badge_token' => $plaintextToken,
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'type' => 'badge',
            'iat' => time(),
            'exp' => $expiresAt?->timestamp,
        ];
        
        $qrPayload = JWT::encode($payload, config('app.key'), 'HS256');
        
        return [
            'qr_payload' => $qrPayload,
            'token' => $plaintextToken, // Show once for printing
            'expires_at' => $expiresAt,
        ];
    }
    
    public function verifyQrToken(string $qrPayload, Organization $organization): ?Member {
        try {
            $payload = JWT::decode($qrPayload, new Key(config('app.key'), 'HS256'));
        } catch (\Exception $e) {
            return null;
        }
        
        // Verify org match
        if ($payload->organization_id !== $organization->id) {
            return null;
        }
        
        // Verify member + badge token if applicable
        if ($payload->type === 'badge') {
            return Member::query()
                ->where('id', $payload->member_id)
                ->where('kiosk_badge_token', hash('sha256', $payload->badge_token))
                ->whereRaw('(kiosk_badge_token_expires_at IS NULL OR kiosk_badge_token_expires_at > NOW())')
                ->first();
        }
        
        // Dynamic QR - just verify member exists
        return Member::query()->find($payload->member_id);
    }
    
    public function revokeBadgeToken(Member $member): void {
        $member->update([
            'kiosk_badge_token' => null,
            'kiosk_badge_token_expires_at' => null,
        ]);
    }
}
```

---

### 4.5 AttendanceService

**File**: `app/Service/AttendanceService.php`

**Responsibilities**:
- Attendance overview queries
- Summary statistics
- Source detection (kiosk/web/api)

**Key Methods**:

```php
class AttendanceService
{
    public function getAttendanceOverview(
        Organization $organization,
        ?Carbon $date = null,
        ?string $statusFilter = null,
        ?string $search = null
    ): array {
        $date = $date ?? now()->startOfDay();
        
        // Get all active kiosk sessions for the date
        $kioskSessions = KioskSession::query()
            ->with(['member.user', 'kiosk'])
            ->where('organization_id', $organization->id)
            ->whereDate('clock_in_at', $date)
            ->when($statusFilter !== null, function ($q) use ($statusFilter) {
                if ($statusFilter !== 'all') {
                    $q->where('status', $statusFilter);
                }
            })
            ->get();
        
        // Get running time entries without kiosk sessions (web/API)
        $runningEntries = TimeEntry::query()
            ->with(['member.user'])
            ->where('organization_id', $organization->id)
            ->whereNull('end')
            ->whereDate('start', $date)
            ->whereNotIn('member_id', $kioskSessions->pluck('member_id'))
            ->get();
        
        $members = [];
        
        // Process kiosk sessions
        foreach ($kioskSessions as $session) {
            $members[] = [
                'member_id' => $session->member_id,
                'name' => $session->member->user->name,
                'profile_photo_url' => $session->member->user->profile_photo_url,
                'status' => $session->status->value,
                'clock_in_at' => $session->clock_in_at,
                'clock_out_at' => $session->clock_out_at,
                'on_break_since' => $session->on_break_since,
                'total_work_seconds' => $session->getTotalWorkSeconds(),
                'total_break_seconds' => $session->total_break_seconds,
                'kiosk_name' => $session->kiosk->name,
                'source' => 'kiosk',
            ];
        }
        
        // Process web/API entries
        foreach ($runningEntries as $entry) {
            $members[] = [
                'member_id' => $entry->member_id,
                'name' => $entry->member->user->name,
                'profile_photo_url' => $entry->member->user->profile_photo_url,
                'status' => 'clocked_in',
                'clock_in_at' => $entry->start,
                'clock_out_at' => null,
                'on_break_since' => null,
                'total_work_seconds' => now()->diffInSeconds($entry->start),
                'total_break_seconds' => 0,
                'kiosk_name' => null,
                'source' => 'web', // Could detect API vs web from creation context
            ];
        }
        
        // Filter by search
        if ($search !== null) {
            $members = array_filter($members, function ($m) use ($search) {
                return str_contains(strtolower($m['name']), strtolower($search));
            });
        }
        
        // Calculate summary
        $summary = [
            'total_members' => count($members),
            'clocked_in' => count(array_filter($members, fn($m) => $m['status'] === 'clocked_in')),
            'on_break' => count(array_filter($members, fn($m) => $m['status'] === 'on_break')),
            'clocked_out' => count(array_filter($members, fn($m) => $m['status'] === 'clocked_out')),
        ];
        
        return [
            'summary' => $summary,
            'members' => array_values($members),
        ];
    }
}
```

---

## 5. Frontend Architecture

### 5.1 Standalone Kiosk Vue SPA

**Architecture Decision**: The kiosk UI is a **standalone Vue SPA**, NOT an Inertia page, to avoid requiring user sessions.

#### Entry Point

**File**: `resources/views/kiosk.blade.php`

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store">
    <meta name="robots" content="noindex">
    <title>Kiosk Mode - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/kiosk.ts'])
</head>
<body class="kiosk-mode">
    <div id="kiosk-app" data-kiosk-token="{{ $token }}"></div>
</body>
</html>
```

**Route**: `routes/web.php`

```php
Route::get('/kiosk/{token}', function (string $token) {
    return view('kiosk', ['token' => $token]);
})->name('kiosk.show');
```

**Security Headers Middleware (AMD-08)**:

```php
// app/Http/Middleware/KioskSecurityHeaders.php
public function handle(Request $request, Closure $next): Response
{
    $response = $next($request);
    
    $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
    $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
    $response->headers->set('X-Frame-Options', 'DENY');
    
    return $response;
}
```

---

#### Kiosk Vue App Entry

**File**: `resources/js/kiosk.ts`

```typescript
import './bootstrap';
import '../css/kiosk.css';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { VueQueryPlugin } from '@tanstack/vue-query';
import KioskApp from './Pages/Kiosk/KioskApp.vue';

const app = createApp(KioskApp);
const pinia = createPinia();

app.use(pinia).use(VueQueryPlugin).mount('#kiosk-app');
```

---

#### Kiosk Root Component

**File**: `resources/js/Pages/Kiosk/KioskApp.vue`

```vue
<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useKioskStore } from '@/utils/useKiosk';
import KioskIdleScreen from './KioskIdleScreen.vue';
import KioskPinPad from './KioskPinPad.vue';
import KioskMemberStatus from './KioskMemberStatus.vue';
import KioskConfirmation from './KioskConfirmation.vue';
import KioskErrorScreen from './KioskErrorScreen.vue';

const kioskStore = useKioskStore();

onMounted(() => {
  const token = document.getElementById('kiosk-app')?.dataset.kioskToken;
  if (token) {
    kioskStore.initializeKiosk(token);
  }
});

const currentScreen = computed(() => kioskStore.currentScreen);
</script>

<template>
  <div class="kiosk-container">
    <KioskIdleScreen v-if="currentScreen === 'idle'" />
    <KioskPinPad v-else-if="currentScreen === 'pin_entry'" />
    <KioskMemberStatus v-else-if="currentScreen === 'member_status'" />
    <KioskConfirmation v-else-if="currentScreen === 'confirmation'" />
    <KioskErrorScreen v-else-if="currentScreen === 'error'" />
    
    <!-- Offline Overlay -->
    <div v-if="kioskStore.isOffline" class="offline-overlay">
      <div class="offline-message">
        <svg class="icon"><!-- Wi-Fi slash icon --></svg>
        <h2>Connection Lost</h2>
        <p>Reconnecting...</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.kiosk-container {
  width: 100vw;
  height: 100vh;
  overflow: hidden;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.offline-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}
</style>
```

---

#### Kiosk Pinia Store

**File**: `resources/js/utils/useKiosk.ts`

```typescript
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { Kiosk, KioskSession, Member } from '@/types/kiosk';

export const useKioskStore = defineStore('kiosk', () => {
  // State
  const token = ref<string | null>(null);
  const kiosk = ref<Kiosk | null>(null);
  const currentScreen = ref<'idle' | 'pin_entry' | 'qr_scan' | 'member_status' | 'confirmation' | 'error'>('idle');
  const currentMember = ref<Member | null>(null);
  const currentSession = ref<KioskSession | null>(null);
  const errorMessage = ref<string | null>(null);
  const isOffline = ref(false);
  const pinAttempts = ref(0);
  const lockedUntil = ref<Date | null>(null);

  // Getters
  const kioskUrl = computed(() => `/api/v1/kiosk`);
  const isLocked = computed(() => lockedUntil.value && lockedUntil.value > new Date());
  const remainingAttempts = computed(() => Math.max(0, 5 - pinAttempts.value));

  // Actions
  async function initializeKiosk(kioskToken: string) {
    token.value = kioskToken;
    await fetchKioskStatus();
    startHealthCheck();
  }

  async function fetchKioskStatus() {
    try {
      const response = await fetch(`${kioskUrl.value}/status`, {
        headers: {
          'Authorization': `Bearer ${token.value}`,
          'Accept': 'application/json',
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch kiosk status');
      }

      const data = await response.json();
      kiosk.value = data.data.kiosk;
      isOffline.value = false;
    } catch (error) {
      isOffline.value = true;
      console.error('Kiosk status fetch failed:', error);
    }
  }

  function startHealthCheck() {
    setInterval(() => {
      fetchKioskStatus();
    }, 30000); // Every 30 seconds
  }

  async function authenticatePin(pin: string) {
    try {
      const response = await fetch(`${kioskUrl.value}/auth/pin`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token.value}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({ pin }),
      });

      const data = await response.json();

      if (!response.ok) {
        if (response.status === 429) {
          lockedUntil.value = new Date(data.locked_until);
          showError(`Too many attempts. Try again in 10 minutes.`);
        } else {
          pinAttempts.value++;
          showError(`Invalid PIN. ${remainingAttempts.value} attempts remaining.`);
        }
        return;
      }

      // Success
      pinAttempts.value = 0;
      currentMember.value = data.data.member;
      currentSession.value = data.data.session;
      currentScreen.value = 'member_status';
    } catch (error) {
      showError('Connection error. Please try again.');
    }
  }

  async function clockIn(projectId?: string, taskId?: string, description?: string) {
    try {
      const response = await fetch(`${kioskUrl.value}/clock-in`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token.value}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          member_id: currentMember.value?.id,
          project_id: projectId,
          task_id: taskId,
          description,
        }),
      });

      const data = await response.json();

      if (!response.ok) {
        showError(data.message || 'Clock in failed');
        return;
      }

      currentSession.value = data.data.session;
      showConfirmation(`Clocked in at ${new Date().toLocaleTimeString()}`);
    } catch (error) {
      showError('Connection error');
    }
  }

  async function clockOut() {
    try {
      const response = await fetch(`${kioskUrl.value}/clock-out`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token.value}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          member_id: currentMember.value?.id,
        }),
      });

      const data = await response.json();

      if (!response.ok) {
        showError(data.message || 'Clock out failed');
        return;
      }

      const totalHours = Math.floor(data.data.session.total_work_seconds / 3600);
      const totalMinutes = Math.floor((data.data.session.total_work_seconds % 3600) / 60);
      showConfirmation(`Clocked out. Total: ${totalHours}h ${totalMinutes}m`);
    } catch (error) {
      showError('Connection error');
    }
  }

  async function startBreak() {
    try {
      const response = await fetch(`${kioskUrl.value}/break/start`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token.value}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          member_id: currentMember.value?.id,
        }),
      });

      const data = await response.json();

      if (!response.ok) {
        showError(data.message || 'Break start failed');
        return;
      }

      currentSession.value = data.data.session;
      showConfirmation('Break started');
    } catch (error) {
      showError('Connection error');
    }
  }

  async function endBreak() {
    try {
      const response = await fetch(`${kioskUrl.value}/break/end`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token.value}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          member_id: currentMember.value?.id,
        }),
      });

      const data = await response.json();

      if (!response.ok) {
        showError(data.message || 'Break end failed');
        return;
      }

      currentSession.value = data.data.session;
      showConfirmation('Break ended');
    } catch (error) {
      showError('Connection error');
    }
  }

  function showError(message: string) {
    errorMessage.value = message;
    currentScreen.value = 'error';
    setTimeout(() => {
      returnToIdle();
    }, 5000);
  }

  function showConfirmation(message: string) {
    errorMessage.value = message;
    currentScreen.value = 'confirmation';
    setTimeout(() => {
      returnToIdle();
    }, 5000);
  }

  function returnToIdle() {
    currentScreen.value = 'idle';
    currentMember.value = null;
    currentSession.value = null;
    errorMessage.value = null;
  }

  return {
    // State
    token,
    kiosk,
    currentScreen,
    currentMember,
    currentSession,
    errorMessage,
    isOffline,
    pinAttempts,
    lockedUntil,
    
    // Getters
    kioskUrl,
    isLocked,
    remainingAttempts,
    
    // Actions
    initializeKiosk,
    fetchKioskStatus,
    authenticatePin,
    clockIn,
    clockOut,
    startBreak,
    endBreak,
    showError,
    showConfirmation,
    returnToIdle,
  };
});
```

---

#### TypeScript Types

**File**: `resources/js/types/kiosk.d.ts`

```typescript
export interface Kiosk {
  id: string;
  name: string;
  mode: 'full' | 'punch_only';
  organization_name: string;
  organization_timezone: string;
}

export interface Member {
  id: string;
  name: string;
  profile_photo_url: string | null;
}

export interface KioskSession {
  id: string;
  status: 'clocked_in' | 'on_break' | 'clocked_out';
  clock_in_at: string;
  clock_out_at: string | null;
  on_break_since: string | null;
  total_break_seconds: number;
  total_work_seconds: number;
}

export interface TimeEntry {
  id: string;
  start: string;
  end: string | null;
  project_id: string | null;
  task_id: string | null;
  description: string;
}
```

---

### 5.2 Attendance Dashboard (Inertia Page)

**File**: `resources/js/Pages/Attendance.vue`

```vue
<script setup lang="ts">
import { ref, computed } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import AppLayout from '@/Layouts/AppLayout.vue';
import AttendanceSummaryCards from '@/Components/Common/Attendance/AttendanceSummaryCards.vue';
import AttendanceTable from '@/Components/Common/Attendance/AttendanceTable.vue';
import AttendanceFilterBar from '@/Components/Common/Attendance/AttendanceFilterBar.vue';
import { useAttendanceStore } from '@/utils/useAttendance';

const attendanceStore = useAttendanceStore();

const statusFilter = ref<string>('all');
const dateFilter = ref<string>(new Date().toISOString().split('T')[0]);
const searchQuery = ref<string>('');

const { data, isLoading, refetch } = useQuery({
  queryKey: ['attendance', dateFilter, statusFilter, searchQuery],
  queryFn: () => attendanceStore.fetchAttendance(dateFilter.value, statusFilter.value, searchQuery.value),
  refetchInterval: 30000, // Auto-refresh every 30 seconds
});

const summary = computed(() => data.value?.summary || {
  total_members: 0,
  clocked_in: 0,
  on_break: 0,
  clocked_out: 0,
});

const members = computed(() => data.value?.members || []);
</script>

<template>
  <AppLayout title="Attendance">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Attendance Dashboard
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <AttendanceSummaryCards :summary="summary" />
        
        <AttendanceFilterBar
          v-model:status="statusFilter"
          v-model:date="dateFilter"
          v-model:search="searchQuery"
          class="mt-6"
        />
        
        <AttendanceTable
          :members="members"
          :loading="isLoading"
          class="mt-6"
        />
      </div>
    </div>
  </AppLayout>
</template>
```

**Route**: `routes/web.php`

```php
Route::get('/attendance', function () {
    return Inertia::render('Attendance');
})->name('attendance')->middleware(['auth:web', 'verified']);
```

**Navigation**: `resources/js/Layouts/AppLayout.vue`

```vue
<NavigationSidebarItem 
  :href="route('attendance')" 
  :active="route().current('attendance')"
  v-if="$page.props.auth.permissions.includes('time-entries:view:all')"
>
  <ClockIcon class="w-5 h-5" />
  Attendance
</NavigationSidebarItem>
```

---

### 5.3 Kiosk Admin UI (Organization Settings)

**File**: `resources/js/Components/Common/Kiosk/KioskTable.vue`

```vue
<script setup lang="ts">
import { ref } from 'vue';
import { useKiosksStore } from '@/utils/useKiosks';
import KioskCreateModal from './KioskCreateModal.vue';
import KioskEditModal from './KioskEditModal.vue';
import KioskTokenModal from './KioskTokenModal.vue';
import KioskMoreOptionsDropdown from './KioskMoreOptionsDropdown.vue';

const kiosksStore = useKiosksStore();

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showTokenModal = ref(false);
const selectedKiosk = ref(null);
const newToken = ref(null);

const { data: kiosks, isLoading } = kiosksStore.useKiosksQuery();

function handleCreateSuccess(kiosk, token) {
  selectedKiosk.value = kiosk;
  newToken.value = token;
  showCreateModal.value = false;
  showTokenModal.value = true;
}

function handleEdit(kiosk) {
  selectedKiosk.value = kiosk;
  showEditModal.value = true;
}

async function handleRegenerateToken(kiosk) {
  if (!confirm('Regenerating the token will invalidate the old URL. Continue?')) {
    return;
  }
  
  const result = await kiosksStore.regenerateToken(kiosk.id);
  if (result) {
    selectedKiosk.value = kiosk;
    newToken.value = result.token;
    showTokenModal.value = true;
  }
}

async function handleDelete(kiosk) {
  if (!confirm(`Delete kiosk "${kiosk.name}"? This will end all active sessions.`)) {
    return;
  }
  
  await kiosksStore.deleteKiosk(kiosk.id);
}
</script>

<template>
  <div>
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg font-semibold">Kiosks</h3>
      <button 
        @click="showCreateModal = true"
        class="btn-primary"
      >
        + Add Kiosk
      </button>
    </div>

    <table class="table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Location</th>
          <th>Mode</th>
          <th>Status</th>
          <th>Last Activity</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="kiosk in kiosks" :key="kiosk.id">
          <td>{{ kiosk.name }}</td>
          <td>{{ kiosk.location || '-' }}</td>
          <td>
            <span class="badge">
              {{ kiosk.mode === 'full' ? 'Full' : 'Punch Only' }}
            </span>
          </td>
          <td>
            <span :class="kiosk.is_active ? 'badge-green' : 'badge-gray'">
              {{ kiosk.is_active ? 'Active' : 'Inactive' }}
            </span>
          </td>
          <td>{{ kiosk.last_activity_at || 'Never' }}</td>
          <td>
            <KioskMoreOptionsDropdown
              :kiosk="kiosk"
              @edit="handleEdit(kiosk)"
              @regenerate-token="handleRegenerateToken(kiosk)"
              @delete="handleDelete(kiosk)"
            />
          </td>
        </tr>
      </tbody>
    </table>

    <KioskCreateModal 
      v-model:show="showCreateModal"
      @success="handleCreateSuccess"
    />
    
    <KioskEditModal
      v-model:show="showEditModal"
      :kiosk="selectedKiosk"
    />
    
    <KioskTokenModal
      v-model:show="showTokenModal"
      :kiosk="selectedKiosk"
      :token="newToken"
    />
  </div>
</template>
```

---

## 6. Security Design

### 6.1 Token-in-URL Mitigation (AMD-08)

**Issue**: Kiosk token in URL path could be logged in browser history, proxy logs, or leaked via Referer headers.

**Mitigations**:

1. **HTTPS Enforcement**:
   ```php
   // app/Http/Middleware/ForceHttps.php (already exists)
   // Redirects all HTTP requests to HTTPS
   ```

2. **Token Expiry**:
   - Default: 30 days (configurable per kiosk)
   - Admin can set custom expiry or no expiry for permanent kiosks

3. **Token Revocation**:
   - Admin can regenerate token at any time
   - Old token immediately invalidated
   - All active sessions using old token are ended

4. **Cache-Control Headers**:
   ```php
   // Added to kiosk route middleware
   'Cache-Control: no-store, no-cache, must-revalidate'
   'X-Robots-Tag: noindex, nofollow'
   'X-Frame-Options: DENY'
   ```

5. **Single-Device Usage**:
   - Kiosk URL should be opened once and browser session maintained
   - Discourage frequent re-opening of URL

6. **Future Enhancement**: Cookie-based session after initial token verification
   - First visit with token establishes cookie
   - Subsequent requests use cookie instead of token in URL

---

### 6.2 PIN Security

**Dual-Hash Design (AMD-04)**:
- **SHA-256 hash**: Deterministic, for database uniqueness constraint
  - Salted with organization ID to prevent cross-org collision detection
  - NEVER used for authentication
- **bcrypt hash**: Non-deterministic, for secure verification
  - Cost factor 10 (Laravel default)
  - Used with `Hash::check()` for authentication

**Rate Limiting**:
- 5 failed attempts per member per kiosk per 15 minutes
- 10-minute lockout after 5 failures
- `KioskPinAttempt` records logged for forensic analysis

**Uniqueness Enforcement**:
- Database unique constraint on `members.kiosk_pin_check`
- Application-level validation before insert

---

### 6.3 QR Security

**Dynamic QR (5-min TTL)**:
- JWT signed with `APP_KEY`
- Payload: `{ member_id, organization_id, type: 'dynamic', iat, exp }`
- Expired tokens rejected with clear error message

**Badge QR (Configurable TTL)**:
- Separate `kiosk_badge_token` column
- Token stored as SHA-256 hash (like kiosk token)
- Admin-revocable at any time
- TTL options: 24h, 7d, 30d, or no expiry

**Verification**:
- JWT signature verified with `APP_KEY`
- Organization ID match enforced
- Badge token hash verified against database

---

## 7. Timezone Handling (AMD-07)

**Storage**: All timestamps stored in **UTC** in database.

**Display**:
- Kiosk fetches `organization.timezone` from `/api/v1/kiosk/status` endpoint
- Frontend converts UTC timestamps to org timezone for display
- Clock display on idle screen shows org timezone

**Implementation**:
```typescript
// In KioskIdleScreen.vue
const orgTimezone = computed(() => kioskStore.kiosk?.organization_timezone || 'UTC');

const currentTime = computed(() => {
  return new Date().toLocaleTimeString('en-US', {
    timeZone: orgTimezone.value,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
});
```

**Time Entry Creation**:
```php
// In KioskSessionService::clockIn()
TimeEntry::create([
  'start' => now(), // Carbon now() uses app timezone (UTC by default)
  'end' => null,
  // ...
]);
```

---

## 8. Migration Strategy

**Date Prefix**: `2026_03_06_` (per SF-03)

**Sequence**:

1. `2026_03_06_000001_create_kiosks_table.php`
2. `2026_03_06_000002_create_kiosk_sessions_table.php`
3. `2026_03_06_000003_add_kiosk_fields_to_members_table.php`
4. `2026_03_06_000004_create_kiosk_pin_attempts_table.php`

**Rollback Safety**:
- All migrations include `down()` methods
- Foreign key constraints use `ON DELETE CASCADE` for org/kiosk relationships
- `ON DELETE SET NULL` for nullable relationships

---

## 9. File Manifest

### 9.1 Backend Files to Create

#### Migrations
- `database/migrations/2026_03_06_000001_create_kiosks_table.php`
- `database/migrations/2026_03_06_000002_create_kiosk_sessions_table.php`
- `database/migrations/2026_03_06_000003_add_kiosk_fields_to_members_table.php`
- `database/migrations/2026_03_06_000004_create_kiosk_pin_attempts_table.php`

#### Models
- `app/Models/Kiosk.php`
- `app/Models/KioskSession.php`
- `app/Models/KioskPinAttempt.php`

#### Enums
- `app/Enums/KioskMode.php`
- `app/Enums/KioskSessionStatus.php`

#### Auth Components
- `app/Auth/KioskGuard.php`
- `app/Auth/KioskTokenProvider.php`
- `app/Http/Middleware/AuthenticateKiosk.php`
- `app/Http/Middleware/KioskSecurityHeaders.php`

#### Controllers
- `app/Http/Controllers/Api/V1/KioskController.php` (Admin CRUD)
- `app/Http/Controllers/Api/V1/MemberPinController.php` (PIN/QR management)
- `app/Http/Controllers/Api/V1/Kiosk/KioskDeviceController.php` (Kiosk device API)
- `app/Http/Controllers/Api/V1/AttendanceController.php` (Attendance dashboard)

#### Requests
- `app/Http/Requests/V1/Kiosk/KioskStoreRequest.php`
- `app/Http/Requests/V1/Kiosk/KioskUpdateRequest.php`
- `app/Http/Requests/V1/Kiosk/KioskPinAuthRequest.php`
- `app/Http/Requests/V1/Kiosk/KioskQrAuthRequest.php`
- `app/Http/Requests/V1/Kiosk/KioskClockInRequest.php`
- `app/Http/Requests/V1/Kiosk/KioskClockOutRequest.php`
- `app/Http/Requests/V1/Kiosk/KioskBreakRequest.php`
- `app/Http/Requests/V1/Member/MemberPinUpdateRequest.php`
- `app/Http/Requests/V1/Member/MemberBadgeTokenRequest.php`
- `app/Http/Requests/V1/Attendance/AttendanceIndexRequest.php`

#### Resources
- `app/Http/Resources/V1/Kiosk/KioskResource.php`
- `app/Http/Resources/V1/Kiosk/KioskCollection.php`
- `app/Http/Resources/V1/Kiosk/KioskWithTokenResource.php`
- `app/Http/Resources/V1/Kiosk/KioskSessionResource.php`
- `app/Http/Resources/V1/Kiosk/KioskMemberResource.php`
- `app/Http/Resources/V1/Kiosk/KioskAttendanceResource.php`
- `app/Http/Resources/V1/Attendance/AttendanceResource.php`
- `app/Http/Resources/V1/Attendance/AttendanceSummaryResource.php`

#### Services
- `app/Service/KioskService.php`
- `app/Service/KioskSessionService.php`
- `app/Service/MemberPinService.php`
- `app/Service/KioskQrService.php`
- `app/Service/AttendanceService.php`

#### Permissions
- `app/Permissions/KioskPermissions.php`

#### Exceptions
- `app/Exceptions/Api/AlreadyClockedInException.php`
- `app/Exceptions/Api/NotClockedInException.php`
- `app/Exceptions/Api/AlreadyOnBreakException.php`
- `app/Exceptions/Api/NotOnBreakException.php`
- `app/Exceptions/Api/DuplicateClockInException.php`

#### Factories
- `database/factories/KioskFactory.php`
- `database/factories/KioskSessionFactory.php`
- `database/factories/KioskPinAttemptFactory.php`

---

### 9.2 Backend Files to Modify

- `app/Models/Member.php` — Add kiosk relationships + PIN fields
- `app/Models/Organization.php` — Add kiosks relationship
- `config/auth.php` — Add kiosk guard and provider
- `app/Providers/AuthServiceProvider.php` — Register kiosk guard
- `app/Providers/JetstreamServiceProvider.php` — Call `KioskPermissions::register()`
- `app/Http/Kernel.php` — Add `auth.kiosk` middleware alias
- `routes/api.php` — Add kiosk routes (admin + device + attendance)
- `routes/web.php` — Add `/kiosk/{token}` route + `/attendance` route

---

### 9.3 Frontend Files to Create

#### Kiosk Standalone SPA
- `resources/views/kiosk.blade.php`
- `resources/js/kiosk.ts` (entry point)
- `resources/css/kiosk.css`
- `resources/js/Pages/Kiosk/KioskApp.vue`
- `resources/js/Pages/Kiosk/KioskIdleScreen.vue`
- `resources/js/Pages/Kiosk/KioskPinPad.vue`
- `resources/js/Pages/Kiosk/KioskQrScanner.vue`
- `resources/js/Pages/Kiosk/KioskMemberStatus.vue`
- `resources/js/Pages/Kiosk/KioskConfirmation.vue`
- `resources/js/Pages/Kiosk/KioskErrorScreen.vue`
- `resources/js/packages/ui/src/Kiosk/KioskClock.vue`
- `resources/js/packages/ui/src/Kiosk/KioskButton.vue`
- `resources/js/packages/ui/src/Kiosk/KioskNumpad.vue`
- `resources/js/utils/useKiosk.ts`
- `resources/js/types/kiosk.d.ts`

#### Attendance Dashboard (Inertia)
- `resources/js/Pages/Attendance.vue`
- `resources/js/Components/Common/Attendance/AttendanceSummaryCards.vue`
- `resources/js/Components/Common/Attendance/AttendanceTable.vue`
- `resources/js/Components/Common/Attendance/AttendanceTableRow.vue`
- `resources/js/Components/Common/Attendance/AttendanceTableHeading.vue`
- `resources/js/Components/Common/Attendance/AttendanceStatusBadge.vue`
- `resources/js/Components/Common/Attendance/AttendanceFilterBar.vue`
- `resources/js/utils/useAttendance.ts`

#### Kiosk Admin UI
- `resources/js/Components/Common/Kiosk/KioskTable.vue`
- `resources/js/Components/Common/Kiosk/KioskTableRow.vue`
- `resources/js/Components/Common/Kiosk/KioskTableHeading.vue`
- `resources/js/Components/Common/Kiosk/KioskCreateModal.vue`
- `resources/js/Components/Common/Kiosk/KioskEditModal.vue`
- `resources/js/Components/Common/Kiosk/KioskTokenModal.vue`
- `resources/js/Components/Common/Kiosk/KioskMoreOptionsDropdown.vue`
- `resources/js/utils/useKiosks.ts`

#### Member QR Management
- `resources/js/Components/Common/Member/MemberQrCodeModal.vue`
- `resources/js/Components/Common/Member/MemberBadgeQrModal.vue`

---

### 9.4 Frontend Files to Modify

- `resources/js/Layouts/AppLayout.vue` — Add attendance navigation item
- `vite.config.js` — Add kiosk entry point (if needed for separate bundle)

---

### 9.5 Testing Files to Create

#### Backend Tests
- `tests/Unit/Model/KioskModelTest.php`
- `tests/Unit/Model/KioskSessionModelTest.php`
- `tests/Unit/Model/KioskPinAttemptModelTest.php`
- `tests/Unit/Service/KioskServiceTest.php`
- `tests/Unit/Service/KioskSessionServiceTest.php`
- `tests/Unit/Service/MemberPinServiceTest.php`
- `tests/Unit/Service/KioskQrServiceTest.php`
- `tests/Unit/Service/AttendanceServiceTest.php`
- `tests/Unit/Endpoint/Api/V1/KioskEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/MemberPinEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/Kiosk/KioskDeviceEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/AttendanceEndpointTest.php`
- `tests/Unit/Auth/KioskGuardTest.php`
- `tests/Feature/KioskClockInOutFlowTest.php`
- `tests/Feature/KioskBreakFlowTest.php`
- `tests/Feature/KioskPinAuthFlowTest.php`

#### Frontend Tests
- `resources/js/packages/ui/src/Kiosk/__tests__/KioskClock.test.ts`
- `resources/js/packages/ui/src/Kiosk/__tests__/KioskNumpad.test.ts`
- `resources/js/utils/__tests__/useKiosk.test.ts`
- `resources/js/utils/__tests__/useAttendance.test.ts`

#### E2E Tests
- `e2e/kiosk-pin-auth.spec.ts`
- `e2e/kiosk-clock-in-out.spec.ts`
- `e2e/kiosk-break-flow.spec.ts`
- `e2e/attendance-dashboard.spec.ts`

---

## 10. Implementation Phases

### Phase 1: Foundation (Sprint 1)
**Focus**: Database, models, auth infrastructure

**Tasks**:
- KIO-001: Database migrations
- KIO-002: Eloquent models + enums
- KIO-003: Kiosk management API (CRUD)
- KIO-004: Kiosk permissions registration
- KIO-005: Member PIN management API
- KIO-022: TypeScript type definitions
- KIO-023: Scaffold kiosk layout

**Deliverable**: Admin can create kiosks and set member PINs via API

---

### Phase 2: Kiosk Core (Sprint 2)
**Focus**: Custom guard, kiosk UI, clock in/out

**Tasks**:
- KIO-006: Custom kiosk authentication guard
- KIO-007a: PIN/QR authentication endpoints
- KIO-007b: Clock in/out + break endpoints
- KIO-008a: Kiosk idle screen + PIN entry + QR scanner
- KIO-008b: Clock status display + action buttons
- KIO-009: QR code scanner component
- KIO-021: Badge QR token support

**Deliverable**: Functional kiosk page with PIN/QR auth and clock in/out

---

### Phase 3: Attendance & Polish (Sprint 3)
**Focus**: Attendance dashboard, break tracking, admin UI

**Tasks**:
- KIO-010: Break tracking logic
- KIO-011: Attendance dashboard backend API
- KIO-012: Attendance dashboard Vue page
- KIO-013: Kiosk management frontend (admin UI)
- KIO-014: Member QR generation UI
- KIO-015: KioskSessionService unit tests
- KIO-016: Kiosk endpoint tests

**Deliverable**: Complete feature with attendance dashboard and admin management

---

### Phase 4: Testing & Documentation (Sprint 4)
**Focus**: Comprehensive testing, E2E flows, documentation

**Tasks**:
- KIO-017: Model unit tests
- KIO-018: Frontend component tests
- KIO-019: E2E Playwright tests
- KIO-020: Feature documentation
- Performance optimization
- Security audit

**Deliverable**: Production-ready feature with full test coverage

---

## 11. Critical Implementation Details

### 11.1 Rate Limiting

**PIN Attempts**:
```php
// In KioskDeviceController::authPin()
$failedAttempts = KioskPinAttempt::query()
    ->where('kiosk_id', $kiosk->id)
    ->where('member_id', $member->id)
    ->where('success', false)
    ->where('attempted_at', '>', now()->subMinutes(15))
    ->count();

if ($failedAttempts >= 5) {
    return response()->json([
        'error' => 'locked_out',
        'locked_until' => now()->addMinutes(10)->toIso8601String(),
    ], 429);
}
```

**Kiosk API Rate Limit**:
```php
// In routes/api.php
Route::middleware(['auth:kiosk', 'throttle:60,1'])->group(...);
// 60 requests per minute per kiosk token
```

---

### 11.2 Error Handling

**Standard Error Response Format**:
```json
{
  "error": "error_code",
  "message": "Human-readable message",
  "details": { /* Optional context */ }
}
```

**HTTP Status Codes**:
- `401 Unauthorized`: Invalid token, PIN, or QR
- `409 Conflict`: Already clocked in, not clocked in, etc.
- `422 Unprocessable Entity`: Validation errors
- `429 Too Many Requests`: Rate limit exceeded

---

### 11.3 State Management

**Kiosk Session State Machine**:
```
idle → [PIN/QR auth] → member_status
  ↑                          ↓
  └── [5s timeout] ←── confirmation
  
member_status states:
- Not clocked in → Show "Clock In" button
- Clocked in → Show "Clock Out" + "Start Break" buttons
- On break → Show "End Break" button
```

**Debouncing**:
- Clock in/out actions debounced (5-second window)
- Prevents duplicate time entries from double-taps

---

### 11.4 Performance Optimizations

**Database Queries**:
- Eager load relationships: `with(['member.user', 'kiosk'])`
- Partial indexes on active sessions
- Denormalized `organization_id` on `kiosk_sessions` for faster queries

**Caching**:
- Kiosk token lookup cached for 60 seconds
- `last_activity_at` updates throttled to once per minute

**Frontend**:
- Kiosk status check every 30 seconds (not every render)
- Attendance dashboard auto-refresh every 30 seconds via Vue Query

---

## 12. Security Checklist

- [ ] HTTPS enforced on all kiosk routes
- [ ] Kiosk tokens stored as SHA-256 hash in database
- [ ] PINs stored as bcrypt hash (never plaintext)
- [ ] PIN uniqueness enforced with dual-hash design (AMD-04)
- [ ] Rate limiting on PIN attempts (5 per 15 min, 10-min lockout)
- [ ] QR tokens signed with `APP_KEY`, expiry enforced
- [ ] Badge tokens revocable by admin
- [ ] Cache-Control: no-store on kiosk page
- [ ] X-Robots-Tag: noindex on kiosk page
- [ ] CSRF exemption for kiosk API routes (token-based auth)
- [ ] Organization blocking middleware on write endpoints
- [ ] Permission checks on all admin endpoints
- [ ] SQL injection prevention via Eloquent ORM
- [ ] Audit logging via `CustomAuditable` trait

---

## 13. Monitoring & Observability

**Metrics to Track**:
- Kiosk uptime (last_activity_at)
- Average clock in/out duration
- Failed PIN attempts per kiosk
- Token regeneration frequency
- Active sessions count

**Alerts**:
- Kiosk offline > 5 minutes
- Failed PIN attempts > 10 in 1 hour (potential attack)
- Break duration > 4 hours (potential forgotten break)

**Logging**:
- All clock in/out/break actions logged via audit trail
- Failed PIN attempts logged in `kiosk_pin_attempts` table
- Token regeneration events logged

---

## 14. Future Enhancements

**Not in Current Scope**:
- Kiosk-specific shift schedules
- Photo capture on clock in/out
- Biometric authentication (fingerprint/face)
- Multi-kiosk session conflict resolution
- Kiosk analytics dashboard
- Scheduled break reminders
- Integration with payroll systems

**Technical Debt Considerations**:
- Cookie-based kiosk auth (instead of token in URL)
- Websocket for real-time attendance updates
- Mobile app for kiosk mode
- Offline-first kiosk support with sync

---

## 15. Definition of Done

**Feature is complete when**:
- [ ] All migrations run cleanly on production
- [ ] Kiosk CRUD API works per spec
- [ ] PIN authentication works with rate limiting
- [ ] QR authentication works (dynamic + badge)
- [ ] Clock in/out creates time entries correctly
- [ ] Break tracking splits time entries properly
- [ ] Attendance dashboard shows real-time data
- [ ] Admin UI manages kiosks via web
- [ ] All unit tests pass (>80% coverage)
- [ ] All E2E tests pass
- [ ] Security audit completed (HTTPS, rate limiting, token security)
- [ ] Documentation updated (README, API docs)
- [ ] Performance benchmarks met (300ms p95 for kiosk API)
- [ ] Deployed to staging and tested by QA
- [ ] Product owner sign-off

---

**Last Updated**: 2026-02-06  
**Architect**: Claude (Sonnet 4.5)  
**Status**: Ready for Implementation