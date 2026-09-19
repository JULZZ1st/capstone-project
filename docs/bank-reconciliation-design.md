# Bank Reconciliation Design

## Goal

Provide treasurer and administrator staff with a workspace for both incoming and outgoing bank reconciliation. Incoming deposits are matched to verified electronic contributions; outgoing withdrawals are matched to approved cash disbursements. Finalized outgoing reconciliations are read-only through the normal workflow.

## Architecture

- Routes: `GET /bank-reconciliation`, `POST /bank-reconciliation/reconcile`
- Outgoing routes: `POST /bank-reconciliation/withdrawals`, `POST /bank-reconciliation/withdrawals/{withdrawal}/matches`, `POST /bank-reconciliation/withdrawals/{withdrawal}/reconcile`, and `GET /bank-reconciliation/withdrawals/{withdrawal}`
- Controller: `App\Http\Controllers\BankReconciliationController`
- Form Request: `App\Http\Requests\BankReconciliation\ReconcileLedgerEntryRequest`
- Policy: `App\Policies\BankLedgerEntryPolicy`
- Services:
  - `App\Services\BankReconciliation\LedgerMatchFinder`
  - `App\Services\BankReconciliation\ReconcileLedgerEntry`
- Outgoing matching uses `CashDisbursement` eligibility rules and the `bank_withdrawal_disbursement_matches` pivot table.
- View: `resources/views/bank-reconciliation.blade.php`
- Components: `resources/views/components/bank-reconciliation/*`

## Database Design

The module depends on:

- `bank_ledger_entries`
  - imported or seeded bank statement rows
  - stores amount, transaction date, transaction type, status, reference number, and incoming/outgoing reconciliation metadata
  - outgoing discrepancy fields: `discrepancy_amount`, `discrepancy_reason`, `reconciled_by`, and `reconciled_at`
- `contributions`
  - cash donations that may be reconciled to bank ledger rows
- `cash_disbursements`
  - approved, active disbursements are eligible for outgoing matching
- `bank_withdrawal_disbursement_matches`
  - links one withdrawal to one or more disbursements and prevents a disbursement from being linked to multiple withdrawals
- `users`
  - records who performed the reconciliation

The schema uses foreign keys and indexes so each bank ledger row can be reconciled to a valid contribution while preventing duplicate matches.

## Data Flow

1. An authorized user opens the workspace and chooses **Incoming Funds** or **Outgoing Funds**.
2. Incoming reconciliation loads unmatched bank deposits and eligible electronic contributions. `LedgerMatchFinder` provides suggestions; `ReconcileLedgerEntryRequest` and `ReconcileLedgerEntry` enforce the incoming match and duplicate protections.
3. A secretary or treasurer submits a fund disbursement request. It starts as `pending`; an admin approves or rejects it, and the requester cannot approve their own request.
4. A treasurer records or imports a withdrawal, then selects approved, active, non-voided disbursements. One withdrawal may have multiple matches.
5. The outgoing screen calculates `bank withdrawal amount - selected disbursements total`.
6. A non-zero difference requires an explanation and is saved as unresolved discrepancy data. The withdrawal remains `unreconciled` and cannot be finalized.
7. When the difference is zero, **Confirm Reconciliation** saves the matches, sets the withdrawal to `reconciled`, and records `reconciled_by` and `reconciled_at`.
8. Reconciled withdrawals and their matched disbursements cannot be edited or reused through normal matching actions.
9. Outgoing history lists finalized withdrawals; **View Details** shows the withdrawal, all matched disbursements, amounts, status, reconciler, and timestamp.

## Permission Model

- `admin`, `secretary`, and `treasurer` can view the workspace.
- `admin` and `treasurer` can import or record withdrawals and manage outgoing matches/finalization.
- `admin` approves or rejects disbursement requests and performs controlled void actions.
- `secretary` can submit disbursement requests but cannot approve, reject, or reconcile.
- `treasurer` verifies incoming receipts, records/imports withdrawals, matches approved disbursements, resolves discrepancies, and finalizes balanced withdrawals.
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
- Withdrawal difference is non-zero or explanation is missing.
- Disbursement is pending, rejected, voided, or already matched to another withdrawal.
- Finalized withdrawal is requested through the normal edit/match route.
- No ledger entries need review.
- No electronic receipts are available.
- Secretary attempts reconciliation and receives validation/authorization denial.
- Balanced outgoing withdrawal can be finalized and records reconciler metadata.
- Outgoing history and read-only details show finalized match data.

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
- `database/migrations/2026_09_19_000001_add_discrepancy_fields_to_bank_ledger_entries_table.php`
- `database/migrations/2026_09_19_000002_add_outgoing_reconciliation_fields_to_bank_ledger_entries_table.php`
- `resources/views/bank-reconciliation.blade.php`
- `resources/views/bank-reconciliation/withdrawal-details.blade.php`
- `resources/views/components/bank-reconciliation/*`
