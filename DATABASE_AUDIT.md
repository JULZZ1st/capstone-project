# Database Usage Audit

Audit date: 2026-11-07  
Branch reviewed: `gina`  
Scope: repository code, routes, controllers, services, models, views, tests, migrations, seeders, framework configuration, and attempted live database metadata access.

## Executive summary

The application has a current workspace-aware donation, inventory, disbursement, security, and bank-reconciliation model. The main current business tables are referenced by Eloquent models, controllers, services, reports, routes, views, seeders, and feature tests.

There are also clear historical or compatibility tables:

- `donations` and `cash_transactions` are older donation representations. Current dashboard tests deliberately prove that they are ignored.
- `campaigns` is retained by an intentional no-op migration even though campaign controls are no longer exposed.
- `legacy_donors`, `legacy_inventory_items`, `cash_donations`, `in_kind_donations`, and `inventory_transactions` are referenced as migration-time import sources, not by current application workflows.
- `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, and `password_reset_tokens` are framework tables. Their importance depends partly on deployment configuration and operational usage, not only on application code references.
- `workspace_backfill_issues` is a maintenance/diagnostic table used by the workspace backfill service and repair tooling, but it is not part of the normal end-user transaction flow.

The live database could not be inspected. `php artisan db:show --counts --views` did not complete within approximately 60 seconds and was interrupted. No schema dump or checked-in SQL database snapshot was available. Therefore this report does **not** claim that every repository-inferred table exists in the configured database, and it does not provide row counts. Live existence, row counts, foreign keys, triggers, procedures, functions, views, and drift from migrations must be verified against an accessible database before any cleanup decision.

No database, application code, or migration was modified by this audit. Only this report was added.

## Evidence and method

Reviewed sources included:

- `app/Models`, including all 25 model classes.
- `app/Http/Controllers`, `app/Services`, `app/Support`, `app/Policies`, middleware, requests, and console commands.
- `routes/web.php` and `routes/api.php`.
- `resources/views` and frontend-facing route/controller behavior.
- `database/migrations`, `database/seeders`, and factories.
- Feature tests, especially workflow, dashboard, inventory, donation, security, workspace, and reconciliation tests.
- `config/session.php`, `config/cache.php`, `config/queue.php`, and `config/psgc.php`.
- Static searches for raw SQL, `DB::table`, joins, model table overrides, foreign keys, triggers, views, functions, procedures, and `DB::statement`.

The audit distinguishes:

- **Active** — current application or framework behavior has a direct, meaningful dependency.
- **Possibly Unused** — referenced by maintenance/framework configuration or tests, but normal production use is not established from static evidence.
- **Legacy** — historical representation or migration source that current workflows do not use.
- **Requires Further Investigation** — cannot be safely classified without live schema/row/dependency evidence.

“No current code reference” does not mean “safe to remove.” Foreign keys, scheduled jobs, reports, external integrations, manually run commands, audit retention, and historical data can all be dependencies.

## Database overview

### Expected database areas

| Area | Current tables |
|---|---|
| Laravel/framework | `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations` |
| Identity and workspaces | `roles`, `workspaces`, `workspace_memberships` |
| Security administration | `security_permissions`, `security_permission_user`, `user_security_profiles`, `security_activity_events` |
| Donors and contributions | `donors`, `contributions`, `contribution_items`, `contribution_evidence` |
| Inventory | `inventory_categories`, `inventory_subcategories`, `inventory_units`, `inventory_items`, `inventory_stock_movements` |
| Disbursements | `cash_disbursements`, `disbursement_vouchers`, `cash_disbursement_payments`, `dv_number_sequences` |
| Bank reconciliation | `bank_ledger_entries`, `bank_reconciliations`, `reconciliation_discrepancies`, `bank_withdrawal_disbursement_matches` |
| Address reference data | `regions`, `provinces`, `cities`, `barangays` |
| Historical/compatibility candidates | `campaigns`, `donations`, `cash_transactions`, `legacy_donors`, `legacy_inventory_items`, `cash_donations`, `in_kind_donations`, `inventory_transactions` |
| Maintenance diagnostics | `workspace_backfill_issues` |

The first 36 business/framework tables in the table inventory are created or altered by repository migrations. The four PSGC tables are configured and seeded by `PSGCSeeder` and the PSGC package. `migrations` is Laravel's migration ledger and is normally created by the migrator. The five legacy source names are referenced defensively by import/preservation migrations and may or may not still exist in the live database.

## Complete repository-inferred table inventory

Live existence and row counts are marked **Unknown — live database unavailable** for every row below. No record contents were read or exposed.

| Table | Purpose in simple words | Application/reference evidence | Classification | Record status | Removal risk |
|---|---|---|---|---|---|
| `users` | Login accounts and staff identities | `User` model; auth controllers; nearly every business audit foreign key | Active | Unknown | Critical: login, ownership, approvals, audit history |
| `roles` | Named user roles such as admin and staff | `Role` model; authorization; workspace memberships | Active | Unknown | Critical: permissions and membership integrity |
| `workspaces` | Separate organization/workspace contexts | `Workspace` model; active-workspace middleware; workspace services | Active | Unknown | Critical: tenant isolation and data ownership |
| `workspace_memberships` | Which users belong to which workspaces and with what role | Model relationships; auth/workspace middleware; backfill service | Active | Unknown | Critical: access control and isolation |
| `workspace_backfill_issues` | Records unresolved workspace assignment problems | `WorkspaceBackfill`; repair/diagnostic command; workspace tests | Possibly Unused | Unknown | Medium/high: removes migration diagnostics and unresolved-data evidence |
| `donors` | Donor profiles and contact information | `Donor` model; donor routes/controller; contribution relationship; reports | Active | Unknown | Critical: donor history and contribution foreign keys |
| `contributions` | Current donation records for cash and in-kind donations | `Contribution` model; donation portal; reports; reconciliation; extensive tests | Active | Unknown | Critical: primary donation ledger |
| `contribution_items` | Individual items inside in-kind contributions | Model relationship; donation portal; inventory/report queries | Active | Unknown | Critical: in-kind details and report totals |
| `contribution_evidence` | Uploaded donation documents and supporting evidence | `ContributionEvidence`; upload/download routes; donation views/tests | Active | Unknown | High: legal/supporting documents and audit trail |
| `inventory_categories` | Main inventory group labels | Model; inventory UI; seed/config; contribution item selectors | Active | Unknown | High: inventory classification and item forms |
| `inventory_subcategories` | More specific inventory classifications | Model; inventory items and contribution item relationships; backfill | Active | Unknown | High: item classification and existing foreign keys |
| `inventory_units` | Units such as pieces, boxes, or kilograms | Model; inventory UI/config/seeders/tests | Active | Unknown | Medium/high: quantity display and validation |
| `inventory_items` | Current stock items and quantities | Model; inventory controller/services/reports | Active | Unknown | Critical: stock balances and inventory workflow |
| `inventory_stock_movements` | History of stock receipts, releases, and adjustments | Model; inventory services; reports; tests | Active | Unknown | Critical: stock audit trail and balance reconstruction |
| `cash_disbursements` | Fund requests and approved/rejected payments | Model; disbursement controller/services/views; reconciliation | Active | Unknown | Critical: outgoing funds and approval history |
| `disbursement_vouchers` | Signed voucher documents/review records for disbursements | Model; voucher upload/review workflow; tests | Active | Unknown | High: payment documentation and compliance evidence |
| `cash_disbursement_payments` | Actual release/payment record for a disbursement | Model; record-payment service; disbursement workflow/tests | Active | Unknown | Critical: payment status and release history |
| `dv_number_sequences` | Per-workspace sequence used to generate voucher numbers | Model; `GenerateDvNumber` service | Active | Unknown | High: duplicate or invalid voucher numbers |
| `bank_ledger_entries` | Imported bank statement transactions | Model; import/matching/reconciliation services; reports | Active | Unknown | Critical: bank reconciliation source ledger |
| `bank_reconciliations` | Reconciliation periods and balances | Model; bank reconciliation controller/service/views/tests | Active | Unknown | Critical: period close and financial controls |
| `reconciliation_discrepancies` | Differences that need review or resolution | Model; discrepancy service/controller/views/tests | Active | Unknown | Critical: unresolved financial exceptions and audit evidence |
| `bank_withdrawal_disbursement_matches` | Links bank withdrawals to fund requests | Model; matching service; bank ledger relationships/tests | Active | Unknown | Critical: prevents incorrect outgoing-payment matching |
| `security_permissions` | Fine-grained permissions | Model; security seeder and administration UI | Active | Unknown | High: authorization behavior |
| `security_permission_user` | User-to-permission many-to-many links | Model relationship; security administration code | Active | Unknown | High: direct permission assignments |
| `user_security_profiles` | Per-user security settings such as two-factor enforcement | Model; security services/controllers | Active | Unknown | High: account security policy |
| `security_activity_events` | Security and business action audit events | Model; audit service/report; disbursement history; backfill | Active | Unknown | Critical: compliance/audit history |
| `regions` | Philippine region reference values | PSGC config/seeder; location API/controller; donor forms/tests | Active | Unknown | Medium/high: address selectors and validation |
| `provinces` | Province reference values | PSGC seeder; location API/controller; donor forms/tests | Active | Unknown | Medium/high: address selectors and validation |
| `cities` | City/municipality reference values | PSGC seeder; location API/controller; donor forms/tests | Active | Unknown | Medium/high: address selectors and validation |
| `barangays` | Barangay reference values | PSGC seeder; location API/controller; donor forms/tests | Active | Unknown | Medium/high: address selectors and validation |
| `sessions` | Browser login/session state when database sessions are configured | `config/session.php`; `.env.example` defaults to database sessions | Requires Further Investigation | Unknown | High if `SESSION_DRIVER=database`; low if deployment overrides it |
| `cache` | Application cache entries when database cache is configured | `config/cache.php`; `.env.example` defaults to database cache | Requires Further Investigation | Unknown | Medium: cache misses or startup/runtime failures if configured |
| `cache_locks` | Locks used by the database cache store | Laravel cache configuration | Requires Further Investigation | Unknown | Medium: locking/concurrency failures if configured |
| `jobs` | Queued jobs when database queue is configured | `config/queue.php`; `.env.example` defaults to database queue | Requires Further Investigation | Unknown | High if queue workers use database queues |
| `job_batches` | Batch metadata for queued job batches | Laravel queue configuration | Possibly Unused | Unknown | Medium/high: batch tracking; live worker usage must be checked |
| `failed_jobs` | Failed queue job records | Laravel queue configuration and framework operations | Possibly Unused | Unknown | Medium: operational failure history and retries |
| `password_reset_tokens` | Password-reset tokens | Laravel auth schema; no active reset route was established in static tracing | Possibly Unused | Unknown | Medium: password reset feature may depend on it |
| `migrations` | Which migrations have run | Laravel migration system | Active | Unknown | Critical: migration tracking and deployment safety |
| `campaigns` | Historical donation campaign metadata | Created early; later migration says campaign workflows are no longer exposed and intentionally preserves schema/data | Legacy | Unknown | High until campaign FK/data and reporting history are verified |
| `donations` | Older donation record format | `Donation` model and old migration; current portal uses `Contribution` instead | Legacy | Unknown | High if old records, reports, or external scripts still depend on it |
| `cash_transactions` | Older cash transaction format | `CashTransaction` model; dashboard regression test explicitly verifies it is ignored | Legacy | Unknown | High if historical data is needed; low for current totals only |
| `legacy_donors` | Old donor source retained for migration/backfill | Referenced only by donor import/backfill migrations | Legacy | Unknown | High if not archived/exported; low current runtime evidence |
| `legacy_inventory_items` | Old inventory source retained for import | Referenced only by legacy inventory import migration | Legacy | Unknown | High if old inventory provenance is required |
| `cash_donations` | Old cash donation import source | Referenced by legacy donation import migration only | Legacy | Unknown | High if historical source data must be retained |
| `in_kind_donations` | Old in-kind donation import source | Referenced by legacy donation import migration only | Legacy | Unknown | High if historical source data must be retained |
| `inventory_transactions` | Old inventory transaction source | Referenced by legacy inventory import migration only | Legacy | Unknown | High if stock-history provenance is required |

### Classification counts

These counts are based on repository evidence, not live schema verification:

- Active: 30
- Possibly Unused: 4
- Legacy: 8
- Requires Further Investigation: 4

The counts include `migrations` and the configured/framework tables. They do not assert that all legacy names still exist in the live database.

## Usage tracing by application layer

### Frontend and routes

The current web UI exposes donor management, the donations portal, inventory, disbursements, reports, bank reconciliation, security administration, workspace selection, and location lookup. These routes resolve to the current Eloquent models and therefore depend on the Active tables above.

Notable current route-backed flows:

- Donations: `routes/web.php` routes named `donations.*` call `DonationPortalController` and use `Contribution`, `ContributionItem`, `ContributionEvidence`, `Donor`, and related users.
- Disbursements: controller/service flows use `CashDisbursement`, `DisbursementVoucher`, `CashDisbursementPayment`, `DvNumberSequence`, and `SecurityActivityEvent`.
- Reconciliation: controller/service flows use `BankReconciliation`, `BankLedgerEntry`, `ReconciliationDiscrepancy`, `BankWithdrawalDisbursementMatch`, `Contribution`, and `CashDisbursement`.
- Locations: `LocationController` reads `regions`, `provinces`, `cities`, and `barangays` for donor address controls.
- Security: security controllers/services use user profiles, permissions, roles, and activity events.

The frontend contains no evidence that it directly reads `donations`, `cash_transactions`, or the migration-only legacy sources.

### Models and relationships

The model layer contains current models for the Active business tables. Important relationship chains include:

```text
Workspace -> WorkspaceMembership -> User -> Role
Donor -> Contribution -> ContributionItem -> InventorySubcategory
Contribution -> ContributionEvidence
Contribution -> BankLedgerEntry
InventoryCategory -> InventorySubcategory -> InventoryItem -> InventoryStockMovement
CashDisbursement -> DisbursementVoucher
CashDisbursement -> CashDisbursementPayment
BankLedgerEntry <-> CashDisbursement through bank_withdrawal_disbursement_matches
BankReconciliation -> ReconciliationDiscrepancy -> BankLedgerEntry/User
User <-> SecurityPermission through security_permission_user
User -> UserSecurityProfile and SecurityActivityEvent
```

There is also a polymorphic-style dependency in `ReconciliationDiscrepancy`: `system_record_type` and `system_record_id` identify a system record without a normal foreign key. This must be checked before cleanup even if the referenced table is not obvious from a database FK listing.

### Services, reports, and maintenance commands

Current services directly query or mutate:

- `WorkspaceBackfill`: workspace assignment and diagnostic tables plus business tables.
- Contribution services: `contributions`, `contribution_items`, `contribution_evidence`, `donors`, and users.
- Inventory services: inventory categories, subcategories, units, items, and stock movements.
- Disbursement services: disbursements, vouchers, payments, voucher sequences, users, and activity events.
- Bank reconciliation services: bank ledger, periods, discrepancies, matches, contributions, disbursements, and users.
- Report builders: contributions, donors, contribution items, inventory movements, bank entries, reconciliation periods, discrepancies, and security events.

`RepairMissingBusinessTables` also inspects information-schema foreign keys and can reconstruct missing business schema when explicitly invoked with its repair option. This command is especially important to review before any removal because it encodes assumptions about expected tables and constraints.

### Tests as usage evidence

Feature tests create and query the Active tables extensively. Particularly relevant evidence:

- `tests/Feature/DashboardMetricsTest.php` creates legacy `CashTransaction` and `Donation` records and verifies that dashboard totals ignore them.
- `tests/Feature/DonationsPortalTest.php` verifies the current portal and `contribution_evidence` behavior.
- `tests/Feature/BankReconciliationTest.php`, `ReconciliationPeriodTest.php`, and `VerifiedWorkflowBugsTest.php` verify reconciliation tables and matching behavior.
- `tests/Feature/WorkspaceBackfillTest.php` verifies `workspace_backfill_issues`.
- `tests/Feature/AddressReferenceSeedingTest.php` verifies all four PSGC tables.

Tests are not proof of production traffic, but they are strong evidence of intended current schema dependencies.

## Database dependencies

### Foreign-key relationships found in migrations

The repository defines or repairs these important relationships:

- `workspace_memberships.workspace_id -> workspaces.id` with cascade behavior.
- `workspace_memberships.user_id -> users.id` with cascade behavior.
- `workspace_memberships.role_id -> roles.id` with restricted deletion.
- Business `workspace_id` columns generally point to `workspaces.id` and are nullable with null-on-delete behavior.
- `contributions.donor_id -> donors.id` is restricted on delete.
- Historical `contributions.campaign_id -> campaigns.id` is nullable with null-on-delete behavior.
- Contribution creator/verifier/certificate/document/void user fields point to `users.id` with nullable behavior.
- `contribution_items.contribution_id -> contributions.id` is restricted on delete.
- `contribution_evidence.contribution_id -> contributions.id` cascades on delete; uploader points to `users.id`.
- `inventory_subcategories.inventory_category_id -> inventory_categories.id` cascades on delete.
- Inventory item subcategory and created/updated user links are nullable.
- `inventory_stock_movements.inventory_item_id -> inventory_items.id` and creator links protect stock history according to the migration definitions.
- Disbursement requester/approver/void/certification/review links point to `users`; voucher and payment rows point to the disbursement.
- `cash_disbursement_payments.cash_disbursement_id` is unique, making payment effectively one-to-one with a disbursement.
- `dv_number_sequences.workspace_id -> workspaces.id` cascades on workspace deletion.
- Bank ledger matched contribution and user links point to `contributions` and `users`.
- Reconciliation discrepancies point to `bank_reconciliations`, `bank_ledger_entries`, and users; cleared entries are nullable.
- Bank withdrawal matches point to both `bank_ledger_entries` and `cash_disbursements` and cascade from those parents.
- Security profile/activity/permission links point to users; the permission pivot links users and permissions.

The exact live constraint names, actions, indexes, and any manually-added constraints remain unverified because the database connection did not return metadata.

### Triggers, procedures, functions, and views

Static source search found no `CREATE TRIGGER`, `CREATE VIEW`, `CREATE FUNCTION`, or `CREATE PROCEDURE` statements, and no application code that creates views or stored routines. The only raw schema statement found is a migration that repairs a contributions foreign key using `ALTER TABLE`.

This is not proof that the live database has no manually-created triggers, routines, or views. Those must be checked in the database itself using vendor-specific metadata queries after access is restored.

### Framework/runtime dependencies

The repository defaults to database-backed sessions, cache, and queue in `.env.example`:

- `sessions` may be required for authentication and active workspace state.
- `cache` and `cache_locks` may be required for application cache and lock coordination.
- `jobs`, `job_batches`, and `failed_jobs` may be required by queue workers or operational retry tooling.
- `password_reset_tokens` is required if password-reset behavior is enabled, even if its route is not currently visible in the inspected web routes.

Check the actual deployed `.env`, worker process configuration, scheduler, and infrastructure before classifying these tables as unused.

## Potentially unused, duplicate, or legacy tables

### Highest-confidence legacy candidates

1. **`donations` versus `contributions`**: both represent donations, but the current portal, models used by controllers, reports, and reconciliation are built around `contributions`. The old `Donation` model remains in the repository, so deletion is not yet safe.
2. **`cash_transactions` versus current contribution/disbursement ledgers**: this is an older cash representation. Regression tests explicitly ensure it does not affect current dashboard totals.
3. **`campaigns`**: the migration `2026_10_28_000001_remove_campaigns_and_supporting_references.php` is intentionally a no-op and says schema/history should remain intact. This is a strong legacy signal, but the table may contain historical data and `contributions` was originally linked to it.
4. **Legacy import sources**: the five `legacy_*`/old donation/inventory names are used only while importing old data. Their presence in a live database must be confirmed before considering archival or removal.

### Lower-confidence candidates

- `workspace_backfill_issues`: maintenance diagnostics, not a normal business table.
- `job_batches`, `failed_jobs`, and `password_reset_tokens`: framework tables whose usage depends on enabled features and operations.
- `sessions`, `cache`, `cache_locks`, and `jobs`: configuration-dependent and potentially critical even without a visible model.

## Risk assessment

| Risk | Tables most affected | Why it matters |
|---|---|---|
| Loss of financial history | `contributions`, `bank_ledger_entries`, `bank_reconciliations`, `reconciliation_discrepancies`, `cash_disbursements`, `cash_disbursement_payments` | Removes evidence needed for reconciliation, reporting, approvals, or audit |
| Broken referential integrity | `donors`, `users`, `workspaces`, `roles`, contribution/inventory/disbursement children | Foreign keys and application relationships can fail or orphan data |
| Incorrect totals or reports | `contributions`, `contribution_items`, `inventory_stock_movements`, bank tables | Reports derive totals from these tables, not from legacy alternatives |
| Authentication/session outage | `users`, `roles`, `sessions`, `password_reset_tokens` | Login, workspace context, or password recovery may fail |
| Security/audit gap | security tables and `security_activity_events` | Removes authorization assignments or activity history |
| Hidden external dependency | legacy and framework tables | Manual scripts, workers, exports, or integrations may not be represented in repository searches |
| Migration/deployment failure | `migrations` and expected business tables | Laravel and the repair command rely on schema state and migration history |

## Recommended cleanup plan

No cleanup is recommended or performed in this audit. Before any removal proposal:

1. Obtain a read-only connection to the target database and capture table names, engines, row counts, approximate sizes, columns, indexes, foreign keys, triggers, views, routines, and recent-write timestamps. Capture metadata only; do not export sensitive rows.
2. Confirm the actual deployment values for session, cache, queue, and password-reset configuration, plus queue workers, scheduled commands, BI/reporting jobs, exports, and integration credentials.
3. Compare live tables against the migration ledger and repository-inferred inventory. Flag live-only tables and migration-only tables separately.
4. For each legacy candidate, record row count, oldest/newest timestamps, FK references, application references, backup/export status, and whether historical retention is required.
5. Trace all dynamic table names and polymorphic references, especially `system_record_type/system_record_id`, configurable PSGC table names, and maintenance commands.
6. Prefer a non-destructive deprecation path: stop writes, archive/export with retention approval, monitor for a full business cycle, then consider a migration that removes dependencies. Do not drop a table solely because it is empty.
7. Test authentication, donation history, reports, inventory balances, disbursement approval/payment, reconciliation, security administration, queue processing, and workspace isolation against a restored copy before any schema change.

### Tables to investigate first

1. `donations`, `cash_transactions`, and `campaigns` — strongest duplicate/legacy signals, but possible historical data.
2. `legacy_donors`, `legacy_inventory_items`, `cash_donations`, `in_kind_donations`, and `inventory_transactions` — likely import remnants; verify data retention and external access.
3. `workspace_backfill_issues` — determine whether unresolved issues remain or whether the maintenance process is complete.
4. `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, and `password_reset_tokens` — verify runtime configuration and worker behavior before changing framework tables.
5. `campaign_id` and any campaign-related indexes/foreign keys on `contributions` — the table may be legacy while its residual constraint still affects current writes.

## Final conclusion

Static evidence shows a healthy current schema centered on `contributions`, workspace isolation, inventory, disbursements, and bank reconciliation, alongside retained historical schema. The most likely cleanup targets are the legacy donation/inventory representations and migration source tables, but none can be safely removed from this audit because live schema existence, record counts, foreign keys, database objects, and external consumers were unavailable.

Approval should be requested only after the live metadata phase is completed and a table-by-table archival/removal plan has been reviewed.
