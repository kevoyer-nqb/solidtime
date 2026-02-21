I'll continue the ARCHITECTURE.md document from where it was cut off.

---

## 8. PDF Generation (continued)

### 8.1 Blade Template (continued)

**File**: `/home/keven/Documents/solidtime-analysis/resources/views/invoices/pdf.blade.php`

```blade
        .totals-row.total {
            font-weight: 700;
            font-size: 14pt;
            border-bottom: 2px solid #333;
            padding-top: 12px;
        }
        
        .notes-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
        }
        
        .notes-section h3 {
            font-weight: 600;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            @if($orgSnapshot['logo_path'])
                <img src="{{ storage_path('app/private/' . $orgSnapshot['logo_path']) }}" class="logo" alt="Logo">
            @endif
            <div style="margin-top: 20px;">
                <strong>{{ $orgSnapshot['name'] }}</strong><br>
                {{ $orgSnapshot['billing_address'] }}<br>
                @if($orgSnapshot['billing_city'])
                    {{ $orgSnapshot['billing_city'] }}, {{ $orgSnapshot['billing_state'] }} {{ $orgSnapshot['billing_postal_code'] }}<br>
                @endif
                @if($orgSnapshot['tax_id'])
                    Tax ID: {{ $orgSnapshot['tax_id'] }}<br>
                @endif
            </div>
        </div>
        
        <div class="invoice-info">
            <div class="invoice-number">INVOICE</div>
            <div style="font-size: 18pt; font-weight: 600; margin-top: 4px;">{{ $invoice->invoice_number }}</div>
            <div style="margin-top: 20px;">
                <strong>Issue Date:</strong> {{ $invoice->issue_date->format('M d, Y') }}<br>
                <strong>Due Date:</strong> {{ $invoice->due_date->format('M d, Y') }}<br>
                @if($invoice->reference)
                    <strong>Reference:</strong> {{ $invoice->reference }}<br>
                @endif
            </div>
        </div>
    </div>
    
    <div class="addresses">
        <div class="address-block">
            <h3>Bill To</h3>
            <strong>{{ $clientSnapshot['name'] }}</strong><br>
            @if($clientSnapshot['billing_address'])
                {{ $clientSnapshot['billing_address'] }}<br>
                {{ $clientSnapshot['billing_city'] }}, {{ $clientSnapshot['billing_state'] }} {{ $clientSnapshot['billing_postal_code'] }}<br>
            @endif
            @if($clientSnapshot['tax_id'])
                Tax ID: {{ $clientSnapshot['tax_id'] }}<br>
            @endif
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th style="width: 100px;">Quantity</th>
                <th class="number" style="width: 100px;">Rate</th>
                <th class="number" style="width: 80px;">Tax</th>
                <th class="number" style="width: 120px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td>
                        @if($line->type === 'time')
                            {{ number_format($line->quantity_hours, 2) }} hrs
                        @else
                            {{ $line->quantity_seconds > 0 ? $line->quantity_seconds : '-' }}
                        @endif
                    </td>
                    <td class="number">{{ $localization->formatMoney($line->unit_rate_cents) }}</td>
                    <td class="number">{{ number_format($line->tax_rate, 1) }}%</td>
                    <td class="number">{{ $localization->formatMoney($line->amount_cents) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="totals">
        <div class="totals-row">
            <span>Subtotal:</span>
            <span>{{ $localization->formatMoney($invoice->subtotal_cents) }}</span>
        </div>
        <div class="totals-row">
            <span>Tax:</span>
            <span>{{ $localization->formatMoney($invoice->tax_total_cents) }}</span>
        </div>
        <div class="totals-row total">
            <span>Total:</span>
            <span>{{ $localization->formatMoney($invoice->total_cents) }}</span>
        </div>
        @if($invoice->amount_paid_cents > 0)
            <div class="totals-row">
                <span>Amount Paid:</span>
                <span>{{ $localization->formatMoney($invoice->amount_paid_cents) }}</span>
            </div>
            <div class="totals-row total">
                <span>Amount Due:</span>
                <span>{{ $localization->formatMoney($invoice->amount_due_cents) }}</span>
            </div>
        @endif
    </div>
    
    @if($invoice->notes || $invoice->terms)
        <div class="notes-section">
            @if($invoice->notes)
                <h3>Notes</h3>
                <p>{{ $invoice->notes }}</p>
            @endif
            
            @if($invoice->terms)
                <h3>Payment Terms</h3>
                <p>{{ $invoice->terms }}</p>
            @endif
        </div>
    @endif
</body>
</html>
```

### 8.2 Footer Template

**File**: `/home/keven/Documents/solidtime-analysis/resources/views/invoices/pdf-footer.blade.php`

```blade
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            font-size: 8pt;
            color: #6b7280;
            text-align: center;
            margin: 0;
            padding: 10px 0;
        }
    </style>
</head>
<body>
    Page <span class="pageNumber"></span> of <span class="totalPages"></span>
</body>
</html>
```

---

## 9. Permission Matrix

### 9.1 Permission Registration

Per **SF-08 (Shared Foundations)**, permissions are registered via modular pattern.

**File**: `/home/keven/Documents/solidtime-analysis/app/Permissions/InvoicePermissions.php`

```php
<?php

declare(strict_types=1);

namespace App\Permissions;

use Laravel\Jetstream\Jetstream;

class InvoicePermissions
{
    public static function register(): void
    {
        // Owner role
        Jetstream::role('owner', 'Owner', [
            'invoices:view',
            'invoices:create',
            'invoices:update',
            'invoices:send',
            'invoices:void',
            'invoices:delete',
            'invoices:settings',
            'invoices:payments:create',
            'invoices:recurring:view',
            'invoices:recurring:create',
            'invoices:recurring:update',
            'invoices:recurring:delete',
        ])->description('Owner has full access to all features');

        // Admin role
        Jetstream::role('admin', 'Administrator', [
            'invoices:view',
            'invoices:create',
            'invoices:update',
            'invoices:send',
            'invoices:void',
            'invoices:delete',
            'invoices:settings',
            'invoices:payments:create',
            'invoices:recurring:view',
            'invoices:recurring:create',
            'invoices:recurring:update',
            'invoices:recurring:delete',
        ])->description('Administrators have full invoice access');

        // Manager role
        Jetstream::role('manager', 'Manager', [
            'invoices:view',
            'invoices:create',
            'invoices:update',
            'invoices:delete',
        ])->description('Managers can create and edit draft invoices');

        // Employee role - no invoice permissions
        Jetstream::role('employee', 'Employee', [
            // No invoice permissions
        ])->description('Employees cannot access invoices');
    }
}
```

**JetstreamServiceProvider Integration**:

**File**: `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php` (modified)

```php
protected function configurePermissions(): void
{
    // ... existing permission setup ...

    // Feature permissions (modular registration per SF-08)
    \App\Permissions\InvoicePermissions::register();
}
```

### 9.2 Permission Matrix Table

| Permission | Owner | Admin | Manager | Employee | Description |
|-----------|-------|-------|---------|----------|-------------|
| `invoices:view` | ✓ | ✓ | ✓ | ✗ | View invoice list and details |
| `invoices:create` | ✓ | ✓ | ✓ | ✗ | Create new invoices |
| `invoices:update` | ✓ | ✓ | ✓ | ✗ | Edit draft invoices |
| `invoices:send` | ✓ | ✓ | ✗ | ✗ | Send invoices to clients |
| `invoices:void` | ✓ | ✓ | ✗ | ✗ | Void sent invoices |
| `invoices:delete` | ✓ | ✓ | ✓ | ✗ | Delete draft invoices |
| `invoices:settings` | ✓ | ✓ | ✗ | ✗ | Configure invoice settings |
| `invoices:payments:create` | ✓ | ✓ | ✗ | ✗ | Record manual payments |
| `invoices:recurring:view` | ✓ | ✓ | ✗ | ✗ | View recurring schedules |
| `invoices:recurring:create` | ✓ | ✓ | ✗ | ✗ | Create recurring schedules |
| `invoices:recurring:update` | ✓ | ✓ | ✗ | ✗ | Edit recurring schedules |
| `invoices:recurring:delete` | ✓ | ✓ | ✗ | ✗ | Delete recurring schedules |

### 9.3 Frontend Permission Helpers

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/utils/permissions.ts` (modified)

```typescript
// Add to existing permission helpers
export function canViewInvoices(): boolean {
    return canPerformAction('invoices', 'view');
}

export function canCreateInvoices(): boolean {
    return canPerformAction('invoices', 'create');
}

export function canSendInvoices(): boolean {
    return canPerformAction('invoices', 'send');
}

export function canManageRecurringInvoices(): boolean {
    return canPerformAction('invoices:recurring', 'view');
}
```

---

## 10. Migration Strategy

### 10.1 Migration Files

All migrations use date prefix `2026_03_04_` per **SF-03**.

#### Migration 1: Create invoices table

**File**: `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_04_000001_create_invoices_table.php`

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
        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('invoice_number', 50);
            $table->string('status', 20)->default('draft')->index();
            $table->uuid('organization_id');
            $table->uuid('client_id');
            $table->date('issue_date');
            $table->date('due_date');
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->bigInteger('subtotal_cents')->default(0);
            $table->bigInteger('tax_total_cents')->default(0);
            $table->bigInteger('total_cents')->default(0);
            $table->bigInteger('amount_paid_cents')->default(0);
            $table->string('currency', 3);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->string('reference', 255)->nullable();
            $table->uuid('template_id')->nullable();
            $table->jsonb('client_snapshot')->nullable();
            $table->jsonb('organization_snapshot')->nullable();
            $table->uuid('recurring_schedule_id')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->unique(['organization_id', 'invoice_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'client_id']);
            $table->index(['organization_id', 'issue_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
```

#### Migration 2: Create invoice_lines table

**File**: `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_04_000002_create_invoice_lines_table.php`

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
        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->text('description')->nullable();
            $table->string('type', 20)->default('time');
            $table->bigInteger('quantity_seconds')->default(0);
            $table->bigInteger('unit_rate_cents')->default(0);
            $table->bigInteger('amount_cents')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0.0);
            $table->bigInteger('tax_amount_cents')->default(0);
            $table->integer('sort_order')->default(1);
            $table->uuid('project_id')->nullable();
            $table->uuid('task_id')->nullable();
            $table->jsonb('time_entry_ids')->nullable();
            $table->timestamps();

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('task_id')
                ->references('id')
                ->on('tasks')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index(['invoice_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
```

#### Migration 3: Create invoice_payments table

**File**: `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_04_000003_create_invoice_payments_table.php`

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
        Schema::create('invoice_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->bigInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('method', 30);
            $table->string('transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->date('payment_date');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->index(['invoice_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
```

#### Migration 4: Create invoice_templates table

**File**: `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_04_000004_create_invoice_templates_table.php`

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
        Schema::create('invoice_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name', 255);
            $table->text('blade_template');
            $table->text('header_template')->nullable();
            $table->text('footer_template')->nullable();
            $table->jsonb('settings')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->index(['organization_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_templates');
    }
};
```

#### Migration 5: Create recurring_invoice_schedules table

**File**: `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_04_000005_create_recurring_invoice_schedules_table.php`

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
        Schema::create('recurring_invoice_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_id');
            $table->string('frequency', 20);
            $table->dateTime('next_run_at')->index();
            $table->dateTime('last_run_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('include_billable_time')->default(true);
            $table->jsonb('fixed_line_items')->nullable();
            $table->jsonb('filters')->nullable();
            $table->uuid('template_id')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('template_id')
                ->references('id')
                ->on('invoice_templates')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index(['organization_id', 'is_active', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_invoice_schedules');
    }
};
```

#### Migration 6: Extend clients table

**File**: `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_04_000006_add_billing_columns_to_clients_table.php`

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
        Schema::table('clients', function (Blueprint $table): void {
            $table->text('billing_address')->nullable();
            $table->string('billing_city', 100)->nullable();
            $table->string('billing_state', 100)->nullable();
            $table->string('billing_postal_code', 20)->nullable();
            $table->string('billing_country', 2)->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->string('billing_email', 255)->nullable();
            $table->integer('payment_terms_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_address',
                'billing_city',
                'billing_state',
                'billing_postal_code',
                'billing_country',
                'tax_id',
                'billing_email',
                'payment_terms_days',
            ]);
        });
    }
};
```

#### Migration 7: Extend organizations table

**File**: `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_04_000007_add_invoice_settings_to_organizations_table.php`

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
            $table->string('invoice_number_prefix', 20)->nullable();
            $table->string('invoice_number_suffix', 20)->nullable();
            $table->integer('invoice_next_number')->default(1);
            $table->integer('default_payment_terms')->default(30);
            $table->decimal('default_tax_rate', 5, 2)->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->text('billing_address')->nullable();
            $table->string('billing_city', 100)->nullable();
            $table->string('billing_state', 100)->nullable();
            $table->string('billing_postal_code', 20)->nullable();
            $table->string('billing_country', 2)->nullable();
            $table->string('billing_email', 255)->nullable();
            $table->string('logo_path', 500)->nullable();
            $table->text('default_invoice_notes')->nullable();
            $table->text('default_invoice_terms')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn([
                'invoice_number_prefix',
                'invoice_number_suffix',
                'invoice_next_number',
                'default_payment_terms',
                'default_tax_rate',
                'tax_id',
                'billing_address',
                'billing_city',
                'billing_state',
                'billing_postal_code',
                'billing_country',
                'billing_email',
                'logo_path',
                'default_invoice_notes',
                'default_invoice_terms',
            ]);
        });
    }
};
```

#### Migration 8: Add invoice_id to time_entries table

**File**: `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_04_000008_add_invoice_id_to_time_entries_table.php`

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
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->uuid('invoice_id')->nullable()->index();
            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropForeign(['invoice_id']);
            $table->dropColumn('invoice_id');
        });
    }
};
```

### 10.2 Migration Execution Order

Migrations must run in numeric order (Laravel handles this automatically via timestamp):

1. `2026_03_04_000001` - Create `invoices` table
2. `2026_03_04_000002` - Create `invoice_lines` table (depends on `invoices`)
3. `2026_03_04_000003` - Create `invoice_payments` table (depends on `invoices`)
4. `2026_03_04_000004` - Create `invoice_templates` table
5. `2026_03_04_000005` - Create `recurring_invoice_schedules` table (depends on `invoice_templates`, `clients`)
6. `2026_03_04_000006` - Extend `clients` table
7. `2026_03_04_000007` - Extend `organizations` table
8. `2026_03_04_000008` - Add `invoice_id` to `time_entries` (depends on `invoices`)

**Command**: `php artisan migrate`

### 10.3 Rollback Plan

Full rollback: `php artisan migrate:rollback --step=8`

Partial rollback (e.g., undo last 3): `php artisan migrate:rollback --step=3`

**Data Safety**: Before rollback in production, export invoice data:
```bash
php artisan invoices:export-backup --path=/backups/invoices-$(date +%Y%m%d).sql
```

---

## 11. Integration Points

### 11.1 Dependencies on Shared Foundations

| Foundation Task | Description | Usage in Invoicing |
|----------------|-------------|-------------------|
| **FOUND-001** | Notification infrastructure | Invoice notifications (sent, paid, overdue) |
| **FOUND-002** | BaseNotification class | Extended by `InvoiceSentNotification`, etc. |
| **FOUND-003** | Notification bell UI | Displays invoice notifications in header |
| **FOUND-004** | Notification API | Backend for notification bell |
| **FOUND-007** | Modular permissions | `InvoicePermissions::register()` |

**Blocking Relationship**: Tasks INV-022 (Invoice Email Service), INV-023 (Overdue Command) depend on FOUND-001 through FOUND-004.

### 11.2 Existing System Integration

#### 11.2.1 BillableRateService Integration

**File**: `app/Service/BillableRateService.php` (existing)

**Integration Point**: `InvoiceService::previewFromTimeEntries()` calls `BillableRateService::getBillableRateForTimeEntry()` to maintain rate hierarchy consistency.

**No Modifications Required**: BillableRateService is used as-is.

#### 11.2.2 TimeEntryFilter Integration

**File**: `app/Service/TimeEntryFilter.php` (existing)

**Integration Point**: `InvoiceService::previewFromTimeEntries()` uses TimeEntryFilter pattern for querying unbilled time entries.

**Pattern Reuse**: Similar filtering logic, but invoicing queries directly via Eloquent for simplicity (already scoped to client).

#### 11.2.3 Gotenberg Integration

**File**: `app/Http/Controllers/Api/V1/TimeEntryController.php::indexExport()` (existing pattern)

**Integration Point**: `InvoicePdfService::generatePdf()` reuses exact Gotenberg pattern:
- Same configuration (`config('services.gotenberg.url')`)
- Same basic auth setup
- Same font assets (`Outfit-VariableFont_wght.ttf`)
- Same temporary directory pattern
- Same storage + temporary URL pattern

**No Changes to Gotenberg Config**: Existing setup is sufficient.

#### 11.2.4 LocalizationService Integration

**File**: `app/Service/LocalizationService.php` (existing)

**Integration Point**: Invoice PDF templates use `LocalizationService::forOrganization()` for number/currency formatting.

**Usage**: `{{ $localization->formatMoney($invoice->total_cents) }}`

### 11.3 Frontend Integration

#### 11.3.1 Navigation Integration

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/Layouts/AppLayout.vue` (existing)

**Current Code** (already present):
```vue
<NavigationSidebarItem
    v-if="isInvoicingActivated() && canViewInvoices()"
    href="/invoices"
    :icon="DocumentTextIcon"
>
    Invoices
</NavigationSidebarItem>
```

**No Changes Required**: Navigation already in place, just needs web route (INV-036).

#### 11.3.2 Permission Helpers Integration

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/utils/permissions.ts` (existing)

**Current Code** (already present):
```typescript
export function canViewInvoices(): boolean {
    return canPerformAction('invoices', 'view');
}
```

**Addition Required**: Additional helpers for `create`, `send`, etc. (see Section 9.3).

#### 11.3.3 Billing Contract Integration

**File**: `app/Service/BillingContract.php` (existing)

**Integration Point**: Premium features (Stripe payments, accounting exports) gated via:
```php
if (!$this->canAccessPremiumFeatures($organization)) {
    throw new FeatureIsNotAvailableInFreePlanApiException;
}
```

**Usage**: Applied in `StripePaymentController`, `InvoiceExportController` (Sprint 6).

---

## 12. File Manifest

### 12.1 Backend Files to Create

**Models** (8 files):
1. `/home/keven/Documents/solidtime-analysis/app/Models/Invoice.php`
2. `/home/keven/Documents/solidtime-analysis/app/Models/InvoiceLine.php`
3. `/home/keven/Documents/solidtime-analysis/app/Models/InvoicePayment.php`
4. `/home/keven/Documents/solidtime-analysis/app/Models/InvoiceTemplate.php`
5. `/home/keven/Documents/solidtime-analysis/app/Models/RecurringInvoiceSchedule.php`

**Enums** (4 files):
6. `/home/keven/Documents/solidtime-analysis/app/Enums/InvoiceStatus.php`
7. `/home/keven/Documents/solidtime-analysis/app/Enums/InvoiceLineType.php`
8. `/home/keven/Documents/solidtime-analysis/app/Enums/PaymentMethod.php`
9. `/home/keven/Documents/solidtime-analysis/app/Enums/RecurringFrequency.php`

**Services** (6 files):
10. `/home/keven/Documents/solidtime-analysis/app/Service/InvoiceService.php`
11. `/home/keven/Documents/solidtime-analysis/app/Service/InvoicePaymentService.php`
12. `/home/keven/Documents/solidtime-analysis/app/Service/InvoicePdfService.php`
13. `/home/keven/Documents/solidtime-analysis/app/Service/InvoiceEmailService.php`
14. `/home/keven/Documents/solidtime-analysis/app/Service/RecurringInvoiceService.php`

**DTOs** (2 files):
15. `/home/keven/Documents/solidtime-analysis/app/Service/Dto/InvoicePreviewDto.php`
16. `/home/keven/Documents/solidtime-analysis/app/Service/Dto/InvoiceLineItemDto.php`

**Controllers** (5 files):
17. `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/InvoiceController.php`
18. `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/InvoicePaymentController.php`
19. `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/InvoiceTemplateController.php`
20. `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/RecurringInvoiceScheduleController.php`
21. `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/InvoiceExportController.php`

**Requests** (15 files):
22. `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Invoice/InvoiceIndexRequest.php`
23. `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Invoice/InvoiceStoreRequest.php`
24. `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Invoice/InvoiceUpdateRequest.php`
25. `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Invoice/InvoicePreviewRequest.php`
26. `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Invoice/InvoiceSendRequest.php`
27. `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Invoice/InvoiceVoidRequest.php`
28. `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Invoice/InvoicePaymentStoreRequest.php`
29. `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Invoice/InvoiceTemplateStoreRequest.php`
30. `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Invoice/InvoiceTemplateUpdateRequest.php`
31. `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Invoice/RecurringScheduleStoreRequest.php`
32. `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Invoice/RecurringScheduleUpdateRequest.php`

**Resources** (10 files):
33. `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Invoice/InvoiceResource.php`
34. `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Invoice/InvoiceDetailedResource.php`
35. `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Invoice/InvoiceCollection.php`
36. `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Invoice/InvoiceLineResource.php`
37. `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Invoice/InvoicePreviewResource.php`
38. `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Invoice/InvoicePaymentResource.php`
39. `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Invoice/InvoicePaymentCollection.php`
40. `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Invoice/InvoiceTemplateResource.php`
41. `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Invoice/RecurringScheduleResource.php`
42. `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Invoice/RecurringScheduleCollection.php`

**Mail** (1 file):
43. `/home/keven/Documents/solidtime-analysis/app/Mail/InvoiceMail.php`

**Notifications** (3 files):
44. `/home/keven/Documents/solidtime-analysis/app/Notifications/InvoiceSentNotification.php`
45. `/home/keven/Documents/solidtime-analysis/app/Notifications/InvoicePaymentReceivedNotification.php`
46. `/home/keven/Documents/solidtime-analysis/app/Notifications/InvoiceOverdueNotification.php`

**Commands** (2 files):
47. `/home/keven/Documents/solidtime-analysis/app/Console/Commands/MarkOverdueInvoicesCommand.php`
48. `/home/keven/Documents/solidtime-analysis/app/Console/Commands/GenerateRecurringInvoicesCommand.php`

**Permissions** (1 file):
49. `/home/keven/Documents/solidtime-analysis/app/Permissions/InvoicePermissions.php`

**Factories** (5 files):
50. `/home/keven/Documents/solidtime-analysis/database/factories/InvoiceFactory.php`
51. `/home/keven/Documents/solidtime-analysis/database/factories/InvoiceLineFactory.php`
52. `/home/keven/Documents/solidtime-analysis/database/factories/InvoicePaymentFactory.php`
53. `/home/keven/Documents/solidtime-analysis/database/factories/InvoiceTemplateFactory.php`
54. `/home/keven/Documents/solidtime-analysis/database/factories/RecurringInvoiceScheduleFactory.php`

**Migrations** (8 files):
55. `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_04_000001_create_invoices_table.php`
56. `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_04_000002_create_invoice_lines_table.php`
57. `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_04_000003_create_invoice_payments_table.php`
58. `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_04_000004_create_invoice_templates_table.php`
59. `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_04_000005_create_recurring_invoice_schedules_table.php`
60. `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_04_000006_add_billing_columns_to_clients_table.php`
61. `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_04_000007_add_invoice_settings_to_organizations_table.php`
62. `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_04_000008_add_invoice_id_to_time_entries_table.php`

**Views** (3 files):
63. `/home/keven/Documents/solidtime-analysis/resources/views/invoices/pdf.blade.php`
64. `/home/keven/Documents/solidtime-analysis/resources/views/invoices/pdf-footer.blade.php`
65. `/home/keven/Documents/solidtime-analysis/resources/views/emails/invoice.blade.php`

**Tests - Backend** (8 files):
66. `/home/keven/Documents/solidtime-analysis/tests/Unit/Service/InvoiceServiceTest.php`
67. `/home/keven/Documents/solidtime-analysis/tests/Unit/Service/InvoicePaymentServiceTest.php`
68. `/home/keven/Documents/solidtime-analysis/tests/Unit/Service/RecurringInvoiceServiceTest.php`
69. `/home/keven/Documents/solidtime-analysis/tests/Unit/Endpoint/Api/V1/InvoiceEndpointTest.php`
70. `/home/keven/Documents/solidtime-analysis/tests/Unit/Endpoint/Api/V1/InvoicePaymentEndpointTest.php`
71. `/home/keven/Documents/solidtime-analysis/tests/Unit/Endpoint/Api/V1/RecurringInvoiceScheduleEndpointTest.php`

### 12.2 Backend Files to Modify

72. `/home/keven/Documents/solidtime-analysis/app/Models/Client.php` - Add casts, relationships
73. `/home/keven/Documents/solidtime-analysis/app/Models/Organization.php` - Add casts, relationships
74. `/home/keven/Documents/solidtime-analysis/app/Models/TimeEntry.php` - Add casts, relationships, scopes
75. `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ClientController.php` - Support billing fields
76. `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Client/ClientStoreRequest.php` - Validate billing fields
77. `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Client/ClientUpdateRequest.php` - Validate billing fields
78. `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Client/ClientResource.php` - Include billing fields
79. `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/OrganizationController.php` - Include invoice settings
80. `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Organization/OrganizationUpdateRequest.php` - Validate invoice settings
81. `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Organization/OrganizationResource.php` - Include invoice settings
82. `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php` - Call `InvoicePermissions::register()`
83. `/home/keven/Documents/solidtime-analysis/app/Console/Kernel.php` - Schedule `MarkOverdueInvoicesCommand`, `GenerateRecurringInvoicesCommand`
84. `/home/keven/Documents/solidtime-analysis/routes/api.php` - Register invoice API routes
85. `/home/keven/Documents/solidtime-analysis/routes/web.php` - Register invoice web routes

### 12.3 Frontend Files to Create

**Pinia Stores** (2 files):
86. `/home/keven/Documents/solidtime-analysis/resources/js/utils/useInvoices.ts`
87. `/home/keven/Documents/solidtime-analysis/resources/js/utils/useRecurringInvoiceSchedules.ts`

**Pages** (3 files):
88. `/home/keven/Documents/solidtime-analysis/resources/js/Pages/Invoices.vue`
89. `/home/keven/Documents/solidtime-analysis/resources/js/Pages/InvoiceShow.vue`
90. `/home/keven/Documents/solidtime-analysis/resources/js/Pages/InvoiceCreate.vue`

**UI Components** (20 files):
91. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/InvoiceTable.vue`
92. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/InvoiceStatusBadge.vue`
93. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/InvoiceFilters.vue`
94. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/InvoiceHeader.vue`
95. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/InvoiceLineItems.vue`
96. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/InvoiceLineItemRow.vue`
97. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/InvoiceSummary.vue`
98. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/InvoiceActions.vue`
99. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/InvoicePaymentHistory.vue`
100. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/RecordPaymentModal.vue`
101. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/InvoiceCreateWizard.vue`
102. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/InvoiceClientSelector.vue`
103. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/InvoiceTimeEntrySelector.vue`
104. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/InvoicePreview.vue`
105. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/InvoiceDetailsForm.vue`
106. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/InvoiceManualLineItem.vue`
107. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Client/ClientBillingForm.vue`
108. `/home/keven/Documents/solidtime-analysis/resources/js/Pages/Teams/Partials/InvoiceSettings.vue`
109. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/InvoiceNumberPreview.vue`
110. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/RecurringScheduleList.vue`
111. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/RecurringScheduleForm.vue`

**Frontend Tests** (5 files):
112. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/__tests__/InvoiceTable.test.ts`
113. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/__tests__/InvoiceStatusBadge.test.ts`
114. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/__tests__/InvoiceLineItems.test.ts`
115. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Invoice/__tests__/InvoiceCreateWizard.test.ts`

**E2E Tests** (3 files):
116. `/home/keven/Documents/solidtime-analysis/e2e/invoices/invoice-crud.spec.ts`
117. `/home/keven/Documents/solidtime-analysis/e2e/invoices/invoice-wizard.spec.ts`
118. `/home/keven/Documents/solidtime-analysis/e2e/invoices/invoice-payment.spec.ts`

### 12.4 Frontend Files to Modify

119. `/home/keven/Documents/solidtime-analysis/resources/js/utils/permissions.ts` - Add invoice permission helpers
120. `/home/keven/Documents/solidtime-analysis/resources/js/types/timesheet.d.ts` - Add invoice TypeScript types (or create new `invoice.d.ts`)
121. `/home/keven/Documents/solidtime-analysis/resources/js/Pages/Teams/Show.vue` - Include InvoiceSettings component

**Total Files**: 121 (71 create, 15 modify backend, 35 frontend)

---

## 13. Implementation Sequence

### Sprint 1 (Weeks 1-2) - Foundation [42h / 17 SP]

**Goal**: Database schema, models, client billing fields

- [ ] **INV-001** (8h): Create migrations for core tables
- [ ] **INV-002** (4h): Client billing columns migration
- [ ] **INV-003** (4h): Organization invoice settings migration
- [ ] **INV-004** (2h): TimeEntry invoice_id migration
- [ ] **INV-005** (8h): Invoice models + enums
- [ ] **INV-006** (6h): Model factories
- [ ] **INV-007** (4h): Client billing API backend
- [ ] **INV-008** (6h): Client billing UI

**Deliverable**: Migrations run successfully, models created, client billing editable

### Sprint 2 (Weeks 3-4) - Core Invoice CRUD [56h / 22 SP]

**Goal**: Backend invoice creation, preview, PDF generation

- [ ] **INV-009** (16h): InvoiceService core logic
- [ ] **INV-010** (14h): InvoiceController + routes (merged with INV-011 per AMD-06)
- [ ] **INV-012** (4h): Register permissions
- [ ] **INV-013** (12h): Invoice PDF Service (Gotenberg)
- [ ] **INV-014** (6h): Invoice settings API
- [ ] **INV-015** (4h): OpenAPI spec + TS client regen

**Deliverable**: API endpoints functional, PDF generation working

### Sprint 3 (Weeks 5-6) - Frontend Invoice UI [56h / 20 SP]

**Goal**: Invoice list, detail, creation wizard

- [ ] **INV-016** (8h): Invoice Pinia store
- [ ] **INV-017** (12h): Invoice list page + web route
- [ ] **INV-018** (16h): Invoice detail/edit page
- [ ] **INV-019** (16h): Invoice creation wizard
- [ ] **INV-020** (4h): PDF preview UI

**Deliverable**: Full invoice CRUD via UI, wizard functional

### Sprint 4 (Weeks 7-8) - Settings, Email, Payments [38h / 15 SP]

**Goal**: Settings UI, email sending, payment recording

- [ ] **INV-021** (8h): Invoice settings UI
- [ ] **INV-022** (8h): Invoice email service
- [ ] **INV-023** (4h): Overdue detection command
- [ ] **INV-024** (6h): Payment controller + API
- [ ] **INV-031** (12h): InvoiceService unit tests

**Deliverable**: Settings configurable, invoices sendable, payments recordable

### Sprint 5 (Weeks 9-10) - Recurring Invoices [38h / 13 SP]

**Goal**: Recurring invoice schedules

- [ ] **INV-025** (12h): Recurring service + command
- [ ] **INV-026** (10h): Recurring UI
- [ ] **INV-032** (16h): Endpoint tests

**Deliverable**: Recurring schedules functional, comprehensive endpoint coverage

### Sprint 6 (Weeks 11-12) - Payments & Export [44h / 15 SP]

**Goal**: Stripe integration, accounting exports

- [ ] **INV-027** (16h): Stripe integration
- [ ] **INV-028** (6h): Stripe UI
- [ ] **INV-029** (10h): Accounting export service
- [ ] **INV-030** (4h): Export UI
- [ ] **INV-033** (8h): Recurring + payment tests

**Deliverable**: Online payments working, exports functional

### Sprint 7 (Weeks 13-14) - Testing & Polish [22h / 8 SP]

**Goal**: Component tests, E2E tests, bug fixes

- [ ] **INV-034** (10h): Frontend component tests
- [ ] **INV-035** (12h): E2E Playwright tests
- [ ] **INV-036** (1h): Register web routes (AMD-10)

**Deliverable**: Full test coverage, production-ready

**Total Effort**: 297 hours / 110 SP across 7 sprints (14 weeks)

---

## 14. Testing Strategy

### 14.1 Unit Tests - Services

**InvoiceServiceTest** (`tests/Unit/Service/InvoiceServiceTest.php`):
- `test_generates_unique_invoice_numbers_concurrently()`
- `test_preview_from_time_entries_groups_correctly()`
- `test_preview_applies_rounding()`
- `test_create_invoice_snapshots_client_and_org()`
- `test_create_invoice_links_time_entries()`
- `test_recalculate_totals_correctly()`
- `test_send_invoice_transitions_status()`
- `test_void_invoice_unlinks_time_entries()`
- `test_transition_to_overdue_updates_correct_invoices()`

**InvoicePaymentServiceTest**:
- `test_record_payment_updates_amount_paid()`
- `test_full_payment_marks_invoice_paid()`
- `test_partial_payment_marks_invoice_partial()`
- `test_overpayment_prevented_by_validation()`

### 14.2 Endpoint Tests

**InvoiceEndpointTest** (`tests/Unit/Endpoint/Api/V1/InvoiceEndpointTest.php`):
- `test_list_invoices_requires_permission()`
- `test_list_invoices_filters_by_status()`
- `test_list_invoices_filters_by_client()`
- `test_preview_requires_permission()`
- `test_preview_returns_correct_line_items()`
- `test_create_invoice_requires_permission()`
- `test_create_invoice_validates_client_exists()`
- `test_create_invoice_requires_line_items()`
- `test_create_invoice_links_time_entries()`
- `test_update_draft_invoice_allowed()`
- `test_update_sent_invoice_line_items_forbidden()`
- `test_send_invoice_requires_permission()`
- `test_send_invoice_requires_draft_status()`
- `test_send_invoice_requires_billing_email()`
- `test_void_invoice_requires_permission()`
- `test_void_invoice_unlinks_time_entries()`
- `test_delete_draft_invoice_allowed()`
- `test_delete_sent_invoice_forbidden()`
- `test_pdf_generation_returns_url()`

**InvoicePaymentEndpointTest**:
- `test_record_payment_requires_permission()`
- `test_record_payment_validates_amount_not_exceeds_due()`
- `test_record_payment_updates_invoice_status()`

### 14.3 Frontend Component Tests

**InvoiceTable.test.ts**:
- Renders invoice list correctly
- Emits row-click event
- Displays status badges with correct colors
- Formats monetary amounts correctly

**InvoiceCreateWizard.test.ts**:
- Navigates through wizard steps
- Validates client selection
- Fetches and displays time entry preview
- Allows editing line items
- Submits complete invoice data

### 14.4 E2E Tests (Playwright)

**invoice-crud.spec.ts**:
```typescript
test('create, edit, send, and void invoice', async ({ page }) => {
    await page.goto('/invoices');
    await page.click('text=New Invoice');
    await page.selectOption('select[name="client_id"]', 'client-uuid');
    // ... wizard steps
    await page.click('text=Create Draft');
    await expect(page.locator('text=Invoice created')).toBeVisible();
    // Edit
    await page.click('text=Edit');
    // ... edit fields
    await page.click('text=Save');
    // Send
    await page.click('text=Send');
    await expect(page.locator('text=Invoice sent')).toBeVisible();
    // Void
    await page.click('text=Void');
    await page.fill('textarea[name="reason"]', 'Test void');
    await page.click('text=Confirm');
    await expect(page.locator('text=Voided')).toBeVisible();
});
```

**invoice-wizard.spec.ts**:
- Test time entry selection and filtering
- Test grouping options
- Test manual line item addition
- Test rounding configuration

**invoice-payment.spec.ts**:
- Test manual payment recording
- Test partial payment flow
- Test full payment marking invoice paid

**Test Coverage Goal**: 85%+ on critical paths (InvoiceService, InvoiceController, wizard)

---

**END OF ARCHITECTURE DOCUMENT**

This comprehensive architecture blueprint provides everything needed to implement the Invoicing System feature following existing Solidtime patterns and conventions. All file paths are absolute, code examples follow established codebase style, and integration points are clearly documented.