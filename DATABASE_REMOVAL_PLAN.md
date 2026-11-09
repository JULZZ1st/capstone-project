# Database Removal Plan

## Scope and safety

This is a read-only removal plan based on the existing project MySQL database, the migration source of truth, `DATABASE_ARCHITECTURE_AUDIT.md`, and `DATABASE_CLEANUP_RESULTS.md`. No rows, tables, columns, migrations, or application files were modified. No migration was run against the existing project database.

The inspected connection was the configured MySQL connection in `.env` (`DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, database `barangay_donation_system`). The connection was successful. The observed server was MySQL 8-compatible and reported by Laravel as MySQL 26.7.0. The existing database contained 43 tables and approximately 12.31 MB.

## Evidence from the existing database

Exact read-only counts for the cleanup candidates and their related tables:

| Table | Rows | FK evidence | Current conclusion |
|---|---:|---|---|
| `donations` | 0 | `donations.donor_id` references `legacy_donors.id` | Conditional future removal candidate; no immediate drop |
| `cash_transactions` | 0 | No foreign keys observed | Conditional future removal candidate; no immediate drop |
| `campaigns` | 2 | `campaigns.workspace_id` references `workspaces.id`; inbound `contributions.campaign_id` reference | Retain |
| `contributions` | 16 | Eight FK relationships, including `campaign_id` | Active |
| `legacy_donors` | 1 | Inbound FK from `donations.donor_id` | Retain until historical provenance/retention is approved |
| `legacy_inventory_items` | 0 | Inbound FK from `inventory_stock_movements.inventory_item_id`; also references users/subcategories | Retain until the movement FK is migrated or retired |
| `inventory_items` | 2 | Active inventory table | Active |
| `inventory_stock_movements` | 0 | FK to `legacy_inventory_items.id` in the existing database | Needs verification; relationship prevents a safe legacy-table drop |

Campaign usage is not merely schema-only: `campaigns` rows with IDs 1 and 2 were present, and `contributions` contained one row referencing each campaign. The application also has an active `Campaign` model, contribution relationship, sample seeder usage, and the `contributions.campaign_id` migration in `database/migrations/2026_07_12_000003_create_contributions_table.php`.

The legacy inventory table is empty, but emptiness does not remove the FK dependency. The actual database reported `inventory_stock_movements.inventory_item_id -> legacy_inventory_items.id`, so dropping `legacy_inventory_items` before changing that relationship would be unsafe even if no movement rows currently exist.

The legacy donor table contains one row and is referenced by the empty `donations` table. It is therefore not an empty, isolated artifact.

## Application dependency evidence

### `donations`

The route name and UI term “donations” refer to the active `contributions` workflow, not the physical `donations` table. Evidence includes:

- `routes/web.php:51-60` routes donation operations to `DonationPortalController`.
- `app/Http/Controllers/DonationPortalController.php` queries and receives `Contribution` models.
- `app/Models/Contribution.php` is the active donation record model.
- `tests/Feature/DashboardMetricsTest.php:212` explicitly verifies that dashboard totals ignore legacy `cash_transactions` and `donations` tables.
- `database/migrations/2026_06_29_000002_create_donations_table.php` and `app/Models/Donation.php` are historical/schema artifacts, not evidence of active production-table use.

Recommendation: keep the table during the capstone. It is a possible later removal candidate only after confirming that no external report, import, SQL view, scheduled job, or deployment script uses it.

### `cash_transactions`

The table has zero rows and no observed foreign keys. Static references are limited to historical migration/model/test context; current donation and reconciliation code uses `contributions`, `bank_ledger_entries`, and related active tables. The explicit regression assertion in `tests/Feature/DashboardMetricsTest.php:212` supports that it is not a source for current dashboard totals.

Recommendation: possible later removal candidate, but only after an external-consumer check and a backup. Do not drop it as part of the capstone without a release/deprecation step.

### `campaigns`

Retain. The live database has two rows and two live contribution references. Removing the table would require first preserving campaign history, migrating or nulling `contributions.campaign_id`, removing the FK and indexes in a new migration, and updating seeders/tests/reporting. This is not safe cleanup.

### `legacy_donors`

Retain. It contains one row and is the parent of the physical `donations.donor_id` FK. Even if the old donation table is later retired, the row may be historical/import provenance. Removal requires a documented archival decision and verification that the original-ID mapping is no longer needed.

### `legacy_inventory_items`

Retain for now. It has zero rows, but the live FK graph includes `inventory_stock_movements.inventory_item_id -> legacy_inventory_items.id`. The migration history in `database/migrations/2026_06_28_000001_preserve_legacy_tables.php` also explicitly preserves legacy table names. Before removal, determine whether the movement table was intended to point to the renamed active table or whether the historical FK is an unresolved migration defect.

## Duplicate and redundant columns

No redundant column is safe to remove based on this inspection alone.

| Column(s) | Evidence | Risk | Recommendation |
|---|---|---|---|
| `contributions.campaign_id` | Two non-null live references, plus FK/index | High | Retain |
| `cash_disbursements.signed_voucher_status` and `disbursement_vouchers.review_status` | Parent table has 18 rows with `signed_voucher_status = missing`; voucher table currently has 0 rows. Application synchronization fixes exist in `app/Services/Disbursement/ApproveDisbursement.php` and `RejectDisbursement.php` | High; separate lifecycle states may be intentional | Retain; define canonical semantics before any consolidation |
| `cash_disbursements.payment_status`, `disbursed_at`, and `cash_disbursement_payments` | Parent has 18 `not_disbursed` rows; payment table has 0 rows; payment records are an auditable child fact | High | Retain; add consistency checks before considering a derived-field reduction |
| `inventory_items.quantity` and `current_quantity` | Both are non-null, different types (`int` and `decimal(12,2)`), and may represent opening versus current stock | High | Retain until domain semantics and all movement code are confirmed |
| `bank_reconciliations.date` and `period_start`/`period_end`; `cash_in`/`cash_out` and calculated/ledger values; `closing_balance` and ending balances | All are present in the live schema and support historical/reporting states | High | Retain; normalize only through a staged reporting-compatible migration |
| `reconciliation_discrepancies.system_record_type/system_record_id` and bank-entry IDs | Discrepancy workflow uses explicit bank ledger references and resolution evidence; these fields support historical linkage | High | Retain; improve validation rather than delete fields |
| nullable `workspace_id` fields | Live counts for sampled active tables (`reconciliation_discrepancies`, `bank_reconciliations`, `inventory_items`, `cash_disbursements`) were all zero-null, but that is not proof for every table or historical import | High | Do not drop/nullability-change yet; complete a table-wide audit and staged constraint migration |

The live discrepancy data had four rows: three `resolved`/`accepted` and one `unresolved`/`pending`. This is active audit history, not redundant data. It should be preserved.

## Candidate classification

| Candidate | Classification | Safe to remove now? | Conditions for a future removal |
|---|---|---|---|
| `donations` | Possibly Unused | No | Confirm no external consumers; archive schema/rows; deploy code cleanup; new drop migration |
| `cash_transactions` | Possibly Unused | No | Confirm no external consumers; archive schema/rows; deploy code cleanup; new drop migration |
| `legacy_inventory_items` | Legacy / Needs Verification | No | Resolve or migrate `inventory_stock_movements` FK; preserve migration history; backup and restore-test |
| `legacy_donors` | Legacy | No | Archive one row and its provenance; remove dependent `donations` FK/table only in a coordinated release |
| `campaigns` | Active | No | No removal planned; campaign history is live |
| Listed redundant columns | Active or Needs Verification | No | Establish canonical source, backfill/dual-write, monitor, then remove only in a later major cleanup |

## Required backups and preconditions

Before any future removal, the owner should:

1. Take and verify a full logical backup of the database, including routines, triggers, and schema.
2. Take a separate table-level export of every candidate and all dependent rows. At minimum this includes `donations`, `legacy_donors`, `cash_transactions`, `legacy_inventory_items`, `inventory_stock_movements`, `campaigns`, and `contributions`.
3. Restore the backup into a scratch MySQL database and verify row counts, foreign keys, application boot, reports, exports, and rollback procedures.
4. Search deployment scripts, scheduled jobs, reporting tools, stored routines, views, and integrations outside this repository for candidate table/column names.
5. Record the pre-change row counts and FK definitions in the release ticket.

Example backup shape (planning only; not executed):

```text
mysqldump --single-transaction --routines --triggers barangay_donation_system \
  donations cash_transactions legacy_donors legacy_inventory_items \
  inventory_stock_movements campaigns contributions > candidate-tables.sql
```

## Safe migration sequence

### Phase 1: evidence and deprecation

1. Approve the candidate scope and retention policy.
2. Add monitoring/logging for any runtime query or import that still touches a candidate.
3. Remove only obsolete application model/fixture references in a normal code release, after confirming historical migration replay requirements.
4. Do not edit any previously applied migration.

### Phase 2: isolated validation

1. Create a separate MySQL test database.
2. Apply the complete migration history plus the proposed new migration.
3. Restore a representative backup copy, including historical rows and FK relationships.
4. Run the full Laravel test suite and Playwright CRUD/workflow checks for Donations, Inventory, Fund Requests, Bank Reconciliation, Dashboard, Reports, and Admin.

### Phase 3: production migration for zero-row candidates only

Only after the above gates pass, create a new timestamped migration. For `cash_transactions` and potentially `donations`, the migration should explicitly verify the expected table/FK state and fail safely if it differs. The deployment must include a tested restore/rollback procedure. A reversible rollback should restore the table from backup or use a documented reverse migration; it must not rely on resetting the database.

`legacy_inventory_items` must not be included until the `inventory_stock_movements` FK is intentionally migrated or removed in a separately reviewed change. `campaigns`, `legacy_donors`, and all redundant columns are out of scope for this phase.

## Go / no-go decision

**No table or column is approved for immediate removal.**

The lowest-risk future candidates are `cash_transactions`, then `donations`, because both are empty and not used by current production workflows. They still require external-consumer confirmation, backup, restore testing, and a new migration. `legacy_inventory_items` is not currently safe because the live FK graph references it. `legacy_donors` contains data. `campaigns` is active and referenced by live contributions.

The safest capstone action is to retain the existing schema, document the candidates, and defer physical deletion to a separately approved release with production backup and rollback controls.

## Evidence files

- `DATABASE_ARCHITECTURE_AUDIT.md` — migration-derived inventory and initial classification.
- `DATABASE_CLEANUP_RESULTS.md` — prior MySQL test-database, migration, test-suite, and workflow verification results.
- `database/migrations/2026_06_28_000001_preserve_legacy_tables.php` — legacy-table preservation behavior.
- `database/migrations/2026_06_29_000002_create_donations_table.php` — historical `donations` table definition.
- `database/migrations/2026_06_29_000005_create_cash_transactions_table.php` — historical `cash_transactions` table definition.
- `database/migrations/2026_07_12_000003_create_contributions_table.php` — active contribution/campaign FK and indexes.
- `app/Models/Contribution.php` — active contribution model and campaign relationship.
- `app/Models/Campaign.php` — active campaign model.
- `app/Http/Controllers/DonationPortalController.php` — active donation portal backed by contributions.
- `tests/Feature/DashboardMetricsTest.php` — regression evidence that legacy donation/cash tables are not dashboard sources.
- `app/Services/Disbursements/ApproveDisbursement.php` and `app/Services/Disbursements/RejectDisbursement.php` — current voucher-status synchronization behavior.
