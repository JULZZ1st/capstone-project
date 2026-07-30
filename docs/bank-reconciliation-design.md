# Bank Reconciliation Design

## Goal

Provide treasurer and administrator staff with a workspace for matching bank ledger entries to logged electronic cash contributions. The module supports auditable reconciliation without changing donation records by hand.

## Architecture

- Route: `GET /bank-reconciliation`
- Submit route: `POST /bank-reconciliation/reconcile`
- Controller: `App\Http\Controllers\BankReconciliationController`
- Form Request: `App\Http\Requests\BankReconciliation\ReconcileLedgerEntryRequest`
- Policy: `App\Policies\BankLedgerEntryPolicy`
- Services:
  - `App\Services\BankReconciliation\LedgerMatchFinder`
  - `App\Services\BankReconciliation\ReconcileLedgerEntry`
- View: `resources/views/bank-reconciliation.blade.php`
- Components: `resources/views/components/bank-reconciliation/*`

## Database Design

The module depends on:

- `bank_ledger_entries`
  - imported or seeded bank statement rows
  - stores amount, transaction date, status, reference number, and matched contribution
- `contributions`
  - cash donations that may be reconciled to bank ledger rows
- `users`
  - records who performed the reconciliation

The schema uses foreign keys and indexes so each bank ledger row can be reconciled to a valid contribution while preventing duplicate matches.

## Data Flow

1. Authorized user opens the Bank Reconciliation page.
2. Controller authorizes `viewAny` on `BankLedgerEntry`.
3. Unmatched bank ledger entries are queried.
4. Eligible electronic cash contributions are queried.
5. `LedgerMatchFinder` scores candidate matches.
6. The Blade view renders ledger entries, pending receipts, and match suggestions.
7. User submits a match.
8. `ReconcileLedgerEntryRequest` validates IDs and authorization.
9. `ReconcileLedgerEntry` updates the ledger entry and contribution state.

## Permission Model

- `admin`, `secretary`, and `treasurer` can view the workspace.
- `admin` and `treasurer` can reconcile.
- Authorization is enforced through `BankLedgerEntryPolicy` and the Form Request.

## UI Components

- Audit banner
- Ledger entry card
- Receipt card
- Match modal
- Status badge

The UI follows the existing app dashboard style: white cards, slate borders, compact headings, and operational data density.

## Edge Cases

- Ledger entry already reconciled.
- Contribution already matched to another ledger row.
- No ledger entries need review.
- No electronic receipts are available.
- Secretary attempts reconciliation and receives validation/authorization denial.

## Testing Strategy

Feature coverage should verify:

- Authorized users can view the workspace.
- Match suggestions appear for compatible ledger and contribution records.
- Treasurer/admin can reconcile.
- Secretary cannot reconcile.
- Duplicate reconciliation is rejected.

Current focused test file:

- `tests/Feature/BankReconciliationTest.php`

## Files

- `app/Http/Controllers/BankReconciliationController.php`
- `app/Http/Requests/BankReconciliation/ReconcileLedgerEntryRequest.php`
- `app/Models/BankLedgerEntry.php`
- `app/Policies/BankLedgerEntryPolicy.php`
- `app/Services/BankReconciliation/*`
- `config/bank_reconciliation.php`
- `database/migrations/2026_07_25_000001_create_bank_ledger_entries_table.php`
- `resources/views/bank-reconciliation.blade.php`
- `resources/views/components/bank-reconciliation/*`
