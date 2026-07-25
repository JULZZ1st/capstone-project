# Report Generator Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `/reports` "Report Generator" page with a real, database-driven Financial Ledgers report (summary cards, cash-intake breakdown, settled receipts ledger, CSV export) plus stubbed In-Kind Audits / Philanthropy Roster tabs, matching the approved design in `docs/superpowers/specs/2026-07-25-report-generator-design.md`.

**Architecture:** Thin `ReportController` + a single `FinancialLedgerReportBuilder` service that runs one eager-loaded Eloquent query per month and derives all summary/breakdown/ledger data from that one in-memory collection (no duplicated queries). A small `FinancialLedgerCsvExporter` reuses the builder's ledger rows to stream CSV. Blade view + small single-purpose components reuse existing layout/dashboard components.

**Tech Stack:** Laravel 11, PHP 8+, Blade, Tailwind CSS, MySQL, Eloquent, PHPUnit (`RefreshDatabase`).

## Global Constraints

- No new database tables/migrations — everything is derived from existing `Contribution` and `BankLedgerEntry` data.
- Controllers stay thin; business logic lives in `app/Services/Reports/`.
- No hardcoded UI data — all stats, breakdowns, and ledger rows come from the database; config lookups (`config('donations.payment_methods')`, `config('bank_reconciliation.receipt_statuses')`) are used for label/badge text, never raw strings.
- File size limits (AGENTS.md): Controllers <300 lines, Services <250 lines, Views <300 lines, Components <150 lines.
- Access restricted to `admin`, `secretary`, `treasurer` — same as Donations/Bank Reconciliation, reusing `ContributionPolicy::viewAny` (no new Policy).
- Cancelled contributions (`status = 'cancelled'`) are excluded everywhere in this report.

---

### Task 1: FinancialLedgerReportBuilder service

**Files:**
- Create: `app/Services/Reports/FinancialLedgerReportBuilder.php`
- Test: `tests/Feature/FinancialLedgerReportBuilderTest.php`

**Interfaces:**
- Consumes: `App\Models\Contribution` (fields: `donor_id`, `campaign_id`, `type`, `status`, `amount`, `payment_method`, `received_at`; relations `donor`, `campaign`, `ledgerEntry`), `App\Models\BankLedgerEntry` (fields: `matched_contribution_id`, `status`), `config('donations.payment_methods')`, `config('donations.electronic_payment_methods')`, `config('bank_reconciliation.receipt_statuses')`.
- Produces: `App\Services\Reports\FinancialLedgerReportBuilder::build(int $year, int $month): array` returning:
  ```
  [
      'period' => Illuminate\Support\Carbon,               // first day of the month
      'summary' => [
          'reconciled_total' => float,
          'pending_clearing_total' => float,
          'recorded_total' => float,
      ],
      'wire_sources' => Illuminate\Support\Collection,      // list of ['method'=>string,'label'=>string,'amount'=>float,'percentage'=>int]
      'ledger' => Illuminate\Support\Collection,            // list of ['id'=>int,'donor_name'=>string,'campaign_name'=>?string,'payment_method_label'=>string,'amount'=>float,'received_at'=>Carbon,'status_key'=>string,'status_label'=>string,'status_class'=>string]
  ]
  ```
  Ledger rows are ordered newest `received_at` first. Wire sources are ordered by amount descending and only include payment methods with activity that month.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/FinancialLedgerReportBuilderTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\BankLedgerEntry;
use App\Models\Contribution;
use App\Models\Donor;
use App\Services\Reports\FinancialLedgerReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialLedgerReportBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_summarizes_reconciled_pending_and_recorded_totals(): void
    {
        $reconciled = $this->cashContribution(['amount' => 15000, 'payment_method' => 'bank_transfer', 'received_at' => '2026-06-05']);
        $this->reconcile($reconciled);

        $this->cashContribution(['amount' => 10000, 'payment_method' => 'gcash', 'received_at' => '2026-06-10']);
        $this->cashContribution(['amount' => 5000, 'payment_method' => 'cash', 'received_at' => '2026-06-12']);
        $this->cashContribution(['amount' => 99999, 'status' => 'cancelled', 'received_at' => '2026-06-15']);
        $this->cashContribution(['amount' => 8000, 'payment_method' => 'gcash', 'received_at' => '2026-05-20']);

        $report = app(FinancialLedgerReportBuilder::class)->build(2026, 6);

        $this->assertSame(15000.0, $report['summary']['reconciled_total']);
        $this->assertSame(10000.0, $report['summary']['pending_clearing_total']);
        $this->assertSame(30000.0, $report['summary']['recorded_total']);
    }

    public function test_it_breaks_down_cash_intake_by_wire_source_with_relative_percentages(): void
    {
        $this->cashContribution(['amount' => 55000, 'payment_method' => 'gcash', 'received_at' => '2026-06-05']);
        $this->cashContribution(['amount' => 115000, 'payment_method' => 'bank_transfer', 'received_at' => '2026-06-06']);

        $report = app(FinancialLedgerReportBuilder::class)->build(2026, 6);
        $sources = $report['wire_sources']->keyBy('method');

        $this->assertSame(55000.0, $sources['gcash']['amount']);
        $this->assertSame(115000.0, $sources['bank_transfer']['amount']);
        $this->assertSame(100, $sources['bank_transfer']['percentage']);
        $this->assertSame(48, $sources['gcash']['percentage']);
        $this->assertFalse($sources->has('check'));
    }

    public function test_ledger_rows_carry_status_labels_and_are_ordered_newest_first(): void
    {
        $reconciled = $this->cashContribution(['amount' => 15000, 'payment_method' => 'bank_transfer', 'received_at' => '2026-06-05', 'donor_name' => 'Dr. Manuel Co']);
        $this->reconcile($reconciled);
        $this->cashContribution(['amount' => 100000, 'payment_method' => 'gcash', 'received_at' => '2026-06-20', 'donor_name' => 'Enrique Razon']);

        $report = app(FinancialLedgerReportBuilder::class)->build(2026, 6);
        $ledger = $report['ledger']->values();

        $this->assertSame('Enrique Razon', $ledger[0]['donor_name']);
        $this->assertSame('pending', $ledger[0]['status_key']);
        $this->assertSame('Pending Ledger Clear', $ledger[0]['status_label']);
        $this->assertSame('Dr. Manuel Co', $ledger[1]['donor_name']);
        $this->assertSame('reconciled', $ledger[1]['status_key']);
        $this->assertSame('Reconciled', $ledger[1]['status_label']);
    }

    private function cashContribution(array $overrides = []): Contribution
    {
        $donor = Donor::query()->create([
            'donor_type' => 'individual',
            'full_name' => $overrides['donor_name'] ?? 'Test Donor '.random_int(1, 100000),
            'address' => 'Brgy. San Jose',
            'contact_number' => '09171234567',
            'email' => 'donor'.random_int(1, 100000).'@example.com',
            'status' => config('donors.defaults.status'),
        ]);

        return Contribution::query()->create(array_merge([
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => 'received',
            'amount' => 1000,
            'payment_method' => 'cash',
            'reference_number' => 'REF-'.random_int(100000, 999999),
            'received_at' => now(),
        ], collect($overrides)->except('donor_name')->all()));
    }

    private function reconcile(Contribution $contribution): BankLedgerEntry
    {
        return BankLedgerEntry::query()->create([
            'statement_id' => 'bt_'.random_int(1000, 9999),
            'description' => 'Bank credit',
            'reference_number' => $contribution->reference_number,
            'amount' => $contribution->amount,
            'transaction_date' => $contribution->received_at->toDateString(),
            'payment_method' => $contribution->payment_method,
            'status' => 'reconciled',
            'matched_contribution_id' => $contribution->id,
            'matched_at' => now(),
        ]);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=FinancialLedgerReportBuilderTest`
Expected: FAIL — `Class "App\Services\Reports\FinancialLedgerReportBuilder" not found`.

- [ ] **Step 3: Write the service**

Create `app/Services/Reports/FinancialLedgerReportBuilder.php`:

```php
<?php

namespace App\Services\Reports;

use App\Models\Contribution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FinancialLedgerReportBuilder
{
    public function build(int $year, int $month): array
    {
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $contributions = Contribution::query()
            ->with([
                'donor:id,full_name',
                'campaign:id,name',
                'ledgerEntry:id,matched_contribution_id,status',
            ])
            ->where('type', 'cash')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('received_at', [$periodStart, $periodEnd])
            ->orderByDesc('received_at')
            ->get();

        return [
            'period' => $periodStart,
            'summary' => $this->summarize($contributions),
            'wire_sources' => $this->wireSourceBreakdown($contributions),
            'ledger' => $this->ledgerRows($contributions),
        ];
    }

    private function summarize(Collection $contributions): array
    {
        $reconciled = $contributions->filter(fn (Contribution $contribution) => $this->isReconciled($contribution));
        $pending = $contributions->filter(
            fn (Contribution $contribution) => ! $this->isReconciled($contribution) && $this->isElectronic($contribution)
        );

        return [
            'reconciled_total' => (float) $reconciled->sum('amount'),
            'pending_clearing_total' => (float) $pending->sum('amount'),
            'recorded_total' => (float) $contributions->sum('amount'),
        ];
    }

    private function wireSourceBreakdown(Collection $contributions): Collection
    {
        $methodLabels = config('donations.payment_methods');

        $totals = $contributions
            ->groupBy('payment_method')
            ->map(fn (Collection $group) => (float) $group->sum('amount'))
            ->sortDesc();

        $max = (float) $totals->max();

        return $totals
            ->map(fn (float $amount, string $method) => [
                'method' => $method,
                'label' => $methodLabels[$method] ?? ucfirst($method),
                'amount' => $amount,
                'percentage' => $max > 0 ? (int) min(100, round($amount / $max * 100)) : 0,
            ])
            ->values();
    }

    private function ledgerRows(Collection $contributions): Collection
    {
        $statuses = config('bank_reconciliation.receipt_statuses');
        $methodLabels = config('donations.payment_methods');

        return $contributions->map(function (Contribution $contribution) use ($statuses, $methodLabels) {
            $statusKey = $this->isReconciled($contribution) ? 'reconciled' : 'pending';

            return [
                'id' => $contribution->id,
                'donor_name' => $contribution->donor?->full_name ?? 'Unknown Donor',
                'campaign_name' => $contribution->campaign?->name,
                'payment_method_label' => $methodLabels[$contribution->payment_method] ?? ucfirst((string) $contribution->payment_method),
                'amount' => (float) $contribution->amount,
                'received_at' => $contribution->received_at,
                'status_key' => $statusKey,
                'status_label' => $statuses[$statusKey]['label'],
                'status_class' => $statuses[$statusKey]['class'],
            ];
        });
    }

    private function isReconciled(Contribution $contribution): bool
    {
        return $contribution->ledgerEntry !== null && $contribution->ledgerEntry->status === 'reconciled';
    }

    private function isElectronic(Contribution $contribution): bool
    {
        return in_array($contribution->payment_method, config('donations.electronic_payment_methods'), true);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=FinancialLedgerReportBuilderTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Reports/FinancialLedgerReportBuilder.php tests/Feature/FinancialLedgerReportBuilderTest.php
git commit -m "feat: add FinancialLedgerReportBuilder service for report generator"
```

---

### Task 2: Report Generator page (routes, request, controller, view, components, sidebar)

**Files:**
- Create: `app/Http/Requests/Reports/ReportIndexRequest.php`
- Create: `app/Http/Controllers/ReportController.php`
- Create: `resources/views/reports/index.blade.php`
- Create: `resources/views/components/reports/tab-nav.blade.php`
- Create: `resources/views/components/reports/month-filter.blade.php`
- Create: `resources/views/components/reports/wire-source-bar.blade.php`
- Create: `resources/views/components/reports/ledger-row.blade.php`
- Modify: `resources/views/components/dashboard/summary-card.blade.php` (add optional `tone` prop, backward compatible)
- Modify: `routes/web.php`
- Modify: `resources/views/components/app/sidebar.blade.php`
- Test: `tests/Feature/ReportGeneratorTest.php`

**Interfaces:**
- Consumes: `App\Services\Reports\FinancialLedgerReportBuilder::build(int $year, int $month): array` (Task 1), `App\Policies\ContributionPolicy::viewAny`, existing `x-dashboard.panel`, `x-dashboard.empty-state`, `x-button`, `layouts.app`.
- Produces: route `reports.index` (GET `/reports`, params `tab`, `year`, `month`); `App\Http\Requests\Reports\ReportIndexRequest` with methods `tab(): string`, `year(): int`, `month(): int`; `App\Http\Controllers\ReportController::index()`; Blade components `x-reports.tab-nav`, `x-reports.month-filter`, `x-reports.wire-source-bar`, `x-reports.ledger-row`; `x-dashboard.summary-card` now accepts `tone` (`'neutral'|'positive'|'warning'`, default `'neutral'`, unchanged default appearance).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ReportGeneratorTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\BankLedgerEntry;
use App\Models\Contribution;
use App\Models\Donor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_report_generator(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_view_financial_ledgers_report(): void
    {
        $reconciled = $this->cashContribution(['amount' => 15000, 'payment_method' => 'bank_transfer', 'received_at' => '2026-06-05', 'donor_name' => 'Dr. Manuel Co']);
        $this->reconcile($reconciled);
        $this->cashContribution(['amount' => 10000, 'payment_method' => 'gcash', 'received_at' => '2026-06-10', 'donor_name' => 'Enrique Razon']);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['year' => 2026, 'month' => 6]));

        $response->assertOk();
        $response->assertSee('Report Generator');
        $response->assertSee('Barangay Treasurer Cash Audit Summary');
        $response->assertSee('15,000');
        $response->assertSee('10,000');
        $response->assertSee('25,000');
        $response->assertSee('Dr. Manuel Co');
        $response->assertSee('Enrique Razon');
        $response->assertSee('Reconciled');
        $response->assertSee('Pending Ledger Clear');
    }

    public function test_month_filter_scopes_the_report(): void
    {
        $this->cashContribution(['amount' => 8000, 'payment_method' => 'gcash', 'received_at' => '2026-05-20', 'donor_name' => 'May Donor']);
        $this->cashContribution(['amount' => 12000, 'payment_method' => 'gcash', 'received_at' => '2026-06-20', 'donor_name' => 'June Donor']);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['year' => 2026, 'month' => 6]));

        $response->assertOk();
        $response->assertSee('June Donor');
        $response->assertDontSee('May Donor');
    }

    public function test_wire_source_breakdown_only_lists_channels_with_activity(): void
    {
        $this->cashContribution(['amount' => 55000, 'payment_method' => 'gcash', 'received_at' => '2026-06-05']);
        $this->cashContribution(['amount' => 115000, 'payment_method' => 'bank_transfer', 'received_at' => '2026-06-06']);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['year' => 2026, 'month' => 6]));

        $response->assertOk();
        $response->assertSee('GCash');
        $response->assertSee('Bank Transfer');
        $response->assertDontSee('Check');
    }

    public function test_in_kind_audits_tab_renders_a_coming_soon_placeholder(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['tab' => 'in-kind-audits']));

        $response->assertOk();
        $response->assertSee('In-Kind Audits');
        $response->assertSee('coming soon');
    }

    public function test_philanthropy_roster_tab_renders_a_coming_soon_placeholder(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['tab' => 'philanthropy-roster']));

        $response->assertOk();
        $response->assertSee('Philanthropy Roster');
        $response->assertSee('coming soon');
    }

    private function cashContribution(array $overrides = []): Contribution
    {
        $donor = Donor::query()->create([
            'donor_type' => 'individual',
            'full_name' => $overrides['donor_name'] ?? 'Test Donor '.random_int(1, 100000),
            'address' => 'Brgy. San Jose',
            'contact_number' => '09171234567',
            'email' => 'donor'.random_int(1, 100000).'@example.com',
            'status' => config('donors.defaults.status'),
        ]);

        return Contribution::query()->create(array_merge([
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => 'received',
            'amount' => 1000,
            'payment_method' => 'cash',
            'reference_number' => 'REF-'.random_int(100000, 999999),
            'received_at' => now(),
        ], collect($overrides)->except('donor_name')->all()));
    }

    private function reconcile(Contribution $contribution): BankLedgerEntry
    {
        return BankLedgerEntry::query()->create([
            'statement_id' => 'bt_'.random_int(1000, 9999),
            'description' => 'Bank credit',
            'reference_number' => $contribution->reference_number,
            'amount' => $contribution->amount,
            'transaction_date' => $contribution->received_at->toDateString(),
            'payment_method' => $contribution->payment_method,
            'status' => 'reconciled',
            'matched_contribution_id' => $contribution->id,
            'matched_at' => now(),
        ]);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=ReportGeneratorTest`
Expected: FAIL — route `reports.index` not defined.

- [ ] **Step 3: Add the `tone` prop to the shared summary card**

Modify `resources/views/components/dashboard/summary-card.blade.php`:

```php
@props(['label', 'value', 'description' => null, 'tone' => 'neutral'])

@php
    $toneClasses = match ($tone) {
        'positive' => 'border-emerald-200 bg-emerald-50',
        'warning' => 'border-amber-200 bg-amber-50',
        default => 'border-slate-200 bg-white',
    };

    $descriptionClasses = match ($tone) {
        'positive' => 'text-emerald-700',
        'warning' => 'text-amber-700',
        default => 'text-slate-600',
    };
@endphp

<article class="rounded-lg border {{ $toneClasses }} p-5 shadow-sm">
    <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
    <p class="mt-3 text-3xl font-bold text-slate-950">{{ $value }}</p>
    @if ($description)
        <p class="mt-2 text-sm {{ $descriptionClasses }}">{{ $description }}</p>
    @endif
</article>
```

This keeps every existing call site (`dashboard.blade.php`, `components/donors/history-summary.blade.php`) rendering identically, since they don't pass `tone` and the default is `'neutral'`.

- [ ] **Step 4: Create the Form Request**

Create `app/Http/Requests/Reports/ReportIndexRequest.php`:

```php
<?php

namespace App\Http\Requests\Reports;

use App\Models\Contribution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Contribution::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'tab' => ['nullable', Rule::in(['financial-ledgers', 'in-kind-audits', 'philanthropy-roster'])],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }

    public function tab(): string
    {
        return $this->validated('tab') ?? 'financial-ledgers';
    }

    public function year(): int
    {
        return (int) ($this->validated('year') ?? now()->year);
    }

    public function month(): int
    {
        return (int) ($this->validated('month') ?? now()->month);
    }
}
```

- [ ] **Step 5: Create the controller**

Create `app/Http/Controllers/ReportController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reports\ReportIndexRequest;
use App\Services\Reports\FinancialLedgerReportBuilder;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(ReportIndexRequest $request, FinancialLedgerReportBuilder $builder): View
    {
        $tab = $request->tab();

        return view('reports.index', [
            'tab' => $tab,
            'year' => $request->year(),
            'month' => $request->month(),
            'report' => $tab === 'financial-ledgers' ? $builder->build($request->year(), $request->month()) : null,
        ]);
    }
}
```

- [ ] **Step 6: Create the tab-nav component**

Create `resources/views/components/reports/tab-nav.blade.php`:

```php
@props(['active', 'year', 'month'])

@php
    $tabs = [
        'financial-ledgers' => 'Financial Ledgers',
        'in-kind-audits' => 'In-Kind Audits',
        'philanthropy-roster' => 'Philanthropy Roster',
    ];
@endphp

<div class="grid gap-3 sm:grid-cols-3">
    @foreach ($tabs as $key => $label)
        <a
            href="{{ route('reports.index', ['tab' => $key, 'year' => $year, 'month' => $month]) }}"
            class="flex items-center justify-center rounded-lg border px-4 py-3 text-sm font-semibold transition {{ $active === $key ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
        >
            {{ $label }}
        </a>
    @endforeach
</div>
```

- [ ] **Step 7: Create the month-filter component**

Create `resources/views/components/reports/month-filter.blade.php`:

```php
@props(['year', 'month'])

<form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4">
    <input type="hidden" name="tab" value="financial-ledgers">

    <div>
        <label for="report-month" class="block text-xs font-semibold uppercase text-slate-500">Month</label>
        <select id="report-month" name="month" class="mt-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            @foreach (range(1, 12) as $monthOption)
                <option value="{{ $monthOption }}" @selected($month === $monthOption)>{{ date('F', mktime(0, 0, 0, $monthOption, 1)) }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="report-year" class="block text-xs font-semibold uppercase text-slate-500">Year</label>
        <select id="report-year" name="year" class="mt-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            @foreach (range(now()->year - 3, now()->year) as $yearOption)
                <option value="{{ $yearOption }}" @selected($year === $yearOption)>{{ $yearOption }}</option>
            @endforeach
        </select>
    </div>

    <x-button class="w-auto">Apply</x-button>
</form>
```

- [ ] **Step 8: Create the wire-source-bar component**

Create `resources/views/components/reports/wire-source-bar.blade.php`:

```php
@props(['source'])

<div class="mb-4 last:mb-0">
    <div class="mb-1 flex items-center justify-between text-sm">
        <span class="font-medium text-emerald-800">{{ $source['label'] }}</span>
        <span class="font-semibold text-slate-950">₱{{ number_format($source['amount'], 0) }}</span>
    </div>
    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
        <div class="h-full rounded-full bg-emerald-600" style="width: {{ $source['percentage'] }}%"></div>
    </div>
</div>
```

- [ ] **Step 9: Create the ledger-row component**

Create `resources/views/components/reports/ledger-row.blade.php`:

```php
@props(['row'])

<div class="flex items-center justify-between border-b border-slate-100 py-4 last:border-b-0">
    <div class="min-w-0">
        <p class="truncate text-sm font-semibold text-slate-950">{{ $row['donor_name'] }}</p>
        <p class="truncate text-xs text-slate-500">{{ $row['campaign_name'] ?? 'General Fund' }} &middot; Method: {{ $row['payment_method_label'] }}</p>
    </div>
    <div class="flex-none text-right">
        <p class="text-sm font-bold text-slate-950">₱{{ number_format($row['amount'], 0) }}</p>
        <span class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $row['status_class'] }}">
            {{ $row['status_label'] }}
        </span>
    </div>
</div>
```

- [ ] **Step 10: Create the page view**

Create `resources/views/reports/index.blade.php`:

```php
@extends('layouts.app')

@section('title', 'Report Generator | '.config('dashboard.barangay_name'))
@section('page-title', 'Report Generator')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-950">Report Generator</h1>
                <p class="mt-1 text-sm text-slate-500">Generate municipal audits and transparency logs for municipal review.</p>
            </div>

            @if ($tab === 'financial-ledgers')
                <a
                    href="{{ route('reports.financial-ledgers.csv', ['year' => $year, 'month' => $month]) }}"
                    class="inline-flex items-center gap-2 rounded-md bg-green-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-green-800"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                        <path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z" />
                        <path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" />
                    </svg>
                    Download Official CSV Report
                </a>
            @endif
        </div>

        <x-reports.tab-nav :active="$tab" :year="$year" :month="$month" />

        @if ($tab === 'financial-ledgers')
            <x-reports.month-filter :year="$year" :month="$month" />

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-1 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Report Status: Final Auditable Ledger</p>
                        <h2 class="text-sm font-bold uppercase text-slate-950">Barangay Treasurer Cash Audit Summary</h2>
                    </div>
                    <p class="text-xs text-slate-400">Month Cycle: {{ $report['period']->format('F Y') }}</p>
                </div>

                <div class="grid gap-4 border-b border-slate-100 p-5 sm:grid-cols-3">
                    <x-dashboard.summary-card
                        tone="positive"
                        label="Total Reconciled Funds"
                        value="₱{{ number_format($report['summary']['reconciled_total'], 0) }}"
                        description="Cleared in Bank Statement"
                    />
                    <x-dashboard.summary-card
                        tone="warning"
                        label="Total Pending Clearing"
                        value="₱{{ number_format($report['summary']['pending_clearing_total'], 0) }}"
                        description="Direct receipts awaiting audit"
                    />
                    <x-dashboard.summary-card
                        label="Total Recorded Receipts"
                        value="₱{{ number_format($report['summary']['recorded_total'], 0) }}"
                        description="Cash grant operations cumulative"
                    />
                </div>

                <div class="border-b border-slate-100 p-5">
                    <h3 class="mb-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Cash Intake Breakdown by Wire Source</h3>

                    @forelse ($report['wire_sources'] as $source)
                        <x-reports.wire-source-bar :source="$source" />
                    @empty
                        <x-dashboard.empty-state message="No cash receipts recorded for this period." />
                    @endforelse
                </div>

                <div class="p-5">
                    <h3 class="mb-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Settled Cash Receipts Ledger</h3>

                    @forelse ($report['ledger'] as $row)
                        <x-reports.ledger-row :row="$row" />
                    @empty
                        <x-dashboard.empty-state message="No cash receipts recorded for this period." />
                    @endforelse
                </div>
            </section>
        @else
            <x-dashboard.panel
                :title="$tab === 'in-kind-audits' ? 'In-Kind Audits' : 'Philanthropy Roster'"
                subtitle="This report is coming soon."
            >
                <x-dashboard.empty-state message="This report category is not available yet." />
            </x-dashboard.panel>
        @endif
    </div>
@endsection
```

- [ ] **Step 11: Wire up the route**

Modify `routes/web.php` — add the import and route inside the existing `auth + role:admin,secretary,treasurer` group:

```php
use App\Http\Controllers\ReportController;
```

```php
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
```

Place it directly after the `bank-reconciliation.reconcile` route, before the closing `});` of the group.

- [ ] **Step 12: Wire up the sidebar link**

Modify `resources/views/components/app/sidebar.blade.php` — replace:

```php
        ['label' => 'Report Generator'],
```

with:

```php
        ['label' => 'Report Generator', 'route' => 'reports.index', 'active' => 'reports.*'],
```

- [ ] **Step 13: Run the tests to verify they pass**

Run: `php artisan test --filter=ReportGeneratorTest`
Expected: PASS (6 tests).

Also run the full suite to confirm the `summary-card` change didn't break existing pages:

Run: `php artisan test --filter=DashboardTest`
Run: `php artisan test --filter=DonorTest`

(If either test class doesn't exist under those exact names, run `php artisan test` for the full suite instead.)

- [ ] **Step 14: Commit**

```bash
git add app/Http/Requests/Reports/ReportIndexRequest.php app/Http/Controllers/ReportController.php resources/views/reports/index.blade.php resources/views/components/reports resources/views/components/dashboard/summary-card.blade.php routes/web.php resources/views/components/app/sidebar.blade.php tests/Feature/ReportGeneratorTest.php
git commit -m "feat: add Report Generator page with Financial Ledgers report"
```

---

### Task 3: CSV export for the Financial Ledgers report

**Files:**
- Create: `app/Services/Reports/FinancialLedgerCsvExporter.php`
- Modify: `app/Http/Controllers/ReportController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/ReportGeneratorTest.php`

**Interfaces:**
- Consumes: `App\Services\Reports\FinancialLedgerReportBuilder::build()` ledger rows (Task 1 shape), `App\Http\Requests\Reports\ReportIndexRequest::year()/month()` (Task 2).
- Produces: `App\Services\Reports\FinancialLedgerCsvExporter::handle(Illuminate\Support\Collection $ledgerRows, string $filename): Symfony\Component\HttpFoundation\StreamedResponse`; route `reports.financial-ledgers.csv` (GET `/reports/financial-ledgers/csv`); `ReportController::financialLedgersCsv()`.

- [ ] **Step 1: Write the failing test**

Add these two test methods to `tests/Feature/ReportGeneratorTest.php` (inside the class, using the existing `cashContribution()`/`reconcile()` helpers already defined in that file from Task 2):

```php
    public function test_authorized_user_can_download_financial_ledgers_csv(): void
    {
        $reconciled = $this->cashContribution(['amount' => 15000, 'payment_method' => 'bank_transfer', 'received_at' => '2026-06-05', 'donor_name' => 'Dr. Manuel Co']);
        $this->reconcile($reconciled);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.financial-ledgers.csv', ['year' => 2026, 'month' => 6]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Dr. Manuel Co', $content);
        $this->assertStringContainsString('15000.00', $content);
        $this->assertStringContainsString('Reconciled', $content);
    }

    public function test_guest_cannot_download_financial_ledgers_csv(): void
    {
        $this->get(route('reports.financial-ledgers.csv'))->assertRedirect(route('login'));
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=ReportGeneratorTest`
Expected: FAIL — route `reports.financial-ledgers.csv` not defined.

- [ ] **Step 3: Create the CSV exporter service**

Create `app/Services/Reports/FinancialLedgerCsvExporter.php`:

```php
<?php

namespace App\Services\Reports;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialLedgerCsvExporter
{
    public function handle(Collection $ledgerRows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($ledgerRows) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Donor', 'Campaign', 'Payment Method', 'Amount (PHP)', 'Received At', 'Status']);

            foreach ($ledgerRows as $row) {
                fputcsv($handle, [
                    $row['donor_name'],
                    $row['campaign_name'] ?? 'General Fund',
                    $row['payment_method_label'],
                    number_format($row['amount'], 2, '.', ''),
                    $row['received_at']->format('Y-m-d'),
                    $row['status_label'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
```

- [ ] **Step 4: Add the controller action**

Modify `app/Http/Controllers/ReportController.php` — add the import and method:

```php
use App\Services\Reports\FinancialLedgerCsvExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;
```

```php
    public function financialLedgersCsv(
        ReportIndexRequest $request,
        FinancialLedgerReportBuilder $builder,
        FinancialLedgerCsvExporter $exporter
    ): StreamedResponse {
        $report = $builder->build($request->year(), $request->month());

        $filename = sprintf('financial-ledger-report-%04d-%02d.csv', $request->year(), $request->month());

        return $exporter->handle($report['ledger'], $filename);
    }
```

- [ ] **Step 5: Wire up the route**

Modify `routes/web.php` — add directly after the `reports.index` route:

```php
    Route::get('/reports/financial-ledgers/csv', [ReportController::class, 'financialLedgersCsv'])->name('reports.financial-ledgers.csv');
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=ReportGeneratorTest`
Expected: PASS (8 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Services/Reports/FinancialLedgerCsvExporter.php app/Http/Controllers/ReportController.php routes/web.php tests/Feature/ReportGeneratorTest.php
git commit -m "feat: add CSV export for the Financial Ledgers report"
```

---

## Final Verification (after Task 3)

- [ ] Run the full test suite: `php artisan test` — expect no failures.
- [ ] Run `php artisan route:list --name=reports` — confirm both routes are registered with the correct middleware.
- [ ] Manually load `/reports` as an admin/secretary/treasurer user and confirm: tabs switch, month filter works, CSV downloads, cards/breakdown/ledger show real numbers that add up (`recorded_total = reconciled_total + pending_clearing_total + any non-electronic-method total`), and the page is responsive (cards stack on mobile via existing `sm:grid-cols-3` breakpoint).
- [ ] Confirm no UI data is hardcoded — search the new Blade files for literal currency figures or donor names (there should be none outside the test file).
