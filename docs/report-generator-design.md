# Report Generator Design

## Goal

Provide official reporting tools for municipal audit review, transparency exports, and financial ledger summaries.

## Architecture

- Route: `GET /reports`
- CSV route: `GET /reports/financial-ledgers/csv`
- Controller: `App\Http\Controllers\ReportController`
- Form Request: `App\Http\Requests\Reports\ReportIndexRequest`
- Services:
  - `App\Services\Reports\FinancialLedgerReportBuilder`
  - `App\Services\Reports\FinancialLedgerCsvExporter`
- View: `resources/views/reports/index.blade.php`
- Components: `resources/views/components/reports/*`

## Database Design

The Financial Ledger report reads from:

- `contributions`
  - cash contribution records
- `donors`
  - donor names
- `campaigns`
  - optional campaign context
- `bank_ledger_entries`
  - reconciliation status for electronic payments

No dedicated report table is needed because reports are generated from normalized operational records.

## Data Flow

1. Authorized user opens `/reports`.
2. `ReportIndexRequest` validates tab, year, and month.
3. Controller determines the active report tab.
4. For `financial-ledgers`, `FinancialLedgerReportBuilder` queries matching cash contributions.
5. Builder returns summary totals, wire source breakdown, and ledger rows.
6. Blade renders the report panels.
7. CSV download uses the same builder data and streams it through `FinancialLedgerCsvExporter`.

## Permission Model

`ReportIndexRequest` authorizes against `Contribution::class` using `viewAny`, so report access follows the existing contribution policy.

Allowed roles through current policy:

- `admin`
- `secretary`
- `treasurer`

## UI Components

- Report tab navigation
- Month filter
- Wire source bar
- Ledger row
- Dashboard summary cards
- Empty states for unavailable or empty reports

## Supported Reports

- Financial Ledgers: implemented
- In-Kind Audits: placeholder
- Philanthropy Roster: placeholder

## Edge Cases

- No cash receipts for selected month.
- Contributions without donors.
- Contributions without campaigns.
- Electronic receipts not yet reconciled.
- Invalid tab values.
- CSV download with no rows.

## Testing Strategy

Feature and service coverage should verify:

- Authorized user can view reports.
- Financial summary totals are correct.
- Wire source breakdown includes only active sources.
- Ledger rows are ordered newest first.
- CSV export streams expected headings and rows.
- Guests cannot download reports.

Current focused test files:

- `tests/Feature/ReportGeneratorTest.php`
- `tests/Feature/FinancialLedgerReportBuilderTest.php`

## Files

- `app/Http/Controllers/ReportController.php`
- `app/Http/Requests/Reports/ReportIndexRequest.php`
- `app/Services/Reports/FinancialLedgerReportBuilder.php`
- `app/Services/Reports/FinancialLedgerCsvExporter.php`
- `resources/views/reports/index.blade.php`
- `resources/views/components/reports/*`
