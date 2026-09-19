# Fund Disbursement and Bank Reconciliation Workflow

This document describes the implemented Fund Disbursement and Bank Reconciliation workflow. The focused coverage is in `tests/Feature/DisbursementWorkflowTest.php` and `tests/Feature/BankReconciliationTest.php`.

### 1. Secretary/Treasurer Creates a Fund Disbursement Request

The **Fund Disbursements** page accepts:

| Field | Example |
|---|---|
| Amount | ₱10,000 |
| Purpose | Purchase of relief goods |
| Recipient/Payee | ABC Grocery |
| Requested Date | Sept 12, 2026 |
| Notes | Food packs for distribution |
| Supporting Reference | PR/Receipt No. if available |

After submission, the request status becomes:

**Pending Approval**

The request remains pending until an administrator makes a decision.

---

### 2. Admin Approves the Request

The Request History table displays:

| Date | Purpose | Amount | Requested By | Status | Action |
|---|---|---:|---|---|---|
| Sept 12 | Relief goods | ₱10,000 | Secretary | Pending | View |

When the Admin opens the request, the available decisions are:

**Approve** | **Reject**

After approval, the request becomes:

> **Approved — ₱10,000**

The requester cannot approve their own disbursement request.

---

## 3. Treasurer Records or Imports the Actual Bank Withdrawal

After the disbursement is approved, the Treasurer proceeds to the existing **Bank Reconciliation** page.

The current page has:

**BANK TRANSACTIONS TO REVIEW**

↔

**DONATION RECEIPTS TO MATCH**

The implemented workspace has two tabs:

### Incoming Funds

Current functionality:

**Bank Deposits ↔ Cash Donations**

### Outgoing Funds

New functionality:

**Bank Withdrawals ↔ Approved Disbursements**

Incoming reconciliation remains deposit-to-contribution matching and is separate from outgoing reconciliation.

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

The outgoing screen shows:

> ⚠️ **₱500 discrepancy detected**

The system requires an explanation for:

**What happened to the remaining ₱500?**

The unresolved withdrawal remains:

- `unreconciled` status with a saved discrepancy amount and explanation

Require an explanation, for example:

> “₱500 remains as cash-on-hand pending liquidation.”

The explanation is saved with the withdrawal and remains visible while the discrepancy is unresolved. It does not permit final reconciliation.

---

## 5. Prevent Duplicate Reconciliation

This is one of the main controls that gives the system an advantage over a normal spreadsheet.

Example:

The disbursement `DIS-2026-0015` is already matched to withdrawal `BANK-0034`.

If someone tries to select it again, server-side validation rejects the request. The disbursement is excluded from the eligible selectable pool.

The same rule applies to a bank transaction:

> This bank withdrawal has already been reconciled.

The unique match constraint, eligibility query, policy, and request validation enforce these rules automatically.

---

## 6. Lock the Reconciled Record

After successful reconciliation, show:

> **Status: Reconciled**  
> Reconciled by: Treasurer  
> Reconciled at: Sept 13, 2026 2:42 PM  
> Bank amount: ₱10,000  
> Matched disbursements: 3  
> Difference: ₱0

A normal user cannot directly edit the reconciled linkage through the normal matching route.

No correction or reversal workflow is included in this task; the finalized record is read-only through the normal workflow.

Approval, rejection, and void actions write security audit events. Finalized outgoing reconciliation records its reconciler and timestamp in the ledger row and history/details view.


---

## 7. Reconciliation History

Outgoing **RECONCILIATION HISTORY** displays:

| Date | Bank Ref | Type | Bank Amount | Matched Amount | Difference | Status | Reconciled By |
|---|---|---|---:|---:|---:|---|---|
| Sep 13 | WD-00981 | Withdrawal | ₱10,000 | ₱10,000 | ₱0 | Reconciled | Treasurer |
| Sep 14 | WD-00982 | Withdrawal | ₱8,000 | ₱7,500 | ₱500 | Unreconciled / Discrepancy | — |

Each row has **View Details**, which shows the finalized withdrawal, every matched disbursement and amount, status, reconciler, and reconciliation timestamp.

---

## 8. Reflect Reconciliation Work on the Dashboard

The **Treasurer Action Items** section shows live operational summaries:

- 3 Bank Withdrawals Awaiting Reconciliation
- 1 Discrepancy Requiring Explanation
- ₱2,500 Pending Liquidation

Each item links directly to **Bank Reconciliation → Outgoing Funds**.

---

# Important: Do Not Match a Bank Withdrawal Directly to a Donation

The current reconciliation flow is:

**Bank Transaction → Contribution**

This makes sense for **incoming bank deposits or donations**.

However, for the manual process described by the adviser:

**Bank Withdrawal → Cash Book Expenses**

The implemented system equivalent is:

**Bank Withdrawal → Approved Cash Disbursement(s)**

The implemented Bank Reconciliation module supports both:

> **Deposit → Verified Contribution**

> **Withdrawal → Approved Disbursement**

This keeps incoming and outgoing reconciliation separate and clear.

---

# Implemented Database Relationship

## Bank Ledger Entry

Relevant fields:

- transaction date
- reference
- type: `deposit` / `withdrawal`
- amount
- reconciliation status, discrepancy amount/reason, reconciled by, and reconciled at

## Cash Disbursement

Relevant fields:

- amount
- purpose
- approval status (`pending`, `approved`, `rejected`) and void fields
- approved by
- approved at

## Reconciliation Match

The implemented linking table stores:

- bank ledger entry
- cash disbursement
- bank ledger entry and cash disbursement foreign keys
- timestamps

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
- Can submit a disbursement request; cannot approve, reject, or finalize reconciliation

## Admin

- Approves or rejects disbursement requests
- Approves or rejects requests and can void approved disbursements with a reason

## Treasurer

- Imports or records bank transactions
- Selects approved disbursements
- Checks the difference between bank withdrawals and system records
- Resolves discrepancy explanations and confirms balanced reconciliation
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

The system refuses to mark it as fully reconciled until the discrepancy is resolved to zero.

---

# Main Value of the System

The system does not only store numbers. It controls the full process:

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
