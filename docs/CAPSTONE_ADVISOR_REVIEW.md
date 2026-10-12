# Capstone Adviser Review — Barangay Donation Management System

**Prepared:** 2026-09-10
**Method:** Written from direct inspection of the current codebase (routes, controllers, policies, models, migrations, views, tests) — **not** from trusting `PROJECT_REQUIREMENTS.md` / `TEAM_TASK_ASSIGNMENT.md` status columns at face value. Those two documents are cross-referenced throughout and several of their "Planned"/"outstanding" markings are now out of date relative to the code. Treat *this* document as the current source of truth for the adviser meeting, and update the older docs afterward.

---

## 1. Project Overview

**Project Title:** Barangay Donation Management System

**Purpose:** A web-based back-office system that lets a barangay's staff record, verify, and account for donations — both cash and in-kind — with proper role separation, an auditable paper trail, and reporting. It replaces manual/paper bookkeeping of donations, in-kind goods distribution, cash disbursements, and bank reconciliation with a single system that enforces who can do what and logs sensitive actions.

**Target Users (roles actually defined in code — `Role` model, `role_id` on `users`):**
| Role | Code constant | General scope |
|---|---|---|
| **Admin** | `Role::ADMIN` (`admin`) | Full system access: approves/rejects/voids disbursements, voids contributions, manages staff accounts, manages item categories, sole editor of System Security settings. |
| **Secretary** | `Role::SECRETARY` (`secretary`) | Day-to-day data entry: donor records, contribution logging, inventory create/update/stock adjustments, can view (not edit) System Security. |
| **Treasurer** | `Role::TREASURER` (`treasurer`) | Cash-facing operations: verifies cash contributions, imports bank statements, reconciles the ledger; blocked from inventory stock adjustments and from category management. |

**Donation Workflow (narrative):**
1. A **Donor** (individual or organization) is created or found in **Donor Management**.
2. Staff log a **Contribution** in the **Donation Portal** — either `cash` (with payment method + reference number) or `in_kind` (with one or more line items tied to inventory categories/subcategories).
3. Cash contributions go through a **verification** step (Admin/Treasurer only) before they count toward ledger totals.
4. In-kind contributions flow into **In-Kind Inventory** as stock, tracked through **Stock-In/Stock-Out** movements.
5. Electronic/bank-channel contributions are cross-checked against imported bank statements in **Bank Reconciliation**, and marked reconciled once matched.
6. Outgoing funds go through **Cash Disbursements**: a request, then a separate Admin **approval/rejection** (maker-checker — the requester cannot approve their own request), before counting as disbursed.
7. Either a contribution or a disbursement can be **voided** (not deleted) if made in error, preserving an audit trail.
8. All of the above rolls up into the **Financial Ledger**/**Reports** module (CSV and PDF), and every sensitive action (void, approve, adjust stock, staff account change, reconciliation) is written to the **Audit Log**.

---

## 2. Page / Module Review

### Dashboard
- **Purpose:** At-a-glance summary of cash ledger totals, pending-verification counts, funding distribution by campaign, recent donation activity, and treasurer action items.
- **Access:** Admin, Secretary, Treasurer (all three; content is the same, not role-filtered).
- **Main functionality:** Single invokable `DashboardController` delegating all computation to `App\Support\Dashboard\DashboardMetrics::build()`. Computes cash ledger totals live from `Contribution` (verified cash only), disbursement totals from `CashDisbursement` (approved + not voided), funding distribution grouped by campaign, and a recent-activity feed.
- **Connects to:** Reads from Contributions, Campaigns, Bank Ledger Entries, Cash Disbursements — i.e., it's a read-only rollup of every other module.
- **Status:** **Complete.** Fully implemented and tested (`tests/Feature/DashboardMetricsTest.php`, 6 tests), including a regression test that explicitly confirms legacy tables (see below) are excluded from totals.
- **Manually check before the meeting:** Load the dashboard as each of the 3 roles and confirm numbers look sane against what's in the Donation Portal/Ledger. Confirm the "recent donations" feed shows both cash and in-kind entries.
- **Remaining issues:** No per-widget error isolation — `docs/dashboard-data-sources.md` notes one failing query can break the whole page (no try/catch per widget). No caching on the money-total queries (only donor/batch counts are cached), so the page may feel slow with a large dataset — not a concern at demo scale.

### Donation Portal / Contributions
- **Purpose:** Log and verify incoming donations (the real "Donation" entity in this codebase is called `Contribution`, not `Donation` — see Known Questions/legacy-model note below).
- **Access:** Admin, Secretary, Treasurer can create; **verify** (cash only, pending only) is Admin/Treasurer only; **void** is Admin only.
- **Main functionality:** `DonationPortalController` (index/store/show/update/verify), backed by `LogContribution` service. Handles both `cash` (amount + payment method + reference number, unique per method) and `in_kind` (one or more `ContributionItem` rows tied to category/subcategory) contributions. Receipt numbers auto-generate (`DON-YYYYMMDD-000001` format). Update is restricted to the creator (or Admin) while the record `isEditable()`.
- **Connects to:** Requires a `Donor`; in-kind items feed inventory classification; verified cash flows into Bank Reconciliation matching and the Financial Ledger; void action is exposed here but processed via the Disbursements module's `VoidContribution` service.
- **Status:** **Complete.** Heavily tested — `tests/Feature/DonationsPortalTest.php` (17 tests) covers cash/in-kind rules, reference-number uniqueness, subcategory validation, receipt numbering.
- **Manually check before the meeting:** Create one cash and one in-kind donation live; verify the cash one; confirm the receipt number format; confirm a voided contribution shows a "Voided" badge rather than disappearing.
- **Remaining issues:** None functionally blocking. Confirm with the team whether the legacy `Donation`/`CashTransaction` models (see §4) should be deleted for code cleanliness before defense.

### Donor Management
- **Purpose:** Maintain donor records (individuals/organizations) used across donations and reporting.
- **Access:** Admin, Secretary, Treasurer — **no dedicated Policy class exists** for this module (see below), so all three roles currently have identical donor CRUD rights, including delete.
- **Main functionality:** `DonorController` — full CRUD, duplicate-donor detection heuristic on create (normalizes name/email/phone, prompts to confirm before proceeding), search by split names. Delete (`RemoveDonor` service) hard-deletes a donor **only if they have no contribution history**; otherwise it **deactivates** them (`status = inactive`) and logs the action — donation history is never lost.
- **Connects to:** Donor is required to create a Contribution; donor profile shows contribution history (now correctly reading `contributions`, not the legacy `donations` table — this was a real bug fixed recently, see `docs/TEAM_TASK_ASSIGNMENT.md` Neziel section).
- **Status:** **Complete**, with one **design inconsistency to flag**: unlike every other module, there is no `DonorPolicy` — access control here is only the blanket route-level role check, not per-action authorization like the rest of the app.
- **Manually check before the meeting:** Try deleting a donor with no history (should hard-delete) and one with history (should deactivate, not delete) — confirm both paths work as designed and are intentional.
- **Remaining issues:** Decide (and be ready to explain) whether Secretary and Treasurer should really have identical donor-delete rights, or whether a `DonorPolicy` should restrict delete to Admin, matching the pattern used everywhere else.

### In-Kind Inventory
- **Purpose:** Track in-kind goods received, current stock levels, and alerts for low/critical/out-of-stock/expiring items.
- **Access:** View — all three roles. Create/update/adjust stock — Admin & Secretary. Archive — Admin only. Treasurer is explicitly **blocked** from stock adjustments (tested).
- **Main functionality:** `InventoryController` (index/store/show/update/exportPdf) + `InventoryStockAdjustmentController` for movements. Stock status (`adequate`/`low_stock`/`critical_stock`/`out_of_stock`/`expired`) computed live via `InventoryItem::stockStatus()`. PDF export via `InventoryReportPdfExporter`.
- **Connects to:** In-kind contribution items feed initial stock; category/subcategory reference data classifies items; stock movements feed the monthly In-Kind Audits report and its CSV/PDF exports.
- **Status:** **Complete.** Tested in `tests/Feature/InventoryManagementTest.php` (13 tests), including "stock-out cannot go below zero" and audit-log recording of adjustments.
- **Manually check before the meeting:** Confirm stock-out correctly blocks over-withdrawal; confirm PDF export downloads and its numbers match the on-screen stock summary.
- **Remaining issues:** `current_quantity` is a stored, mutable column updated only through `AdjustInventoryStock` — worth confirming no other code path writes to it directly (a drift risk if it did, though none was found).

### Categories & Subcategories
- **Purpose:** Admin-managed reference data for classifying in-kind items (categories, subcategories, units) — replaces a formerly static config file.
- **Access:** View — all three roles (though the index route itself is gated to the `create` ability, effectively admin-only for the management screen). Create/update/delete — **Admin only**.
- **Main functionality:** `InventoryCategoryController`, `InventorySubcategoryController`, `InventoryUnitController`, all delegating to `ManageInventoryReferenceItem`. Deleting a category/subcategory/unit is blocked while it's still referenced by an inventory item or contribution item — it must be deactivated instead.
- **Connects to:** Feeds validation and dropdowns in both the Donation Portal (in-kind items) and Inventory.
- **Status:** **Complete.** Heavily tested — `tests/Feature/InventoryCategoryManagementTest.php` (19 tests, the largest test file in the suite).
- **Manually check before the meeting:** Try deleting a category currently in use (should be blocked with a clear message) and one that's unused (should succeed).
- **Remaining issues:** `docs/category-management-admin-guide.md` documents only categories and units — the **subcategories** feature exists in code but is undocumented in that guide. Not a functional bug, just a documentation gap worth patching after the meeting.

### Stock-In / Stock-Out
- **Purpose:** Record inventory movements — incoming donations (stock-in) and outgoing distributions (stock-out).
- **Access:** Admin & Secretary (Treasurer explicitly blocked, tested).
- **Main functionality:** Single `InventoryStockAdjustmentController::store` wrapping `AdjustInventoryStock`, which locks the item row, computes the new quantity based on movement type, rejects negative results, and writes an `InventoryStockMovement` row plus an audit log entry.
- **Connects to:** Movement history displays on the inventory item detail page; feeds "Monitor Live Stock" alerts.
- **Status:** **Complete.**
- **Manually check before the meeting:** Perform one stock-in and one stock-out live and confirm the movement history entry, the quantity change, and the audit log entry all appear.
- **Remaining issues:** None found.

### Financial Ledger
- **Purpose:** Unified view of cash inflows (verified contributions) and outflows (approved disbursements), with reconciliation status.
- **Access:** View — all three roles (via the Reports page); reconciliation actions live in the separate Bank Reconciliation module.
- **Main functionality:** `FinancialLedgerReportBuilder` merges contributions and disbursements into one chronological ledger with status labels (including "Voided" for voided rows, which stay visible but are excluded from totals). Filterable by year/month. Exportable as CSV and PDF.
- **Connects to:** Reads Contributions (Donation Portal), Cash Disbursements (Disbursements module), Bank Ledger Entries (Bank Reconciliation).
- **Status:** **Complete for the summary/CSV path; Needs Verification for the PDF path** (PDF route exists and is wired, but has no feature test — see §4).
- **Manually check before the meeting:** Generate the report for a month with known data, confirm totals match manual addition, download both the CSV and the PDF and compare row-for-row.
- **Remaining issues:** No automated regression test yet confirms `disbursement_total` correctly excludes voided/rejected disbursements end-to-end (state transitions are tested; the report-total interaction specifically is not) — the team's own task doc flags this as outstanding.

### Cash Disbursements and Approval
- **Purpose:** Request, approve/reject, and (if needed) void outgoing cash payments, with a maker-checker control.
- **Access:** Request — all three roles. Approve/Reject/Void — **Admin only**, and the approver **cannot be the same user who made the request** (enforced in code, not just UI).
- **Main functionality:** `DisbursementController` (index/store/approve/reject/voidDisbursement/voidContribution) delegating to single-purpose services (`RequestDisbursement`, `ApproveDisbursement`, `RejectDisbursement`, `VoidDisbursement`, `VoidContribution`), each writing an audit event. Rejection requires a reason; void requires a reason.
- **Connects to:** Approved, non-voided disbursements feed the Dashboard and Financial Ledger totals; void action here also handles voiding a pending, unreconciled cash **Contribution** (not just disbursements).
- **Status:** **Needs Verification.** The code is complete and the corresponding test file (`tests/Feature/DisbursementWorkflowTest.php`, 7 tests) covers the maker-checker rule, rejection-reason requirement, and audit logging — but the team's own notes record this as "implemented but not locally verified" (no PHP/Composer available when it was built). **Run the test suite to confirm before claiming this is done in the defense.**
- **Manually check before the meeting:** Log in as the same user who requested a disbursement and confirm the Approve/Reject buttons are unavailable or rejected server-side; approve one as a different Admin and confirm it appears correctly in the ledger; void an approved disbursement and confirm it still shows in history as "Voided," not deleted.
- **Remaining issues:** No balance-ceiling validation on disbursement amount (deliberately deferred per the team's own notes — no authoritative running-balance source existed at the time this was scoped). Decide whether to mention this as a known limitation.

### Bank Reconciliation
- **Purpose:** Import bank statement transactions and match them against recorded contributions.
- **Access:** Import/reconcile — Admin & Treasurer only (Secretary explicitly blocked, tested). View history — all three.
- **Main functionality:** `BankReconciliationController` (index/import/reconcile). CSV import (`ImportBankStatement`) rejects duplicate statement IDs. `LedgerMatchFinder` suggests candidate contribution matches (by reference number); `ReconcileLedgerEntry` performs the match and marks it reconciled. Unverified electronic contributions are excluded as match candidates.
- **Connects to:** Reads Contributions; writes reconciliation status consumed by the Financial Ledger and Dashboard.
- **Status:** **Complete.** Tested in `tests/Feature/BankReconciliationTest.php` (13 tests) including role restrictions, duplicate-ID rejection, and audit logging.
- **Manually check before the meeting:** Import a small sample CSV, confirm duplicate statement IDs are rejected without partially importing, and complete one match end-to-end.
- **Remaining issues:** **Clarify with the adviser what "Reconcile Cash Ledger" is meant to cover** — see §7. As currently built, this is strictly bank-statement-to-contribution matching, not a general cash-in/cash-out balance sheet.

### Staff Account Management / System Security
- **Purpose:** Admin tooling to create/edit/deactivate staff accounts and toggle two-factor-authentication enforcement.
- **Access:** View — Admin & Secretary. All mutations (create/edit/deactivate account, toggle 2FA) — **Admin only**.
- **Main functionality:** `StaffAccountController` (store/update/deactivate) and `SystemSecurityController` (index/updateTwoFactorEnforcement), both delegating to dedicated services. Includes a safeguard that blocks deactivating the **last remaining Admin account**.
- **Connects to:** Every module's `created_by`/`verified_by`/`matched_by`/`approved_by` foreign keys reference `users` managed here; audit log actions also surface on this page.
- **Status:** **Needs Verification** (same caveat as Disbursements — team notes mark this "implemented but not locally verified" even though tests exist and reportedly passed in one prior run: `tests/Feature/SystemSecurityStaffAccountTest.php`, 7 tests, and `SystemSecurityAdministrationTest.php`, 4 tests).
- **Manually check before the meeting:** Create a new staff account, edit it, then try to deactivate every Admin account down to the last one and confirm the system blocks that last deactivation.
- **Remaining issues:** See §7 — the open, unauthenticated `/register` route (separate from this admin-managed flow) still exists and self-provisions Secretary accounts. Decide before defense whether that should remain enabled.

### Audit Logs
- **Purpose:** Searchable, filterable record of security-relevant and financial actions (who did what, when).
- **Access:** View — Admin & Secretary. (No separate "edit" concept — logs are append-only.)
- **Main functionality:** Custom `SecurityActivityEvent` model (not a package), written explicitly by each relevant service (approve/reject/void disbursement, void contribution, remove donor, adjust stock, import/reconcile bank statement, create/update/deactivate staff account, toggle 2FA) — captures actor, action, summary, metadata, IP address, timestamp. Displayed on the System Security page with action-type filtering and free-text search.
- **Connects to:** Cross-cuts every module listed above.
- **Status:** **Complete** for the actions it covers.
- **Manually check before the meeting:** Perform one action from each module (void, approve, stock adjust, staff account change) and confirm each produces a corresponding, correctly attributed audit row.
- **Remaining issues:** **Login, logout, and registration events are not currently written to the audit log** — only business-transaction actions are captured. Worth deciding whether authentication events should also be audited, given this is presented as a security/administration feature.

### Reports / PDF Export
- **Purpose:** Generate and export structured reports: Financial Ledgers, Philanthropy Roster (donor giving summary), and In-Kind Audits.
- **Access:** View/export — all three roles.
- **Main functionality:** `ReportController` provides Financial Ledgers, Philanthropy Roster, and In-Kind Audits. The In-Kind Audits report reads the inventory stock-movement trail, filters by month, and exports CSV/PDF with quantities before/after, reason, reference, remarks, and recorder.
- **Connects to:** Reads Contributions, Cash Disbursements, Bank Ledger Entries, Inventory (for the separate inventory PDF export reachable from the Inventory page itself).
- **Status:** **Complete.** Financial ledger, philanthropy roster, inventory summary, and in-kind audit PDF exports are wired. CSV exports are available for financial ledger and in-kind audit reports.
- **Manually check before the meeting:** Download all three PDF types live and visually confirm they render correctly (headers, totals, no missing fonts/layout breaks) — since two of the three have no automated test as a safety net.
- **Remaining issues:** Visually inspect the exported PDFs with representative data before the demo.

---

## 3. Must-Haves Checklist

Statuses below reflect **actual code**, cross-checked against `PROJECT_REQUIREMENTS.md`'s Functional Requirement Summary Table (24 items, A1–A12/S1–S12). Differences from that document's current status column are called out explicitly.

| Requirement | Current Status | Evidence in System | What Still Needs Checking |
|---|---|---|---|
| A1/S1 — Log In | ✅ Complete | `Auth\AuthenticatedSessionController`, `tests/Feature/Auth/LoginTest.php` (8 tests incl. deactivated-user block) | Nothing significant |
| A2/S2 — Log Out | ✅ Complete | `AuthenticatedSessionController@destroy`, shared `POST /logout` | Nothing significant |
| A3 — Manage Staff Accounts | ⚠️ Needs Verification (code says done, doc says 🟡 partial — **doc is stale**) | `StaffAccountController` store/update/deactivate, admin-gated, "last admin" safeguard; `SystemSecurityStaffAccountTest.php` (7 tests) | Re-run the test file; confirm last-admin safeguard live |
| A4 — Audit System Logs | ✅ Complete | `SecurityActivityEvent`, `SystemSecurityController@index`, filter/search UI | Confirm login/logout events are intentionally excluded (see §2) |
| A5 — Manage Donor Records | ✅ Complete | `DonorController` full CRUD | Confirm intended access split for delete (no `DonorPolicy` exists — see §2) |
| A6 — Configure Item Categories | ✅ Complete | `InventoryCategoryController`/`InventoryUnitController`, DB-backed, admin-only mutation, 19 tests | Nothing significant |
| A7 — Approve Fund Disbursements | ⚠️ Needs Verification (doc says ⏳ planned — **doc is stale**) | `DisbursementController@approve/reject`, `CashDisbursementPolicy` (maker-checker), `DisbursementWorkflowTest.php` (7 tests) | Run the test suite; live-demo the self-approval block |
| A8 — Reconcile Cash Ledger | ✅ Complete | `BankReconciliationController`, `LedgerMatchFinder`, `ReconcileLedgerEntry`, 13 tests | Confirm this matches the adviser's intended meaning of "reconcile" (see §7) |
| A9 — Void Cash Transactions | ⚠️ Needs Verification (doc says ⏳ planned — **doc is stale**) | `VoidContribution`/`VoidDisbursement` services, `voided_at/voided_by/void_reason` columns, no hard deletes | Run the test suite; confirm voided rows still display correctly everywhere |
| A10 — Access Main Dashboard | ✅ Complete | `DashboardController`, `DashboardMetrics`, 6 tests | Nothing significant |
| A11 — Generate Financial Reports | ✅ Complete | `FinancialLedgerReportBuilder`, `ReportController`, 5+13 tests | Nothing significant |
| A12 — Export PDF Files | ⚠️ Needs Verification (doc says ⏳ planned — **doc is materially stale, feature is built**) | `barryvdh/laravel-dompdf` in `composer.json`; `FinancialLedgerPdfExporter`, `DonorGivingSummaryPdfExporter`, `InventoryReportPdfExporter`; routes + Blade PDF templates all exist | Only inventory PDF export has a test — add tests for the other two, and visually verify all three render correctly |
| S3 — Search Donor Database | ✅ Complete | `DonorController@index` search | Nothing significant |
| S4 — View Donor Profiles | ✅ Complete | `DonorController@show`, history summary | Nothing significant |
| S5 — Record Cash Receipts | ✅ Complete | `DonationPortalController`, `LogContribution`, 17 tests | Nothing significant |
| S6 — Record Cash Disbursements | ✅ Complete | `DisbursementController@store` (request step) | Nothing significant |
| S7 — Process Stock-In | ✅ Complete | `InventoryStockAdjustmentController`, `movement_type = stock_in` | Nothing significant |
| S8 — Process Stock-Out | ✅ Complete | Same controller, `movement_type = stock_out`, cannot go below zero | Nothing significant |
| S9 — Monitor Live Stock | ✅ Complete | `InventoryAlertQuery`, config-driven thresholds | Nothing significant |
| S10 — View Cash Ledger Logs | ✅ Complete | Financial Ledgers report + bank ledger listings | Nothing significant |
| S11 — View Inventory Logs | ✅ Complete | `InventoryStockMovement` displayed on item detail page | Nothing significant |
| S12 — Download PDF Reports | ⚠️ Needs Verification (doc says ⏳ planned — **doc is stale, same caveat as A12**) | Same evidence as A12 | Same as A12 |

**Bottom line:** Of the 24 requirements, the requirements doc currently shows 19 ✅ / 1 🟡 / 4 ⏳. Based on actual code, **all 24 have real implementations** — but 5 of them (A3, A7, A9, A12, S12) need a fresh test-suite run and/or a live demo check before they can be confidently claimed as "done" in front of the panel, since they were built without a local PHP environment to verify against.

---

## 4. End-to-End Flows to Demonstrate

1. **Create/find donor → record donation → verify contribution**
   Donors → Create Donor (or search existing) → Donation Portal → Log new cash donation for that donor → Donation Portal → Verify the pending donation → confirm it now counts in Dashboard/Ledger totals.

2. **Electronic donation → import bank statement → reconcile**
   Donation Portal → Log a non-cash contribution (e.g., bank transfer, with a reference number) → Bank Reconciliation → Import a CSV containing a matching statement line → confirm the system suggests the match → Reconcile it → confirm status flips and it appears in the Financial Ledger as reconciled.

3. **In-kind donation → inventory → stock movement**
   Donation Portal → Log an in-kind donation with one or more items (choose category/subcategory) → Inventory → confirm the item(s) appear with correct initial quantity and a stock-in movement recorded → Inventory → perform a manual stock-out (simulate distribution) → confirm quantity decreases and a new movement row appears.

4. **Create disbursement → approval/rejection → financial ledger**
   Disbursements → Request a disbursement as User A → Log in as a different Admin (User B) → Approve it → confirm User A cannot approve their own request if attempted → Financial Ledger → confirm the approved disbursement appears and reduces the net total.

5. **Void transaction → audit trail**
   Pick a pending, unreconciled cash contribution → void it with a reason → confirm it shows "Voided" (not removed) in the Donation Portal and Ledger → System Security → Audit Logs → confirm a `contribution_voided` entry appears with the correct actor and timestamp. Repeat for an approved disbursement (`disbursement_voided`).

6. **Generate/export reports**
   Reports → Financial Ledgers tab → filter by month → export CSV, then export PDF, and compare totals → switch to Philanthropy Roster tab → confirm donor giving summary renders → export that PDF too → switch to In-Kind Audits, filter by month, and review/export the inventory movement history.

---

## 5. Before-Meeting Checklist

**High priority (do these first):**
1. **Run the full automated test suite** (`php artisan test`) and confirm a clean pass — several features (disbursement approval/void, staff account CRUD, PDF export) were built and reportedly tested once, but the team's own notes say this was never re-verified after later changes were merged in. This is the single highest-value thing to do before the meeting.
2. **Sample/demo data**: there is currently **no seeder for donors, contributions, or inventory items** — `DatabaseSeeder` only creates roles, one demo user per role (and only if an env var is set), and security permissions. **Before the meeting, either write a demo seeder or manually populate the app with realistic sample data** so the dashboard/reports/ledger aren't empty during the live demo.
3. **PDF exports**: financial-ledger and philanthropy-roster PDF downloads have no automated test — manually download and visually inspect both (layout, totals, fonts) before relying on them live.
4. **Authentication for all roles**: manually log in as Admin, Secretary, and Treasurer and click through every module to confirm each role sees exactly what it should (especially the System Security page, which Secretary can view but not edit).
5. **Broken routes/forms**: no dangling `route()` references were found in a code-level cross-check, but do a manual click-through of every sidebar link and form submission as a final sanity pass — automated checks don't catch UI/JS issues.

**Medium priority:**
6. **Permissions**: specifically re-test the maker-checker rule (a user cannot approve their own disbursement request) and the "cannot deactivate last Admin" safeguard live, since these are the two most sensitive access-control rules in the system.
7. **Calculations/totals**: manually verify a handful of dashboard and ledger totals against raw data (e.g., sum a few contributions by hand and compare to what the Financial Ledger reports) — all totals are computed live at request time, not cached, but that also means a bug would show immediately.
8. **Empty/error states**: check what each list/report view looks like with zero data (relevant given the empty-database issue above) — confirm these are graceful "no data" messages, not raw errors.
9. **Database migrations**: run `php artisan migrate:fresh --seed` on a clean database to confirm the full migration chain runs cleanly end-to-end without manual intervention.
10. **Audit logs**: confirm every sensitive action you plan to demo actually produces a log entry (see Flow 5 above) — and decide whether to proactively mention that login/logout events are currently *not* logged.
11. **Responsive UI**: quickly check the dashboard and a couple of data-heavy pages (Reports, Inventory) on a narrower browser window/tablet size, since this wasn't part of the code-level review.

**Low priority / cleanup, time permitting:**
12. Remove the unused `app/Http/Controllers/AuthController.php` (confirmed dead code, not referenced by any route).
13. Consider deleting or clearly archiving the orphaned `Donation`, `CashTransaction`, and `BankReconciliation` models/tables — they're confirmed unused but still present, which could confuse a question from the panel about the data model.
14. Update `PROJECT_REQUIREMENTS.md`'s status column and `project-structure.md`/`README.md` to match the current codebase (both are meaningfully out of date, per the investigation above) — do this *after* the meeting if time is short, but flag it now so it doesn't get forgotten.

---

## 6. Known Questions / Decisions for Adviser

1. **What does "Reconcile Cash Ledger" mean for this project?** The current implementation is specifically **bank-statement-to-contribution matching** (import a CSV of bank lines, match each to a recorded donation, mark it reconciled) — it is *not* a general cash-inflow-vs-outflow balancing/trial-balance feature. If the adviser's original intent was the latter, that's a scope conversation to have now rather than after the defense.

2. **Donor delete vs. deactivate when transaction history exists.** The system already implements a specific rule: a donor with **no** contribution history can be permanently deleted; a donor **with** history is automatically deactivated instead (never deleted), preserving all donation records. Confirm this is the intended policy — and separately, confirm whether Secretary/Treasurer should really have the same delete rights as Admin here, since there's no dedicated policy restricting it (unlike every other module).

3. **Should public self-service staff registration remain available?** `/register` is currently open to anyone, unauthenticated, and immediately creates an active **Secretary** account with no approval step — this exists in parallel with a fully-built, Admin-only staff account creation flow (`StaffAccountController`). For a back-office system like this, having both an open self-registration path *and* an admin-gated one is worth a direct decision: keep both, restrict `/register` to invite-only, or remove it entirely in favor of Admin-created accounts.

4. **What is the intended scope of PDF reports?** Current report PDFs cover Financial Ledger, Philanthropy Roster, Inventory Summary, and In-Kind Movement Audit. Confirm whether this is the full intended report set or whether additional report types are expected before defense.

---

## 7. Recommended Adviser Demo Order

1. **Log in** as Admin — walk through the Dashboard, briefly explaining the three roles and how access differs.
2. **Donor Management** — find/create a donor.
3. **Donation Portal** — log a cash donation for that donor, then verify it (switch to Treasurer if you want to show the role split).
4. **In-Kind Inventory** — log an in-kind donation, show the resulting stock entry, then perform a stock-out.
5. **Categories & Subcategories** — briefly show the admin-only reference-data screen (optional, time permitting).
6. **Bank Reconciliation** — import a small sample statement and reconcile one entry.
7. **Cash Disbursements** — request a disbursement as one user, approve it as another (demonstrating the maker-checker rule), then void it to show the audit-preserving void flow.
8. **Financial Ledger / Reports** — generate the Financial Ledgers and Philanthropy Roster reports, export one as CSV and one as PDF.
9. **System Security / Audit Logs** — show the staff account list, the "last admin" safeguard (describe rather than actually locking yourself out), and the audit log entries generated by everything just demonstrated.
10. **Close** with the Must-Haves table (§3) and Known Questions (§6) to frame the remaining discussion.

---

## Summary Report

**Pages/modules found (12):** Dashboard; Donation Portal/Contributions; Donor Management; In-Kind Inventory; Categories & Subcategories; Stock-In/Stock-Out; Financial Ledger; Cash Disbursements & Approval; Bank Reconciliation; Staff Account Management/System Security; Audit Logs; Reports/PDF Export. (Authentication — Login/Logout/Register — also reviewed as a supporting module.)

**Incomplete or questionable requirements:**
- In-Kind Audits report tab — implemented with monthly movement history and CSV/PDF export.
- Financial-ledger and philanthropy-roster PDF export routes — implemented but untested (only inventory PDF has a test).
- No demo/sample-data seeder for donors, contributions, or inventory.
- Donor module has no dedicated policy class — access control is coarser here than everywhere else.
- No regression test confirming the Financial Ledger's `disbursement_total` correctly excludes voided/rejected disbursements end-to-end.
- Login/logout/registration events are not written to the audit log (only business-transaction actions are).
- Five requirements (A3, A7, A9, A12, S12) are marked "Planned"/"Partial" in `PROJECT_REQUIREMENTS.md` but are actually implemented in code — the doc itself is the thing that's incomplete, not the feature.

**Highest-priority items to fix/verify before the meeting:**
1. Run the full test suite (`php artisan test`) — several features were never verified after being built.
2. Populate the app with realistic sample data — there is currently no seeded demo data at all.
3. Manually verify the two untested PDF export routes render correctly.
4. Resolve the four Known Questions in §6 with the adviser, especially the open `/register` route and the meaning of "Reconcile Cash Ledger" — both are scope/security decisions, not bugs.
5. Update `PROJECT_REQUIREMENTS.md`'s status column after the meeting so it stops contradicting the actual codebase.
