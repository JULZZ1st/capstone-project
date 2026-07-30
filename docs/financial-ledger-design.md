# Financial Ledger Design

## Goal

Provide a treasurer-facing ledger workflow for recording and reviewing cash transaction entries used by financial reporting and reconciliation workflows.

## Architecture

- Route: `GET /role-workflows`
- Cash submit route: `POST /role-workflows/cash`
- Controller: `App\Http\Controllers\InventoryCashController`
- View: `resources/views/role-workflows.blade.php`
- Model: `App\Models\CashTransaction`
- Reusable layout: `resources/views/layouts/app.blade.php`
- Reusable components:
  - `x-dashboard.panel`
  - `x-dashboard.empty-state`
  - `x-button`

## Database Design

The module depends on `cash_transactions`, which stores:

- donor name
- amount
- purpose
- received date
- status
- notes
- timestamps

The table is used as a legacy/simple cash ledger source and is also summarized on the dashboard.

## Data Flow

1. Authorized user opens `/role-workflows`.
2. Controller queries latest cash transactions and inventory items.
3. View renders the Financial Ledger panel beside the inventory workflow.
4. User submits a cash ledger entry.
5. Controller validates the request and creates a `CashTransaction`.
6. User is redirected back with a success message.

## Permission Model

The route is inside the authenticated role middleware for:

- `admin`
- `secretary`
- `treasurer`

Future hardening should move the cash store logic into a Form Request and policy so only `admin` and `treasurer` can create cash transactions.

## UI Components

- Treasurer cash transaction form
- Cash transactions list
- Empty state
- Shared dashboard panel styling

## Edge Cases

- Empty ledger list.
- Invalid or negative amount.
- Missing donor name or purpose.
- Optional date, status, and notes fields.

## Testing Strategy

Feature coverage should verify:

- Authorized users can view the page.
- Cash entries can be created with valid data.
- Invalid amounts are rejected.
- Guests are redirected to login.

## Files

- `app/Http/Controllers/InventoryCashController.php`
- `app/Models/CashTransaction.php`
- `database/migrations/2026_06_29_000005_create_cash_transactions_table.php`
- `resources/views/role-workflows.blade.php`
- `resources/views/components/dashboard/panel.blade.php`
- `resources/views/components/dashboard/empty-state.blade.php`
