# Feature 15: Attendance & Overtime Tracking -- Technical Architecture

**Date**: 2026-02-09
**Status**: Draft
**Feature Branch**: `feature/attendance-overtime` (from `main`)
**Task Prefix**: `ATT-` (per task assignments)

---

## Executive Summary

This document provides the complete technical architecture for the **Attendance & Overtime Tracking** feature. The feature adds daily attendance computation, overtime calculation, break detection, work schedule policy management, and reporting capabilities to Solidtime.

**Key Architectural Decisions**:
- **4 new database tables**: `work_schedule_policies`, `member_work_schedules`, `overtime_rules`, `attendance_records`
- **2 new enums**: `AttendanceStatus`, `OvertimeRuleType`
- **2 new services**: `AttendanceService` (computation engine), `WorkScheduleService` (policy resolution)
- **3 new controllers**: `AttendanceController`, `WorkScheduleController`, `OvertimeRuleController`
- **15 API endpoints** across the 3 controllers
- **1 scheduled Artisan command**: `attendance:compute` running daily
- **5 new attendance permissions** registered via the modular pattern (FOUND-007)
- **Frontend**: Pinia store + 11 Vue components + Inertia.js page
- **Soft dependencies** on Feature 06 (Kiosk) and Feature 07 (PTO) via `class_exists()` runtime checks
- **Stale record marking** via model observers on `TimeEntry` create/update/delete

---

## Table of Contents

1. [Data Model Design](#1-data-model-design)
2. [API Contract](#2-api-contract)
3. [Service Layer](#3-service-layer)
4. [Scheduled Command](#4-scheduled-command)
5. [Controller Layer](#5-controller-layer)
6. [Request Validation](#6-request-validation)
7. [Frontend Architecture](#7-frontend-architecture)
8. [Permission Matrix](#8-permission-matrix)
9. [Performance Strategy](#9-performance-strategy)
10. [Integration Points](#10-integration-points)
11. [File Manifest](#11-file-manifest)

---

## 1. Data Model Design

### 1.1 New Tables Overview

This feature introduces 4 new database tables. None of the existing tables are modified.

```
+----------------------------+       +----------------------------+
| work_schedule_policies     |       | overtime_rules             |
| (org-level schedule defs)  |       | (org-level OT thresholds)  |
+----------------------------+       +----------------------------+
| id                  UUID PK|       | id                  UUID PK|
| organization_id     UUID FK|       | organization_id     UUID FK|
| name            VARCHAR(255)|      | name            VARCHAR(255)|
| description           TEXT |       | rule_type       VARCHAR(50)|
| is_default         BOOLEAN |       | threshold_seconds  INTEGER|
| is_active          BOOLEAN |       | multiplier     DECIMAL(4,2)|
| monday_seconds     INTEGER |       | is_active          BOOLEAN |
| tuesday_seconds    INTEGER |       | effective_from        DATE |
| wednesday_seconds  INTEGER |       | effective_until       DATE |
| thursday_seconds   INTEGER |       | created_at       TIMESTAMP |
| friday_seconds     INTEGER |       | updated_at       TIMESTAMP |
| saturday_seconds   INTEGER |       +----------------------------+
| sunday_seconds     INTEGER |
| min_break_minutes  INTEGER |
| break_required_after_hours |
|                DECIMAL(4,2)|
| min_break_gap_minutes      |
|                    INTEGER |
| max_break_gap_minutes      |
|                    INTEGER |
| timezone        VARCHAR(64)|
| created_at       TIMESTAMP |
| updated_at       TIMESTAMP |
+----------------------------+
            |
            | 1:N
            v
+----------------------------+
| member_work_schedules      |
| (pivot: member <-> policy) |
+----------------------------+
| id                  UUID PK|
| member_id           UUID FK| --> members.id
| work_schedule_policy_id    |
|                     UUID FK| --> work_schedule_policies.id
| effective_from        DATE |
| effective_until       DATE |
| created_at       TIMESTAMP |
| updated_at       TIMESTAMP |
+----------------------------+

+----------------------------+
| attendance_records         |
| (computed, 1 per member/day)|
+----------------------------+
| id                  UUID PK|
| member_id           UUID FK| --> members.id
| organization_id     UUID FK| --> organizations.id
| date                  DATE |
| status          VARCHAR(20)| --> AttendanceStatus enum
| expected_seconds   INTEGER |
| actual_seconds     INTEGER |
| overtime_seconds   INTEGER |
| double_time_seconds INTEGER|
| undertime_seconds  INTEGER |
| break_seconds      INTEGER |
| break_compliant    BOOLEAN |
| first_entry_at   TIMESTAMP |
| last_entry_at    TIMESTAMP |
| time_entry_count   INTEGER |
| work_schedule_policy_id    |
|                     UUID FK| --> work_schedule_policies.id (SET NULL on delete)
| is_stale           BOOLEAN | --> marked for recomputation
| computed_at      TIMESTAMP |
| created_at       TIMESTAMP |
| updated_at       TIMESTAMP |
+----------------------------+
  UNIQUE(member_id, date)
```

### 1.2 New Eloquent Models

#### WorkSchedulePolicy

**File**: `app/Models/WorkSchedulePolicy.php`

```php
class WorkSchedulePolicy extends Model implements AuditableContract
{
    use CustomAuditable, HasFactory, HasUuids;

    protected $casts = [
        'is_default' => 'bool',
        'is_active' => 'bool',
        'monday_seconds' => 'int',
        'tuesday_seconds' => 'int',
        'wednesday_seconds' => 'int',
        'thursday_seconds' => 'int',
        'friday_seconds' => 'int',
        'saturday_seconds' => 'int',
        'sunday_seconds' => 'int',
        'min_break_minutes' => 'int',
        'break_required_after_hours' => 'decimal:2',
        'min_break_gap_minutes' => 'int',
        'max_break_gap_minutes' => 'int',
    ];

    // Relationships
    public function organization(): BelongsTo { /* Organization */ }
    public function memberWorkSchedules(): HasMany { /* MemberWorkSchedule */ }
    public function attendanceRecords(): HasMany { /* AttendanceRecord */ }

    // Helper: get expected seconds for a given Weekday
    public function getExpectedSecondsForWeekday(Weekday $day): int
    {
        return match ($day) {
            Weekday::Monday    => $this->monday_seconds,
            Weekday::Tuesday   => $this->tuesday_seconds,
            Weekday::Wednesday => $this->wednesday_seconds,
            Weekday::Thursday  => $this->thursday_seconds,
            Weekday::Friday    => $this->friday_seconds,
            Weekday::Saturday  => $this->saturday_seconds,
            Weekday::Sunday    => $this->sunday_seconds,
        };
    }
}
```

#### MemberWorkSchedule

**File**: `app/Models/MemberWorkSchedule.php`

```php
class MemberWorkSchedule extends Model implements AuditableContract
{
    use CustomAuditable, HasFactory, HasUuids;

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    // Relationships
    public function member(): BelongsTo { /* Member */ }
    public function workSchedulePolicy(): BelongsTo { /* WorkSchedulePolicy */ }
}
```

#### OvertimeRule

**File**: `app/Models/OvertimeRule.php`

```php
class OvertimeRule extends Model implements AuditableContract
{
    use CustomAuditable, HasFactory, HasUuids;

    protected $casts = [
        'rule_type' => OvertimeRuleType::class,
        'threshold_seconds' => 'int',
        'multiplier' => 'decimal:2',
        'is_active' => 'bool',
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    // Relationships
    public function organization(): BelongsTo { /* Organization */ }
}
```

#### AttendanceRecord

**File**: `app/Models/AttendanceRecord.php`

```php
class AttendanceRecord extends Model implements AuditableContract
{
    use CustomAuditable, HasFactory, HasUuids;

    protected $casts = [
        'date' => 'date',
        'status' => AttendanceStatus::class,
        'expected_seconds' => 'int',
        'actual_seconds' => 'int',
        'overtime_seconds' => 'int',
        'double_time_seconds' => 'int',
        'undertime_seconds' => 'int',
        'break_seconds' => 'int',
        'break_compliant' => 'bool',
        'first_entry_at' => 'datetime',
        'last_entry_at' => 'datetime',
        'time_entry_count' => 'int',
        'is_stale' => 'bool',
        'computed_at' => 'datetime',
    ];

    // Relationships
    public function member(): BelongsTo { /* Member */ }
    public function organization(): BelongsTo { /* Organization */ }
    public function workSchedulePolicy(): BelongsTo { /* WorkSchedulePolicy */ }
}
```

### 1.3 New Enums

#### AttendanceStatus

**File**: `app/Enums/AttendanceStatus.php`

```php
enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case HalfDay = 'half_day';
    case OnLeave = 'on_leave';
    case Holiday = 'holiday';
    case RestDay = 'rest_day';
}
```

Follows the existing enum pattern used by `Role`, `ApprovalStatus`, and `Weekday`. Uses string-backed values for database storage and API serialization.

#### OvertimeRuleType

**File**: `app/Enums/OvertimeRuleType.php`

```php
enum OvertimeRuleType: string
{
    case DailyThreshold = 'daily_threshold';
    case WeeklyThreshold = 'weekly_threshold';
    case DailyDoubleTime = 'daily_double_time';
    case RestDayWork = 'rest_day_work';
    case HolidayWork = 'holiday_work';
}
```

### 1.4 Migration Details

Per SHARED-FOUNDATIONS.md SF-03 convention, Feature 15 uses date prefix `2026_03_15_`:

| Migration | File | Task |
|-----------|------|------|
| Create `work_schedule_policies` table | `2026_03_15_000001_create_work_schedule_policies_table.php` | ATT-001 |
| Create `member_work_schedules` table | `2026_03_15_000002_create_member_work_schedules_table.php` | ATT-001 |
| Create `overtime_rules` table | `2026_03_15_000003_create_overtime_rules_table.php` | ATT-001 |
| Create `attendance_records` table | `2026_03_15_000004_create_attendance_records_table.php` | ATT-001 |

**Key constraints and indexes**:

```sql
-- work_schedule_policies
CONSTRAINT uq_org_default_policy UNIQUE (organization_id, is_default) WHERE is_default = TRUE
INDEX idx_wsp_org (organization_id)

-- member_work_schedules
INDEX idx_mws_member (member_id)
INDEX idx_mws_policy (work_schedule_policy_id)
INDEX idx_mws_effective (member_id, effective_from, effective_until)

-- overtime_rules
INDEX idx_or_org (organization_id)
INDEX idx_or_effective (organization_id, effective_from, effective_until)

-- attendance_records
CONSTRAINT uq_attendance_member_date UNIQUE (member_id, date)
INDEX idx_ar_org_date (organization_id, date)
INDEX idx_ar_member_date (member_id, date)
INDEX idx_ar_stale (organization_id, is_stale) WHERE is_stale = TRUE
```

### 1.5 Existing Model Usage (Read-Only)

| Model | Usage |
|-------|-------|
| `TimeEntry` | Source data for attendance computation. Query `start`, `end`, `member_id` for day summaries. |
| `Member` | Target of attendance tracking. Uses `weekly_capacity` as fallback when no policy is configured. |
| `Organization` | Scoping, `default_weekly_capacity` as fallback. |
| `User` | Timezone retrieval via `$user->timezone`. |
| `Project` | Not directly used, but time entries reference projects. |

**No schema changes to existing tables.**

---

## 2. API Contract

### 2.1 Route Registration

**File**: `routes/api.php` (inside existing `auth:api` + `verified` middleware group)

```php
// Work Schedule routes
Route::name('work-schedules.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/work-schedules', [WorkScheduleController::class, 'index'])->name('index');
    Route::post('/work-schedules', [WorkScheduleController::class, 'store'])
        ->name('store')->middleware('check-organization-blocked');
    Route::get('/work-schedules/{workSchedule}', [WorkScheduleController::class, 'show'])->name('show');
    Route::put('/work-schedules/{workSchedule}', [WorkScheduleController::class, 'update'])
        ->name('update')->middleware('check-organization-blocked');
    Route::delete('/work-schedules/{workSchedule}', [WorkScheduleController::class, 'destroy'])
        ->name('destroy')->middleware('check-organization-blocked');
    Route::post('/work-schedules/assign', [WorkScheduleController::class, 'assign'])
        ->name('assign')->middleware('check-organization-blocked');
});

// Overtime Rule routes
Route::name('overtime-rules.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/overtime-rules', [OvertimeRuleController::class, 'index'])->name('index');
    Route::post('/overtime-rules', [OvertimeRuleController::class, 'store'])
        ->name('store')->middleware('check-organization-blocked');
    Route::put('/overtime-rules/{overtimeRule}', [OvertimeRuleController::class, 'update'])
        ->name('update')->middleware('check-organization-blocked');
    Route::delete('/overtime-rules/{overtimeRule}', [OvertimeRuleController::class, 'destroy'])
        ->name('destroy')->middleware('check-organization-blocked');
});

// Attendance routes
Route::name('attendance.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/attendance', [AttendanceController::class, 'dailySummary'])->name('daily-summary');
    Route::get('/attendance/member/{member}', [AttendanceController::class, 'memberDetail'])->name('member-detail');
    Route::get('/attendance/overtime', [AttendanceController::class, 'overtimeReport'])->name('overtime-report');
    Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('export');
    Route::post('/attendance/recompute', [AttendanceController::class, 'recompute'])
        ->name('recompute')->middleware('check-organization-blocked');
});
```

Route names resolve to:
- `api.v1.work-schedules.{index|store|show|update|destroy|assign}`
- `api.v1.overtime-rules.{index|store|update|destroy}`
- `api.v1.attendance.{daily-summary|member-detail|overtime-report|export|recompute}`

All write endpoints use `check-organization-blocked` middleware.

### 2.2 Endpoint Summary Table

| # | Method | Path | Controller Method | Request Class | Permission |
|---|--------|------|-------------------|---------------|------------|
| 1 | GET | `/work-schedules` | `index()` | -- | `attendance:view` or `attendance:configure` |
| 2 | POST | `/work-schedules` | `store()` | `WorkScheduleStoreRequest` | `attendance:configure` |
| 3 | GET | `/work-schedules/{ws}` | `show()` | -- | `attendance:view` or `attendance:configure` |
| 4 | PUT | `/work-schedules/{ws}` | `update()` | `WorkScheduleUpdateRequest` | `attendance:configure` |
| 5 | DELETE | `/work-schedules/{ws}` | `destroy()` | -- | `attendance:configure` |
| 6 | POST | `/work-schedules/assign` | `assign()` | `WorkScheduleAssignRequest` | `attendance:configure` |
| 7 | GET | `/overtime-rules` | `index()` | -- | `attendance:view` or `attendance:configure` |
| 8 | POST | `/overtime-rules` | `store()` | `OvertimeRuleStoreRequest` | `attendance:configure` |
| 9 | PUT | `/overtime-rules/{rule}` | `update()` | `OvertimeRuleUpdateRequest` | `attendance:configure` |
| 10 | DELETE | `/overtime-rules/{rule}` | `destroy()` | -- | `attendance:configure` |
| 11 | GET | `/attendance` | `dailySummary()` | `AttendanceDailySummaryRequest` | `attendance:view:all` or `attendance:view:own` |
| 12 | GET | `/attendance/member/{m}` | `memberDetail()` | `AttendanceMemberDetailRequest` | `attendance:view:all` or `attendance:view:own` (self) |
| 13 | GET | `/attendance/overtime` | `overtimeReport()` | `AttendanceOvertimeReportRequest` | `attendance:view:all` |
| 14 | GET | `/attendance/export` | `export()` | `AttendanceExportRequest` | `attendance:export` |
| 15 | POST | `/attendance/recompute` | `recompute()` | `AttendanceRecomputeRequest` | `attendance:recompute` |

### 2.3 Response Shapes

**GET /work-schedules** -> `JsonResponse`:
```json
{
  "data": [
    {
      "id": "uuid",
      "organization_id": "uuid",
      "name": "Standard Full-Time",
      "description": "8h Mon-Fri",
      "is_default": true,
      "is_active": true,
      "monday_seconds": 28800,
      "tuesday_seconds": 28800,
      "wednesday_seconds": 28800,
      "thursday_seconds": 28800,
      "friday_seconds": 28800,
      "saturday_seconds": 0,
      "sunday_seconds": 0,
      "min_break_minutes": 30,
      "break_required_after_hours": 6.00,
      "timezone": "Europe/Berlin",
      "created_at": "2026-02-09T00:00:00.000000Z",
      "updated_at": "2026-02-09T00:00:00.000000Z"
    }
  ]
}
```

**GET /attendance** -> `JsonResponse`:
```json
{
  "data": {
    "date": "2026-02-08",
    "members": [
      {
        "member_id": "uuid",
        "member_name": "Alice",
        "status": "present",
        "expected_seconds": 28800,
        "actual_seconds": 32400,
        "overtime_seconds": 3600,
        "break_seconds": 1800,
        "break_compliant": true
      }
    ],
    "totals": {
      "present": 8,
      "absent": 1,
      "late": 0,
      "on_leave": 2,
      "holiday": 0
    }
  }
}
```

**GET /attendance/member/{member}** -> `JsonResponse`:
```json
{
  "data": {
    "member_id": "uuid",
    "member_name": "Alice",
    "date_from": "2026-02-01",
    "date_to": "2026-02-28",
    "records": [
      {
        "id": "uuid",
        "date": "2026-02-03",
        "status": "present",
        "expected_seconds": 28800,
        "actual_seconds": 32400,
        "overtime_seconds": 3600,
        "double_time_seconds": 0,
        "undertime_seconds": 0,
        "break_seconds": 1800,
        "break_compliant": true,
        "first_entry_at": "2026-02-03T08:00:00Z",
        "last_entry_at": "2026-02-03T17:00:00Z",
        "time_entry_count": 3
      }
    ],
    "totals": {
      "total_expected_seconds": 576000,
      "total_actual_seconds": 590400,
      "total_overtime_seconds": 14400,
      "total_double_time_seconds": 0,
      "total_undertime_seconds": 0,
      "total_break_seconds": 36000,
      "days_present": 18,
      "days_absent": 0,
      "days_late": 2,
      "days_on_leave": 0,
      "days_holiday": 0,
      "days_rest": 8
    }
  }
}
```

**GET /attendance/overtime** -> `JsonResponse`:
```json
{
  "data": [
    {
      "member_id": "uuid",
      "member_name": "Alice",
      "period_start": "2026-02-01",
      "period_end": "2026-02-28",
      "regular_seconds": 576000,
      "daily_overtime_seconds": 7200,
      "daily_double_time_seconds": 0,
      "weekly_overtime_seconds": 3600,
      "rest_day_overtime_seconds": 0,
      "holiday_overtime_seconds": 0,
      "total_overtime_seconds": 10800,
      "overtime_cost": null
    }
  ]
}
```

**POST /attendance/recompute** -> `JsonResponse` (202 Accepted):
```json
{
  "data": {
    "message": "Recomputation queued",
    "dates_affected": 7,
    "members_affected": 12
  }
}
```

---

## 3. Service Layer

### 3.1 WorkScheduleService

**File**: `app/Service/WorkScheduleService.php`

Stateless service that resolves which work schedule policy applies to a given member on a given date.

#### `getEffectivePolicy(Member $member, Carbon $date): WorkSchedulePolicy`

Resolution order:
1. Check `member_work_schedules` for an active assignment where `effective_from <= $date` and (`effective_until IS NULL` or `effective_until >= $date`), ordered by `effective_from DESC`, take the first
2. If no member-level assignment: get the organization's default policy (`is_default = true AND is_active = true`)
3. If no org default: return a system-default policy object (8h Mon-Fri, not persisted)

```php
public function getEffectivePolicy(Member $member, Carbon $date): WorkSchedulePolicy
{
    // Step 1: Member-specific assignment
    $assignment = MemberWorkSchedule::query()
        ->where('member_id', $member->id)
        ->where('effective_from', '<=', $date)
        ->where(function ($q) use ($date) {
            $q->whereNull('effective_until')
              ->orWhere('effective_until', '>=', $date);
        })
        ->orderByDesc('effective_from')
        ->first();

    if ($assignment !== null) {
        $policy = $assignment->workSchedulePolicy;
        if ($policy !== null && $policy->is_active) {
            return $policy;
        }
    }

    // Step 2: Organization default
    $orgDefault = WorkSchedulePolicy::query()
        ->where('organization_id', $member->organization_id)
        ->where('is_default', true)
        ->where('is_active', true)
        ->first();

    if ($orgDefault !== null) {
        return $orgDefault;
    }

    // Step 3: System default (8h Mon-Fri)
    return $this->getSystemDefaultPolicy();
}
```

#### `getExpectedSecondsForDate(Member $member, Carbon $date): int`

Returns the expected work seconds for a specific member on a specific date, using the effective policy.

```php
public function getExpectedSecondsForDate(Member $member, Carbon $date): int
{
    $policy = $this->getEffectivePolicy($member, $date);
    $weekday = Weekday::from(strtolower($date->format('l')));
    return $policy->getExpectedSecondsForWeekday($weekday);
}
```

#### `isWorkDay(Member $member, Carbon $date): bool`

Returns `true` if expected seconds > 0 for the given date.

#### `isRestDay(Member $member, Carbon $date): bool`

Returns `true` if expected seconds == 0 for the given date.

### 3.2 AttendanceService

**File**: `app/Service/AttendanceService.php`

The core computation engine. Stateless service injected into both the controller and the scheduled command.

#### `computeAttendanceForMemberDate(Member $member, Carbon $date): AttendanceRecord`

The primary computation method. Creates or updates an `AttendanceRecord` for the given member and date.

**Algorithm**:

```
1. Resolve effective policy via WorkScheduleService
2. Get expected_seconds for this weekday
3. Determine day status priority:
   a. Is holiday? (Feature 07 integration) -> status = holiday
   b. Is rest day? (expected_seconds == 0) -> status = rest_day
   c. Is on leave? (Feature 07 integration) -> status = on_leave
   d. Is a work day -> proceed to time entry analysis
4. Query TimeEntry records for this member+date:
   - WHERE member_id = ? AND date(start AT TIME ZONE policy.timezone) = ? AND end IS NOT NULL
   - ORDER BY start ASC
5. Compute actual_seconds = SUM(EXTRACT(EPOCH FROM (end - start)))
6. Determine work day status:
   - actual_seconds == 0 -> absent
   - actual_seconds < expected_seconds * 0.5 -> half_day
   - first entry starts after grace period -> late
   - else -> present
7. Compute overtime via computeOvertime()
8. Compute breaks via computeBreaks()
9. Upsert AttendanceRecord (ON CONFLICT member_id, date DO UPDATE)
10. Return the record
```

```php
public function computeAttendanceForMemberDate(
    Member $member,
    Carbon $date
): AttendanceRecord {
    $workScheduleService = app(WorkScheduleService::class);
    $policy = $workScheduleService->getEffectivePolicy($member, $date);
    $expectedSeconds = $workScheduleService->getExpectedSecondsForDate($member, $date);

    // Priority checks
    if ($this->isHoliday($member->organization_id, $date)) {
        $status = AttendanceStatus::Holiday;
    } elseif ($expectedSeconds === 0) {
        $status = AttendanceStatus::RestDay;
    } elseif ($this->isOnLeave($member->id, $date)) {
        $status = AttendanceStatus::OnLeave;
    } else {
        $status = null; // Determined below from time entries
    }

    // Query time entries for this day
    $entries = TimeEntry::query()
        ->where('member_id', $member->id)
        ->whereNotNull('end')
        ->whereRaw("date(start AT TIME ZONE ?) = ?", [$policy->timezone, $date->toDateString()])
        ->orderBy('start')
        ->get();

    $actualSeconds = $this->sumEntrySeconds($entries);
    $entryCount = $entries->count();
    $firstEntryAt = $entries->first()?->start;
    $lastEntryAt = $entries->last()?->end;

    // Determine status for work days
    if ($status === null) {
        if ($actualSeconds === 0) {
            $status = AttendanceStatus::Absent;
        } elseif ($actualSeconds < (int) ($expectedSeconds * 0.5)) {
            $status = AttendanceStatus::HalfDay;
        } else {
            $status = AttendanceStatus::Present;
        }
    }

    // Compute overtime
    $overtimeResult = $this->computeOvertime($member, $date, $actualSeconds, $expectedSeconds, $status);

    // Compute breaks
    $breakResult = $this->computeBreaks($entries, $policy, $actualSeconds);

    // Upsert
    return AttendanceRecord::updateOrCreate(
        ['member_id' => $member->id, 'date' => $date],
        [
            'organization_id' => $member->organization_id,
            'status' => $status,
            'expected_seconds' => $expectedSeconds,
            'actual_seconds' => $actualSeconds,
            'overtime_seconds' => $overtimeResult['overtime'],
            'double_time_seconds' => $overtimeResult['double_time'],
            'undertime_seconds' => max(0, $expectedSeconds - $actualSeconds),
            'break_seconds' => $breakResult['total_break_seconds'],
            'break_compliant' => $breakResult['compliant'],
            'first_entry_at' => $firstEntryAt,
            'last_entry_at' => $lastEntryAt,
            'time_entry_count' => $entryCount,
            'work_schedule_policy_id' => $policy->exists ? $policy->id : null,
            'is_stale' => false,
            'computed_at' => now(),
        ]
    );
}
```

#### `computeOvertime(Member $member, Carbon $date, int $actualSeconds, int $expectedSeconds, AttendanceStatus $status): array`

Computes daily overtime for a single day. Weekly overtime is computed separately during report generation.

```php
public function computeOvertime(
    Member $member,
    Carbon $date,
    int $actualSeconds,
    int $expectedSeconds,
    AttendanceStatus $status
): array {
    $rules = OvertimeRule::query()
        ->where('organization_id', $member->organization_id)
        ->where('is_active', true)
        ->where('effective_from', '<=', $date)
        ->where(function ($q) use ($date) {
            $q->whereNull('effective_until')
              ->orWhere('effective_until', '>=', $date);
        })
        ->get();

    $overtime = 0;
    $doubleTime = 0;

    // Rest day / holiday work: entire actual hours are overtime
    if ($status === AttendanceStatus::RestDay) {
        $restDayRule = $rules->firstWhere('rule_type', OvertimeRuleType::RestDayWork);
        if ($restDayRule !== null) {
            $overtime = $actualSeconds;
            return ['overtime' => $overtime, 'double_time' => $doubleTime];
        }
    }

    if ($status === AttendanceStatus::Holiday) {
        $holidayRule = $rules->firstWhere('rule_type', OvertimeRuleType::HolidayWork);
        if ($holidayRule !== null) {
            $overtime = $actualSeconds;
            return ['overtime' => $overtime, 'double_time' => $doubleTime];
        }
    }

    // Daily threshold
    $dailyRule = $rules->firstWhere('rule_type', OvertimeRuleType::DailyThreshold);
    if ($dailyRule !== null && $dailyRule->threshold_seconds !== null) {
        $overtime = max(0, $actualSeconds - $dailyRule->threshold_seconds);
    }

    // Daily double-time threshold
    $doubleTimeRule = $rules->firstWhere('rule_type', OvertimeRuleType::DailyDoubleTime);
    if ($doubleTimeRule !== null && $doubleTimeRule->threshold_seconds !== null) {
        $doubleTime = max(0, $actualSeconds - $doubleTimeRule->threshold_seconds);
        // Adjust overtime: overtime = OT threshold to DT threshold range
        if ($doubleTime > 0 && $dailyRule !== null) {
            $overtime = max(0, $overtime - $doubleTime);
        }
    }

    return ['overtime' => $overtime, 'double_time' => $doubleTime];
}
```

#### `computeBreaks(Collection $entries, WorkSchedulePolicy $policy, int $actualSeconds): array`

Detects breaks from gaps between consecutive time entries.

```php
public function computeBreaks(
    Collection $entries,
    WorkSchedulePolicy $policy,
    int $actualSeconds
): array {
    $totalBreakSeconds = 0;
    $minGap = ($policy->min_break_gap_minutes ?? 5) * 60;
    $maxGap = ($policy->max_break_gap_minutes ?? 180) * 60;

    for ($i = 1; $i < $entries->count(); $i++) {
        $prevEnd = $entries[$i - 1]->end;
        $currStart = $entries[$i]->start;
        $gap = $currStart->diffInSeconds($prevEnd);

        if ($gap >= $minGap && $gap <= $maxGap) {
            $totalBreakSeconds += $gap;
        }
    }

    // Compliance check
    $compliant = null;
    if ($policy->min_break_minutes !== null && $policy->break_required_after_hours !== null) {
        $thresholdSeconds = (int) ($policy->break_required_after_hours * 3600);
        if ($actualSeconds > $thresholdSeconds) {
            $requiredBreakSeconds = $policy->min_break_minutes * 60;
            $compliant = $totalBreakSeconds >= $requiredBreakSeconds;
        }
    }

    return [
        'total_break_seconds' => $totalBreakSeconds,
        'compliant' => $compliant,
    ];
}
```

#### Soft Integration Methods

```php
public function isOnLeave(string $memberId, Carbon $date): bool
{
    if (!class_exists(\App\Models\TimeOffRequest::class)) {
        return false;
    }
    return \App\Models\TimeOffRequest::query()
        ->where('member_id', $memberId)
        ->where('status', 'approved')
        ->where('start_date', '<=', $date)
        ->where('end_date', '>=', $date)
        ->exists();
}

public function isHoliday(string $organizationId, Carbon $date): bool
{
    if (!class_exists(\App\Models\Holiday::class)) {
        return false;
    }
    return \App\Models\Holiday::query()
        ->where('organization_id', $organizationId)
        ->where(function ($q) use ($date) {
            $q->where('date', $date->toDateString())
              ->orWhere(function ($q2) use ($date) {
                  $q2->where('is_recurring', true)
                     ->whereMonth('date', $date->month)
                     ->whereDay('date', $date->day);
              });
        })
        ->exists();
}
```

#### Reporting Methods

```php
public function getDailySummary(Organization $org, Carbon $date, ?array $memberIds, ?array $statusFilter): array
public function getMemberDetail(Organization $org, Member $member, Carbon $from, Carbon $to): array
public function getOvertimeReport(Organization $org, Carbon $from, Carbon $to, ?array $memberIds, string $aggregation): array
```

These methods query the pre-computed `attendance_records` table and return structured arrays for the API response.

---

## 4. Scheduled Command

### 4.1 ComputeAttendanceCommand

**File**: `app/Console/Commands/ComputeAttendanceCommand.php`

```php
class ComputeAttendanceCommand extends Command
{
    protected $signature = 'attendance:compute
        {--date= : Specific date to compute (YYYY-MM-DD, default: yesterday)}
        {--member= : Specific member UUID to compute}
        {--recompute : Force recomputation of stale records}
        {--organization= : Specific organization UUID}';

    protected $description = 'Compute daily attendance records from time entries';
}
```

**Execution flow**:
1. Determine target date (default: yesterday in UTC)
2. If `--recompute`: query all stale records and recompute those specific dates+members
3. Otherwise: iterate all active organizations, then all active members, call `AttendanceService::computeAttendanceForMemberDate()`
4. Log summary: "Computed attendance for X members, Y records created/updated"

**Performance**: Members are processed in chunks of 50 to limit memory usage.

### 4.2 Scheduler Registration

**File**: `app/Console/Kernel.php`

```php
$schedule->command('attendance:compute')
    ->when(fn (): bool => config('scheduling.tasks.attendance_compute'))
    ->dailyAt('02:00');

$schedule->command('attendance:compute --recompute')
    ->when(fn (): bool => config('scheduling.tasks.attendance_compute'))
    ->everyThirtyMinutes();
```

The recompute run processes only stale records (marked when time entries change), keeping it fast.

### 4.3 Stale Record Marking

**File**: `app/Observers/TimeEntryObserver.php` (or model event in `TimeEntry` boot method)

When a `TimeEntry` is created, updated, or deleted, mark the corresponding `AttendanceRecord` as stale:

```php
// In TimeEntry model boot() or via observer
static::saved(function (TimeEntry $entry) {
    if ($entry->end !== null) {
        AttendanceRecord::query()
            ->where('member_id', $entry->member_id)
            ->where('date', $entry->start->toDateString())
            ->update(['is_stale' => true]);
    }
});

static::deleted(function (TimeEntry $entry) {
    AttendanceRecord::query()
        ->where('member_id', $entry->member_id)
        ->where('date', $entry->start->toDateString())
        ->update(['is_stale' => true]);
});
```

This is implemented in ATT-026 and ensures attendance data stays consistent without requiring real-time recomputation.

---

## 5. Controller Layer

### 5.1 WorkScheduleController

**File**: `app/Http/Controllers/Api/V1/WorkScheduleController.php`

Extends `App\Http\Controllers\Api\V1\Controller`. Standard CRUD controller for work schedule policies plus a member assignment endpoint.

```php
class WorkScheduleController extends Controller
{
    public function index(Organization $organization): JsonResponse
    {
        $this->checkAnyPermission($organization, [
            'attendance:view:all', 'attendance:view:own', 'attendance:configure',
        ]);
        $policies = WorkSchedulePolicy::query()
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->get();
        return response()->json(['data' => $policies]);
    }

    public function store(Organization $organization, WorkScheduleStoreRequest $request): JsonResponse
    {
        $this->checkPermission($organization, 'attendance:configure');
        // Create policy, handle is_default toggling
        return response()->json(['data' => $policy], 201);
    }

    public function show(Organization $organization, WorkSchedulePolicy $workSchedule): JsonResponse
    {
        $this->checkAnyPermission($organization, [
            'attendance:view:all', 'attendance:view:own', 'attendance:configure',
        ]);
        return response()->json(['data' => $workSchedule]);
    }

    public function update(Organization $organization, WorkSchedulePolicy $workSchedule, WorkScheduleUpdateRequest $request): JsonResponse
    {
        $this->checkPermission($organization, 'attendance:configure');
        // Update policy, handle is_default toggling
        return response()->json(['data' => $workSchedule]);
    }

    public function destroy(Organization $organization, WorkSchedulePolicy $workSchedule): JsonResponse
    {
        $this->checkPermission($organization, 'attendance:configure');
        if ($workSchedule->is_default) {
            return response()->json(['error' => 'Cannot delete the default policy'], 409);
        }
        $workSchedule->delete();
        return response()->noContent();
    }

    public function assign(Organization $organization, WorkScheduleAssignRequest $request): JsonResponse
    {
        $this->checkPermission($organization, 'attendance:configure');
        // Create MemberWorkSchedule record
        return response()->json(['data' => $assignment], 201);
    }
}
```

### 5.2 OvertimeRuleController

**File**: `app/Http/Controllers/Api/V1/OvertimeRuleController.php`

Standard CRUD controller for overtime rules.

```php
class OvertimeRuleController extends Controller
{
    public function index(Organization $organization): JsonResponse { /* list rules */ }
    public function store(Organization $organization, OvertimeRuleStoreRequest $request): JsonResponse { /* create rule */ }
    public function update(Organization $organization, OvertimeRule $overtimeRule, OvertimeRuleUpdateRequest $request): JsonResponse { /* update rule */ }
    public function destroy(Organization $organization, OvertimeRule $overtimeRule): JsonResponse { /* delete rule */ }
}
```

### 5.3 AttendanceController

**File**: `app/Http/Controllers/Api/V1/AttendanceController.php`

Read-only reporting controller plus recompute action. Depends on `AttendanceService`.

```php
class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService
    ) {
        parent::__construct(app(PermissionStore::class));
    }

    public function dailySummary(Organization $organization, AttendanceDailySummaryRequest $request): JsonResponse
    {
        // Checks attendance:view:all or attendance:view:own
        // Scopes to own record only if only view:own
        $data = $this->attendanceService->getDailySummary(
            $organization,
            Carbon::parse($request->validated('date')),
            $request->validated('member_ids'),
            $request->validated('status')
        );
        return response()->json(['data' => $data]);
    }

    public function memberDetail(Organization $organization, Member $member, AttendanceMemberDetailRequest $request): JsonResponse
    {
        // Check attendance:view:all, or attendance:view:own if member is self
        $data = $this->attendanceService->getMemberDetail(
            $organization,
            $member,
            Carbon::parse($request->validated('date_from')),
            Carbon::parse($request->validated('date_to'))
        );
        return response()->json(['data' => $data]);
    }

    public function overtimeReport(Organization $organization, AttendanceOvertimeReportRequest $request): JsonResponse
    {
        $this->checkPermission($organization, 'attendance:view:all');
        $data = $this->attendanceService->getOvertimeReport(
            $organization,
            Carbon::parse($request->validated('date_from')),
            Carbon::parse($request->validated('date_to')),
            $request->validated('member_ids'),
            $request->validated('aggregation', 'daily')
        );
        return response()->json(['data' => $data]);
    }

    public function export(Organization $organization, AttendanceExportRequest $request): Response
    {
        $this->checkPermission($organization, 'attendance:export');
        // Dispatch CSV or PDF export
    }

    public function recompute(Organization $organization, AttendanceRecomputeRequest $request): JsonResponse
    {
        $this->checkPermission($organization, 'attendance:recompute');
        // Mark records as stale and dispatch recomputation
        return response()->json(['data' => [
            'message' => 'Recomputation queued',
            'dates_affected' => $datesCount,
            'members_affected' => $membersCount,
        ]], 202);
    }
}
```

---

## 6. Request Validation

All request classes extend `App\Http\Requests\V1\BaseFormRequest`.

### WorkScheduleStoreRequest

```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'description' => ['sometimes', 'string'],
        'is_default' => ['sometimes', 'boolean'],
        'monday_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
        'tuesday_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
        'wednesday_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
        'thursday_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
        'friday_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
        'saturday_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
        'sunday_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
        'min_break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
        'break_required_after_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
        'timezone' => ['sometimes', 'string', 'timezone'],
    ];
}
```

### WorkScheduleAssignRequest

```php
public function rules(): array
{
    return [
        'member_id' => ['required', 'string', 'uuid',
            new ExistsEloquent(Member::class, null, function ($builder) {
                $builder->where('organization_id', $this->organization->id);
            }),
        ],
        'work_schedule_policy_id' => ['required', 'string', 'uuid',
            new ExistsEloquent(WorkSchedulePolicy::class, null, function ($builder) {
                $builder->where('organization_id', $this->organization->id)
                        ->where('is_active', true);
            }),
        ],
        'effective_from' => ['required', 'date_format:Y-m-d'],
        'effective_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
    ];
}
```

### OvertimeRuleStoreRequest

```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'rule_type' => ['required', 'string', Rule::in(OvertimeRuleType::values())],
        'threshold_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        'multiplier' => ['required', 'numeric', 'min:0.01', 'max:10.00'],
        'is_active' => ['sometimes', 'boolean'],
        'effective_from' => ['required', 'date_format:Y-m-d'],
        'effective_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
    ];
}
```

### AttendanceDailySummaryRequest

```php
public function rules(): array
{
    return [
        'date' => ['required', 'date_format:Y-m-d'],
        'member_ids' => ['sometimes', 'array'],
        'member_ids.*' => ['string', 'uuid'],
        'status' => ['sometimes', 'array'],
        'status.*' => ['string', Rule::in(AttendanceStatus::values())],
    ];
}
```

### AttendanceMemberDetailRequest

```php
public function rules(): array
{
    return [
        'date_from' => ['required', 'date_format:Y-m-d'],
        'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
    ];
}
```

### AttendanceOvertimeReportRequest

```php
public function rules(): array
{
    return [
        'date_from' => ['required', 'date_format:Y-m-d'],
        'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        'member_ids' => ['sometimes', 'array'],
        'member_ids.*' => ['string', 'uuid'],
        'aggregation' => ['sometimes', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
    ];
}
```

### AttendanceExportRequest

```php
public function rules(): array
{
    return [
        'type' => ['required', 'string', Rule::in(['attendance', 'overtime'])],
        'format' => ['required', 'string', Rule::in(['csv', 'pdf'])],
        'date_from' => ['required', 'date_format:Y-m-d'],
        'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        'member_ids' => ['sometimes', 'array'],
        'member_ids.*' => ['string', 'uuid'],
    ];
}
```

### AttendanceRecomputeRequest

```php
public function rules(): array
{
    return [
        'date_from' => ['required', 'date_format:Y-m-d'],
        'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        'member_ids' => ['sometimes', 'array'],
        'member_ids.*' => ['string', 'uuid'],
    ];
}
```

---

## 7. Frontend Architecture

### 7.1 Component Hierarchy

```
Attendance.vue (Page)
+-- Tab Navigation: ["Attendance", "Overtime", "Settings"]
|
+-- Tab: Attendance
|   +-- AttendanceSummaryBar.vue
|   |   +-- Present count (green)
|   |   +-- Absent count (red)
|   |   +-- Late count (yellow)
|   |   +-- On Leave count (blue)
|   |   +-- Holiday count (purple)
|   +-- AttendanceGrid.vue
|   |   +-- <thead> Day columns with date headers
|   |   +-- <tbody> Member rows
|   |   |   +-- <td> Member name
|   |   |   +-- <td> AttendanceStatusBadge.vue x N (one per day)
|   |   |       +-- Color-coded icon/label
|   |   |       +-- AttendanceDayTooltip.vue (on hover)
|   |   |           +-- Expected hours
|   |   |           +-- Actual hours
|   |   |           +-- Overtime
|   |   |           +-- Break time
|   |   +-- Click member -> AttendanceMemberDetail.vue
|   +-- AttendanceMemberDetail.vue
|   |   +-- Day-by-day breakdown table
|   |   +-- Time entry list per day
|   |   +-- Summary statistics
|   +-- AttendanceCalendar.vue (personal "My Attendance" view)
|       +-- Monthly calendar grid
|       +-- Status indicators per day
|       +-- Summary statistics bar
|
+-- Tab: Overtime
|   +-- OvertimeReport.vue
|   |   +-- Date range picker
|   |   +-- Aggregation toggle (daily/weekly/monthly)
|   |   +-- Member filter
|   |   +-- Overtime breakdown table
|   |   +-- Totals row
|   |   +-- AttendanceExportButton.vue
|   |       +-- CSV button
|   |       +-- PDF button
|
+-- Tab: Settings (visible to attendance:configure only)
    +-- WorkScheduleSettings.vue
    |   +-- Policy list
    |   +-- Create/edit form
    |   +-- Per-weekday hour inputs
    |   +-- Break requirement inputs
    |   +-- Default policy toggle
    +-- MemberScheduleAssignment.vue
    |   +-- Member list
    |   +-- Policy assignment dropdown per member
    |   +-- Effective date range inputs
    +-- OvertimeRuleSettings.vue
        +-- Rule list
        +-- Create/edit form
        +-- Rule type dropdown
        +-- Threshold input
        +-- Multiplier input
```

### 7.2 Pinia Store: `useAttendanceStore`

**File**: `resources/js/utils/useAttendance.ts`

```typescript
export const useAttendanceStore = defineStore('attendance', () => {
    // State
    const dailySummary = ref<AttendanceDailySummary | null>(null);
    const memberDetail = ref<AttendanceMemberDetail | null>(null);
    const overtimeReport = ref<OvertimeReportEntry[]>([]);
    const workSchedulePolicies = ref<WorkSchedulePolicy[]>([]);
    const overtimeRules = ref<OvertimeRule[]>([]);
    const selectedDate = ref<string>(dayjs().format('YYYY-MM-DD'));
    const dateRange = ref<{ from: string; to: string }>({
        from: dayjs().startOf('month').format('YYYY-MM-DD'),
        to: dayjs().endOf('month').format('YYYY-MM-DD'),
    });
    const isLoading = ref(false);
    const activeTab = ref<'attendance' | 'overtime' | 'settings'>('attendance');

    // Actions
    async function loadDailySummary(date: string) { /* GET /attendance */ }
    async function loadMemberDetail(memberId: string, from: string, to: string) { /* GET /attendance/member/{m} */ }
    async function loadOvertimeReport(from: string, to: string, aggregation: string) { /* GET /attendance/overtime */ }
    async function loadWorkSchedulePolicies() { /* GET /work-schedules */ }
    async function loadOvertimeRules() { /* GET /overtime-rules */ }
    async function createWorkSchedulePolicy(data: Partial<WorkSchedulePolicy>) { /* POST /work-schedules */ }
    async function updateWorkSchedulePolicy(id: string, data: Partial<WorkSchedulePolicy>) { /* PUT /work-schedules/{id} */ }
    async function deleteWorkSchedulePolicy(id: string) { /* DELETE /work-schedules/{id} */ }
    async function assignWorkSchedule(data: MemberWorkScheduleAssignment) { /* POST /work-schedules/assign */ }
    async function createOvertimeRule(data: Partial<OvertimeRule>) { /* POST /overtime-rules */ }
    async function updateOvertimeRule(id: string, data: Partial<OvertimeRule>) { /* PUT /overtime-rules/{id} */ }
    async function deleteOvertimeRule(id: string) { /* DELETE /overtime-rules/{id} */ }
    async function exportAttendance(format: 'csv' | 'pdf') { /* GET /attendance/export */ }
    async function recomputeAttendance(from: string, to: string) { /* POST /attendance/recompute */ }

    return {
        dailySummary, memberDetail, overtimeReport,
        workSchedulePolicies, overtimeRules,
        selectedDate, dateRange, isLoading, activeTab,
        loadDailySummary, loadMemberDetail, loadOvertimeReport,
        loadWorkSchedulePolicies, loadOvertimeRules,
        createWorkSchedulePolicy, updateWorkSchedulePolicy, deleteWorkSchedulePolicy,
        assignWorkSchedule,
        createOvertimeRule, updateOvertimeRule, deleteOvertimeRule,
        exportAttendance, recomputeAttendance,
    };
});
```

### 7.3 TypeScript Types

**File**: `resources/js/types/attendance.d.ts`

Defines: `AttendanceStatus`, `OvertimeRuleType`, `WorkSchedulePolicy`, `MemberWorkScheduleAssignment`, `OvertimeRule`, `AttendanceRecord`, `AttendanceDailySummary`, `AttendanceMemberDetail`, `OvertimeReportEntry`

Full type definitions are specified in PRD Section 4.2.

### 7.4 Page Registration

**File**: `routes/web.php`
```php
Route::get('/attendance', function () {
    return Inertia::render('Attendance');
})->name('attendance');
```

**File**: `resources/js/Layouts/AppLayout.vue`
```vue
<NavigationSidebarItem
    title="Attendance"
    :icon="ClipboardDocumentCheckIcon"
    :current="route().current('attendance')"
    :href="route('attendance')">
</NavigationSidebarItem>
```

Uses `ClipboardDocumentCheckIcon` from `@heroicons/vue/20/solid`.

---

## 8. Permission Matrix

### 8.1 New Permissions

| Permission | Description |
|------------|-------------|
| `attendance:configure` | Create/update/delete work schedules, overtime rules, trigger recompute |
| `attendance:view:all` | View attendance records for all organization members |
| `attendance:view:own` | View own attendance records only |
| `attendance:export` | Export attendance and overtime reports |
| `attendance:recompute` | Manually trigger attendance recomputation |

### 8.2 Role Mapping

| Permission | Owner | Admin | Manager | Employee |
|------------|:-----:|:-----:|:-------:|:--------:|
| `attendance:configure` | Yes | Yes | No | No |
| `attendance:view:all` | Yes | Yes | Yes | No |
| `attendance:view:own` | Yes | Yes | Yes | Yes |
| `attendance:export` | Yes | Yes | Yes | No |
| `attendance:recompute` | Yes | Yes | No | No |

### 8.3 Registration

**File**: `app/Permissions/AttendancePermissions.php`

Following the modular pattern from `CorePermissions.php`:

```php
class AttendancePermissions
{
    public static function register(): void
    {
        // Append attendance permissions to each role
        // Owner, Admin: all 5 permissions
        // Manager: view:all, view:own, export
        // Employee: view:own
    }
}
```

Called from `JetstreamServiceProvider` or the modular permissions registration infrastructure (FOUND-007).

### 8.4 Endpoint Permission Mapping

| Endpoint | Permission Check |
|----------|-----------------|
| GET /work-schedules | `checkAnyPermission(['attendance:view:all', 'attendance:view:own', 'attendance:configure'])` |
| POST /work-schedules | `checkPermission('attendance:configure')` |
| GET /work-schedules/{ws} | `checkAnyPermission(['attendance:view:all', 'attendance:view:own', 'attendance:configure'])` |
| PUT /work-schedules/{ws} | `checkPermission('attendance:configure')` |
| DELETE /work-schedules/{ws} | `checkPermission('attendance:configure')` |
| POST /work-schedules/assign | `checkPermission('attendance:configure')` |
| GET /overtime-rules | `checkAnyPermission(['attendance:view:all', 'attendance:view:own', 'attendance:configure'])` |
| POST /overtime-rules | `checkPermission('attendance:configure')` |
| PUT /overtime-rules/{rule} | `checkPermission('attendance:configure')` |
| DELETE /overtime-rules/{rule} | `checkPermission('attendance:configure')` |
| GET /attendance | `checkAnyPermission(['attendance:view:all', 'attendance:view:own'])` (scoped) |
| GET /attendance/member/{m} | `checkAnyPermission(['attendance:view:all', 'attendance:view:own'])` (self-only for view:own) |
| GET /attendance/overtime | `checkPermission('attendance:view:all')` |
| GET /attendance/export | `checkPermission('attendance:export')` |
| POST /attendance/recompute | `checkPermission('attendance:recompute')` |

---

## 9. Performance Strategy

### 9.1 Database

- **Pre-computed records**: Attendance data is computed once per day per member and stored in `attendance_records`. Report queries read from this table, not from raw `time_entries`.
- **Unique constraint**: `(member_id, date)` on `attendance_records` enables efficient `updateOrCreate` upserts
- **Partial index**: `idx_ar_stale` filters `WHERE is_stale = TRUE` for fast stale record lookup
- **Composite indexes**: `(organization_id, date)` and `(member_id, date)` cover the primary report query patterns
- **Chunked processing**: The scheduled command processes members in chunks of 50
- **Server-side aggregation**: Reporting methods use SQL `SUM()` and `GROUP BY` rather than loading records into PHP

### 9.2 Frontend

- **Tab-based lazy loading**: Each tab (Attendance, Overtime, Settings) loads its data only when activated
- **Date-scoped queries**: All queries are scoped to a date range, preventing full-table scans
- **Pagination**: Attendance grid paginates members for large organizations
- **Debounced date navigation**: Date picker changes debounce the API call by 300ms

### 9.3 Targets

| Metric | Target |
|--------|--------|
| GET /attendance (daily summary, 100 members) | < 1s |
| GET /attendance/member/{m} (1 month) | < 500ms |
| GET /attendance/overtime (1 month, 100 members) | < 2s |
| GET /attendance/export (CSV, 100 members, 1 month) | < 5s |
| GET /attendance/export (PDF, 100 members, 1 month) | < 15s |
| `attendance:compute` (100 members, 1 day) | < 30s |
| `attendance:compute` (1000 members, 1 day) | < 120s |
| Attendance per-member per-day computation | < 100ms |

---

## 10. Integration Points

### 10.1 Feature 06 (Kiosk & Clock Mode)

**Integration type**: Read kiosk break events for explicit break detection.

**When Feature 06 is absent**: Break detection relies solely on gap analysis between time entries.

**When Feature 06 is present**: The `computeBreaks()` method additionally queries kiosk session data:

```php
// In AttendanceService::computeBreaks()
if (class_exists(\App\Models\KioskSession::class)) {
    $kioskBreaks = \App\Models\KioskSession::query()
        ->where('member_id', $member->id)
        ->whereDate('on_break_since', $date)
        ->whereNotNull('break_ended_at')
        ->get();

    foreach ($kioskBreaks as $kioskBreak) {
        $totalBreakSeconds += $kioskBreak->break_ended_at->diffInSeconds($kioskBreak->on_break_since);
    }
}
```

### 10.2 Feature 07 (PTO & Time Off)

**Integration type**: Read approved time-off requests and holiday calendar.

**When Feature 07 is absent**: All expected work days are treated as work days. No holiday detection. No "on leave" status.

**When Feature 07 is present**: The `isOnLeave()` and `isHoliday()` methods query PTO models as shown in Section 3.2.

### 10.3 Feature 00 (Weekly Timesheet Grid)

**Integration type**: Uses the same `TimeEntry` data. The `weekly_capacity` on `Member` and `default_weekly_capacity` on `Organization` serve as fallback values when no work schedule policy exists.

**No conflict**: Attendance operates on pre-computed records, not on the same API surface as the timesheet grid.

### 10.4 Existing Features -- No Conflicts

| Feature | Interaction |
|---------|-------------|
| Timer (running entries) | Excluded from computation via `whereNotNull('end')` |
| Time page (entry list) | Independent -- attendance reads same data, different view |
| Reporting | Complementary -- attendance adds new report types |
| Timesheet Grid | Complementary -- same underlying data, no shared components |

### 10.5 Downstream Features

| Feature | How it uses attendance data |
|---------|---------------------------|
| Feature 09 (Advanced Reporting) | Can include attendance and overtime in custom reports |
| Feature 04 (Invoicing) | Overtime cost calculations can feed into invoice line items |
| Feature 08 (Resource Scheduling) | Scheduled shifts can override expected hours per day |

---

## 11. File Manifest

### 11.1 New Files (47)

| File | Type | Task |
|------|------|------|
| `app/Enums/AttendanceStatus.php` | Enum | ATT-006 |
| `app/Enums/OvertimeRuleType.php` | Enum | ATT-007 |
| `app/Models/WorkSchedulePolicy.php` | Model | ATT-002 |
| `app/Models/MemberWorkSchedule.php` | Model | ATT-003 |
| `app/Models/OvertimeRule.php` | Model | ATT-004 |
| `app/Models/AttendanceRecord.php` | Model | ATT-005 |
| `app/Service/AttendanceService.php` | Service | ATT-016, ATT-017, ATT-018 |
| `app/Service/WorkScheduleService.php` | Service | ATT-008 |
| `app/Http/Controllers/Api/V1/WorkScheduleController.php` | Controller | ATT-009 |
| `app/Http/Controllers/Api/V1/OvertimeRuleController.php` | Controller | ATT-011 |
| `app/Http/Controllers/Api/V1/AttendanceController.php` | Controller | ATT-021 |
| `app/Http/Requests/V1/WorkSchedule/WorkScheduleStoreRequest.php` | Request | ATT-010 |
| `app/Http/Requests/V1/WorkSchedule/WorkScheduleUpdateRequest.php` | Request | ATT-010 |
| `app/Http/Requests/V1/WorkSchedule/WorkScheduleAssignRequest.php` | Request | ATT-010 |
| `app/Http/Requests/V1/OvertimeRule/OvertimeRuleStoreRequest.php` | Request | ATT-012 |
| `app/Http/Requests/V1/OvertimeRule/OvertimeRuleUpdateRequest.php` | Request | ATT-012 |
| `app/Http/Requests/V1/Attendance/AttendanceDailySummaryRequest.php` | Request | ATT-022 |
| `app/Http/Requests/V1/Attendance/AttendanceMemberDetailRequest.php` | Request | ATT-022 |
| `app/Http/Requests/V1/Attendance/AttendanceOvertimeReportRequest.php` | Request | ATT-022 |
| `app/Http/Requests/V1/Attendance/AttendanceExportRequest.php` | Request | ATT-022 |
| `app/Http/Requests/V1/Attendance/AttendanceRecomputeRequest.php` | Request | ATT-022 |
| `app/Permissions/AttendancePermissions.php` | Permissions | ATT-013 |
| `app/Console/Commands/ComputeAttendanceCommand.php` | Command | ATT-019 |
| `database/migrations/2026_03_15_000001_create_work_schedule_policies_table.php` | Migration | ATT-001 |
| `database/migrations/2026_03_15_000002_create_member_work_schedules_table.php` | Migration | ATT-001 |
| `database/migrations/2026_03_15_000003_create_overtime_rules_table.php` | Migration | ATT-001 |
| `database/migrations/2026_03_15_000004_create_attendance_records_table.php` | Migration | ATT-001 |
| `database/factories/WorkSchedulePolicyFactory.php` | Factory | ATT-002 |
| `database/factories/MemberWorkScheduleFactory.php` | Factory | ATT-003 |
| `database/factories/OvertimeRuleFactory.php` | Factory | ATT-004 |
| `database/factories/AttendanceRecordFactory.php` | Factory | ATT-005 |
| `resources/js/Pages/Attendance.vue` | Page | ATT-029 |
| `resources/js/packages/ui/src/Attendance/AttendanceGrid.vue` | Component | ATT-036 |
| `resources/js/packages/ui/src/Attendance/AttendanceStatusBadge.vue` | Component | ATT-037 |
| `resources/js/packages/ui/src/Attendance/AttendanceMemberDetail.vue` | Component | ATT-038 |
| `resources/js/packages/ui/src/Attendance/AttendanceCalendar.vue` | Component | ATT-039 |
| `resources/js/packages/ui/src/Attendance/AttendanceSummaryBar.vue` | Component | ATT-042 |
| `resources/js/packages/ui/src/Attendance/AttendanceDayTooltip.vue` | Component | ATT-043 |
| `resources/js/packages/ui/src/Attendance/AttendanceExportButton.vue` | Component | ATT-041 |
| `resources/js/packages/ui/src/Attendance/OvertimeReport.vue` | Component | ATT-040 |
| `resources/js/packages/ui/src/Attendance/WorkScheduleSettings.vue` | Component | ATT-030 |
| `resources/js/packages/ui/src/Attendance/MemberScheduleAssignment.vue` | Component | ATT-031 |
| `resources/js/packages/ui/src/Attendance/OvertimeRuleSettings.vue` | Component | ATT-032 |
| `resources/js/utils/useAttendance.ts` | Store | ATT-033 |
| `resources/js/types/attendance.d.ts` | Types | ATT-015 |
| `resources/js/packages/ui/src/Attendance/__tests__/AttendanceGrid.test.ts` | Test | ATT-050 |
| `resources/js/packages/ui/src/Attendance/__tests__/AttendanceStatusBadge.test.ts` | Test | ATT-050 |
| `resources/js/packages/ui/src/Attendance/__tests__/OvertimeReport.test.ts` | Test | ATT-050 |

### 11.2 New Test Files (9)

| File | Type | Task |
|------|------|------|
| `tests/Unit/Endpoint/Api/V1/WorkScheduleEndpointTest.php` | Endpoint Test | ATT-044 |
| `tests/Unit/Endpoint/Api/V1/OvertimeRuleEndpointTest.php` | Endpoint Test | ATT-045 |
| `tests/Unit/Endpoint/Api/V1/AttendanceEndpointTest.php` | Endpoint Test | ATT-046 |
| `tests/Unit/Service/AttendanceServiceTest.php` | Service Test | ATT-047 |
| `tests/Unit/Service/WorkScheduleServiceTest.php` | Service Test | ATT-048 |
| `tests/Feature/ComputeAttendanceCommandTest.php` | Feature Test | ATT-049 |
| `resources/js/packages/ui/src/Attendance/__tests__/*.test.ts` | Component Tests | ATT-050 |
| `e2e/attendance-config.spec.ts` | E2E Test | ATT-051 |
| `e2e/attendance-view.spec.ts` | E2E Test | ATT-052 |

### 11.3 Modified Files (5)

| File | Change | Task |
|------|--------|------|
| `routes/api.php` | Add 3 route groups (work-schedules, overtime-rules, attendance) | ATT-014, ATT-025 |
| `routes/web.php` | Add Inertia page route for `/attendance` | ATT-034 |
| `resources/js/Layouts/AppLayout.vue` | Add sidebar navigation item for Attendance | ATT-035 |
| `app/Console/Kernel.php` | Register `attendance:compute` in scheduler | ATT-020 |
| `openapi.json` + `resources/js/packages/api/src/openapi.json.client.ts` | Add 15 endpoint definitions; regenerate TS client | ATT-027, ATT-028 |
