# Database Cleanup and Verification Plan

**Scope:** read-only verification of issues in `DATABASE_ARCHITECTURE_AUDIT.md`
**Date:** 2026-11-09
**Database access:** unavailable; conclusions are based on migrations, application code, seeders, and tests
**Change policy:** no code, migration, schema, data, commit, or push changes were made

## 1. Executive conclusion

The original audit identified real historical layering, but the verification separates confirmed runtime behavior from design concerns:

- `donations` and `cash_transactions` have no current production controller/service/report dependency. They are still referenced by regression tests and model classes, so they should be deprecated deliberately rather than deleted.
- `campaigns` is not exposed in current routes or controllers, but it is used by `database/seeders/SampleBarangayDataSeeder.php` and retained intentionally by tests. That seeder imports `App\Models\Campaign`, but no `app/Models/Campaign.php` exists in the repository. This is a confirmed seeder dependency defect if that seeder is invoked.
- Payment and voucher statuses are duplicated, but the main workflow intentionally updates both parent and child records. Existing tests assert synchronization. This is currently a latent consistency risk, not evidence of an existing production inconsistency.
- Workspace isolation is strongly enforced for normal authenticated routes through middleware, global scopes, route model binding, policies, and explicit cross-workspace checks. The remaining risk is structural: many business-table `workspace_id` columns are nullable and direct query-builder paths can bypass Eloquent global scopes.
- Discrepancy validation is substantially stronger than a simple polymorphic design: the request allow-lists types, the controller resolves concrete models, and it checks workspace and period boundaries. A remaining validation gap is that `system_record_id` is not itself required when `system_record_type` is supplied, and the database cannot enforce the target relationship.

## 2. Verification matrix

| Issue | Verified result | Classification | Risk |
|---|---|---|---|
| `donations` active dependency | Runtime application uses `Contribution`; old `Donation` is used by a dashboard regression test only | Historical/compatibility | Low now; medium if future developers write to it |
| `cash_transactions` active dependency | No runtime app usage found; only model plus regression test fixture | Historical/compatibility | Low now; medium if mistaken for canonical cash ledger |
| `campaigns` active dependency | No current user-facing campaign routes, but sample seeder creates campaigns and tests verify retained schema; missing `Campaign` model makes seeder fragile/broken | Dormant but referenced | Medium |
| Parent payment status vs payment row | `RecordDisbursementPayment` transaction writes child payment and parent status together; duplicate prevention checks both | Design duplication, not confirmed bug | Medium |
| Parent voucher status vs voucher review status | Upload/request/approve/reject flows update both in most paths; approval updates voucher review status but does not explicitly update parent `signed_voucher_status` | Potential state drift | Medium |
| Workspace route isolation | Middleware plus `WorkspaceScoped` global scope and workspace-aware tests cover normal routes | Mostly effective | Medium structural risk |
| Nullable workspace IDs | Migration intentionally adds nullable columns and backfill service records mismatches/issues | Confirmed hardening gap | High for future tenant growth |
| Discrepancy source validation | Concrete source model and workspace/period checks exist; DB polymorphic target remains unenforced | Mostly validated | Medium |

## 3. Legacy table dependency verification

### 3.1 `donations`

**Schema/model evidence**

- Table definition: `database/migrations/2026_06_29_000002_create_donations_table.php`.
- Model: `app/Models/Donation.php`.
- Current donation model: `app/Models/Contribution.php`.
- Current donation routes bind `{contribution}` to `DonationPortalController`: `routes/web.php:51-60`.

**Observed dependency**

No runtime controller, service, route, report builder, or seeder was found creating or querying `Donation`. The only non-migration application reference is the regression fixture in `tests/Feature/DashboardMetricsTest.php:212-230`, whose test name explicitly says the dashboard must ignore legacy `donations` data.

**Finding**

`donations` is not an active business dependency. It is a compatibility table/model retained so the regression test can prove that current dashboard totals do not accidentally include it.

**Risk level: Low currently / Medium architectural drift.**

The risk is not current data leakage; it is developer confusion. The table lacks `workspace_id`, verification actors, evidence, voiding, and current contribution workflow fields. New writes to it would bypass the canonical donation lifecycle.

**Safe fix priority: P2.**

Document `contributions` as the only canonical donation ledger. Keep the old model/table until production data and external consumers are verified. If desired later, move the regression fixture to a dedicated legacy-fixture strategy without deleting production history.

### 3.2 `cash_transactions`

**Schema/model evidence**

- Table definition: `database/migrations/2026_06_29_000005_create_cash_transactions_table.php`.
- Model: `app/Models/CashTransaction.php`.
- Current cash contribution handling: `app/Services/Contributions/LogContribution.php`, `app/Http/Controllers/DonationPortalController.php`, and `app/Models/Contribution.php`.

**Observed dependency**

No current controller, service, route, report, seeder, or production query uses `CashTransaction`. The only repository usage is `tests/Feature/DashboardMetricsTest.php:214-221`, again as a legacy row that must not affect current dashboard totals.

**Finding**

`cash_transactions` is more clearly unused than `donations`. It has no donor FK, workspace FK, audit actors, evidence, or bank reconciliation relationship.

**Risk level: Low currently / Medium if reused.**

It is safe to leave in place for the capstone, but unsafe to treat as an alternate cash ledger.

**Safe fix priority: P3.**

Mark the model/table deprecated in documentation. Do not remove it until production row counts, exports, and external integrations have been checked in a permitted environment.

### 3.3 `campaigns`

**Schema/model evidence**

- Table definition: `database/migrations/2026_07_12_000002_create_campaigns_table.php`.
- FK retained on `contributions`: `database/migrations/2026_07_12_000003_create_contributions_table.php`.
- No-op retention migration: `database/migrations/2026_10_28_000001_remove_campaigns_and_supporting_references.php`.
- Sample dependency: `database/seeders/SampleBarangayDataSeeder.php:8,38-66,115-138`.
- Tests intentionally verify retention and absence of controls: `tests/Feature/DonationsPortalTest.php:79-86`.
- No model exists: the `app/Models` directory contains no `Campaign.php`, while the seeder imports `App\Models\Campaign`.
- No user-facing campaign route/controller was found in `routes/web.php` or `app/Http/Controllers`.

**Finding**

Campaigns are dormant in the user-facing application, but not entirely unused. They remain a sample-data dependency and a retained historical dimension. The missing model is a confirmed repository defect for any invocation of `SampleBarangayDataSeeder`.

**Risk level: Medium.**

The defect affects demo/sample seeding rather than the default `DatabaseSeeder`, which does not call `SampleBarangayDataSeeder` (`database/seeders/DatabaseSeeder.php:7-14`). It can still block a capstone demo or environment bootstrap if the sample seeder is run directly.

**Safe fix priority: P1 for verification, P3 for cleanup.**

For the capstone, choose and document one of these safe directions:

1. Keep campaigns as a sample-only retained table and restore/define the missing model in a separately approved code change; or
2. Remove the sample seeder's campaign dependency in a future code change only after confirming that campaign-linked sample records are not required.

Do not drop `campaigns` or `contributions.campaign_id` based solely on the absence of UI controls.

## 4. Payment and voucher status verification

### 4.1 Payment state

The parent table stores `cash_disbursements.payment_status` and `disbursed_at`; the child table stores payment date, amount, method, reference, releasing user, and release timestamp.

**Evidence**

- Parent columns: `database/migrations/2026_10_30_000003_add_payment_status_to_cash_disbursements.php`.
- Child table: `database/migrations/2026_10_30_000004_create_cash_disbursement_payments_table.php`.
- Parent relationship and guards: `app/Models/CashDisbursement.php:108-157`.
- Atomic write: `app/Services/Disbursements/RecordDisbursementPayment.php:11-63`.
- Regression coverage: `tests/Feature/DisbursementWorkflowTest.php:479-520`.

`RecordDisbursementPayment` locks the parent, rejects an existing child or `payment_status = disbursed`, creates exactly one child payment, then sets the parent status and timestamp inside one transaction. The schema's unique `cash_disbursement_id` also prevents a second child payment.

**Finding: no confirmed current bug.**

The duplicate fields are intentionally maintained as a denormalized workflow/read-model state. The remaining risk is direct writes, future services, imports, or query-builder writes that update only one side.

**Risk level: Medium design risk.**

**Safe fix priority: P2.**

Declare `cash_disbursement_payments` the factual source of payment details and the parent status/timestamp a synchronized summary. Add a consistency diagnostic before any cleanup; do not remove parent fields during the capstone.

### 4.2 Voucher state

The parent stores `signed_voucher_status` and request metadata; child `disbursement_vouchers` stores document and review state.

**Evidence**

- Parent columns: `database/migrations/2026_10_30_000002_add_signed_voucher_review_to_cash_disbursements.php`.
- Child table: `database/migrations/2026_10_30_000001_create_disbursement_vouchers_table.php`.
- Upload synchronization: `app/Http/Controllers/DisbursementController.php:116-151`.
- Missing-document synchronization: `app/Http/Controllers/DisbursementController.php:180-200`.
- Admin review: `app/Services/Disbursements/ApproveDisbursement.php:25-37` and `RejectDisbursement.php:21-35`.
- Read logic: `app/Models/CashDisbursement.php:121-130`.
- Tests: `tests/Feature/DisbursementWorkflowTest.php:317-405` and `tests/Feature/WorkflowIntegrityRegressionTest.php:181-193`.

Upload sets both child `review_status = pending_review` and parent `signed_voucher_status = pending_review`. Requesting missing documents sets both to `missing`. Rejecting or approving a latest child voucher updates the child review status.

**Potential inconsistency found**

`ApproveDisbursement` and `RejectDisbursement` update the latest voucher's `review_status`, but do not explicitly update the parent `signed_voucher_status`. In normal workflow this is partly harmless because `CashDisbursement::hasSignedVoucherForApproval()` accepts either parent pending/approved or child pending/approved. However, after approval, the parent may remain `pending_review` while the latest voucher is `approved`; after rejection, the parent may remain `pending_review` while the child is `rejected`.

This is not proven to occur in a production row because MySQL is unavailable, but the code path permits the states to diverge.

**Risk level: Medium; actual latent bug, not merely historical.**

**Safe fix priority: P1.**

Before schema cleanup, define the allowed state machine and add a read-only consistency report/check. Then, in a separately approved code change, centralize voucher state transitions so the parent summary is updated whenever the latest child review state changes. Do not delete either column until all views, policies, tests, and reports use one documented source of truth.

## 5. Workspace isolation verification

### 5.1 Controls that are working by static inspection

- Normal operational routes are inside `auth`, `role`, `workspace`, `workspace.required`, and `workspace.models` middleware: `routes/web.php:21-146`.
- `ResolveActiveWorkspace` clears context at request start, validates session workspace access, chooses an active authorized membership, and sets `WorkspaceContext`: `app/Http/Middleware/ResolveActiveWorkspace.php:20-78`.
- `WorkspaceScoped` applies an `active_workspace` global scope and fills workspace IDs on model creation: `app/Models/Concerns/WorkspaceScoped.php:11-42`.
- Business models use the trait, including donors, contributions, contribution items/evidence, inventory, banking, reconciliation, disbursements, matches, and security activity: `app/Models/*` and `app/Models/Concerns/WorkspaceScoped.php`.
- Discrepancy controller actions explicitly compare period, source record, and active context workspace: `app/Http/Controllers/BankReconciliationController.php:196-220,224-260`.
- Workspace backfill validates parent/child workspace mismatches and records issues: `app/Services/Workspaces/WorkspaceBackfill.php:121-250`.
- Isolation tests exist: `tests/Feature/WorkspaceIsolationTest.php`, `WorkspaceArchitectureTest.php`, `WorkspaceAuthorizationQueryTest.php`, `WorkspaceBackfillTest.php`, and `VerifiedWorkflowBugsTest.php:279-287`.

**Finding**

Normal route-level isolation is not a confirmed bug. The repository has explicit controls and regression coverage.

### 5.2 Remaining structural risks

1. `database/migrations/2026_10_17_000003_add_workspace_ids_to_business_tables.php` adds nullable `workspace_id` columns with `nullOnDelete` rather than non-null tenant ownership.
2. `WorkspaceScoped` does not apply a scope when no workspace context exists. Any code path using an Eloquent model outside middleware can query all workspaces.
3. Direct `DB::table()` usage bypasses Eloquent global scopes. The most important examples are `app/Services/Workspaces/WorkspaceBackfill.php`, which is intentionally cross-workspace maintenance, and reporting/seed paths that must be reviewed individually.
4. `DvNumberSequence` has an explicit workspace FK but does not use `WorkspaceScoped`; its service scopes every lookup by workspace: `app/Models/DvNumberSequence.php` and `app/Services/Disbursements/GenerateDvNumber.php:18-43`. This is currently controlled, but future callers could omit the filter.

**Risk level: Medium for current routes; High for future maintenance/import code.**

**Safe fix priority: P1 verification, P2 hardening.**

Safe capstone actions:

- Review all direct `DB::table()` calls and classify them as intentional system maintenance or workspace-scoped business access.
- Add a static/integration check that new tenant business rows cannot be created with null workspace IDs.
- Before making columns non-null, run an authorized data audit/backfill and resolve all `workspace_backfill_issues`.
- Keep explicit workspace checks in controllers/services even though global scopes exist; they protect against missing context and make cross-tenant intent visible.

## 6. Discrepancy record validation verification

### 6.1 Controls that are working

- Database FKs exist for `bank_reconciliation_id`, `bank_ledger_entry_id`, `cleared_bank_ledger_entry_id`, and user actors: `database/migrations/2026_10_12_000003_create_reconciliation_discrepancies_table.php`, `2026_10_12_000005_add_timing_clearance_fields.php`, and `2026_11_05_000001_add_*discrepancy*.php`.
- Request validation allow-lists discrepancy type, classification, status, source type, and ledger existence: `app/Http/Requests/BankReconciliation/CreateReconciliationDiscrepancyRequest.php:17-31`.
- Context-sensitive type validation inspects the ledger transaction type: `CreateReconciliationDiscrepancyRequest.php:41-60`.
- Controller resolves contribution/disbursement source records and checks source workspace equals period workspace: `app/Http/Controllers/BankReconciliationController.php:196-220`.
- Ledger sources must be in the selected period date range and cannot already be reconciled: same controller block.
- Service prevents duplicate unresolved reports for the same selected source in a period and blocks changes to locked periods: `app/Services/BankReconciliation/ReconciliationDiscrepancyService.php:37-74`.
- Update/review/resolve operations verify period ownership and status transitions: `ReconciliationDiscrepancyService.php:76-165`.

### 6.2 Remaining validation gaps

1. `system_record_type` and `system_record_id` are independently nullable. A request can theoretically supply a type without an ID, or an ID without a type. The controller only resolves/checks the target when both are non-empty (`BankReconciliationController.php:205-211`).
2. `system_record_id` has no `exists` rule because it is polymorphic. The controller catches invalid IDs for the two allowed types, so the HTTP path is protected, but direct service/model creation is not.
3. The database cannot enforce that `system_record_type/system_record_id` points to a real `contributions` or `cash_disbursements` row, nor that the target remains in the same workspace after later edits.
4. `ReconciliationDiscrepancy::systemRecord()` uses `morphTo` (`app/Models/ReconciliationDiscrepancy.php:35-38`), but no explicit morph map was found. Stored short values such as `contribution` and `cash_disbursement` are manually interpreted in the controller; they are not a general Eloquent morph-map contract.

**Risk level: Medium.**

The HTTP controller path is reasonably defended. The real risk is future code that calls the model/service directly or changes a target record's workspace after discrepancy creation.

**Safe fix priority: P1.**

Add a validation invariant that source type and source ID are both present or both absent. Keep the concrete controller lookup and workspace comparison. Add a service-level target resolver/allow-list so non-HTTP callers receive the same validation. Do not replace the polymorphic fields with new FKs until the allowed target set and cross-period semantics are confirmed.

## 7. Actual bugs versus harmless historical fields

| Item | Assessment | Why |
|---|---|---|
| `donations` table/model | Harmless historical compatibility for current capstone behavior | Current routes use `Contribution`; regression test proves legacy rows are ignored |
| `cash_transactions` table/model | Harmless historical compatibility, unless reused | No runtime references; only a negative dashboard regression fixture |
| `campaigns` table/FK | Dormant retained feature, not harmlessly removable | Sample seeder and tests depend on it; missing `Campaign` model is a real seeder defect |
| `payment_status` + payment row | Intentional denormalized summary with safeguards | Transaction, row lock, unique child FK, and tests currently protect normal path |
| `signed_voucher_status` + voucher review status | Actual latent consistency bug | Several flows update both, but approve/reject update only the child review state |
| `donors.full_name` plus name parts | Historical/compatibility duplication | Migration backfills and current UI can operate with both; risk is drift, not immediate failure |
| donor `address`/`barangay_area` plus structured address | Historical compatibility plus reporting risk | Structured fields were added and `barangay` is backfilled from legacy field |
| `inventory_items.category` plus subcategory FK | Intentional snapshot/free-text compatibility, but weak normalization | Existing reports and forms use string category; changing it could break historical item descriptions |
| nullable `workspace_id` | Confirmed architectural hardening gap | Backfill service explicitly tracks null/mismatch cases; tenant ownership is not DB-mandatory |
| discrepancy polymorphic source | Validated at HTTP boundary but not DB-enforced | Concrete controller checks exist, but direct callers and later workspace changes remain possible |

## 8. Prioritized cleanup plan for the capstone

### P0 — Do not perform destructive cleanup

- Do not drop `donations`, `cash_transactions`, `campaigns`, or any legacy columns during the capstone.
- Do not make workspace columns non-null until a real data audit/backfill has been completed.
- Do not replace discrepancy source fields or payment/voucher fields without preserving audit history.

### P1 — Resolve confirmed or high-impact issues first

1. **Campaign seeder dependency:** decide whether `SampleBarangayDataSeeder` is in capstone scope. If yes, separately repair its missing `Campaign` model dependency; if no, document it as an excluded legacy/demo seeder. This audit does not make that code change.
2. **Voucher status consistency:** define one state machine and ensure approve/reject transitions cannot leave parent and latest child states inconsistent. Add a diagnostic/test before changing schema.
3. **Discrepancy source pair validation:** require `system_record_type` and `system_record_id` together and move concrete target validation into the service boundary as well as the controller.
4. **Workspace null/mismatch audit:** use the existing `WorkspaceBackfill` logic and `workspace_backfill_issues` evidence to identify unresolved records before considering constraints.

### P2 — Prevent future drift

1. Document `contributions` as the canonical donation table and prohibit new runtime writes to `donations`/`cash_transactions`.
2. Treat `cash_disbursement_payments` as the payment fact and `disbursement_vouchers` as the document fact; parent statuses are synchronized summaries.
3. Review direct `DB::table()` access for workspace scoping and require explicit justification for cross-workspace maintenance.
4. Add consistency checks for:
   - payment row existence versus `payment_status`/`disbursed_at`;
   - latest voucher review versus `signed_voucher_status`;
   - child/parent workspace equality;
   - discrepancy source existence and workspace equality.

### P3 — Defer structural cleanup until after capstone acceptance

1. Verify production row counts, external exports, and integrations for `donations` and `cash_transactions`.
2. Decide whether campaigns are permanently retired or should be restored as a supported feature.
3. Migrate remaining consumers, archive historical data, and only then consider dropping obsolete tables/columns through reviewed migrations with backup and rollback procedures.

## 9. Final recommendation

For the capstone, the safest cleanup is documentation, validation hardening, and consistency diagnostics—not table deletion. The only confirmed code defect found in this verification is the missing `Campaign` model dependency in the optional sample seeder. The most important latent workflow defect is voucher parent/child status drift. Workspace nullability and polymorphic discrepancy references should be treated as controlled risks with staged hardening after data verification.
