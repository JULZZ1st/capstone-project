# Fund Disbursement and Bank Reconciliation Enhancement

## Recommended Actual System Flow

### 1. Secretary/Treasurer Creates a Fund Disbursement Request

On the current **Fund Disbursements** page, instead of having only `Amount + Purpose`, expand the request form with the following fields:

| Field | Example |
|---|---|
| Amount | ₱10,000 |
| Purpose | Purchase of relief goods |
| Recipient/Payee | ABC Grocery |
| Requested Date | Sept 12, 2026 |
| Notes | Food packs for distribution |
| Supporting Reference | PR/Receipt No. if available |

After submission, the request status should automatically become:

**Pending Approval**

The current Fund Disbursements page can remain. The existing form or modal only needs to be expanded rather than redesigned from scratch.

---

### 2. Admin Approves the Request

The Request History table can display:

| Date | Purpose | Amount | Requested By | Status | Action |
|---|---|---:|---|---|---|
| Sept 12 | Relief goods | ₱10,000 | Secretary | Pending | View |

When the Admin opens the request, show:

**Approve** | **Reject**

After approval:

> **Approved — ₱10,000**

The requester should not be allowed to approve their own disbursement request.

---

## 3. Treasurer Records or Imports the Actual Bank Withdrawal

After the disbursement is approved, the Treasurer proceeds to the existing **Bank Reconciliation** page.

The current page has:

**BANK TRANSACTIONS TO REVIEW**

↔

**DONATION RECEIPTS TO MATCH**

For the improved workflow, add two tabs:

### Incoming Funds

Current functionality:

**Bank Deposits ↔ Cash Donations**

### Outgoing Funds

New functionality:

**Bank Withdrawals ↔ Approved Disbursements**

The existing incoming reconciliation flow should remain.

The workspace can be structured as:

**Bank Reconciliation Workspace**

`Incoming Funds` | **`Outgoing Funds`**

Under **Outgoing Funds**:

**BANK WITHDRAWALS TO REVIEW** | **APPROVED DISBURSEMENTS**

Example:

### Bank Withdrawal

> Sept 13, 2026  
> Ref: WD-00981  
> **₱10,000**  
> Status: Unreconciled

### Approved Disbursements

- ☑ Relief Goods — ₱6,000
- ☑ Medicine — ₱2,500
- ☑ Transportation — ₱1,500

The system automatically calculates:

> **Bank Withdrawal:** ₱10,000  
> **Selected Approved Disbursements:** ₱10,000  
> **Difference:** ₱0  
> ✅ **Ready to Reconcile**

Button:

**Confirm Reconciliation**

---

## 4. If the Amounts Do Not Match, Do Not Fully Reconcile Yet

Example:

**Bank Withdrawal:** ₱10,000  
**Recorded/Approved Disbursements:** ₱9,500  
**Difference:** **₱500**

Instead of allowing immediate reconciliation, show:

> ⚠️ **₱500 discrepancy detected**

The system should ask:

**What happened to the remaining ₱500?**

Possible statuses:

- Pending Review
- Pending Liquidation
- Discrepancy
- Reconciled

Require an explanation, for example:

> “₱500 remains as cash-on-hand pending liquidation.”

The explanation becomes part of the reconciliation history and audit trail.

---

## 5. Prevent Duplicate Reconciliation

This is one of the main controls that gives the system an advantage over a normal spreadsheet.

Example:

The disbursement `DIS-2026-0015` is already matched to withdrawal `BANK-0034`.

If someone tries to select it again, show:

> 🔒 **Already reconciled with BANK-0034**

The disbursement should no longer be selectable.

The same rule applies to a bank transaction:

> This bank withdrawal has already been reconciled.

The system should enforce these rules automatically.

---

## 6. Lock the Reconciled Record

After successful reconciliation, show:

> **Status: Reconciled**  
> Reconciled by: Treasurer  
> Reconciled at: Sept 13, 2026 2:42 PM  
> Bank amount: ₱10,000  
> Matched disbursements: 3  
> Difference: ₱0

A normal user should not be able to directly edit the reconciled linkage.

If a correction is required, it should use a controlled correction or reversal process instead of direct editing.

The audit trail should record sensitive actions such as:

- disbursement approval
- disbursement rejection
- reconciliation
- cancellation or correction
- account or access changes

---

## 7. Reconciliation History

The existing **RECONCILIATION HISTORY** section can be enhanced with:

| Date | Bank Ref | Type | Bank Amount | Matched Amount | Difference | Status | Reconciled By |
|---|---|---|---:|---:|---:|---|---|
| Sep 13 | WD-00981 | Withdrawal | ₱10,000 | ₱10,000 | ₱0 | Reconciled | Treasurer |
| Sep 14 | WD-00982 | Withdrawal | ₱8,000 | ₱7,500 | ₱500 | Pending Liquidation | — |

Clicking a row should open **View Details**, showing exactly which disbursements were matched to the bank transaction.

---

## 8. Reflect Reconciliation Work on the Dashboard

The **Treasurer Action Items** section can show operational counts such as:

- 3 Bank Withdrawals Awaiting Reconciliation
- 1 Discrepancy Requiring Explanation
- ₱2,500 Pending Liquidation

Each item can link directly to the Bank Reconciliation page.

---

# Important: Do Not Match a Bank Withdrawal Directly to a Donation

The current reconciliation flow is:

**Bank Transaction → Contribution**

This makes sense for **incoming bank deposits or donations**.

However, for the manual process described by the adviser:

**Bank Withdrawal → Cash Book Expenses**

The correct system equivalent should be:

**Bank Withdrawal → Approved Cash Disbursement(s)**

Therefore, the Bank Reconciliation module should support both:

> **Deposit → Verified Contribution**

> **Withdrawal → Approved Disbursement**

This keeps incoming and outgoing reconciliation separate and clear.

---

# Suggested Database Relationship

## Bank Ledger Entry

Suggested fields:

- transaction date
- reference
- type: `deposit` / `withdrawal`
- amount
- reconciliation status

## Cash Disbursement

Suggested fields:

- amount
- purpose
- approval status
- approved by
- approved at

## Reconciliation Match

Use a separate linking record with fields such as:

- bank ledger entry
- cash disbursement
- amount matched
- matched by
- matched at

A separate matching table is important because **one bank withdrawal can pay for several disbursements**.

Example:

`WD001 = ₱10,000`

Linked to:

- `DIS001 = ₱6,000`
- `DIS002 = ₱2,500`
- `DIS003 = ₱1,500`

A single `matched_disbursement_id` field would not be enough because one withdrawal may be linked to multiple disbursements.

---

# Role Behavior

## Secretary

- Records operational transactions, donations, and inventory
- Can submit a disbursement request if allowed by policy

## Admin

- Approves or rejects disbursement requests
- Handles controlled cancellations or corrections

## Treasurer

- Imports or records bank transactions
- Selects approved disbursements
- Checks the difference between bank withdrawals and system records
- Confirms reconciliation
- Reviews the financial ledger and reports

---

# Recommended Defense Demo Scenario

### 1. Secretary

> Request ₱10,000 — Relief Operations

### 2. Admin

> Approve ₱10,000

### 3. Treasurer

> Import or record bank withdrawal ₱10,000

### 4. Bank Reconciliation

Select:

- ₱6,000 Food
- ₱2,500 Medicine
- ₱1,500 Transport

The system calculates:

> Bank: ₱10,000  
> Cash records: ₱10,000  
> Difference: ₱0

### 5. Confirm

> ✅ Reconciled by Treasurer, date and time recorded

Then demonstrate a second case:

> Bank withdrawal: ₱10,000  
> System records: ₱9,500  
> **₱500 discrepancy**

The system should refuse to mark it as fully reconciled until the discrepancy is explained or resolved.

---

# Main Value of the System

The system should not only store numbers. It should control the full process:

**Disbursement Request → Approval → Bank Comparison → Discrepancy Handling → Reconciliation → Audit Trail**

Compared with a normal spreadsheet, the system can enforce:

- approval rules
- separation of duties
- duplicate prevention
- reconciliation status
- discrepancy tracking
- required explanations
- restricted editing after reconciliation
- user and timestamp audit history
- dashboard action items
- consistent reporting

The existing UI does not need a complete redesign. The main enhancement is to extend **Fund Disbursements** and turn **Bank Reconciliation** into an **Incoming Funds | Outgoing Funds** reconciliation workspace.
