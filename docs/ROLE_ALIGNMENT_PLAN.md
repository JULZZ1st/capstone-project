# Role Alignment Plan

**Status:** Proposed plan only. No application code, migration, permission, account, workspace membership, or database record was changed for this plan.

**Confirmed target role model:**

- **Admin** — manages staff accounts and workspace security, has system-wide access, and performs restricted approvals and reviews.
- **Staff** — represents the Barangay Treasurer. Staff records donors and donations, operates inventory, prepares Fund Requests and vouchers, records approved payments, and prepares Bank Reconciliation. Staff cannot manage user accounts or workspaces, approve their own transactions, or perform Admin-only approvals.

## 1. Read-only findings

### MySQL role and assignment snapshot

The application is configured for MySQL in `.env` with database `barangay_donation_system`. A read-only Laravel query returned:

| Role row | Role ID | Assigned users | Workspace memberships |
|---|---:|---:|---:|
| `admin` | 1 | 10 | 10 |
| `staff` | 4 | 11 | 11 |
| `staff_1` | 55 | 0 | 0 |
| `staff_2` | 56 | 0 | 0 |

The `staff_1` and `staff_2` rows exist but are currently unassigned. The `staff` role is the actual assigned Staff role and must not be treated as an obsolete account role. Counts must be rechecked immediately before any future cleanup migration.

### Application references that still implement the three-staff-role model

| Area | Evidence | Required alignment |
|---|---|---|
| Role constants | `app/Models/Role.php` defines `STAFF_1` and `STAFF_2`. | Keep only the Admin and Staff constants after dependent code is migrated. |
| User capabilities | `app/Models/User.php` defines `isLegacyStaff()`, `isStaffOne()`, `isStaffTwo()`, and role-specific capability branches. | Make `isStaff()` represent the `staff` role and collapse Staff 1/Staff 2 branches into Staff. Preserve existing capability methods where they are used by policies. |
| Default role configuration | `config/auth_defaults.php` defines `staff_1` and `staff_2`; the `staff` description calls it a legacy role. | Keep `admin` and `staff` only, and describe Staff as the Barangay Treasurer role. |
| Protected routes | `routes/web.php` uses `role:admin,staff,staff_1,staff_2`. | Change the protected role boundary to `role:admin,staff` after policy tests pass. |
| Admin account forms | `app/Http/Requests/SystemSecurity/CreateStaffAccountRequest.php` and `UpdateStaffAccountRequest.php` validate `staff,staff_1,staff_2`. | Accept only `staff`; preserve Admin-only authorization for these requests. |
| Staff Management UI | `resources/views/system-security.blade.php` and `resources/views/components/system-security/user-security-card.blade.php` display Staff 1, Staff 2, and Legacy Staff options. | Display only Staff and keep existing account status and security controls. |
| Workspace authorization | `app/Support/Workspaces/WorkspaceAuthorizer.php` accepts all three staff names for memberships and Admin role assignment. | Accept only `staff` for non-Admin members. Do not change workspace ownership or existing Staff memberships. |
| System security query | `app/Services/SystemSecurity/SystemSecurityDashboardQuery.php` lists all three staff role constants. | List Admin and Staff accounts only. |
| Contribution permissions | `app/Policies/ContributionPolicy.php` calls `isLegacyStaff()` and separates Staff 1/Staff 2 donor, cash, and in-kind actions. | Map the assigned Staff role to the intended Treasurer workflow while retaining creator/reviewer safeguards. |
| Financial and reconciliation policies | `app/Policies/CashDisbursementPolicy.php`, `app/Policies/BankReconciliationPolicy.php`, and `app/Http/Requests/BankReconciliation/ResolveReconciliationDiscrepancyRequest.php` check Staff 2 or `isLegacyStaff()`. | Replace the split checks with Admin/Staff checks and preserve Admin review, self-approval prevention, and workspace isolation. |
| Role migration history | `database/migrations/2026_11_10_000002_add_staff_responsibility_roles.php` created the two unassigned role rows. `database/migrations/2026_10_24_000002_combine_secretary_and_treasurer_as_staff.php` consolidated prior Secretary/Treasurer assignments into `staff`. | Do not edit either applied migration. If approved, add a new guarded cleanup migration for only the unassigned `staff_1`/`staff_2` rows. |

## 2. Legacy-role references to distinguish

The following are role-related and should be aligned:

- `Legacy Staff (transition)` labels in the Staff Management views.
- `isLegacyStaff()` and all branches that use it as the name for the canonical `staff` role.
- SRS references to Staff 1, Staff 2, and Legacy Staff.
- The `staff_1` and `staff_2` role rows and the migration that created them.

The following uses of the word “legacy” are not automatically role defects and must remain unless separately verified:

- The `legacy` workspace used for compatibility and historical backfill.
- Legacy payment-method input compatibility in `config/donations.php` and related contribution handling.
- Historical migration names and comments.
- Nullable historical inventory source links and preserved audit history.

## 3. Proposed safe implementation sequence

### Phase 1 — Code and authorization alignment

1. Update role configuration and display text to Admin and Staff only.
2. Replace the misleading `isLegacyStaff()` naming with `isStaff()`. A temporary compatibility alias may remain internally during the transition if all callers are updated and its meaning is documented.
3. Remove Staff 1/Staff 2 branches from `User` capability methods and use the assigned `staff` role for the Staff workflow.
4. Restrict protected routes, account validation, workspace membership checks, policy checks, discrepancy resolution, and Staff Management queries to `admin` and `staff`.
5. Preserve all existing safeguards:
   - Admin-only staff-account management.
   - No self-approval or self-verification.
   - Workspace-scoped record access.
   - Admin review for restricted cash, Fund Request, and reconciliation decisions.
   - Existing void history and audit events.

### Phase 2 — Tests before database cleanup

Update or replace role-split tests, especially:

- `tests/Feature/StaffResponsibilityAuthorizationTest.php`
- `tests/Feature/SystemSecurityStaffAccountTest.php`
- `tests/Feature/UserRoleQueryTest.php`
- `tests/Feature/WorkspaceArchitectureTest.php`
- `tests/Feature/WorkspaceAuthorizationQueryTest.php`
- `tests/Feature/WorkspaceIsolationTest.php`
- `tests/Feature/BankReconciliationTest.php`
- `tests/Feature/ReconciliationPeriodTest.php`
- `tests/Feature/InventoryCategoryManagementTest.php`
- `tests/Feature/FinancialLedgerReportBuilderTest.php`

The replacement coverage should verify:

- Admin can create, activate, deactivate, and assign Staff.
- Staff cannot access Staff Management or assign roles.
- Staff can perform the intended Treasurer workflows.
- Admin approval remains separate from Staff preparation and payment recording.
- Staff cannot approve or verify a transaction created by that Staff user.
- Cross-workspace access remains denied.
- Unsupported `staff_1` and `staff_2` assignment input is rejected.
- Existing Staff accounts continue to authenticate and retain their records.

Run these tests against an isolated MySQL database before any main-database migration.

### Phase 3 — Guarded database cleanup migration

Only after Phase 1 and Phase 2 pass:

1. Create a new Laravel migration; do not modify applied migrations.
2. In the migration preflight, verify that no `users.role_id` or `workspace_memberships.role_id` references `staff_1` or `staff_2`.
3. Abort with an exception if any reference exists. Never reassign users automatically.
4. Delete only the unreferenced `staff_1` and `staff_2` role rows, preserving the `admin` and `staff` rows.
5. Provide a guarded rollback that can recreate the two role definitions only if no new references exist, or stop safely and require review.
6. Take and verify a fresh backup before applying the migration to the main MySQL database.
7. Recheck role counts, user assignments, workspace memberships, and authentication after migration.

No user rows, workspace memberships, financial records, approval records, audit events, or historical contribution records should be deleted or rewritten.

## 4. SRS alignment status

`docs/BDMS-SRS-v1.1.docx` has been revised to describe only Admin and Staff. It now contains an Admin/Staff permission matrix and no Staff 1, Staff 2, or legacy-role wording. The original v1.0 backup remains preserved.

The SRS should continue to describe Staff as the Barangay Treasurer and should not claim that Staff 1/Staff 2 assignment is part of the approved workflow.

## 5. Risks and controls

| Risk | Control |
|---|---|
| A hidden user or workspace membership is assigned to Staff 1/Staff 2 after the read-only snapshot. | Recheck both foreign-key columns immediately before migration and abort if any reference exists. |
| Renaming `isLegacyStaff()` breaks an authorization caller. | Search all callers, add focused authorization tests, and retain a temporary alias only if needed. |
| Staff loses access to a required Treasurer workflow. | Test donations, inventory, Fund Requests, vouchers, payments, Bank Reconciliation, reports, and audit views as Staff before deployment. |
| Admin approval safeguards are widened accidentally. | Keep Admin-only route/policy checks and test self-approval denial. |
| Historical compatibility is removed unintentionally. | Do not remove the legacy workspace, payment compatibility, historical fields, or audit records as part of role cleanup. |

## 6. Not approved or performed

- No role rows were deleted.
- No users or workspace memberships were reassigned.
- No application code, permissions, migrations, or database records were modified.
- No main-database migration was run.
- No commit or push was performed.

The next action requiring approval is the code/test alignment followed by a guarded cleanup migration, after an additional read-only assignment check and verified backup.
