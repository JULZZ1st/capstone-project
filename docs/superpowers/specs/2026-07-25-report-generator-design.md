# Report Generator — Design

## Goal

Add a new **Report Generator** page (`/reports`) that gives Admin/Secretary/Treasurer
users a month-scoped audit report of cash contributions, matching the attached
reference screenshot: header + description, CSV download, report-category tabs,
summary cards, a cash-intake-by-channel breakdown, and a settled receipts ledger.

This is a new module. It does not change how Donations, Bank Reconciliation, or
Inventory work today — it reads their existing data.

## Scope

**In scope (built with real data):**
- "Financial Ledgers" tab — the only tab with visible content in the screenshot.
- Month picker (GET form, server-rendered, no JS).
- CSV export of the Financial Ledgers report for the selected month.
- Sidebar link wired up (currently a disabled `<span>`).

**Out of scope (stubbed for now):**
- "In-Kind Audits" and "Philanthropy Roster" tabs render a plain "coming soon"
  placeholder. They are real, clickable tabs (so the UI matches the screenshot),
  but no report logic is built for them in this pass. Follow-up work can reuse
  the same `ReportController`/tab pattern once their data requirements are
  scoped.

## Data model — no new tables

Everything the Financial Ledgers tab needs already exists on `Contribution` and
`BankLedgerEntry`. No migrations are added.

For a given month (contributions where `type = 'cash'`, `status != 'cancelled'`,
and `received_at` falls in the selected month — cancelled contributions are
excluded everywhere in this report since they were never real receipts):

| Screenshot element | Source |
|---|---|
| Total Reconciled Funds | Sum of contribution `amount` where a `ledgerEntry` exists with `status = 'reconciled'` |
| Total Pending Clearing | Sum of `Contribution::eligibleForReconciliation()` (existing scope: cash + electronic payment method + no ledger entry yet) |
| Total Recorded Receipts | Sum of *all* cash contributions for the month, any payment method/status |
| Cash Intake Breakdown by Wire Source | Cash contributions grouped by `payment_method`, summed, labelled via `config('donations.payment_methods')`. Fully data-driven — only methods with activity that month appear, in descending amount order. |
| Progress bar width | `amount / max(channel amounts) * 100`, capped at 100 — same relative-scaling idea as `InventoryItem::stockProgressPercentage()` |
| Settled Cash Receipts Ledger | Cash contributions for the month, newest first: donor name, campaign name + payment method, amount, and a status badge reusing `config('bank_reconciliation.receipt_statuses')` (Reconciled / Pending Ledger Clear) |

Empty month → cards show ₱0, breakdown and ledger show the existing
`x-dashboard.empty-state` component.

## Routes

Added to the existing `auth + role:admin,secretary,treasurer` group in
`routes/web.php` (same access level as Donations/Bank Reconciliation, whose data
this report reads):

```
GET /reports                          reports.index
GET /reports/financial-ledgers/csv    reports.financial-ledgers.csv
```

Query params on `reports.index`: `tab` (`financial-ledgers` default,
`in-kind-audits`, `philanthropy-roster`), `year`, `month` (default: current
month). Invalid values fail validation (422), consistent with other index
pages.

## Architecture

- **`ReportController`** (thin) — validates filters via a Form Request, calls
  the report builder service for the active tab, returns the view. CSV action
  streams via `StreamedResponse` using the same builder's ledger rows.
- **`app/Http/Requests/Reports/ReportIndexRequest.php`** — validates
  `tab`/`year`/`month`; `authorize()` reuses the existing
  `ContributionPolicy::viewAny` (no new Policy needed).
- **`app/Services/Reports/FinancialLedgerReportBuilder.php`** — builds the
  summary cards, wire-source breakdown, and ledger rows for a given
  year/month. Single responsibility, no view/HTTP concerns.
- **`app/Services/Reports/FinancialLedgerCsvExporter.php`** — takes the same
  ledger rows from the builder and streams them as CSV. No duplicated query
  logic between the page and the export.

## Blade / UI

- Reuse `x-dashboard.summary-card`, `x-dashboard.panel`, `x-dashboard.empty-state`,
  and the existing layout/sidebar/header — unchanged.
- New components under `resources/views/components/reports/` (each single
  purpose, under ~150 lines per AGENTS.md limits):
  - `tab-nav.blade.php` — the three report-category tab buttons.
  - `wire-source-bar.blade.php` — one labelled progress bar row.
  - `ledger-row.blade.php` — one settled-receipt row with status badge.
  - `month-filter.blade.php` — GET form month/year `<select>`, styled like
    `x-donations.filter-bar`.
- Sidebar: change the static "Report Generator" `<span>` entry in
  `resources/views/components/app/sidebar.blade.php` to a real link
  (`route: 'reports.index'`, `active: 'reports.*'`), following the exact
  pattern every other sidebar item already uses. No changes to the sidebar's
  active-state color scheme (that's an app-wide style decision, out of scope
  here).

## Testing

`tests/Feature/ReportGeneratorTest.php`, following the structure of
`BankReconciliationTest`/`InventoryManagementTest`:
- Access control: allowed roles can view, others get 403; guests redirect to login.
- Summary totals match seeded contributions for a given month (reconciled,
  pending clearing, total recorded).
- Wire-source breakdown groups and sums correctly, only shows methods with
  activity.
- Ledger lists the right rows with the right status badge.
- CSV endpoint returns a downloadable response with the expected rows.
