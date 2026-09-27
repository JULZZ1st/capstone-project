# Dashboard Widget Data-Source Reference

**Module:** Dashboard (Gina)
**Related Requirement:** A10 (Access Main Dashboard)
**Source of truth:** `App\Support\Dashboard\DashboardMetrics::build()`, rendered by `App\Http\Controllers\DashboardController`

## Overview

`DashboardController` is a single-action controller that calls `DashboardMetrics::build()` and passes the whole array to `dashboard.blade.php` as one `$dashboard` view model. Every widget below maps to one key in that array.

> **Update (2026-08-09):** `DashboardMetrics` originally queried the platform's earliest tables (`cash_transactions`, `donations`, `bank_reconciliations`). None of those tables are written to by any current feature — the Donation Portal writes to `contributions`/`contribution_items`, and Bank Reconciliation writes to `bank_ledger_entries`. The dashboard has been repointed to the live schema so its totals reflect real data; see `tests/Feature/DashboardMetricsTest.php` for regression coverage (including an explicit test that legacy-table rows no longer affect dashboard totals).

## Widget → Query Map

| View model key | Widget | Table(s) | Query logic |
|---|---|---|---|
| `summary.total_cash_ledger` | Total Cash Ledger card | `contributions` | `Contribution::where('type','cash')->where('status', config('dashboard.cash_statuses.verified'))->sum('amount')` |
| `summary.pending_cash_verification` | Pending Cash Verification card | `contributions` | `Contribution::where('type','cash')->where('status', config('dashboard.cash_statuses.pending'))->count()` |
| `summary.in_kind_batches` | In-Kind Batches card | `inventory_items` | `InventoryItem::count()`, cached 300s (`dashboard.cache.safe_summary_ttl`) under key `dashboard.summary.in_kind_batches` |
| `summary.registered_donors` | Registered Donors card | `donors` | `Donor::count()`, cached 300s under key `dashboard.summary.registered_donors` |
| `treasurer_action_items` | Treasurer Action Items panel | `contributions`, `bank_ledger_entries` | `pending_cash_count`: pending cash contributions. `latest_reconciliation`: most recent reconciled ledger row, ordered by `matched_at`. `unreconciled_ledger_count`: `BankLedgerEntry::needsReview()->count()`. `bank_withdrawals_awaiting_reconciliation`: unreconciled withdrawal count. `discrepancies_requiring_review`: unreconciled withdrawals with a saved discrepancy. `unresolved_difference_amount`: sum of absolute unresolved discrepancy amounts. Each outgoing item links to `bank-reconciliation.index?tab=outgoing`. |
| `funding_distribution` | Funding Distribution panel | `contributions`, `campaigns` | Verified cash contributions grouped by `campaign_id`, summed, ordered by total desc, capped at `config('dashboard.limits.funding_categories')` (5). Contributions with no campaign roll up under "Uncategorized" |
| `recent_cash_awaiting_audit` | Recent Cash Donations Awaiting Audit list | `contributions`, `donors` | Pending cash contributions, eager-loaded with `donor:id,full_name`, latest `received_at` then `created_at`, limited to `config('dashboard.limits.recent_items')` (5) |
| `system_log_activity` | System Log Activity feed | `contributions`, `inventory_items`, `cash_disbursements` | Latest N rows from each table are merged into one labeled activity stream (`Contribution logged`, `Inventory updated`, `Cash disbursed`), then re-sorted by `created_at` desc and truncated to the limit. This is derived on the fly, not a dedicated audit table |
| `secretary_in_kind_control.status_counts` | Secretary In-Kind Control Panel — status tiles | `inventory_items` | `InventoryItem` grouped by `status`, counted |
| `secretary_in_kind_control.recent_items` | Secretary In-Kind Control Panel — recent items | `inventory_items` | Latest items by `received_at` then `created_at`, limited |
| `recent_donations` | Recent Donations Stream | `contributions`, `donors` | Latest `contributions` rows of either type, eager-loading `donor:id,full_name` and an `items_count` (via `withCount('items')`) to avoid N+1, limited. Cash rows show the amount; in-kind rows show item count since `amount` is null for in-kind contributions |
| `disbursements.total_disbursed` | Cash Disbursements summary | `cash_disbursements` | Sum of approved, non-voided disbursement amounts. Pending, rejected, and voided requests are excluded. |
| `disbursements.recent` | Cash Disbursements recent activity | `cash_disbursements` | Latest disbursements by `disbursed_at` then `created_at`, limited |

## Shared Config

- `config('dashboard.limits.recent_items')` — default `5`, controls every "recent N" list above.
- `config('dashboard.limits.funding_categories')` — default `5`, caps the funding distribution breakdown.
- `config('dashboard.cash_statuses.pending')` / `config('dashboard.cash_statuses.verified')` — default `pending` / `verified`, matching the status strings `LogContribution` and `DonationPortalController::verify` write onto `contributions.status`.
- `config('dashboard.cache.safe_summary_ttl')` — default `300` seconds; only applied to the two count-only summary cards (`in_kind_batches`, `registered_donors`) that carry no sensitive/user-specific data. Every other widget is queried live on each request — the dashboard route itself is never cached.

## Why `bank_ledger_entries` and not `contributions` alone for reconciliation status

The Treasurer Action Items panel exists specifically to surface reconciliation work, which is tracked on `bank_ledger_entries` (`transaction_type`, `status`, `discrepancy_amount`, `discrepancy_reason`, `reconciled_by`, and `reconciled_at`) and the withdrawal/disbursement match table. This is a read-only query from `DashboardMetrics`; it does not write to or modify reconciliation state.

## Graceful Degradation

Each widget is built from an independent query inside `DashboardMetrics::build()`. There is currently no per-widget try/catch — a failure in any one query will surface as a full-page error rather than a partial dashboard. This matches the "Do not cache the full dashboard response" design intent but differs from the original design doc's per-card error-state recommendation; revisit if defense Q&A calls for graceful per-card failure.
