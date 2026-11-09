# Database Architecture Audit

**System:** Laravel Donation Management System
**Audit mode:** Read-only static audit; MySQL was not used
**Schema authority:** ordered migration files under `database/migrations`
**Audit date:** 2026-11-09

## 1. Executive summary

The repository contains a working, workspace-scoped donation platform whose active business model is centered on `donors` -> `contributions` -> `contribution_items`, supported by inventory, bank reconciliation, disbursement, evidence, security, and workspace tables.

The main architectural concern is historical layering. The migrations preserve or leave behind several older structures while current controllers, services, routes, and reports use newer equivalents:

- `contributions` is the active donation ledger; the older `donations` table and `Donation` model are not used by current business workflows.
- `cash_transactions` has no application references outside its migration and appears unused.
- `campaigns` remains in the schema because its removal migration is intentionally a no-op, but current application workflows no longer expose campaign functionality.
- `legacy_donors` and `legacy_inventory_items` are explicitly preserved migration snapshots and are not application tables.
- `cash_disbursements` is an active request/workflow record, while `cash_disbursement_payments` is the active payment fact and `disbursement_vouchers` is the active document record. The duplicated status/timestamp fields on `cash_disbursements` need a defined source-of-truth policy.

The schema is generally relational and has good foreign-key coverage. The highest-risk issues are: duplicated historical donation models, denormalized classification/address fields, polymorphic reconciliation references without database-enforced targets, nullable workspace ownership on many business tables, and a few missing inverse Eloquent relationships that make the graph harder to navigate and easier to misuse.

No migration, model, controller, service, route, or database data was modified. The only created file is this audit.

## 2. Evidence and method

Reviewed:

- All migration files in `database/migrations`, including additive, repair, preservation, backfill, and no-op migrations.
- Eloquent models in `app/Models`.
- Controllers in `app/Http/Controllers`, services in `app/Services`, support queries, seeders, requests, and `routes/web.php`.
- Relevant tests as corroborating usage evidence.

Important migration evidence:

- Initial tables: `database/migrations/0001_01_01_000000_create_users_table.php`, `0001_01_01_000001_create_cache_table.php`, `0001_01_01_000002_create_jobs_table.php`.
- Original business schema: `2026_06_29_000001_create_donors_table.php` through `2026_06_29_000006_create_bank_reconciliations_table.php`.
- Current donation schema: `2026_07_12_000003_create_contributions_table.php`, `2026_07_12_000004_create_contribution_items_table.php`, and later `contributions`/`contribution_items` additions.
- Workspace conversion: `2026_10_17_000001_create_workspaces_and_memberships.php`, `2026_10_17_000003_add_workspace_ids_to_business_tables.php`, `2026_10_17_000005_backfill_workspace_ownership.php`, `2026_10_17_000006_add_workspace_scoped_unique_constraints.php`.
- Legacy preservation: `2026_06_28_000001_preserve_legacy_tables.php` and `2026_10_28_000001_remove_campaigns_and_supporting_references.php`.

Classifications below mean:

- **Active:** referenced by current application workflows, models, services, routes, reports, or seeders.
- **Possibly Unused:** present in migrations but no meaningful current production workflow reference was found.
- **Legacy:** intentionally retained for compatibility, migration, or historical preservation.
- **Needs Verification:** ambiguous or structurally risky; usage exists or is implied, but ownership/source-of-truth or final deployment state should be confirmed.

## 3. Intended schema inventory

Columns below describe the final intended shape after applying the migration history in filename order. `id`, timestamps, indexes, and nullable/default behavior are included where architecturally relevant. Laravel infrastructure tables are included for completeness.

### 3.1 Laravel infrastructure and preserved historical tables

| Table | Columns | Foreign keys / relationships | Classification |
|---|---|---|---|
| `users` | `id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `first_name`, `last_name`, `status`, `last_login_at`, `role_id`, `must_change_password`, `is_super_admin` is removed by later migration; timestamps | `role_id → roles.id` restrict; referenced by many audit/workflow FKs | Active |
| `password_reset_tokens` | `email` PK, `token`, `created_at` | None | Active infrastructure |
| `sessions` | `id` PK, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity` | `user_id` is indexed but not declared as a FK | Active infrastructure |
| `cache` | `key` PK, `value`, `expiration` | None | Active infrastructure |
| `cache_locks` | `key` PK, `owner`, `expiration` | None | Active infrastructure |
| `jobs` | `id`, `queue`, `payload`, `attempts`, `reserved_at`, `available_at`, `created_at` | None | Active infrastructure |
| `job_batches` | `id` PK, `name`, `total_jobs`, `pending_jobs`, `failed_jobs`, `failed_job_ids`, `options`, `cancelled_at`, `created_at`, `finished_at` | None | Active infrastructure |
| `failed_jobs` | `id`, `uuid`, `connection`, `queue`, `payload`, `exception`, `failed_at` | None | Active infrastructure |
| `legacy_donors` | Historical donor columns copied from the pre-migration `donors` table; exact shape is inherited from the installation being upgraded | No current application FK; source for donor import | Legacy |
| `legacy_inventory_items` | Historical inventory columns inherited from the pre-migration `inventory_items` table | No current application FK; source for inventory import | Legacy |

`legacy_donors` and `legacy_inventory_items` are created by renaming existing tables, not by a new canonical schema definition. Their exact columns therefore cannot be guaranteed from a fresh install migration alone; this is an intentional verification limitation.

### 3.2 Identity, tenancy, and security

| Table | Columns | Foreign keys / relationships | Classification |
|---|---|---|---|
| `roles` | `id`, `name`, `display_name`, `description`, timestamps | `users.role_id`, `workspace_memberships.role_id` | Active |
| `workspaces` | `id`, `name`, `slug`, `status`, timestamps | Parent of workspace-scoped business rows and memberships | Active |
| `workspace_memberships` | `id`, `workspace_id`, `user_id`, `role_id`, `status`, `created_by`, timestamps; unique `(workspace_id,user_id)` | `workspace_id → workspaces.id` cascade; `user_id → users.id` cascade; `role_id → roles.id` restrict; `created_by → users.id` null | Active |
| `workspace_backfill_issues` | `id`, `table_name`, `record_id`, `issue_type`, `details`, timestamps; unique `(table_name,record_id,issue_type)` | No FK because `record_id` is intentionally cross-table | Active operational support |
| `security_permissions` | `id`, `key`, `name`, `description`, `sort_order`, timestamps | Parent of pivot rows | Active |
| `user_security_profiles` | `id`, `user_id`, `two_factor_enabled`, `two_factor_enforced`, `two_factor_verified_at`, `security_key_suffix`, timestamps; unique `user_id` | `user_id → users.id` cascade; one-to-one with `users` | Active |
| `security_permission_user` | `id`, `user_id`, `security_permission_id`, `granted_by`, timestamps; unique `(user_id,security_permission_id)` | `user_id → users.id` cascade; `security_permission_id → security_permissions.id` cascade; `granted_by → users.id` null | Active |
| `security_activity_events` | `id`, `workspace_id`, `user_id`, `actor_id`, `action`, `summary`, `metadata` JSON, `ip_address`, `occurred_at`, timestamps | `workspace_id → workspaces.id` null; `user_id/actor_id → users.id` null | Active |

### 3.3 Donors and donations

| Table | Columns | Foreign keys / relationships | Classification |
|---|---|---|---|
| `donors` | `id`, `workspace_id`, `donor_type`, `first_name`, `middle_name`, `last_name`, `suffix`, `full_name`, `contact_person`, `address`, `barangay`, `city_municipality`, `province`, `region`, `barangay_area`, `contact_number`, `preferred_contact_method`, `status`, `email`, `tin`, `notes`, timestamps | `workspace_id → workspaces.id` null; parent of `contributions`; `Donor` has many `Contribution` | Active |
| `contributions` | `id`, `workspace_id`, `receipt_number`, `donor_id`, `campaign_id`, `created_by`, `verified_by`, `type`, `status`, `amount`, `purpose_type`, `purpose_name`, `payment_method`, `payment_method_name`, `reference_number`, `check_number`, `bank_name`, `registered_bank_account`, `official_receipt_number`, `certificate_requested`, `certificate_status`, `certificate_issued_by`, `certificate_issued_at`, `donor_tin`, `deed_declined`, `deed_decline_reason`, `document_review_status`, `document_request_reason`, `documents_requested_by`, `documents_requested_at`, `notes`, `photo_path`, `received_at`, `verified_at`, `voided_at`, `voided_by`, `void_reason`, timestamps | `workspace_id → workspaces.id` null; `donor_id → donors.id` restrict; `campaign_id → campaigns.id` null; user actor FKs (`created_by`, `verified_by`, `certificate_issued_by`, `documents_requested_by`, `voided_by`) null; parent of `contribution_items`, `contribution_evidence`; one optional matched `bank_ledger_entries` row via that table's unique `matched_contribution_id` | Active |
| `contribution_items` | `id`, `workspace_id`, `contribution_id`, `name`, `category`, `category_description`, `subcategory`, `quantity`, `unit`, `condition`, `expiration_date`, `brand`, `model`, `serial_number`, `estimated_value`, `notes`, timestamps | `workspace_id → workspaces.id` null; `contribution_id → contributions.id` restrict | Active |
| `contribution_evidence` | `id`, `workspace_id`, `contribution_id`, `document_type`, `disk`, `path`, `original_name`, `mime_type`, `size`, `uploaded_by`, timestamps | `workspace_id → workspaces.id` cascade; `contribution_id → contributions.id` cascade; `uploaded_by → users.id` null | Active |
| `donations` | `id`, `donor_id`, `donation_type`, `amount`, `description`, `received_at`, timestamps | `donor_id → donors.id` cascade; `Donation` model has `belongsTo(Donor)` | Possibly Unused / overlapping |
| `campaigns` | `id`, `workspace_id`, `name`, `slug`, `description`, `status`, `starts_at`, `ends_at`, timestamps | `workspace_id → workspaces.id` null; `contributions.campaign_id → campaigns.id` null | Needs Verification / dormant |

The `contributions.campaign_id` FK remains part of the intended migration state even though `2026_10_28_000001_remove_campaigns_and_supporting_references.php` is explicitly a no-op. Current code does not expose campaign management, and `Contribution` does not define a campaign relationship.

### 3.4 Inventory

| Table | Columns | Foreign keys / relationships | Classification |
|---|---|---|---|
| `inventory_categories` | `id`, `workspace_id`, `key`, `label`, `is_active`, timestamps; workspace-scoped unique `key` | `workspace_id → workspaces.id` null; parent of `inventory_subcategories` | Active |
| `inventory_subcategories` | `id`, `workspace_id`, `inventory_category_id`, `key`, `label`, `is_active`, timestamps; unique `(inventory_category_id,key)` | `workspace_id → workspaces.id` null; `inventory_category_id → inventory_categories.id` cascade | Active |
| `inventory_units` | `id`, `workspace_id`, `key`, `label`, `is_active`, timestamps; workspace-scoped unique `key` | `workspace_id → workspaces.id` null | Active |
| `inventory_items` | `id`, `workspace_id`, `name`, `category`, `inventory_subcategory_id`, `unit`, `quantity`, `current_quantity`, `minimum_reserve_quantity`, `estimated_unit_value`, `description`, `received_at`, `last_audited_at`, `last_movement_at`, `batch_number`, `storage_location`, `notes`, `status`, `created_by`, `updated_by`, timestamps | `workspace_id → workspaces.id` null; `inventory_subcategory_id → inventory_subcategories.id` null; `created_by/updated_by → users.id` null; parent of stock movements | Active, with denormalized classification |
| `inventory_stock_movements` | `id`, `workspace_id`, `inventory_item_id`, `created_by`, `movement_type`, `quantity`, `quantity_before`, `quantity_after`, `reason`, `reference_number`, `remarks`, timestamps | `workspace_id → workspaces.id` null; `inventory_item_id → inventory_items.id` restrict; `created_by → users.id` null | Active |

### 3.5 Financial, banking, reconciliation, and disbursement

| Table | Columns | Foreign keys / relationships | Classification |
|---|---|---|---|
| `cash_transactions` | `id`, `donor_name`, `amount`, `purpose`, `received_at`, `status`, `notes`, timestamps | None | Possibly Unused / overlapping |
| `bank_reconciliations` | `id`, `workspace_id`, `bank_account`, legacy `date`, `period_start`, `period_end`, `opening_balance`, `bank_ending_balance`, `cash_in`, `cash_out`, `calculated_bank_cash_in`, `calculated_bank_cash_out`, `financial_ledger_cash_in`, `financial_ledger_cash_out`, `closing_balance`, `financial_ledger_ending_balance`, `reconciliation_difference`, `accounting_adjustment`, `adjusted_bank_balance`, `unresolved_discrepancy_amount`, `status`, `locked_by`, `locked_at`, `calculated_at`, `prepared_by`, `submitted_at`, `approved_by`, `approved_at`, `rejection_reason`, `correction_requested_by`, `correction_requested_at`, `correction_count`, `notes`, timestamps | `workspace_id → workspaces.id` null; user FKs `locked_by`, `prepared_by`, `approved_by`, `correction_requested_by` null; parent of `reconciliation_discrepancies` | Active, needs period-field consolidation |
| `bank_ledger_entries` | `id`, `workspace_id`, `statement_id`, `duplicate_fingerprint`, `description`, `reference_number`, `amount`, `transaction_date`, `transaction_type`, `payment_method`, `status`, `matched_contribution_id`, `matched_by`, `matched_at`, `reconciled_by`, `reconciled_at`, `notes`, `discrepancy_amount`, `discrepancy_reason`, timestamps; workspace-scoped unique `statement_id` | `workspace_id → workspaces.id` null; `matched_contribution_id → contributions.id` null and unique; `matched_by/reconciled_by → users.id` null; parent of withdrawal matches and discrepancy links | Active |
| `reconciliation_discrepancies` | `id`, `workspace_id`, `bank_reconciliation_id`, `reported_by`, `type`, `custom_type`, `classification`, `amount`, `explanation`, `resolution_explanation`, `evidence_reference`, `evidence_path`, `resolution_evidence_path`, `bank_ledger_entry_id`, `system_record_type`, `system_record_id`, `resolution_status`, `admin_review_status`, `admin_reviewed_by`, `admin_reviewed_at`, `admin_review_note`, `adjustment_amount`, `carry_forward`, `resolved_by`, `resolved_at`, `cleared_bank_ledger_entry_id`, `cleared_at`, timestamps | `workspace_id → workspaces.id` null; `bank_reconciliation_id → bank_reconciliations.id` cascade; `bank_ledger_entry_id/cleared_bank_ledger_entry_id → bank_ledger_entries.id` null; user actor FKs null; `system_record_type/system_record_id` is polymorphic and not FK-enforced | Active, needs verification of polymorphic policy |
| `cash_disbursements` | `id`, `workspace_id`, `amount`, `purpose`, `recipient`, `payee_address`, `province`, `city_municipality`, `barangay`, `payee_tin`, `fund_source`, `obr_number`, `dv_number`, `requested_date`, `notes`, `supporting_reference`, `requested_by`, `approved_by`, `treasurer_certified_by`, `treasurer_certified_at`, `treasurer_certification_notes`, `status`, `signed_voucher_status`, `signed_voucher_request_reason`, `signed_voucher_requested_by`, `signed_voucher_requested_at`, `payment_status`, `disbursed_at`, `approved_at`, `rejected_at`, `rejection_reason`, `voided_at`, `voided_by`, `void_reason`, timestamps; unique `(workspace_id,dv_number)` | `workspace_id → workspaces.id` null; user FKs `requested_by` restrict, others null; parent of payment, voucher, and withdrawal match | Active, needs workflow-source verification |
| `cash_disbursement_payments` | `id`, `workspace_id`, `cash_disbursement_id`, `payment_date`, `amount`, `payment_method`, `reference_number`, `released_by`, `released_at`, timestamps; unique `cash_disbursement_id` | `workspace_id → workspaces.id` cascade; `cash_disbursement_id → cash_disbursements.id` cascade; `released_by → users.id` restrict; one-to-one payment | Active |
| `disbursement_vouchers` | `id`, `workspace_id`, `cash_disbursement_id`, `document_type`, `review_status`, `disk`, `path`, `original_name`, `mime_type`, `size`, `uploaded_by`, `reviewed_by`, `reviewed_at`, `review_reason`, timestamps | `workspace_id → workspaces.id` cascade; `cash_disbursement_id → cash_disbursements.id` cascade; uploader/reviewer → users null; one-to-many vouchers per disbursement | Active |
| `bank_withdrawal_disbursement_matches` | `id`, `workspace_id`, `bank_ledger_entry_id`, `cash_disbursement_id`, timestamps; unique pair and unique `cash_disbursement_id` | `workspace_id → workspaces.id` null; ledger/disbursement FKs cascade; effective one ledger withdrawal to many disbursements, one disbursement to one withdrawal | Active |
| `dv_number_sequences` | `id`, `workspace_id`, `period`, `last_number`, timestamps; unique `(workspace_id,period)` | `workspace_id → workspaces.id` cascade | Active |

## 4. Relationships represented in code

### Core donation graph

`Donor` has many `Contribution`; `Contribution` belongs to `Donor`, belongs to several user actors, has many `ContributionItem` and `ContributionEvidence`, and has one matched `BankLedgerEntry`. Evidence belongs to a contribution and uploader. Items belong to a contribution.

Evidence:

- `app/Models/Donor.php`, `app/Models/Contribution.php`, `app/Models/ContributionItem.php`, `app/Models/ContributionEvidence.php`.
- `app/Http/Controllers/DonationPortalController.php`, `app/Http/Controllers/DonorController.php`.
- `app/Services/Contributions/LogContribution.php`, `UploadContributionDeed.php`.
- `routes/web.php` donation routes and `tests/Feature/DonationsPortalTest.php`.

### Inventory graph

`InventoryCategory` has many `InventorySubcategory`; a subcategory belongs to a category. `InventoryItem` belongs to a subcategory and has many stock movements. Items and movements belong to workspace and user actors through the workspace scope and explicit user FKs.

Evidence: `app/Models/InventoryCategory.php`, `InventorySubcategory.php`, `InventoryItem.php`, `InventoryStockMovement.php`; `app/Services/Inventory/*`; `app/Http/Controllers/Inventory*Controller.php`; `routes/web.php` inventory routes.

### Banking and reconciliation graph

`BankReconciliation` has many discrepancies. `BankLedgerEntry` optionally matches one contribution, has many withdrawal-match rows, has many discrepancies, and belongs to matcher/reconciler users. `ReconciliationDiscrepancy` belongs to the period, optional source ledger entry, optional cleared ledger entry, and several user actors. A polymorphic `system_record` points to a record by type/id.

Evidence: `app/Models/BankReconciliation.php`, `BankLedgerEntry.php`, `ReconciliationDiscrepancy.php`; `app/Services/BankReconciliation/*`; `app/Http/Controllers/BankReconciliationController.php`; `routes/web.php` bank-reconciliation routes.

### Disbursement graph

`CashDisbursement` belongs to request/approval/certification/void users, has one payment, many vouchers, one withdrawal match, and a latest voucher. `CashDisbursementPayment` belongs to the disbursement and releasing user. `DisbursementVoucher` belongs to the disbursement and uploading/reviewing users. The matching model connects cash disbursements to bank ledger entries.

Evidence: `app/Models/CashDisbursement.php`, `CashDisbursementPayment.php`, `DisbursementVoucher.php`, `BankWithdrawalDisbursementMatch.php`; `app/Services/Disbursements/*`; `app/Http/Controllers/DisbursementController.php`; `tests/Feature/DisbursementWorkflowTest.php`.

### Identity/security/workspace graph

Users belong to a global role, have workspace memberships, security profile, permissions, and security events. Workspaces have memberships and users through the pivot. Most business models use `app/Models/Concerns/WorkspaceScoped.php`, which adds an active-workspace global scope and fills `workspace_id` on create.

Evidence: `app/Models/User.php`, `Role.php`, `Workspace.php`, `WorkspaceMembership.php`, security models, and `app/Models/Concerns/WorkspaceScoped.php`.

## 5. Usage trace by application area

| Area | Tables observed in current code | Evidence |
|---|---|---|
| Authentication and staff administration | `users`, `roles`, `workspaces`, `workspace_memberships`, `user_security_profiles`, `security_permissions`, `security_permission_user`, `security_activity_events` | `app/Http/Controllers/Auth/*`, `StaffAccountController.php`, `SystemSecurityController.php`, `app/Services/SystemSecurity/*`, `routes/web.php` |
| Donor management | `donors`, `contributions`, `contribution_items`, `contribution_evidence` | `DonorController.php`, `DonationPortalController.php`, donor/contribution models and requests |
| Dashboard and reports | `contributions`, `donors`, `contribution_items`, `inventory_items`, `inventory_stock_movements`, `bank_ledger_entries`, `bank_reconciliations`, `cash_disbursements`, `security_activity_events` | `app/Support/Dashboard/DashboardMetrics.php`, `app/Services/Reports/*`, `ReportController.php` |
| Inventory | `inventory_items`, `inventory_categories`, `inventory_subcategories`, `inventory_units`, `inventory_stock_movements`, `contribution_items` | `app/Http/Controllers/Inventory*Controller.php`, `app/Services/Inventory/*` |
| Bank reconciliation | `bank_reconciliations`, `bank_ledger_entries`, `reconciliation_discrepancies`, `bank_withdrawal_disbursement_matches`, plus `contributions` and `cash_disbursements` | `app/Services/BankReconciliation/*`, `BankReconciliationController.php` |
| Disbursement | `cash_disbursements`, `cash_disbursement_payments`, `disbursement_vouchers`, `dv_number_sequences`, `bank_withdrawal_disbursement_matches` | `DisbursementController.php`, `app/Services/Disbursements/*`, disbursement routes |
| Legacy/compatibility | `legacy_donors`, `legacy_inventory_items`, `donations`, `cash_transactions`, `campaigns` | migration history, `app/Models/Donation.php`, and absence/presence analysis described below |

## 6. Unused, duplicate, legacy, and overlapping structures

### 6.1 `donations` versus `contributions`

`donations` is the original simple donation table: donor, type, amount, description, receipt time. The active workflow uses `contributions`, which supports workspace ownership, receipt numbers, verification, payment details, evidence, certificates, voiding, in-kind items, and reconciliation.

Evidence:

- Old table: `database/migrations/2026_06_29_000002_create_donations_table.php`.
- Current table: `database/migrations/2026_07_12_000003_create_contributions_table.php` and later additions.
- Old model still present: `app/Models/Donation.php`.
- Current usage: `app/Models/Contribution.php`, `DonationPortalController.php`, contribution services, reports, and routes.

Risk: two concepts can be mistaken for the canonical donation ledger. A future developer could write to `donations` and bypass workspace isolation, verification, evidence, voiding, and bank matching.

Classification: `donations` is **Possibly Unused / overlapping**, not safe to remove without a data inventory and explicit compatibility decision.

### 6.2 `cash_transactions` versus cash contributions

`cash_transactions` stores a denormalized `donor_name`, amount, purpose, status, and notes. It has no donor FK, workspace FK, audit actors, evidence, or reconciliation linkage. Current cash donation behavior is implemented on `contributions` and its document fields.

Evidence: `database/migrations/2026_06_29_000005_create_cash_transactions_table.php`, `app/Models/CashTransaction.php`, versus `Contribution.php`, `DonationPortalController.php`, and `app/Services/Contributions/*`.

Classification: **Possibly Unused**. It is a strong candidate for a future archive/removal plan after verifying production row counts and backups.

### 6.3 `campaigns` and supporting references

`campaigns` was created with a `contributions.campaign_id` FK. The later migration named `remove_campaigns_and_supporting_references` is intentionally a no-op, explicitly retaining the schema and historical rows. Current routes/controllers do not expose campaign management, and the `Contribution` model has no `campaign()` method.

Classification: **Needs Verification / dormant**. Either document it as a retained historical dimension or complete a future, data-preserving deprecation plan. Do not drop it based only on current route absence.

### 6.4 Legacy snapshots

`legacy_donors` and `legacy_inventory_items` are intentionally produced by `preserve_legacy_tables` and consumed by import/backfill migrations. No current model maps to them. They are **Legacy**, not unused accidental tables.

### 6.5 Denormalized inventory classifications

`inventory_items.category` remains a string even though `inventory_categories` and `inventory_subcategories` exist; `inventory_items.inventory_subcategory_id` is optional. `contribution_items.category` and `subcategory` are also strings rather than FKs to the reference tables. This supports historical/free-form donation descriptions but allows spelling, label, and category-parent drift.

Evidence: `InventoryItem.php`, `ContributionItem.php`, `InventoryCategory.php`, `InventorySubcategory.php`, and the inventory migrations.

Classification: active functionality with a **Needs Verification** data-quality boundary.

## 7. Findings, risks, and missing/redundant relationships

### Finding A — workspace ownership is nullable on most business tables

`add_workspace_ids_to_business_tables` adds nullable `workspace_id` and a `nullOnDelete` FK to donors, campaigns, contributions, items, inventory, banking, reconciliation, matching, and security activity tables. The workspace trait scopes only when a context exists and fills the ID only during model creation.

Risk: a row with `workspace_id = NULL` is not visible to a workspace-scoped query, and direct queries or background jobs without a workspace context can bypass tenant boundaries. The backfill issue table confirms that ownership backfill was a real migration concern.

Safe recommendation: treat `workspace_id` as mandatory in the target design after an audited backfill; add runtime checks for all writes and an explicit system/global scope policy for administrative queries. Do this through a staged migration, not an immediate constraint change.

### Finding B — contribution-to-campaign relationship is missing in the model

The database retains `contributions.campaign_id → campaigns.id`, but `Contribution` has no `campaign()` relationship and no current campaign workflow was found.

Risk: the FK is easy to overlook, and campaign rows can become orphan-like from the application perspective even though referential integrity exists.

Safe recommendation: either add/document the relationship if historical reporting needs it, or formally mark the column/table deprecated and plan a data-preserving archive. No deletion is recommended in this audit.

### Finding C — incomplete inverse relationships for core reconciliation/disbursement navigation

`BankReconciliation` defines `discrepancies()` but no explicit relationship to ledger entries because ledger rows are not directly tied to a reconciliation period. `BankLedgerEntry` has match/discrepancy relationships but no inverse `workspace` method in its class (it inherits it from the trait). `CashDisbursement` has no explicit `matches()` collection, only the singular `withdrawalMatch()`.

Risk: the database graph is less discoverable than the actual workflow, and future queries may use ad hoc joins or incorrect cardinality assumptions.

Safe recommendation: document cardinalities and add inverse model relationships only where they represent existing FKs; do not invent a period-to-ledger FK without deciding whether a ledger row can belong to multiple reconciliation periods.

### Finding D — reconciliation discrepancy uses an unenforced polymorphic reference

`system_record_type` + `system_record_id` is indexed but cannot be protected by a normal FK. The model exposes `systemRecord()` through `morphTo`.

Risk: invalid type names, missing records, cross-workspace references, or deleted target rows can persist silently.

Safe recommendation: constrain allowed morph types in application code, validate target workspace equality, and retain a human-readable `system_record_label` or evidence reference for audit continuity. Consider explicit nullable FKs if the target set stabilizes.

### Finding E — duplicated state on `cash_disbursements` and payment/voucher tables

`cash_disbursements` stores `payment_status` and `disbursed_at`, while `cash_disbursement_payments` stores the payment date, amount, method, releasing user, and release timestamp. Similarly, `signed_voucher_status` and request fields coexist with a `disbursement_vouchers` table containing review status and review actors.

Risk: statuses can disagree. For example, a payment row may exist while `payment_status` remains `not_disbursed`, or a latest voucher review state may differ from the denormalized disbursement status.

Safe recommendation: define the child records as the source of truth; keep parent status as a derived/cache field only if workflows require fast filtering, with a single service transaction updating both and reconciliation checks reporting mismatches.

### Finding F — duplicated donor identity/address fields

`donors` contains `full_name` plus first/middle/last/suffix, and both `address`/`barangay_area` plus structured `barangay`, `city_municipality`, `province`, and `region`. Contributions also store `donor_tin`, while donors store `tin`.

Risk: names, addresses, and tax identifiers can diverge. The migration itself backfills `barangay` from `barangay_area`, which indicates historical duplication.

Safe recommendation: retain legacy columns during transition, designate structured identity fields as canonical, derive display name, and define whether `donor_tin` is a historical snapshot required on a receipt or an accidental duplicate. If receipts require historical legal identity, keep the contribution snapshot but name/document it as such.

### Finding G — monetary totals are stored at multiple levels

Examples include `contributions.amount`, `contribution_items.estimated_value`, `inventory_items.estimated_unit_value`, `cash_disbursements.amount`, `cash_disbursement_payments.amount`, and many bank/reconciliation calculated totals.

Risk: totals may differ without an explicit accounting rule. In particular, contribution amount versus sum of item estimated values is not necessarily equal, but the distinction is not encoded.

Safe recommendation: document each amount as transaction amount, estimate, authorized amount, released amount, or calculated report value. Add validation/reporting for expected relationships; do not collapse these columns without business confirmation.

### Finding H — uniqueness and null semantics require verification

`contributions` has workspace-scoped uniqueness on `(workspace_id,payment_method,reference_number)`, while `receipt_number` remains globally unique. `cash_disbursements.dv_number` is unique per workspace. MySQL permits multiple NULLs in ordinary unique indexes, which may be intended but should be explicit.

Risk: reference uniqueness may not prevent duplicate blank/NULL references, and receipt numbering may be global while other document numbering is workspace-local.

Safe recommendation: confirm numbering scope and whether blank references should be allowed; enforce non-null only after data profiling.

### Finding I — migration history has same-timestamp filenames

There are two migrations named with timestamp prefix `2026_10_28_000001` and two with `2026_10_29_000006`, including separate donor/contribution changes. Laravel can execute both because the full filenames differ, but timestamp assumptions in deployment tooling or human review are weaker.

Evidence: `database/migrations/2026_10_28_000001_create_contribution_evidence_table.php`, `2026_10_28_000001_remove_campaigns_and_supporting_references.php`, `2026_10_29_000006_add_donor_identity_fields.php`, `2026_10_29_000006_add_region_to_donors_table.php`.

Safe recommendation: leave applied migrations immutable; for future migrations use unique monotonic names and record the intended ordering in release notes.

## 8. Recommended cleaner target structure

This is a logical target, not an instruction to alter the current database immediately.

1. Keep one canonical donation aggregate: `donors`, `contributions`, `contribution_items`, and `contribution_evidence`. Treat `donations` and `cash_transactions` as historical compatibility sources only. Add a deprecation note and a data-migration/archival plan before any removal.

2. Keep disbursement lifecycle separate from payment facts and documents:
   - `cash_disbursements`: request, approval, void, payee, and authorization facts.
   - `cash_disbursement_payments`: released payment fact, one or many depending on confirmed business rules.
   - `disbursement_vouchers`: uploaded/reviewed document facts.
   - `dv_number_sequences`: number allocation.
   Define one source of truth for each status and derive parent statuses where possible.

3. Make workspace ownership explicit for all tenant business records. Use a staged approach: audit nulls, repair/backfill, reject new nulls, then add non-null constraints and workspace-aware unique indexes. Preserve global platform tables (`users`, `roles`, permissions) separately.

4. Normalize reference data gradually. Keep free-text category snapshots on historical `contribution_items` if required for audit, but use explicit nullable reference IDs for new inventory and contribution classification where the business needs reporting consistency. Preserve the original submitted label as a snapshot rather than overloading a canonical category field.

5. Consolidate donor profile representation. Use structured name/address fields as canonical; keep `full_name` and legacy `barangay_area` only as compatibility/snapshot fields until consumers are migrated. Keep contribution-level tax identity only if it is intentionally a receipt-time snapshot.

6. Keep reconciliation as a separate bounded subsystem: reconciliation periods, bank ledger entries, discrepancies, and explicit links to contributions/disbursements. Decide whether ledger rows are period-owned, date-selected, or can participate in multiple periods before introducing any new FK.

7. Replace or constrain polymorphic discrepancy targets if the target types become stable. Until then, enforce an allow-list and workspace consistency at the service layer.

8. Add schema documentation/tests for cardinality and invariants: one payment per disbursement (if still required), one bank withdrawal per disbursement, one contribution match per ledger entry, voucher review consistency, workspace equality across joins, and no active writes to deprecated tables.

## 9. Prioritized safe action plan

### Priority 1 — verification without schema changes

- Inventory row counts and recent writes for `donations`, `cash_transactions`, `campaigns`, `legacy_donors`, and `legacy_inventory_items` in a permitted environment.
- Confirm whether any external reporting, scheduled job, or integration reads the legacy tables.
- Compare `cash_disbursements.payment_status`/`disbursed_at` with `cash_disbursement_payments` and compare voucher parent status with latest voucher review status.
- Find all rows with null `workspace_id` and all cross-workspace references.
- Confirm whether `donor_tin` is a receipt snapshot or duplicate profile data.

### Priority 2 — documentation and application guardrails

- Declare canonical tables in developer documentation and code comments.
- Add service-level checks preventing new writes to deprecated tables.
- Validate polymorphic discrepancy targets and workspace ownership.
- Add consistency diagnostics for duplicated parent/child statuses and monetary totals.

### Priority 3 — staged schema cleanup, only after evidence

- Backfill and make workspace IDs non-null where safe.
- Migrate remaining consumers from legacy donation/cash tables.
- Decide campaign retention or archival.
- Remove only after a documented backup, export, rollback strategy, and verified absence of consumers.

## 10. Final classification summary

| Classification | Tables |
|---|---|
| Active | `users`, `roles`, `workspaces`, `workspace_memberships`, `workspace_backfill_issues`, security tables, `donors`, `contributions`, `contribution_items`, `contribution_evidence`, inventory tables, `bank_reconciliations`, `bank_ledger_entries`, `reconciliation_discrepancies`, `cash_disbursements`, `cash_disbursement_payments`, `disbursement_vouchers`, `bank_withdrawal_disbursement_matches`, `dv_number_sequences`, Laravel infrastructure tables |
| Possibly Unused | `donations`, `cash_transactions` |
| Legacy | `legacy_donors`, `legacy_inventory_items` |
| Needs Verification | `campaigns`; duplicated/denormalized fields within `donors`, `inventory_items`, `contribution_items`, `bank_reconciliations`, and `cash_disbursements`; polymorphic discrepancy references |

## 11. Audit conclusion

The current schema supports the observed active workflows and has a coherent relational backbone. The primary cleanup opportunity is not an immediate table deletion; it is establishing canonical ownership and source-of-truth rules across historical donation tables, denormalized profile/classification fields, workspace IDs, reconciliation references, and disbursement child records. A staged verification-first approach preserves required functionality while reducing the chance of data loss or cross-workspace/reporting inconsistencies.
