# Barangay Donation Management System

## Project Overview

The Barangay Donation Management System helps a barangay record donations, manage donated goods, control cash disbursements, and compare its financial records with bank statements.

The system solves common problems caused by paper records and separate spreadsheets:

- donor information is stored in one place;
- cash and in-kind donations can be tracked separately;
- donated goods can be counted as they enter and leave inventory;
- disbursements require approval;
- bank transactions can be matched to the financial ledger;
- approved reconciliation periods can be locked; and
- reports can be downloaded for review and audit.

The application is multi-tenant. In this project, a tenant is called a **workspace**. Each workspace represents a separate barangay or operating group. Users work inside an active workspace, and business records belong to that workspace.

Code evidence: `routes/web.php`, `app/Models/Concerns/WorkspaceScoped.php`, `app/Http/Middleware/ResolveActiveWorkspace.php`, and the workspace migrations under `database/migrations/`.

## Multi-Tenant Workflow

### 1. Admin creates workspaces and Admin accounts

An Admin opens **Workspace Management** and creates:

| Workspace | Assigned Admin |
|---|---|
| Workspace A | Admin A |
| Workspace B | Admin B |

The workspace creation service creates the workspace, creates its initial Admin account, creates the membership connecting that Admin to the workspace, and records an audit event. An Admin can open any active workspace after selecting it from Workspace Management. Admin accounts receive a temporary password and must change it when required by the application.

Code evidence: `app/Http/Controllers/WorkspaceManagementController.php`, `app/Services/Workspaces/CreateWorkspaceWithAdmin.php`, `resources/views/workspaces/index.blade.php`.

### 2. Each Admin creates local staff

Admin A creates Secretary A and Treasurer A from the staff/security page. Admin B creates the corresponding staff for Workspace B.

An Admin can create only:

- Secretary accounts; and
- Treasurer accounts.

The staff form does not offer Admin as a selectable role. Backend validation also accepts only `secretary` and `treasurer` for this form. Admin accounts are created through Workspace Management.

### 3. Workspace isolation

When a user signs in, the application resolves an active workspace from the session and the user’s memberships. Workspace-scoped models automatically filter records by that active workspace. Route-model checks also reject a record belonging to another workspace.

For example:

1. Admin A creates Donor A in Workspace A.
2. Admin B creates Donor B in Workspace B.
3. Admin A can search for and edit Donor A.
4. With Workspace A selected, the Admin cannot open Donor B by changing an ID in the URL. The route-model protection returns a not-found response.
5. Reports, dashboard totals, inventory, contributions, disbursements, bank records, and audit records are calculated within the active workspace.

Admins can manage workspaces and enter operational pages after selecting an active workspace. Secretary and Treasurer accounts remain restricted to their assigned workspace; workspace-scoped queries and route-model checks prevent records from crossing workspace boundaries.

Code evidence: `app/Models/Concerns/WorkspaceScoped.php`, `app/Services/Workspaces/WorkspaceAuthorizer.php`, `app/Http/Middleware/EnsureWorkspaceRouteModels.php`, `app/Http/Middleware/RequireActiveWorkspace.php`, and `app/Http/Controllers/Auth/AuthenticatedSessionController.php`.

## User Roles and Responsibilities

### Admin

Purpose: manage workspaces, supervise operational records, and control staff access.

Can:

- sign in and change a temporary password when required;
- open Workspace Management;
- create workspaces and initial Admin accounts;
- view workspace membership information needed for platform administration;
- select and open any active workspace;
- activate or deactivate workspaces;
- use the dashboard and workspace operational pages;
- create and manage Secretary and Treasurer accounts in the active workspace;
- manage donors, donations, inventory, disbursements, and security settings allowed by policy;
- approve or reject disbursement requests;
- review and approve reconciliation periods;
- export reports; and
- reopen an approved reconciliation period only through the audited correction workflow.

Cannot:

- create another Admin from the staff form;
- approve their own disbursement request;
- approve a reconciliation period they prepared as the same person;
- change locked-period financial records without the audited reopening rules.

Everyday responsibility: onboard workspaces, supervise controlled transactions, review reports, and maintain staff access across workspaces.

### Secretary

Purpose: maintain donor and in-kind donation records and help maintain inventory.

Can:

- view workspace records;
- create and update donor records;
- record donations;
- record in-kind donation items;
- create or update inventory records where policy permits; and
- view operational reports and history allowed by the application.

Cannot:

- create Admin accounts;
- import or reconcile bank statements;
- approve or reject disbursements;
- approve reconciliation periods; or
- access another workspace’s data.

Everyday responsibility: record who donated, what was donated, and how donated goods are stored and moved.

### Treasurer

Purpose: maintain cash-related records and perform bank reconciliation.

Can:

- view donors and workspace records;
- record cash donations;
- verify pending cash donations where policy permits;
- create and import bank ledger records;
- match incoming deposits and outgoing withdrawals;
- prepare and submit reconciliation periods for review; and
- view financial and reconciliation reports.

Cannot:

- create Admin accounts;
- approve their own reconciliation submission;
- approve or reject disbursement requests;
- change locked-period records; or
- access another workspace’s data.

Everyday responsibility: keep the cash ledger accurate, import bank statements, investigate differences, and submit periods for Admin review.

## User Stories

The stories below use the implemented role boundaries and current routes and services.

### Authentication and account management

- As a user, I want to sign in with my account, so that I can reach the pages allowed for my role.
- As a user, I want to be required to change a temporary password when required, so that an initial password is not kept permanently.
- As a user, I want to log out and invalidate my session, so that another person using the browser cannot continue as me.
- As a user, I want stale or unauthorized workspace context cleared, so that an old session cannot expose another workspace.
- As an Admin, I want to create workspace Admin accounts through Workspace Management, so that account creation is controlled.
- As an Admin, I want to create Secretary and Treasurer accounts, so that daily work can be divided safely.
- As a visitor, I should not be able to publicly self-register, so that accounts are created only by authorized administrators.

Evidence: `routes/web.php`, `app/Http/Controllers/Auth/AuthenticatedSessionController.php`, `app/Http/Controllers/SystemSecurityController.php`, and `tests/Feature/Auth/RegistrationTest.php`.

### Workspace management

- As an Admin, I want to create Workspace A and its Admin, so that a new barangay can start using the system.
- As an Admin, I want to create Workspace B independently, so that another barangay has its own records.
- As an Admin, I want to see workspace status and membership information, so that I can manage platform onboarding.
- As an Admin, I want to manage workspace staff, so that I can assign Secretary and Treasurer responsibilities.
- As an Admin, I should not see an Admin option in the staff form, so that platform privileges cannot be delegated accidentally.

### Dashboard

- As an authorized workspace user, I want to see donation, donor, inventory, disbursement, and reconciliation summaries, so that I understand the workspace’s current position.
- As a Treasurer, I want to see pending reconciliation actions, so that I know what needs attention.
- As an Admin, I want recent activity and disbursement information, so that I can supervise the workspace.

Evidence: `app/Http/Controllers/DashboardController.php`, `resources/views/dashboard.blade.php`, and `tests/Feature/DashboardMetricsTest.php`.

### Donor management

- As a Secretary, I want to create a donor profile, so that future donations can be connected to the correct person or organization.
- As a Secretary, I want to search and update donor information, so that records stay useful.
- As a Treasurer, I want to view donors, so that cash contributions can be understood in context.
- As a user, I want donor lists and giving summaries limited to my active workspace, so that another barangay’s donors are not disclosed.

Evidence: `app/Models/Donor.php`, `app/Policies/DonorPolicy.php`, `app/Http/Controllers/DonorController.php`, and `app/Services/Reports/DonorGivingSummaryReportBuilder.php`.

### Cash donations

- As a Secretary or Treasurer, I want to record a cash donation, so that the amount and payment reference enter the workspace ledger.
- As a Treasurer, I want pending electronic cash donations to be verified, so that only confirmed donations enter the verified totals.
- As an Admin, I want to void an eligible un-reconciled cash donation, so that an error can be corrected with an audit trail.
- As a user, I want a receipt number, donor, amount, method, and date recorded together, so that the transaction can be traced.

Evidence: `app/Services/Donations/LogContribution.php`, `app/Models/Contribution.php`, `app/Policies/ContributionPolicy.php`, and `app/Http/Controllers/DonationController.php`.

### In-kind donations

- As a Secretary, I want to record donated goods and quantities, so that the barangay knows what it received.
- As a Secretary, I want item category, unit, condition, and estimated value recorded, so that the donation can be reported accurately.
- As an Admin, I want the in-kind donation and inventory movement linked, so that stock totals can be checked later.
- As a user, I want in-kind records limited to my workspace, so that stock from another barangay is never mixed into local totals.

### Inventory and stock movements

- As a Secretary, I want to create an inventory item with opening stock, so that received goods become available for tracking.
- As an authorized inventory user, I want to record stock in, stock out, adjustments, and transfers with a reason, so that every quantity change is explainable.
- As an Admin, I want to see low, critical, expired, and inactive stock, so that I can act before supplies become unusable.
- As a user, I want negative stock blocked, so that inventory totals cannot become mathematically impossible.
- As an Admin, I want to archive an item without deleting its history, so that audit information is preserved.

Evidence: `app/Models/InventoryItem.php`, `app/Models/InventoryStockMovement.php`, `app/Services/Inventory/CreateInventoryItem.php`, `app/Services/Inventory/AdjustInventoryStock.php`, and `app/Policies/InventoryItemPolicy.php`.

### Fund disbursements

- As a Secretary or Treasurer, I want to request a disbursement, so that a planned payment can be reviewed.
- As an Admin, I want to approve or reject a pending request, so that money cannot leave without independent review.
- As a requester, I should not approve my own request, so that maker-checker control is preserved.
- As an Admin, I want to void an eligible disbursement with a reason, so that cancelled financial activity remains auditable.
- As a user, I want disbursements limited to my workspace and protected from locked periods, so that financial history cannot be changed improperly.

Evidence: `app/Services/Disbursements/RequestDisbursement.php`, `ApproveDisbursement.php`, `RejectDisbursement.php`, `app/Policies/CashDisbursementPolicy.php`, and `app/Http/Controllers/DisbursementController.php`.

### Bank reconciliation

- As a Treasurer, I want to import a bank statement CSV, so that bank activity can be compared with the ledger.
- As a Treasurer, I want incoming deposits suggested by amount, reference, method, and date, so that matching is faster.
- As a Treasurer, I want to match an outgoing bank withdrawal to approved disbursements, so that payments are proven against approved requests.
- As a Treasurer, I want to submit a mathematically balanced period, so that an Admin can review it.
- As an Admin, I want to approve or reject a submitted period, so that a second person reviews the Treasurer’s work.
- As an Admin, I want an approved period locked, so that its financial records cannot silently change.

### Financial ledger

- As a Treasurer, I want cash contributions and approved disbursements summarized, so that I can calculate the workspace’s financial position.
- As an Admin, I want ledger rows and totals by date, so that reports can be checked against source transactions.
- As a user, I want ledger calculations restricted to the active workspace, so that totals remain trustworthy.

### Reports and exports

- As an Admin, I want financial, inventory, audit, and reconciliation reports, so that I can review operations.
- As a Treasurer, I want a reconciliation report, so that I can explain matched and outstanding bank activity.
- As a Secretary, I want donor and in-kind reports where permitted, so that donation work can be summarized.
- As a user, I want CSV and PDF downloads to contain actual workspace records and amounts, so that an export is useful outside the browser.

Evidence: `app/Http/Controllers/ReportController.php`, `app/Services/Reports/FinancialLedgerReportBuilder.php`, `InventoryReportBuilder.php`, `ReconciliationReportBuilder.php`, `AuditTrailReportBuilder.php`, `DonorGivingSummaryReportBuilder.php`, and `InKindAuditReportBuilder.php`.

### Audit history and security

- As an Admin, I want staff account changes recorded, so that access changes can be investigated.
- As an Admin, I want security activity filtered by date and module, so that an audit review is practical.
- As a user, I want important approvals, imports, matches, voids, and corrections recorded, so that financial history is traceable.
- As an Admin, I want staff roles restricted by policy, so that an ordinary Admin cannot create another Admin.

## Complete Step-by-Step Workflows

### Admin workspace onboarding

1. An Admin signs in and is sent to Workspace Management.
2. The Admin opens **Create Workspace**.
3. The Admin enters the workspace name and initial Admin details.
4. The application creates the workspace, Admin user, membership, temporary-password state, and audit event in one transaction.
5. The Admin repeats the process for another workspace if needed.
6. The Admin gives each new account its initial credentials through the approved local process.

### Admin staff creation

1. An Admin signs in and chooses a workspace from Workspace Management.
2. Admin A opens the staff/security page.
3. Admin A chooses Secretary or Treasurer; Admin is not an available staff-form option.
4. Admin A enters the staff account details and submits the form.
5. The application validates the role, creates the user, creates the Workspace A membership, and records the security event.
6. The new user signs in and is automatically resolved to Workspace A.
7. The Admin may repeat the steps in another workspace. Each membership and its records remain separate.

### Cash donation processing

1. Secretary A or Treasurer A opens the donation page.
2. They select an existing Workspace A donor or create one if permitted.
3. They select cash, enter the amount, date, method, reference, and notes.
4. The system creates a cash contribution. Cash contributions start pending when verification is required.
5. Treasurer A reviews the supporting information.
6. Treasurer A verifies the pending contribution when the policy allows.
7. The dashboard and reports show the contribution in the appropriate pending or verified totals.
8. If an eligible error is found, an Admin may void it with a reason. The audit trail retains the event.

### In-kind donation and inventory processing

1. Secretary A opens the donation page and selects an in-kind donation.
2. They record the donor and each item’s name, category, subcategory, quantity, unit, condition, and estimated value.
3. The application stores the contribution and its item lines.
4. The item is reflected in inventory through the inventory service and stock movement history.
5. The Secretary or other authorized user records later stock movements using a reason.
6. The system stores the quantity before and after the movement and prevents negative stock.
7. Admin A reviews low, critical, expired, and inactive inventory reports.

### Fund disbursement request and approval

1. A Secretary or Treasurer opens **Disbursements**.
2. The requester enters the amount, purpose, recipient, date, and supporting reference.
3. The request is stored as pending in the active workspace.
4. Admin A reviews the request and supporting information.
5. Admin A approves or rejects it. A rejection requires a reason.
6. The requester cannot approve their own request.
7. Approved requests can later be linked to an outgoing bank withdrawal.
8. A void action, where eligible, requires the correct role, reason, workspace, and period protections.

### Reconciliation period creation

1. Treasurer A opens Bank Reconciliation.
2. Treasurer A creates a period with a start date, end date, opening balance, and bank ending balance when available.
3. The system prevents overlapping periods.
4. After a locked period, the next period must start on the following day and use the prior ending balance as its opening balance.
5. Unresolved timing items can be carried forward when the period rules allow it.

### Bank statement CSV import

1. Treasurer A opens the import page.
2. Treasurer A selects a CSV file and provides the required statement information.
3. The system validates the file type, columns, dates, amounts, transaction direction, and statement identifier.
4. The import runs atomically. Invalid input does not create a partial import.
5. Duplicate statement identifiers and duplicate transaction fingerprints are rejected.
6. The system blocks rows whose dates belong to a locked period.
7. Imported records are scoped to Workspace A and are available for matching.

### Incoming bank deposit matching

1. Treasurer A opens an incoming bank ledger entry.
2. The system suggests eligible cash contributions using amount, reference, payment method, and date proximity.
3. Treasurer A checks the suggested contribution and supporting information.
4. The match is accepted only when the contribution is eligible, belongs to the same workspace, and the amount is exact.
5. The ledger entry and contribution are marked reconciled and the audit event is recorded.
6. A contribution or bank entry already matched cannot be matched again.

### Outgoing bank withdrawal matching

1. Treasurer A opens an outgoing bank transaction.
2. Treasurer A selects approved, active Workspace A disbursements.
3. The system calculates the difference between the bank withdrawal and selected disbursements.
4. The withdrawal can be finalized only when the difference is zero, the period is not locked, and there is no unresolved discrepancy blocking the operation.
5. The bridge match and reconciliation audit event are stored.

### Discrepancy handling

1. Treasurer A records or reviews a discrepancy when a bank row cannot yet be matched.
2. The discrepancy is classified, for example as a timing item, bank charge, error, or other supported category.
3. Treasurer A adds an explanation and supporting details.
4. If it is a timing item, it may be carried forward to the next period according to the reconciliation rules.
5. The period cannot be submitted as balanced while unexplained discrepancies remain.

Outstanding examples:

- **Outstanding check:** the ledger has an approved outgoing payment, but the bank has not cleared it yet.
- **Deposit in transit:** the organization recorded an incoming deposit, but the bank statement does not show it yet.
- **Bank charge:** the bank statement contains a fee that must be explained and recorded according to the available business process.

### Treasurer submission for review

1. Treasurer A confirms all bank movements are matched or properly explained.
2. Treasurer A confirms the calculated difference is zero and the period validation passes.
3. Treasurer A submits the period.
4. The status changes to ready for review.
5. Treasurer A cannot approve the same submission.

### Admin approval, rejection, and locking

1. Admin A opens the ready-for-review period.
2. Admin A reviews the opening balance, bank ending balance, ledger totals, matched entries, and discrepancies.
3. Admin A approves the period. The period becomes approved and locked.
4. If the review finds a problem, Admin A rejects it with a reason. It returns to a correctable state.
5. A locked period rejects later imports, matches, reconciliations, and protected financial changes.
6. If a correction is genuinely required, an authorized Admin uses the audited reopening flow, which records the reason and actor.

### Carry-forward to the next period

1. The next period is created after the locked period.
2. Its opening date follows the previous period’s end date.
3. Its opening balance equals the previous bank ending balance.
4. Eligible unresolved timing items are carried forward.
5. When the next statement contains the expected bank row, the timing item can be cleared through matching.

### Financial reports

1. An authorized user opens Reports.
2. They choose the report and date filters.
3. The application builds the report from workspace-scoped records.
4. The user downloads CSV or PDF.
5. The downloaded file is checked against the dashboard and source records before it is used for a meeting or audit.

## Role Permission Matrix

The matrix describes the normal operational permissions enforced by current policies and route middleware. A dash means the role does not have that action through the normal UI. “Manage” includes administration functions specific to that row, not unrestricted access to every record.

| Module | Admin | Secretary | Treasurer |
|---|---|---|---|
| Authentication/account self-service | View, Edit | View, Edit | View, Edit |
| Workspace management | View, Create, Activate/Deactivate, Delete empty workspaces | — | — |
| Workspace staff | Create, Edit, Manage Secretary/Treasurer | View own permitted pages | View own permitted pages |
| Dashboard | View in selected workspace | View | View |
| Donors | View, Create, Edit, Manage | View, Create, Edit, Manage | View |
| Cash donations | View, Create, Edit, Void, Export, Verify | View, Create, Edit, Export | View, Create, Edit, Verify, Export |
| In-kind donations | View, Create, Edit, Export | View, Create, Edit, Export | View, Export |
| Inventory items and stock | View, Create, Edit, Manage, Export | View, Create, Edit, Stock adjustments | View, Export |
| Fund disbursements | View, Create, Approve, Reject, Void, Export | View, Create, Export | View, Create, Export |
| Bank ledger and reconciliation | View, Create, Edit, Import, Match, Approve periods, Export | View, Export | View, Create, Edit, Import, Match, Submit periods, Export |
| Locked-period correction | Manage through audited reopen | — | — |
| Financial ledger and reports | View, Export | View, Export where permitted | View, Export |
| Audit history/security | View, Edit settings, Manage staff | View where permitted | — |

Admin permissions apply to any active workspace, but data remains isolated to the workspace explicitly selected for the current session. Secretary permissions focus on donor/in-kind recording and inventory; Treasurer permissions focus on cash verification and reconciliation, without workspace-management or staff-administration access. Workspaces with operational records cannot be deleted; deactivate them instead.

## Real-Life Scenarios

The amounts below are **sample data only**. They are examples for a capstone demonstration and are not claimed to be records in the database.

### Scenario 1: Rice donation

Sample data: 50 sacks of rice, estimated at PHP 1,250 per sack, total estimated value PHP 62,500.

1. Secretary A selects or creates the donor.
2. Secretary A records an in-kind donation with quantity 50 and unit “sack.”
3. The system records the item details and opening stock movement.
4. Admin A checks the inventory report.
5. If 10 sacks are distributed, an authorized user records stock out of 10 with the distribution reason.
6. Remaining stock should be 40 sacks.

### Scenario 2: Cash donation for relief packs

Sample data: PHP 20,000 donated by a local business through bank transfer.

1. Treasurer A records the donor, amount, method, and bank reference.
2. The contribution remains pending if verification is required.
3. Treasurer A verifies it after checking the supporting information.
4. The bank statement row is imported later.
5. Treasurer A matches the exact PHP 20,000 deposit.
6. The contribution and bank entry become reconciled.

### Scenario 3: Approved food purchase

Sample data: PHP 8,500 requested for food packs.

1. Secretary A submits a PHP 8,500 disbursement request.
2. The request remains pending.
3. Admin A reviews the purpose and supporting reference.
4. Admin A approves it; Secretary A cannot approve it.
5. Treasurer A later matches the bank withdrawal to the approved request.
6. The withdrawal can be finalized only when the amounts match exactly.

### Scenario 4: Deposit in transit

Sample data: PHP 5,000 recorded in the ledger on June 30, but the bank shows it on July 1.

1. Treasurer A records or verifies the contribution on June 30.
2. The June bank statement does not contain the deposit.
3. Treasurer A documents it as a deposit in transit.
4. The June period includes the supported timing item.
5. The July period carries it forward.
6. When the July bank row appears, Treasurer A matches and clears it.

### Scenario 5: Wrong-workspace access attempt

Sample data: Admin A has a donor named “Sample Donor A”; Admin B has “Sample Donor B.”

1. Admin A signs in to Workspace A and opens the donor list.
2. Admin A sees only Workspace A donors.
3. Admin A guesses the URL for Admin B’s donor.
4. Workspace route-model protection rejects the cross-workspace record.
5. Admin B can still access the record from Workspace B.
6. Reports for the two workspaces remain separate.

## Bank Reconciliation Explained Simply

**Reconciliation** means comparing what the organization says happened with what the bank says happened.

The **Financial Ledger** is the application’s record of cash contributions, disbursements, and related financial activity. The **Bank Statement** is the bank’s record of deposits, withdrawals, and charges.

The two records may temporarily differ because:

- a deposit was recorded before the bank posted it;
- a check or withdrawal was recorded before the bank cleared it;
- the bank charged a fee;
- a reference or amount was entered incorrectly; or
- a transaction is missing from one side.

The Treasurer imports the statement manually because the current application supports a controlled CSV/XLSX import rather than an automatic bank connection. The import validates rows and blocks duplicate statements and duplicate transaction fingerprints.

For an incoming match, the system suggests contributions using reference, amount, payment method, and date proximity. The actual match requires an eligible contribution in the same workspace and an exact amount.

For an outgoing match, the Treasurer selects approved disbursements. The bank withdrawal can be finalized only when the selected disbursements total exactly equals the withdrawal.

If balances do not match, the Treasurer investigates and records a supported discrepancy or timing explanation. A period cannot be submitted while unexplained differences remain. A submitted period receives independent Admin review. Approval locks the period, which protects the financial history from silent changes.

This is safer than manually comparing Excel sheets because the application:

- keeps records in one workspace-scoped database;
- rejects duplicate imports;
- prevents duplicate matching;
- checks exact amounts for controlled matches;
- enforces Treasurer/Admin separation;
- preserves audit events; and
- locks approved periods.

## Complete Capstone Demo Flow

The following is a practical demonstration script. Use isolated demonstration accounts and sample data.

### Part 1: Workspace onboarding

1. Log in as Admin.
2. Show that login goes to Workspace Management and that Admin can select an active workspace.
3. Create Workspace A with its initial Admin.
4. Create Workspace B with its initial Admin.
5. Show both workspace records and their initial Admin memberships.

Expected panel outcome: two separate workspaces with separate Admin accounts.

### Part 2: Staff and isolation

1. Open Workspace A as Admin.
2. Open the staff/security page.
3. Show Secretary and Treasurer options.
4. Show that Admin is not offered in the staff form.
5. Create Secretary A and Treasurer A.
6. Open Workspace B and create the equivalent staff.
7. Create one sample donor in each workspace.
8. Attempt to open Workspace B’s donor while Workspace A is selected.

Expected panel outcome: cross-workspace records remain blocked, even for an Admin.

### Part 3: Donations and inventory

1. Log in as Secretary A.
2. Create a sample donor.
3. Record a sample in-kind donation.
4. Open Inventory and show the item and opening stock movement.
5. Record a sample stock-out movement with a reason.
6. Open the inventory report.

Expected panel outcome: item quantities and movement history agree.

### Part 4: Cash and disbursement controls

1. Log in as Treasurer A.
2. Record a sample cash donation and show its status.
3. Verify it if the workflow requires verification.
4. Create a sample disbursement request as Treasurer A.
5. Log in as Admin A and approve it.
6. Show that the request is approved and that the requester was not the approver.

Expected panel outcome: maker-checker approval is visible in the request history.

### Part 5: Reconciliation

1. As Treasurer A, create a sample reconciliation period.
2. Import a sample CSV containing an incoming deposit and an outgoing withdrawal.
3. Match the deposit to the exact cash contribution.
4. Match the withdrawal to the approved disbursement.
5. Resolve or document any sample timing item.
6. Submit the balanced period.
7. Log in as Admin A and approve it.
8. Show the locked status.
9. Attempt a protected change after locking.

Expected panel outcome: the period is approved and locked, and protected changes are rejected.

### Part 6: Reports and audit

1. Download the financial ledger CSV and PDF.
2. Download the inventory and reconciliation reports.
3. Open the downloaded files and show the sample amounts and records.
4. Open audit history and show the donation, approval, import, match, submission, and approval events.
5. Repeat a report check in Workspace B.

Expected panel outcome: each export contains only its own workspace’s sample records.

## Implementation Status

### Implemented and verified

The following features are implemented in the current code and covered by Laravel tests and/or browser verification:

- login, logout, password-change enforcement, and role-aware redirects;
- disabled public self-registration;
- Admin workspace creation and initial Admin creation;
- workspace memberships and active-workspace resolution;
- workspace-scoped business models and cross-workspace route protection;
- Admin staff creation restricted to Secretary and Treasurer;
- donor management and donor giving summaries;
- cash and in-kind contribution recording;
- inventory creation, opening stock, stock movements, low/critical/expired indicators, and negative-stock protection;
- disbursement request, approval, rejection, void rules, and maker-checker protection;
- bank statement CSV/XLSX import validation and duplicate prevention;
- incoming and outgoing bank matching;
- reconciliation period creation, submission, approval, rejection, locking, and audited reopening rules;
- financial, inventory, reconciliation, audit, donor, and in-kind reports;
- CSV and PDF export endpoints;
- security activity events and staff/security administration; and
- Laravel regression coverage for authentication, workspaces, isolation, donations, inventory, disbursements, reconciliation, reports, dashboard metrics, and security.

Recent full-suite evidence: 219 Laravel tests and 935 assertions passed after Phase 4 stabilization. Browser evidence includes Workspace Management, the staff form without an Admin option, role-aware login redirects, cross-workspace blocking, and Phase 5 financial workflows on isolated E2E data.

### Implemented but not fully browser-tested

These paths exist and have automated Laravel coverage, but not every branch has been demonstrated end-to-end in a browser session:

- every edit, delete, void, rejection, and validation edge case for every module;
- all detailed security-setting combinations;
- every report filter and format combination;
- the full XLSX import path through the browser;
- legacy migration and import paths on every historical schema variation; and
- unusual reconciliation correction and carry-forward combinations.

For a capstone demo, use the tested CSV path and the ordinary happy-path records, then explain the protected error cases.

### Not implemented or not evidenced in the current code

- Public registration is intentionally disabled. A registration controller and view remain in the source tree, but no public registration route is present in `routes/web.php`.
- No automatic bank API connection is present; statements are imported manually.
- No separate automatic notification or email approval workflow was found in the inspected application paths.
- Admin can manage all workspaces; Secretary and Treasurer remain limited to assigned workspaces and their operational responsibilities.
- No evidence was found of a separate bank-charge automation workflow; bank charges must follow the supported discrepancy and ledger processes.
- A general user-facing workspace-switch route is not exposed in the current web route list; active context is resolved from authorized session/membership state and platform management routes.

### Important business rules and limitations

- Every operational request must have an authorized active workspace.
- Cross-workspace records must not be accessed by changing IDs or URLs.
- Admins create only Secretary and Treasurer accounts from the staff console.
- A requester cannot approve their own disbursement.
- A Treasurer cannot approve their own reconciliation submission.
- Incoming matches require exact amounts; outgoing withdrawals must total exactly.
- Duplicate bank statements and duplicate transaction fingerprints are rejected.
- Locked reconciliation periods protect their related financial records.
- Do not treat sample scenario amounts in this document as database records.
- Dashboard cache keys should be reviewed to ensure all cached workspace summaries remain workspace-specific; the inspected dashboard contains global-looking keys for some summary values and this is a maintenance risk even though the underlying models are workspace-scoped.

## Code Reference Guide

For groupmates who want to verify a feature in the source code:

| Concern | Main locations |
|---|---|
| Web pages and middleware | `routes/web.php`, `app/Http/Middleware/` |
| Users and workspaces | `app/Models/User.php`, `Workspace.php`, `WorkspaceMembership.php` |
| Workspace authorization | `app/Services/Workspaces/WorkspaceAuthorizer.php`, workspace policies/middleware |
| Donations | `app/Models/Contribution.php`, `Donor.php`, `app/Services/Donations/`, `app/Policies/` |
| Inventory | `app/Models/InventoryItem.php`, `InventoryStockMovement.php`, `app/Services/Inventory/` |
| Disbursements | `app/Models/CashDisbursement.php`, `app/Services/Disbursements/` |
| Bank reconciliation | `app/Models/BankLedgerEntry.php`, `BankReconciliation.php`, `app/Services/Reconciliation/`, `app/Http/Controllers/BankReconciliationController.php` |
| Reports | `app/Http/Controllers/ReportController.php`, `app/Services/Reports/` |
| Security/audit | `app/Models/SecurityActivityEvent.php`, `app/Http/Controllers/SystemSecurityController.php` |
| Database history | `database/migrations/` |
| Automated verification | `tests/Feature/`, `tests/Unit/`, and isolated Playwright artifacts under `artifacts/` |
