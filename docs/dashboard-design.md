# Dashboard Module Design

> Historical design specification: the current dashboard has no campaign-based funding-distribution panel. Campaign records and contribution links have been removed.

## Purpose and Scope

This document describes the implementation design for a responsive authenticated dashboard for the Barangay Donation Platform.

The current project is a Laravel 11 application using Blade templates, Tailwind CSS, Vite, MySQL, the default `web` session guard, role-based middleware, and existing domain models for donors, contributions, inventory items, disbursements, and bank reconciliation. The dashboard reuses this architecture and does not hardcode display data.

The dashboard described here is implemented through `DashboardController`, `DashboardMetrics`, and `resources/views/dashboard.blade.php`.

## Design Principles

- Use the existing authenticated route group protected by `auth` and `role:admin,staff`.
- Keep the dashboard server-rendered with Blade unless a future requirement explicitly needs client-side polling or charts.
- Use reusable Blade layout and card components instead of repeated markup.
- Keep controllers thin by moving dashboard aggregation into a query/service class if the controller grows.
- Query only the data required for each dashboard section.
- Use named routes instead of literal URLs.
- Do not cache user-specific, session-specific, or sensitive information.
- Do not introduce new tables unless existing tables cannot represent the required workflow safely.

## Responsive Layout

### Shared App Shell

Create a shared authenticated layout for operational pages:

- Header:
  - Platform name.
  - Current page title.
  - Authenticated user name and role.
  - Logout form using `POST /logout`.
  - Mobile sidebar toggle.
- Sidebar:
  - Dashboard.
  - Donors / Donations.
  - Role Workflows.
  - Bank Reconciliation.
  - Future user management link for admin only.
- Main content:
  - Constrained content width on large screens.
  - Full-width stacked content on mobile.
  - Grid-based dashboard sections.

### Breakpoints

- Mobile: sidebar hidden behind a toggle; cards stack in a single column.
- Tablet: summary cards use a two-column grid.
- Desktop: summary cards use a four-column grid; dashboard cards use a balanced two-column layout.

## Top Summary Cards

### Total Cash Ledger

- Purpose:
  - Show the current total verified cash amount managed by the platform.
- Data source:
  - `cash_transactions.amount`
  - `cash_transactions.status`
  - Optionally `bank_reconciliations.closing_balance` for reconciled balance if the product owner wants bank-ledger total instead of transaction-ledger total.
- Required backend logic:
  - Sum cash transaction amounts where status is verified or approved.
  - Status values must come from constants/config, not repeated strings.
  - Format as currency in the view.
- Required database tables:
  - `cash_transactions`
  - Optional: `bank_reconciliations`
- Loading state:
  - For server-rendered pages, show skeleton card only if the dashboard later uses async partial loading.
- Empty state:
  - Display zero amount with helper text indicating no verified cash has been recorded.
- Error state:
  - Show a compact error card and log the exception server-side without exposing query details.

### Pending Cash Verification

- Purpose:
  - Surface cash transactions awaiting review by staff/admin users.
- Data source:
  - `cash_transactions.status`
- Required backend logic:
  - Count transactions with pending status.
  - Link to the role workflow or future cash verification screen.
- Required database tables:
  - `cash_transactions`
- Loading state:
  - Same as summary card loading behavior.
- Empty state:
  - Show zero pending items and a neutral "All caught up" message.
- Error state:
  - Show unavailable state while preserving the rest of the dashboard.

### In-Kind Batches

- Purpose:
  - Show the total count of in-kind inventory entries currently tracked.
- Data source:
  - `inventory_items`
- Required backend logic:
  - Count inventory item records.
  - If batch-level grouping is later required, add a normalized batch table instead of overloading item names.
- Required database tables:
  - `inventory_items`
- Loading state:
  - Skeleton count card for async loading only.
- Empty state:
  - Show zero and link staff users to record inventory.
- Error state:
  - Show unavailable state and log the failure.

### Registered Donors

- Purpose:
  - Show total donors available for donation records.
- Data source:
  - `donors`
- Required backend logic:
  - Count donor records.
  - Do not load full donor rows for this summary.
- Required database tables:
  - `donors`
- Loading state:
  - Skeleton count card for async loading only.
- Empty state:
  - Show zero and link to donor creation workflow.
- Error state:
  - Show unavailable state and log the failure.

## Dashboard Cards

### Staff Action Items

- Purpose:
  - Give staffs a focused queue for cash and reconciliation work.
- Data source:
  - pending cash `contributions`
  - `bank_ledger_entries` and `bank_withdrawal_disbursement_matches`
- Implemented backend logic:
  - Count pending cash contributions.
  - Count bank ledger entries needing review.
  - Count unreconciled bank withdrawals.
  - Count unreconciled withdrawals with discrepancy amounts.
  - Sum absolute unresolved discrepancy amounts.
  - Link outgoing summaries to `bank-reconciliation.index` with `tab=outgoing`.
- Required database tables:
  - `contributions`
  - `bank_ledger_entries`
  - `bank_withdrawal_disbursement_matches`
- Loading state:
  - Card-level skeleton if async partials are introduced.
- Empty state:
  - "No staff action items right now."
- Error state:
  - Show a non-blocking error message and keep other dashboard cards visible.

### Campaign Contributions / Funding Distribution

- Purpose:
  - Summarize where cash donations are being allocated by purpose or campaign-like category.
- Data source:
  - Current: `cash_transactions.purpose`, `cash_transactions.amount`
  - Future: `campaigns` table if campaigns become first-class records.
- Required backend logic:
  - Group verified cash transactions by purpose.
  - Sum amount per purpose.
  - Limit to top categories and aggregate the rest as "Other" if needed.
- Required database tables:
  - `cash_transactions`
  - Optional future: `campaigns`
- Loading state:
  - Chart/list placeholder if rendered asynchronously.
- Empty state:
  - Show "No funding distribution available yet."
- Error state:
  - Show unavailable state and log the exception.

### Recent Cash Donations Awaiting Audit

- Purpose:
  - List recent cash entries that still need verification or audit.
- Data source:
  - `cash_transactions`
- Required backend logic:
  - Fetch latest pending cash transactions ordered by `received_at` then `created_at`.
  - Select only required columns.
  - Apply a small configurable limit.
- Required database tables:
  - `cash_transactions`
- Loading state:
  - Table/list skeleton for async loading only.
- Empty state:
  - "No cash donations awaiting audit."
- Error state:
  - Show compact error row.

### System Log Activity

- Purpose:
  - Show recent operational activity such as created donations, inventory updates, cash entries, reconciliations, and authentication events if auditing is enabled.
- Data source:
  - Current minimum: derive limited activity from latest records in existing tables.
  - Recommended future: `activity_logs` table for a reliable audit stream.
- Required backend logic:
  - Before an audit table exists, compose recent activity from latest timestamps in existing domain tables.
  - Do not infer sensitive auth events from sessions.
  - Once implemented, write activity records through model events or explicit service calls.
- Required database tables:
  - Current: `donations`, `inventory_items`, `cash_transactions`, `bank_reconciliations`
  - Recommended future: `activity_logs`
- Loading state:
  - Activity list skeleton for async loading only.
- Empty state:
  - "No recent system activity."
- Error state:
  - Show unavailable activity state without blocking the page.

### Staff In-Kind Control Panel

- Purpose:
  - Give staff users quick visibility into inventory intake and pending item handling.
- Data source:
  - `inventory_items`
- Required backend logic:
  - Count inventory items by status.
  - Fetch latest inventory items.
  - Link to the existing role workflow page for creation and updates.
- Required database tables:
  - `inventory_items`
- Loading state:
  - Status tiles skeleton if async loading only.
- Empty state:
  - "No in-kind donations recorded yet."
- Error state:
  - Show unavailable state and log the exception.

### Recent Donations Stream

- Purpose:
  - Show recent donation activity across donors and donation records.
- Data source:
  - `donations`
  - `donors`
- Required backend logic:
  - Fetch latest donations with eager-loaded donor relationship.
  - Select only fields needed for the stream.
  - Limit result count.
- Required database tables:
  - `donations`
  - `donors`
- Loading state:
  - Stream skeleton if async loading only.
- Empty state:
  - "No donations recorded yet."
- Error state:
  - Show unavailable state and keep page layout stable.

## Recommended Database Migrations

No migration is required for a first dashboard implementation because all requested cards can be derived from existing tables.

Recommended future migrations only if product requirements expand:

### Activity Logs

Create `activity_logs` when a reliable system activity stream is required.

Fields:

- `id`
- `user_id` nullable foreign key to `users`
- `subject_type` nullable morph type
- `subject_id` nullable morph id
- `event` indexed string
- `description` nullable text
- `metadata` nullable JSON
- `created_at`

Indexes:

- `created_at`
- `event`
- `user_id`
- Morph index on `subject_type`, `subject_id`

### Campaigns

Create `campaigns` only when campaign/funding distribution needs managed records rather than grouping by `cash_transactions.purpose`.

Fields:

- `id`
- `name`
- `slug` unique
- `description` nullable text
- `status` indexed string
- timestamps

Then add nullable `campaign_id` to `cash_transactions`.

## Models and Relationships

Existing models to reuse:

- `Donor hasMany Donation`
- `Donation belongsTo Donor`
- `InventoryItem`
- `CashTransaction`
- `BankReconciliation`
- `User belongsTo Role`
- `Role hasMany User`

Recommended future models only if migrations are approved:

- `ActivityLog belongsTo User`
- `ActivityLog morphTo subject`
- `Campaign hasMany CashTransaction`
- `CashTransaction belongsTo Campaign`

Model recommendations:

- Add query scopes for common statuses, for example `pending()`, `verified()`, or `received()`.
- Keep status names in config, constants, or enum-like classes.
- Avoid placing dashboard aggregation logic directly in model accessors.

## Routes

Recommended route structure:

```php
Route::middleware(['auth', 'role:admin,staff'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
});
```

Existing domain routes should remain available:

- `GET /donors`
- `POST /donors`
- `POST /donations`
- `GET /role-workflows`
- `POST /role-workflows/inventory`
- `POST /role-workflows/cash`
- `GET /bank-reconciliation`
- `POST /bank-reconciliation`

Avoid adding API routes unless the dashboard later uses asynchronous widgets.

## Controllers

Recommended controller:

- `App\Http\Controllers\DashboardController`
  - Single-action controller using `__invoke()`.
  - Calls a dashboard query/service class.
  - Returns `resources/views/dashboard.blade.php`.

Recommended service/query class if aggregation becomes non-trivial:

- `App\Support\Dashboard\DashboardMetrics`
  - Builds summary cards.
  - Builds each dashboard card dataset.
  - Keeps controller thin and testable.

The current `DonationController@index` currently serves `/` and `/donors`. During implementation, split dashboard rendering from donor listing so dashboard-specific queries do not load full donor and donation lists unnecessarily.

## Reusable Blade Components

Recommended components:

- `resources/views/layouts/app.blade.php`
  - Authenticated shell with header, sidebar, and main content.
- `resources/views/components/app/header.blade.php`
- `resources/views/components/app/sidebar.blade.php`
- `resources/views/components/dashboard/summary-card.blade.php`
- `resources/views/components/dashboard/panel.blade.php`
- `resources/views/components/dashboard/stat-list.blade.php`
- `resources/views/components/dashboard/activity-list.blade.php`
- `resources/views/components/dashboard/empty-state.blade.php`
- `resources/views/components/dashboard/error-state.blade.php`
- Reuse existing `components/alert.blade.php` and button/form components where appropriate.

Component requirements:

- Accept data as props; never query inside Blade components.
- Use accessible headings and labels.
- Keep card dimensions stable across empty and populated states.
- Use responsive Tailwind grids and avoid duplicated card markup.

## Backend Query Strategy

The dashboard should prepare a single view model array with these keys:

- `summary.total_cash_ledger`
- `summary.pending_cash_verification`
- `summary.in_kind_batches`
- `summary.registered_donors`
- `staff_finance_action_items`
- `funding_distribution`
- `recent_cash_awaiting_audit`
- `system_log_activity`
- `staff_in_kind_control`
- `recent_donations`

Query guidelines:

- Use aggregate queries for counts and sums.
- Use `with('donor')` for recent donations to avoid N+1 queries.
- Use `select()` to avoid loading unused columns.
- Use small configurable limits for recent lists.
- Do not load all records into memory for dashboard summaries.

## Caching Recommendations

- Do not cache the full dashboard response because it is authenticated and user-specific.
- Safe to cache global aggregate metrics briefly only if they do not include sensitive user-specific data.
- Prefer no cache for the first implementation to keep behavior simple and correct.
- If caching is later introduced:
  - Use short TTLs.
  - Use clear cache keys by widget and role.
  - Invalidate on donation, donor, inventory, cash transaction, and reconciliation writes.
  - Never cache sessions, passwords, tokens, remember tokens, or personal authentication state.

## Performance Considerations

- Add indexes only when query volume or explain plans show a need.
- Likely useful future indexes:
  - `cash_transactions(status, received_at)`
  - `inventory_items(status, received_at)`
  - `donations(received_at)`
  - `bank_reconciliations(date)`
- Use database aggregates rather than collection sums.
- Limit recent streams to a small number such as 5 or 10 from config.
- Avoid chart libraries for the first version unless the funding distribution requires a visual chart; a Blade list is cheaper and sufficient.
- Keep dashboard queries independent enough that one failed optional card can degrade gracefully.

## Authorization

- Dashboard route must require authenticated users.
- Admin and Staff may view the dashboard.
- Card-level visibility can be role-aware:
  - Staff-focused action cards should be visible to staff and admin.
  - Staff-focused in-kind cards should be visible to staff and admin.
  - Shared summary and recent donation cards can be visible to all approved roles.
- Do not duplicate role strings across views; use model helpers, policies, gates, or config-backed constants.

## Error Handling

- Log backend query exceptions with context, not sensitive payloads.
- Show card-level error states where possible.
- If the dashboard service cannot load any data, show a page-level alert and stable empty shell.
- Do not expose SQL errors, stack traces, or internal exception messages in the UI.

## Testing Recommendations

- Feature test that guests are redirected to login.
- Feature test that approved roles can access the dashboard.
- Feature test that unauthorized roles receive `403`.
- Feature test summary metrics with known factories.
- Feature test empty dashboard state.
- Feature test that recent donations eager-load donors without N+1 regressions if query-count testing is added.
- View test or snapshot-style assertion for shared layout sections if the project adopts that pattern.

## Implementation Plan After Approval

1. Create authenticated app layout with shared header and sidebar.
2. Create reusable dashboard Blade components.
3. Create `DashboardController`.
4. Create dashboard query/service class if needed.
5. Move `/` from `DonationController@index` to `DashboardController`.
6. Keep `/donors` on `DonationController@index`.
7. Update dashboard view to use only prepared data.
8. Add focused feature tests.
9. Run migrations, tests, route list, Blade compilation, and Vite build.

## Approval Notes

Before implementation, confirm:

- Whether `Total Cash Ledger` should mean verified cash transaction total or latest reconciled bank closing balance.
- Which cash statuses count as pending and verified.
- Whether `System Log Activity` should initially be derived from existing records or wait for a dedicated `activity_logs` table.
- Whether `Campaign Contributions / Funding Distribution` should group by current `purpose` values or wait for a formal campaigns table.
