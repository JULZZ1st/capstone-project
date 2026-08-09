# Donation Portal: Intake-to-Verification Flow

**Module:** Donation Portal (Gina)
**Related Requirements:** S5 (Record Cash Receipts), A5/S4 (Donor dependency, owned by Neziel)
**Source of truth:** `App\Http\Controllers\DonationPortalController`, `App\Services\Contributions\LogContribution`, `config/donations.php`

## Overview

The Donation Portal logs every gift as one `contributions` row, branching into a `cash` or `in_kind` shape at intake. Both branches share the same controller, request, and service — only the fields (and, for in-kind, the child `contribution_items` rows) differ.

## Flow Diagram

```mermaid
flowchart TD
    Start([Log New Contribution<br/>modal opened]) --> Select{donation_type}

    Select -- cash --> C1[Fields: donor_id, campaign_id,<br/>amount, payment_method,<br/>reference_number, notes]
    Select -- in_kind --> K1[Fields: donor_id, campaign_id,<br/>items[] rows, notes]

    C1 --> Submit[POST /donations<br/>StoreContributionRequest]
    K1 --> Submit

    Submit --> Validate{Validation}
    Validate -- fails --> Reopen[Redirect back,<br/>modal reopens with errors,<br/>safe input preserved]
    Validate -- passes --> Log[LogContribution::handle<br/>DB transaction]

    Log --> Row[contributions row created<br/>created_by = current user]
    Row --> StatusCash{type = cash?}
    StatusCash -- yes --> SCash["status = config('donations.default_status.cash')<br/>→ pending"]
    StatusCash -- no --> SKind["status = config('donations.default_status.in_kind')<br/>→ received"]

    SKind --> Items[contribution_items rows created:<br/>name, category, quantity, unit,<br/>condition, estimated_value]

    SCash --> Redirect[Redirect to donations.index<br/>flash: Contribution logged successfully]
    Items --> Redirect

    Redirect --> List[Contribution appears in list,<br/>highlighted via session flash]

    List --> VerifyCheck{type = cash<br/>and status = pending?}
    VerifyCheck -- yes --> VerifyAction["Verify action<br/>PATCH /donations/{contribution}/verify"]
    VerifyCheck -- no --> Done1[In-kind contributions do not<br/>go through cash verification]

    VerifyAction --> VerifyUpdate[status → verified<br/>verified_by = current user<br/>verified_at = now]
    VerifyUpdate --> Done2[Verified contribution flows into<br/>Julius's Bank Reconciliation and<br/>Neziel's Financial Ledgers report]
```

## Branch Details

### Cash / Electronic Transfer (`type = cash`)
- Required: `donor_id`, `amount`, `payment_method`; `reference_number` required for electronic methods (`config('donations.electronic_payment_methods')`: bank transfer, GCash, Maya, check, money order).
- Default status: `pending` (`config('donations.default_status.cash')`).
- No child rows — `amount`, `payment_method`, `reference_number` live directly on `contributions`.

### In-Kind Goods (`type = in_kind`)
- Required: `donor_id`, at least one row in `items[]` (`name`, `category`, `quantity`, `unit`, `condition`; `estimated_value` optional).
- Default status: `received` (`config('donations.default_status.in_kind')`).
- Each item row becomes one `contribution_items` row, created in the same transaction as the parent `contributions` row — an in-kind contribution can never exist with a missing or partial item list.
- In-kind contributions are **not** part of the verify flow (verification is cash-specific — treasurer/admin sign-off on money received).

## Verification (Cash only)

- Route: `PATCH /donations/{contribution}/verify` (`donations.verify`), gated by `ContributionPolicy::verify`.
- Sets `status = verified`, `verified_by`, `verified_at`.
- This is the point at which a contribution becomes visible to downstream financial workflows — Julius's Bank Reconciliation matches against contributions, and Neziel's Financial Ledgers report reads verified cash totals.

## Status Reference (`config/donations.php`)

| Status | Meaning |
|---|---|
| `pending` | Cash logged, awaiting treasurer/admin verification |
| `verified` | Cash confirmed |
| `received` | In-kind goods accepted (default, no separate approval step) |
| `distributed` | In-kind goods have left inventory via relief operations |
| `cancelled` | Voided record, retained for audit |

## Where Donor Data Comes From

`donor_id` is a hard dependency on Neziel's Donor Management module — a contribution cannot be created without an existing, active donor record (`Donor::active()`). The intake modal only lists active donors.
