# Gina's Remaining Tasks — Design Spec

**Date:** 2026-08-02
**Scope owner:** Gina (Dashboard, In-Kind Inventory, Donation Portal)
**Source of truth:** `docs/PROJECT_REQUIREMENTS.md`, `docs/TEAM_TASK_ASSIGNMENT.md`
**Status:** Design approved, pending implementation

## Purpose and Scope

This spec covers Gina's two remaining outstanding items from `TEAM_TASK_ASSIGNMENT.md`, plus the dashboard hook they require:

1. **A6 — Configure Item Categories (admin-editable).** Replace `config('inventory.categories')` / `config('inventory.units')` with database-backed reference tables and an admin-only CRUD UI.
2. **S6 — Record Cash Disbursements.** A new, self-contained module for logging outbound cash disbursements, distinct from the existing inbound `contributions` table.
3. **Dashboard widgets for disbursement data.** Extend `DashboardMetrics` additively so the dashboard reflects disbursement totals now, without assuming Julius's future approval/ledger schema.

All other modules (`Contribution`/`LogContribution`, `InventoryItem`/`AdjustInventoryStock` stock math, Bank Reconciliation, Report Generator, System Security) are **out of scope** and must not be modified except at the specific call sites listed below where they read `config('inventory.categories')`/`.units`.

### Explicit non-goals (per user constraints)

- No approval/maker-checker workflow for disbursements (A7 — Julius, not yet built).
- No changes to `bank_ledger_entries`, `FinancialLedgerReportBuilder`, or any Financial Ledger schema (Julius's ownership).
- No balance-ceiling validation on disbursement amount — there is no authoritative balance source yet. This is a documented, intentional gap.
- No new sidebar entries beyond the one specified for Cash Disbursements; no new pages beyond those specified.
- No hardcoded categories/units/purposes/payment methods in controllers or Blade — all enumerable values come from config or the new DB tables.

## Part 1 — A6: Admin-Managed Item Categories

### Data Model

**Migration:** `create_inventory_categories_and_units_tables`

```php
Schema::create('inventory_categories', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->string('label');
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
});

Schema::create('inventory_units', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->string('label');
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
});
```

Same migration seeds both tables from the current `config('inventory.categories')` and `config('inventory.units')` arrays (in `up()`, via `DB::table(...)->insert(...)`), so every existing `inventory_items.category` / `.unit` value remains a valid key with zero data migration. `inventory_items.category` / `.unit` columns are **unchanged** — they keep storing the string key; only the source of truth for *valid* keys moves from config to these tables.

`movement_types`, `adjustment_reasons`, `item_statuses`, `stock_statuses`, `defaults`, `pagination`, `alerts`, `critical_stock_ratio`, `expiring_soon_days` **stay in `config/inventory.php` unchanged** — A6 is scoped to categories and units only, matching the exact wording of the requirement.

### Models

- `App\Models\InventoryCategory` — `fillable: [key, label, is_active]`, `scopeActive()`, static `options(): array` returning `key => label` for active rows via a plain query (`static::query()->active()->orderBy('label')->pluck('label', 'key')->all()`).
- `App\Models\InventoryUnit` — identical shape/behavior.

Deliberately **no caching** on `options()`: these tables are tiny (tens of rows), the query runs once per request, and caching validation-critical reference data (admin adds a category → staff must be able to use it immediately) risks stale-cache correctness bugs and cross-test pollution (the test suite has no cache-flush step between tests). This is a scoped simplification of the original design — YAGNI outweighs the marginal query-count savings here.

### Policies

- `App\Policies\InventoryCategoryPolicy` and `App\Policies\InventoryUnitPolicy`:
  - `viewAny`: `admin, secretary, treasurer` (item forms need the options list).
  - `create`, `update`, `delete`: `admin` only (A6 is an Administrator-only requirement in PROJECT_REQUIREMENTS.md).

### Service

- `App\Services\Inventory\ManageInventoryReferenceItem` — a small generic service used by both controllers, parameterized by the target model class (`InventoryCategory::class` / `InventoryUnit::class`):
  - `create(string $modelClass, array $data): Model`
  - `update(string $modelClass, Model $item, array $data): Model`
  - `delete(string $modelClass, Model $item): void` — throws `ValidationException` if any `inventory_items` row currently references `$item->key` in the corresponding column (`category` or `unit`); otherwise hard-deletes.

  Rationale for one generic service instead of two near-duplicates: `InventoryCategory` and `InventoryUnit` have identical shape and identical business rules (unique key, in-use guard on delete); a parameterized service avoids copy-pasted logic while each Form Request/Controller/Policy stays per-model and explicit.

### Controllers

- `App\Http\Controllers\InventoryCategoryController` — `index` (JSON-free, returns view data merged into the categories page), `store`, `update`, `destroy`.
- `App\Http\Controllers\InventoryUnitController` — same shape.

Both are thin: authorize via `Gate::authorize(...)`, validate via Form Request, delegate to `ManageInventoryReferenceItem`, redirect with flash message.

### Form Requests

- `App\Http\Requests\Inventory\StoreInventoryCategoryRequest` / `UpdateInventoryCategoryRequest`:
  - `key`: required, string, max 100, lowercase-normalized in `prepareForValidation`, `Rule::unique('inventory_categories', 'key')` (ignoring self on update).
  - `label`: required, string, max 255.
  - `is_active`: boolean, defaults true.
- Same shape for `StoreInventoryUnitRequest` / `UpdateInventoryUnitRequest` against `inventory_units`.

### Routes

Inside the existing `['auth', 'role:admin,secretary,treasurer']` group (authorization narrowed further by policy, matching how `InventoryItemPolicy::archive` is already role-narrower than the route middleware):

```php
Route::get('/inventory/categories', [InventoryCategoryController::class, 'index'])->name('inventory.categories.index');
Route::post('/inventory/categories', [InventoryCategoryController::class, 'store'])->name('inventory.categories.store');
Route::patch('/inventory/categories/{inventoryCategory}', [InventoryCategoryController::class, 'update'])->name('inventory.categories.update');
Route::delete('/inventory/categories/{inventoryCategory}', [InventoryCategoryController::class, 'destroy'])->name('inventory.categories.destroy');

Route::post('/inventory/units', [InventoryUnitController::class, 'store'])->name('inventory.units.store');
Route::patch('/inventory/units/{inventoryUnit}', [InventoryUnitController::class, 'update'])->name('inventory.units.update');
Route::delete('/inventory/units/{inventoryUnit}', [InventoryUnitController::class, 'destroy'])->name('inventory.units.destroy');
```

(`inventory.categories.index` renders both panels; units don't need a separate index route since they render on the same page.)

### Blade / UI

- `resources/views/inventory/categories.blade.php` — extends `layouts.app`, page title "Manage Item Categories". Two side-by-side panels (reusing `x-dashboard.panel`):
  - Categories: table of key/label/active/actions + "Add Category" modal.
  - Units: table of key/label/active/actions + "Add Unit" modal.
- New components: `components/inventory/reference-table.blade.php` (generic key/label/active/actions table, reused for both panels via props), `components/inventory/reference-form-modal.blade.php` (generic add/edit modal, reused via props).
- Entry point: an admin-only "Manage Categories & Units" `x-button` link added to `resources/views/inventory/index.blade.php` header (visible only when `Gate::allows('create', InventoryCategory::class)`), not a new sidebar item.

### Call sites updated (verified against current codebase)

| File | Current | Change |
|---|---|---|
| `app/Http/Requests/Inventory/StoreInventoryItemRequest.php` | `Rule::in(array_keys(config('inventory.categories')))` / `.units` | `Rule::in(array_keys(InventoryCategory::options()))` / `InventoryUnit::options()` |
| `app/Http/Requests/Inventory/UpdateInventoryItemRequest.php` | same | same |
| `app/Http/Requests/Inventory/InventoryIndexRequest.php` | same (category filter) | same |
| `app/Models/InventoryItem.php::scopeSearch()` | `collect(config('inventory.categories'))->filter(...)` | `collect(InventoryCategory::options())->filter(...)` |
| `resources/views/inventory/index.blade.php`, `create-modal`/`item-form` components | any direct `config('inventory.categories')`/`.units` Blade calls | controller passes `$categories = InventoryCategory::active()->get()` / `$units = InventoryUnit::active()->get()` as view data, consumed via props (no `config()` calls in Blade) |

`AdjustInventoryStock` and `CreateInventoryItem` are **not modified** — they never read `config('inventory.categories')`/`.units`; they operate on already-validated data.

## Part 2 — S6: Cash Disbursement Recording

### Data Model

**Migration:** `create_cash_disbursements_table`

```php
Schema::create('cash_disbursements', function (Blueprint $table) {
    $table->id();
    $table->string('disbursed_to');
    $table->string('purpose')->index();
    $table->decimal('amount', 12, 2);
    $table->string('payment_method')->nullable();
    $table->string('reference_number', 100)->nullable();
    $table->text('notes')->nullable();
    $table->timestamp('disbursed_at')->index();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index(['purpose', 'disbursed_at']);
});
```

No `status`/`approved_by`/`approved_at` columns — per confirmed scope, S6 records a final entry. Julius's future A7 work adds approval state via its own migration against this same table; this spec does not predict its shape.

### Config

`config/disbursements.php` (new file, same style as `config/donations.php`):

```php
return [
    'purposes' => [
        'aid_payout' => 'Aid Payout',
        'operational_expense' => 'Operational Expense',
        'honorarium' => 'Honorarium',
        'utilities' => 'Utilities',
        'supplies_purchase' => 'Supplies Purchase',
        'other' => 'Other',
    ],
    'pagination' => [
        'per_page' => 10,
    ],
];
```

`payment_method` reuses `config('donations.payment_methods')` — no duplicate list.

### Model

`App\Models\CashDisbursement`:
- `fillable`: `disbursed_to, purpose, amount, payment_method, reference_number, notes, disbursed_at, created_by`.
- casts: `amount => decimal:2`, `disbursed_at => datetime`.
- `creator(): BelongsTo` → `User`.
- `scopeSearch(?string $term)` — matches `disbursed_to`, `purpose`, `reference_number`, `notes`.
- `scopePurpose(?string $purpose)`.
- `scopeDateRange(?string $from, ?string $to)`.

### Policy

`App\Policies\CashDisbursementPolicy`:
- `viewAny`, `view`, `create`: `admin, secretary, treasurer` (matches `ContributionPolicy`, per confirmed scope).
- No `update`/`delete` methods — immutable record, same convention as `Contribution` (no destroy action).

### Service

`App\Services\Disbursements\RecordCashDisbursement::handle(array $data, User $user): CashDisbursement` — single-row create in a `DB::transaction`, defaults `disbursed_at` to `now()` when not provided. Mirrors `LogContribution` exactly in shape/simplicity (no child rows, no conditional branching needed since there's only one "type").

### Controller

`App\Http\Controllers\CashDisbursementController`:
- `index(CashDisbursementIndexRequest $request): View` — filtered, paginated list (search, purpose, date range), same query shape as `DonationPortalController::index`.
- `store(StoreCashDisbursementRequest $request, RecordCashDisbursement $recorder): RedirectResponse` — redirect to index with `success` flash + `highlight_cash_disbursement` session key (matches the `highlight_contribution` / `highlight_inventory_item` pattern already used).
- `show(CashDisbursement $cashDisbursement): View`.

### Form Requests

`App\Http\Requests\Disbursements\CashDisbursementIndexRequest`:
- `search`: nullable string max 255.
- `purpose`: nullable, `Rule::in(array_keys(config('disbursements.purposes')))`.
- `from`, `to`: nullable date.

`App\Http\Requests\Disbursements\StoreCashDisbursementRequest`:
- `disbursed_to`: required string max 255.
- `purpose`: required, `Rule::in(array_keys(config('disbursements.purposes')))`.
- `amount`: required numeric min 0.01 max 99999999.99.
- `payment_method`: nullable, `Rule::in(array_keys(config('donations.payment_methods')))`.
- `reference_number`: nullable string max 100.
- `notes`: nullable string max 2000.
- `disbursed_at`: nullable date.
- `prepareForValidation`: trim text fields, lowercase `purpose`/`payment_method`, blank→null for optional fields (same helper pattern as existing inventory/donation requests).

### Routes

New group inside the existing authenticated middleware block:

```php
Route::get('/cash-disbursements', [CashDisbursementController::class, 'index'])->name('cash-disbursements.index');
Route::post('/cash-disbursements', [CashDisbursementController::class, 'store'])->name('cash-disbursements.store');
Route::get('/cash-disbursements/{cashDisbursement}', [CashDisbursementController::class, 'show'])->name('cash-disbursements.show');
```

### Blade / UI

- `resources/views/cash-disbursements/index.blade.php` — extends `layouts.app`, follows `donations/index.blade.php` structure: filter bar, "Record Disbursement" button opening a modal, responsive table, pagination, empty state.
- `resources/views/cash-disbursements/show.blade.php` — single-record detail view (mirrors `donations/show.blade.php`).
- New components: `components/cash-disbursements/filter-bar.blade.php`, `table.blade.php`, `table-row.blade.php`, `log-modal.blade.php`, `form.blade.php`. All accept data via props, no queries inside components (existing rule).
- Reuses `x-alert`, `x-button`, `x-form.input`, `x-form.label`, `x-form.error`, `x-dashboard.empty-state`.

### Sidebar

Add one entry to `resources/views/components/app/sidebar.blade.php`'s `$items` array, directly after "Donations Portal":

```php
['label' => 'Cash Disbursements', 'route' => 'cash-disbursements.index', 'active' => 'cash-disbursements.*'],
```

## Part 3 — Dashboard Hook

`App\Support\Dashboard\DashboardMetrics::build()` gains one additive block — no existing keys are renamed, removed, or restructured:

```php
'disbursements' => [
    'total_disbursed' => CashDisbursement::query()->sum('amount'),
    'recent' => CashDisbursement::query()
        ->select(['id', 'disbursed_to', 'purpose', 'amount', 'disbursed_at'])
        ->latest('disbursed_at')
        ->latest()
        ->limit($limit)
        ->get(),
],
```

`resources/views/dashboard.blade.php` gains one additive panel, "Recent Cash Disbursements" (same list-panel pattern as "Recent Cash Donations Awaiting Audit"), plus `total_disbursed` surfaced as read-only context inside the existing "Treasurer Action Items" panel (not a new top-level summary card, to avoid reflowing the existing 4-column grid).

This block is built entirely from Gina's own `cash_disbursements` table — it does not reference or assume any future Julius column (`status`, `approved_by`, etc.). When Julius's A7 lands, extending this query to filter by his approval status is a non-breaking addition to this same array key.

## Testing Plan

- `tests/Feature/InventoryCategoryManagementTest.php`:
  - Guest redirected to login; non-admin roles cannot create/update/delete categories or units (403).
  - Admin can create, update, and delete an unused category/unit.
  - Deleting a category/unit currently referenced by an `inventory_items` row is rejected with a validation error.
  - `InventoryCategory::options()` / `InventoryUnit::options()` reflect DB state and are used by `StoreInventoryItemRequest` validation (a newly created category is immediately accepted; a deactivated one is rejected).
- `tests/Feature/CashDisbursementTest.php`:
  - Guest redirected to login.
  - `admin`, `secretary`, `treasurer` can record a disbursement; validation rejects missing/invalid `amount`, `purpose`, `disbursed_to`.
  - Valid submission creates exactly one `cash_disbursements` row with `created_by` set.
  - Index filters by search/purpose/date range.
  - Dashboard `summary.disbursements.total_disbursed` reflects the sum of recorded disbursements (extend or add alongside existing dashboard test coverage).

## Implementation Order

1. `config/disbursements.php`.
2. A6 migration (`inventory_categories`, `inventory_units` + seed) → models → `options()` caching → policies.
3. A6 service, controllers, Form Requests, routes.
4. A6 call-site updates (Form Requests, `InventoryItem::scopeSearch`, Blade).
5. A6 Blade UI (categories page, components, entry button on inventory index).
6. S6 migration (`cash_disbursements`) → model → policy → service.
7. S6 controller, Form Requests, routes.
8. S6 Blade UI (index/show, components, sidebar entry).
9. Dashboard hook (`DashboardMetrics`, `dashboard.blade.php`).
10. Feature tests for both modules + dashboard assertion.
11. Run full test suite, `route:list`, Blade compilation, `npm run build` — confirm no regression to existing routes/views.

## Approval Notes (confirmed)

- Balance-ceiling validation on disbursement amount: **skipped**, documented gap pending Julius's Financial Ledger.
- Disbursement status/approval workflow: **not implemented** — S6 records final entries only.
- Roles authorized to record disbursements: **admin, secretary, treasurer**.
- Cash Disbursement UI placement: **new sidebar item + own page**, not a tab inside Donations Portal.
- Category/unit storage: **two separate reference tables**, string `key` kept as the FK-equivalent on `inventory_items` (no column type change).
