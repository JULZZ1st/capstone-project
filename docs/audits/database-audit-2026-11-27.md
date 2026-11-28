# BDMS Database Audit — `barangay_donation_system`

**Date:** 2026-11-27
**Scope:** Audit only. No DDL, data, or application code was modified.
**Method:** Live schema introspected via `information_schema` (41 tables, 506 columns, confirmed exact match to the stated counts) + full read of migrations (91 applied, `php artisan migrate:status`), Eloquent models, controllers, services, form requests, policies, Blade views, and `.env` runtime config (`SESSION_DRIVER=file`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`).

## How to read this report

- **Required** — actively read/written by live routes, confirmed by direct code citation. Removing breaks a working feature or destroys financial/audit history.
- **Potentially Redundant** — present and perhaps still written, but a specific, cited reason exists to question its necessity (duplicate data, no current route, no consuming code, framework feature never exercised).
- **Legacy** — superseded by a newer table/column; explicitly retained for historical/audit integrity per the team's own migration comments or import history. Not touched by current request flows.
- **Safe-to-Remove Candidate** — no code reference anywhere, not historical-record-bearing, genuinely free to drop. (Very few tables qualify — see summary.)

No empty table was assumed unused; every classification below is backed by a controller/service/policy/view/migration citation, not row count.

---

## Executive summary

| Classification | Tables | 
|---|---|
| Required | 30 |
| Potentially Redundant | 6 |
| Legacy | 3 |
| Safe-to-Remove Candidate | 0 (table level) — see column/index-level candidates instead |
| **Total** | **41** |

**Headline findings** (details and citations below):

1. **`campaigns` is officially deprecated by the team itself.** Migration `2026_10_28_000001_remove_campaigns_and_supporting_references.php` is a no-op whose only content is a comment: *"Campaigns and supporting references are no longer exposed by the current workflows, but their schema and historical records must remain intact."* No `CampaignController`, no route, no UI. `Contribution.campaign_id` still points at it. → **Legacy.**
2. **A live bug affects a 2FA-adjacent feature**, which matters for classifying `user_security_profiles`. `resources/views/components/system-security/user-security-card.blade.php:37` calls `route('system-security.two-factor-enforcement.update', $user)` — **this route does not exist anywhere** (`routes/web.php`, `routes/api.php`, `routes/console.php` all checked; `php artisan tinker` confirms `Route [system-security.two-factor-enforcement.update] not defined.`). The backend service (`ToggleTwoFactorEnforcement.php`) and form request (`ToggleTwoFactorEnforcementRequest.php`) exist but are **never called by any controller**. The card's own UI label calls this a "Simulated Key" — this looks like a demo/placeholder feature, not a real TOTP implementation, and it is currently broken end-to-end.
3. **Two Eloquent models point at tables that no longer exist**: `app/Models/CashTransaction.php` (table `cash_transactions`, created by migration `2026_06_29_000005`) and `app/Models/Donation.php` (table `donations`, created by migration `2026_06_29_000002`). Neither table is in the live 41-table schema — they were superseded by `cash_disbursements`/`bank_ledger_entries` and `contributions` respectively — but **the PHP model files were never deleted**. Not a DB finding per se (the tables are already gone), but directly relevant: it shows the team has already cleaned up tables before without full follow-through on the model layer.
4. **`workspace_memberships.role_id` duplicates `users.role_id`** and is **not consulted by any authorization check**. `WorkspaceAuthorizer::canAccess()` and `User::hasRole()` only ever read `users.role_id`. The only two places `workspace_memberships.role_id` is read are (a) a display label in `resources/views/workspaces/index.blade.php:26` and (b) `WorkspaceBackfill.php`, which *detects* drift between the two (`membership_role_mismatch`) but does not correct it. → genuine denormalization risk, **Potentially Redundant**.
5. **Three Laravel framework tables are provisioned but never exercised by any application code**: no `app/Jobs` directory exists and zero `dispatch()` calls exist anywhere, so `jobs`, `job_batches`, and `failed_jobs` are pure dormant scaffolding despite `QUEUE_CONNECTION=database`. Similarly, `sessions` is unused because `SESSION_DRIVER=file`, and `password_reset_tokens` is unused because there is no forgot-password flow (only `ChangePasswordController` for authenticated users; Admin resets Staff passwords directly via `StaffAccountController`).
6. **`legacy_donors` and `legacy_inventory_items`** are confirmed frozen historical snapshots: no Eloquent model, no controller/service reference outside the one-time import migrations (`2026_06_29_000007_import_legacy_donors.php`, `2026_07_15_000003_import_legacy_inventory_data.php`, `2026_09_02_000002_backfill_legacy_donor_profiles.php`). Their indexes are literal clones of the live tables' original (pre-repair) index names — further evidence they're untouched snapshots. The team already deleted some siblings once (`2026_11_10_000001_remove_empty_legacy_donation_tables.php`), so there's precedent for this kind of cleanup.
7. **`workspace_backfill_issues`** records the outcome of a one-time multi-tenancy data migration (`WorkspaceBackfill.php`, confirmed **not invoked by any controller or console command**). It is an audit trail of that migration, not a live feature.
8. A handful of **redundant single-column indexes are fully covered by a composite index's leftmost prefix** — concrete, low-risk cleanup opportunities (see "Index redundancy" section).

---

## Table-by-table report

### Identity & Access

| Table | Classification | Evidence / Notes |
|---|---|---|
| `users` | **Required** | Core auth entity. `role_id` → `roles.id` is the single source of truth read by `User::hasRole()`/`roleName()` (`app/Models/User.php:117-136`), gating every policy in the app. |
| `roles` | **Required** | `admin`/`staff` (2 rows). Referenced by `users.role_id`, `workspace_memberships.role_id`, `Role::ADMIN`/`Role::STAFF` constants used throughout policies. |
| `password_reset_tokens` | **Potentially Redundant** | Default Laravel migration. No `ForgotPasswordController`/`ResetPasswordController`, no `password.request`/`password.reset` routes, no reset-password view or mail notification exist anywhere in `app/` or `resources/views/`. Only `ChangePasswordController` (authenticated self-service) and `StaffAccountController` (admin resets a staff member's password) exist. This table is never written to. |
| `sessions` | **Potentially Redundant** | `.env: SESSION_DRIVER=file` — Laravel is not using this table for session storage. It only becomes relevant if the driver is switched to `database`. |
| `security_permissions` | **Required** | Granular per-staff permission catalog (`view_reports_donations`, `submit_fund_requests`, etc.) consumed by `ReportAccess.php` and `CashDisbursementPolicy::create()`. |
| `security_permission_user` | **Required** | Pivot backing `User::hasSecurityPermission()`/`securityPermissions()`, actively used in report-tab gating and disbursement-creation policy. |
| `security_activity_events` | **Required** | Audit log. Written via `->audit(...)` calls across `DisbursementController`, `DonationPortalController`, etc. Gated for viewing by `SecurityActivityEventPolicy`. Required for auditing per your own retention requirement. |
| `user_security_profiles` | **Required, but see flag** | Columns `two_factor_enabled`/`two_factor_enforced`/`two_factor_verified_at`/`security_key_suffix` are read and rendered in `user-security-card.blade.php`. However the "Toggle Enforce" button posts to a **route that does not exist** (see headline finding #2) — the write path (`ToggleTwoFactorEnforcement` service) is **never invoked by any route**, so these columns are effectively frozen at whatever value they were seeded/created with. The UI itself labels this a "Simulated Key." Recommend the engineering team decide whether to (a) finish wiring the route, or (b) formally retire this slice — do not delete without that decision, since the table backs a live, rendered dashboard component. |

### Multi-tenancy (Workspaces)

| Table | Classification | Evidence / Notes |
|---|---|---|
| `workspaces` | **Required** | Root of the multi-tenancy model; every domain table carries `workspace_id` scoped through `WorkspaceAuthorizer`/`WorkspaceScoped` trait. |
| `workspace_memberships` | **Required, with a flagged column** | Drives `WorkspaceAuthorizer::canAccess()` (membership existence + `status='active'`). **`role_id` column is redundant** — never read for authorization (confirmed by reading the full `WorkspaceAuthorizer.php`); only used for a display label (`workspaces/index.blade.php:26`) and a drift-detector in `WorkspaceBackfill.php` that logs but never corrects mismatches against `users.role_id`. Recommend treating `users.role_id` as the single source of truth and either removing this column or adding a real sync mechanism — your call, not something to change under an audit. |
| `workspace_backfill_issues` | **Legacy** | Records exceptions found during the one-time workspace-ownership backfill (`WorkspaceBackfill.php`). Confirmed **not invoked** by any controller or `app/Console` command — it ran once (1 row present), its job is done. Retain as the audit trail of that migration; do not treat as a live operational table. |

### Location Reference Data (PSGC)

| Table | Classification | Evidence / Notes |
|---|---|---|
| `regions` | **Required** | Seeded by `database/seeders/PSGCSeeder.php`. Read by `app/Services/Donors/ResolveDonorAddressOptions.php:23` (`Region::query()->orderBy('name')->get(...)`) to populate the donor address form — not exposed via `LocationController`'s AJAX endpoints, but genuinely consumed. |
| `provinces` | **Required** | Same PSGC seeder; exposed via `LocationController::provinces()`. |
| `cities` | **Required** | Exposed via `LocationController::cities()`; 1,642 rows. |
| `barangays` | **Required** | Exposed via `LocationController::barangays()`; largest table (42,011 rows / 8.55 MB) — legitimate reference-data size, not bloat. |
| Note | — | No formal FK constraints exist between `regions→provinces→cities→barangays` (joins happen on string codes, confirmed from the live `KEY_COLUMN_USAGE` dump — zero FK rows for these four tables). This is typical for bulk-imported PSGC reference data but is worth noting under "unnecessary relationships" if referential integrity is ever a concern — not an immediate risk given the data is static reference data, not user input. |

### Donor & Donation Domain

| Table | Classification | Evidence / Notes |
|---|---|---|
| `donors` | **Required** | Core entity, full CRUD, both roles. |
| `legacy_donors` | **Legacy** | No Eloquent model exists for it anywhere in `app/Models`. Only referenced by import/backfill migrations (`2026_06_29_000007_import_legacy_donors.php`, `2026_09_02_000002_backfill_legacy_donor_profiles.php`, `2026_06_28_000001_preserve_legacy_tables.php`). Its indexes are verbatim copies of `donors`' original index names, confirming it's a frozen snapshot taken before a schema change. Retain for historical record per your own prior migration naming ("preserve_legacy_tables"); do not wire into live code. |
| `contributions` | **Required** | Core transactional table (40 columns) — donation/contribution record, cash and in-kind, with verify/void/certificate workflow. |
| `contribution_items` | **Required** | Line-item breakdown for in-kind donations; linked to inventory posting via `app/Services/Inventory/PostContributionToInventory.php`. |
| `contribution_evidence` | **Required** | File-evidence child table (uses non-default `$table = 'contribution_evidence'` override in the model — intentional singular naming, not a typo). |
| `campaigns` | **Legacy** | See headline finding #1. `Campaign.php` model exists, `contributions.campaign_id` FK exists and is seeded by `SampleBarangayDataSeeder.php`, but no controller/route exposes campaign management, and the team's own `2026_10_28_000001` migration documents this as an intentional deprecation while preserving the schema/data for historical integrity. |

### Inventory

| Table | Classification | Evidence / Notes |
|---|---|---|
| `inventory_items` | **Required** | Core stock entity. Its FK index names were recently renamed (`repair_inventory_items_*_fk`), matching the migration `2026_11_27_000001_repair_inventory_stock_movement_foreign_key.php` that was in-flight elsewhere in this repo during this session — i.e., this table is under active development right now, not legacy. |
| `inventory_categories` | **Required** | Admin-managed reference data (per the earlier use-case audit). |
| `inventory_subcategories` | **Required** | Same. |
| `inventory_units` | **Required** | Same. |
| `inventory_stock_movements` | **Required** | Written by `PostContributionToInventory.php:81`; read by `InKindAuditReportBuilder.php` and `InventoryReportBuilder.php`. 0 current rows only because no in-kind donation has reached `status='received'` yet in this dataset — not evidence of disuse. |
| `legacy_inventory_items` | **Legacy** | Same pattern as `legacy_donors`: no model, populated only by `2026_07_15_000003_import_legacy_inventory_data.php`, indexes are literal clones of the pre-repair `inventory_items` index names. |

### Bank Reconciliation (maker–checker)

| Table | Classification | Evidence / Notes |
|---|---|---|
| `bank_reconciliations` | **Required** | 37 columns — all of the "duplicate-looking" balance fields were individually verified in `app/Services/BankReconciliation/ReconciliationPeriodService.php`: `book_opening_balance`, `calculated_bank_cash_in/out`, `financial_ledger_cash_in/out`, `financial_ledger_ending_balance`, `accounting_adjustment`, `adjusted_book_balance`/`adjusted_bank_balance` are each read/written distinctly — they represent genuinely different accounting concepts (bank-side vs. ledger-side vs. adjusted figures), consistent with the audit-integrity design already on record for this module (do not collapse these; a prior incident already showed that forcing one balance field to agree with another can mask a real imbalance). |
| `bank_ledger_entries` | **Required** | Core ledger entry; heavily indexed, matched against contributions and disbursements. |
| `bank_withdrawal_disbursement_matches` | **Required** | Join table for withdrawal↔disbursement matching in the maker-checker flow. |
| `reconciliation_discrepancies` | **Required** | Backs the discrepancy review/resolve workflow (`ReconciliationDiscrepancyService.php`, `BankReconciliationController`). |

### Fund Disbursement (maker–checker)

| Table | Classification | Evidence / Notes |
|---|---|---|
| `cash_disbursements` | **Required** | Core disbursement record. `signed_voucher_status`/`signed_voucher_requested_by`/etc. track **state**, while... |
| `disbursement_vouchers` | **Required** | ...this table holds the actual **uploaded files** — confirmed both are used together in `DisbursementController::uploadSignedVoucher()` (`$disbursement->vouchers()->create([...])` alongside `$disbursement->forceFill(['signed_voucher_status' => ...])`). Same pattern as `contributions`/`contribution_evidence` — state-flag-on-parent + file-record-on-child is a deliberate, consistent design, not duplication. |
| `cash_disbursement_payments` | **Required** | Payment-release recording, one-to-one per disbursement (unique index confirms). |
| `dv_number_sequences` | **Required** | Generates `cash_disbursements.dv_number` (Disbursement Voucher numbering), scoped per workspace+period. |

### Reports

No dedicated tables — reports are generated from the tables above (`ReportController` + `app/Services/Reports/*`). Nothing to classify here beyond what's already listed.

### Framework / Infrastructure Tables

| Table | Classification | Evidence / Notes |
|---|---|---|
| `migrations` | **Required** | Laravel's own migration bookkeeping. Never remove. |
| `cache` | **Required** | `.env: CACHE_STORE=database` — actively used, e.g. `Cache::remember(...)` in `DashboardMetrics.php` and `ReportAccess.php`. |
| `cache_locks` | **Required** | Companion atomic-lock table for the database cache driver. |
| `jobs` | **Potentially Redundant** | `.env: QUEUE_CONNECTION=database`, but **no `app/Jobs` directory exists and zero `dispatch()` calls exist anywhere in `app/`.** The queue driver is configured but nothing in this codebase ever enqueues a job. Harmless to keep (zero cost), but currently 100% dormant. |
| `job_batches` | **Potentially Redundant** | Same reasoning — no `Bus::batch()` call anywhere in the codebase. |
| `failed_jobs` | **Potentially Redundant** | Same reasoning — would only ever receive rows if `jobs` were used. |

---

## Column-level findings (requirement #2 / #4)

| Columns | Verdict | Evidence |
|---|---|---|
| `contributions.payment_method` vs `payment_method_name` | **Not redundant — keep both** | `payment_method` is the constrained selector (`cash/check/bank_transfer/gcash/maya/other`); `payment_method_name` is a free-text label used *only* when `payment_method = 'other'` (`StoreContributionRequest.php:38`, `Contribution.php:272`). Legitimate "please specify" pattern. |
| `contributions.registered_bank_account` vs `reconciliation_bank_account` | **Not redundant — keep both** | `registered_bank_account` is the donor-supplied value captured at donation time; `reconciliation_bank_account` is a *derived*, normalized value computed in `LogContribution.php:55-58` against the configured `bank_reconciliation.bank_accounts` list, used specifically by `ReconcileLedgerEntry.php:51` for matching. Different purposes. |
| `donors.address` vs `donors.barangay_area` | **Potentially Redundant (transitional)** | Both are populated and searchable (`Donor.php` search scope), but `StoreDonorRequest.php:95`/`UpdateDonorRequest.php:72` normalize via `$this->nullableTrim('barangay') ?? $this->nullableTrim('barangay_area')` — i.e., `barangay_area` is treated as a fallback/legacy alias for `barangay`, not `address`. This reads as a field being phased out in favor of `barangay`. Worth confirming with whoever owns the donor-form history whether `barangay_area` can be retired now that `barangay` is primary — not clear-cut enough to act on without that confirmation. |
| `workspace_memberships.role_id` | **Potentially Redundant** | See headline finding #4 above. |
| `user_security_profiles.two_factor_*` | **Potentially Redundant (dormant/broken)** | See headline finding #2. |
| Two duplicate migration files | *(not a column issue, minor dev-hygiene note)* | `2026_10_25_000001_add_payment_method_name_to_contributions_table.php` and `2026_10_27_000001_add_payment_method_name_to_contributions_table.php` do the identical thing; the second is a guarded no-op (`Schema::hasColumn` check) that ran harmlessly. Not a data problem, just a migration file that could be deleted for cleanliness — entirely your call, zero risk either way. |

## Index redundancy (requirement #5)

These standalone single-column indexes are fully covered by an existing composite index's leftmost prefix, making them redundant for query planning (MySQL will use the composite index equally well for a single-column lookup on its first column):

| Table | Redundant index | Covered by |
|---|---|---|
| `contributions` | `contributions_type_index (type)` | `contributions_type_status_received_at_index (type,status,received_at)` |
| `contributions` | `contributions_campaign_id_index (campaign_id)` | `contributions_campaign_id_status_index (campaign_id,status)` |
| `contributions` | `contributions_payment_method_index (payment_method)` | `contributions_payment_method_check_number_index (payment_method,check_number)` |
| `inventory_items` | `inventory_items_category_index (category)` | `inventory_items_category_status_index (category,status)` |
| `inventory_items` | `inventory_items_status_index (status)` | `inventory_items_status_expires_at_index (status,expires_at)` |
| `bank_ledger_entries` | `bank_ledger_entries_transaction_type_index (transaction_type)` | `bank_ledger_entries_type_status_date_index (transaction_type,status,transaction_date)` |
| `bank_reconciliations` | `bank_reconciliations_workspace_id_index (workspace_id)` | `bank_reconciliations_workspace_id_bank_account_period_end_index (workspace_id,bank_account,period_end)` |

This list is from a manual leftmost-prefix check across the full index dump, not an exhaustive `EXPLAIN`-driven audit — treat it as a starting point, not a complete inventory. Dropping these is low-risk (MySQL will transparently fall back to the composite index) but should still go through your normal migration/review process, not be done ad hoc.

Separately: `legacy_donors` and `legacy_inventory_items` each carry the full original index set of their live counterparts (5 and 10 indexes respectively). For a frozen historical table that's never filtered in production queries, this is unnecessary index-maintenance overhead — negligible at current row counts (1 and 0 rows), but worth remembering if these tables ever grow (e.g., via a future bulk import).

## What must absolutely be retained (requirement #6)

- **Financial history**: `contributions`, `cash_disbursements`, `cash_disbursement_payments`, `bank_ledger_entries`, `bank_reconciliations`, `reconciliation_discrepancies`, `bank_withdrawal_disbursement_matches`, `dv_number_sequences`, `legacy_donors` (historical donor records tied to pre-migration financial history).
- **Inventory integrity**: `inventory_items`, `inventory_stock_movements`, `contribution_items`, `inventory_categories/subcategories/units`, `legacy_inventory_items`.
- **Authentication**: `users`, `roles`, `password_reset_tokens` (even if currently unused, it's part of Laravel's auth contract and costs nothing to keep), `security_permissions`, `security_permission_user`.
- **Auditing**: `security_activity_events`, `workspace_backfill_issues` (one-time migration record), `migrations`.

None of these are candidates for removal regardless of their activity level — flagged items above are about redundant *duplication*, not about these tables' necessity.

---

## Summary of recommended next steps (no action taken — for your decision)

1. Confirm with the team whether `campaigns` should be fully removed (deleting the dependent `contribution_items`... no — `contributions.campaign_id`, FK, and UI stub) or left as explicitly-deprecated-but-retained schema, per their own migration comment. Either is defensible; just make the decision explicit rather than leaving it ambiguous.
2. Decide the fate of the 2FA enforcement feature (`user_security_profiles.two_factor_*`, `ToggleTwoFactorEnforcement` service, the broken route) — finish wiring it or formally shelve it. Currently it's a half-built feature with a live bug in a rendered admin page.
3. Delete the two orphaned model files (`CashTransaction.php`, `Donation.php`) that reference tables already dropped from the schema — pure dead code, zero risk, not a DB change.
4. Decide whether `workspace_memberships.role_id` should be dropped in favor of always reading `users.role_id`, or properly synced — right now it can silently drift and nothing corrects it.
5. The 7 redundant single-column indexes above are safe, low-effort cleanup whenever a migration touches those tables next.
6. No action needed on `jobs`/`job_batches`/`failed_jobs`/`sessions`/`password_reset_tokens` — they cost nothing at this scale and are standard Laravel scaffolding; only worth revisiting if you want to trim the schema purely for cleanliness.

**Nothing in this report was deleted, altered, or migrated.** All 41 tables and 506 columns remain exactly as found.
