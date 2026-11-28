# Design: Deactivate/Retire lifecycle for inventory categories, subcategories, and units

## Intent

The Manage Item Categories page (`/inventory/categories`) lets admins create, edit, and delete
the reference data (`InventoryCategory`, `InventorySubcategory`, `InventoryUnit`) used to classify
in-kind inventory. QA raised that deleting a record already referenced by historical records is
unsafe, that admins have no visibility into what depends on a record before acting, and that the
only confirmation is a generic browser `confirm()`. This design adds an explicit Deactivate/Reactivate
lifecycle, dependency counts in each table, and real confirmation dialogs — while closing two
data-integrity gaps found during recon.

## Goals

- A record already referenced by inventory/donation data can be retired (`is_active = false`)
  instead of deleted, stays visible in historical records, and is excluded from selection in new
  entries (already true today via the existing `is_active` scopes — just needs a clear action).
- Each table row shows what depends on it (subcategory count + inventory item count for
  categories; inventory item count for subcategories and units) before the admin acts.
- Deactivate, Reactivate, and Delete each show a confirmation dialog explaining the consequence,
  replacing the generic `confirm()`.
- Hard delete remains available, but only ever offered/allowed for records with zero dependents.
- Fix: deleting a category no longer silently cascades away subcategories that are themselves
  referenced by inventory or donation records.
- Fix: deleting a subcategory checks `InventoryItem.inventory_subcategory_id`, not just donation
  (`ContributionItem`) usage, before allowing a hard delete.
- No change to existing permissions (admin-only mutation, staff can view/select).

## Non-goals / anti-goals

- No new database columns — `is_active` already exists and is already respected by selection
  scopes and validation. This is a UI + controller-logic feature, not a schema migration.
- Not building a generic "soft delete" (no `deleted_at`); `is_active` already serves that purpose
  for this domain and changing it would touch more than this page.
- Not reworking `ManageInventoryReferenceItem`'s cross-workspace "shared reference" usage check —
  it stays as the source of truth for what counts as "in use," we only extend what it checks.
- Not adding bulk/batch deactivate — one row at a time, matching the current table's action model.

## Constraints

- Must preserve existing inventory/donation data, FK relationships, and all current permissions.
- Must follow the existing UI patterns: native `<dialog>` + `data-dialog-open`/`data-dialog-close`
  (see `resources/views/components/inventory/reference-form-modal.blade.php`), Tailwind utility
  classes already used on this page, and the existing route/controller/service shapes.
- `InventoryItem.category` / `.unit` are plain string columns matching `key` (not FKs) — usage
  checks for categories/units must stay key-based, as they are today.
- `InventorySubcategory` is tied to `InventoryItem` via a real FK (`inventory_subcategory_id`,
  `nullOnDelete`) in addition to the string-based `ContributionItem` match.

## Approach

Given how much is already in place (is_active, scopes, validation, the shared dialog/modal pattern,
the per-row Edit-modal-per-item rendering convention), there's one approach that fits: extend the
existing pieces rather than introduce a new pattern.

- **Routes/controllers:** add `deactivate` and `reactivate` actions per entity (category,
  subcategory, unit), mirroring the existing `destroy` action — a lightweight `PATCH` route, a
  `Gate::authorize('update', ...)` check (same authorization boundary as edit), no new
  `FormRequest` needed since there's no input, just a state flip. Rejected alternative: folding
  this into the existing `update` route — would force the Deactivate/Reactivate button to resubmit
  `key`/`label`, re-triggering the rename-while-in-use guard for no reason, and couldn't carry a
  distinct confirmation message.
- **Service layer:** add `deactivate()`/`reactivate()` to `ManageInventoryReferenceItem` (used by
  category + unit controllers, same as `create`/`update`/`delete` today). Subcategory keeps its
  own controller-local logic, matching how it already diverges from the shared service.
- **Dependency counts:** computed once per `index()` call via grouped aggregate queries (no N+1),
  attached as plain properties on the already-loaded collections, matching how the controller
  already eager-loads `subcategories`.
- **Confirmation dialogs:** one new shared Blade component (`x-inventory.confirm-action-dialog`),
  styled like `reference-form-modal.blade.php`, rendered once per row per available action — same
  convention as today's one-edit-modal-per-row. Replaces the `onsubmit="confirm(...)"` forms.
- **Per-row action rule** (keeps "safe deletion for unused records only" literal):
  - active + in use → **Deactivate** only
  - active + not in use → **Delete** only
  - inactive + in use → **Reactivate** only
  - inactive + not in use → **Reactivate** and **Delete**
- **Bug fixes**, both enforced in the same transaction/lock the existing delete methods already use:
  - Category hard-delete also blocked when `subcategories()->count() > 0` (not just direct
    `InventoryItem` usage), so cascade can never silently remove subcategories.
  - Subcategory "in use" check also queries `InventoryItem::where('inventory_subcategory_id', ...)`
    in addition to the existing `ContributionItem` check.

## Resolved after builder check

A fresh-agent review of this doc flagged five places a wrong guess would matter. Four were
internal consistency calls I'm settling here (so the "dependency count" shown never contradicts
what actually blocks an action); one was a real fork and went back to the human partner.

- **Count scope must match the block, or the UI lies.** All dependency counts are computed the
  same way the existing "in use" checks are computed, not with a fresh/simpler query:
  - Category/unit item counts: cross-workspace (`InventoryItem::withoutGlobalScope('active_workspace')`),
    matching `ManageInventoryReferenceItem::isInUse()`. A workspace-scoped count could show "0
    items" while another workspace's data still blocks the delete.
  - Category's subcategory count: **all** subcategories, active or not — this is literally what
    the new hard-delete guard checks (`inventory_category_id` cascades regardless of `is_active`),
    so the badge must count the same set.
  - Subcategory's usage count: both sources that can block it — `InventoryItem.inventory_subcategory_id`
    (FK) and `ContributionItem` (category+subcategory key match, donations) — shown as two distinct
    numbers (e.g. "2 inventory items · 1 donation record") rather than merged into one, so the
    confirmation dialog can name which kind of record depends on it.
- **Edit-modal `is_active` checkbox is removed** from `reference-form-modal.blade.php` and
  `subcategory-form-modal.blade.php` (human partner's call). Deactivate/Reactivate become the only
  way to change active status — no second, unconfirmed path. `UpdateInventoryCategoryRequest`,
  `UpdateInventoryUnitRequest`, and `UpdateInventorySubcategoryRequest` stop accepting `is_active`
  (key/label only); `ManageInventoryReferenceItem::update()` stops touching `is_active`.
  `test_admin_can_create_update_and_delete_a_subcategory` gets its `is_active` toggle-via-update
  assertion replaced with a toggle-via-deactivate assertion.
- **Subcategory controller gains the same transactional locking** category/unit already get via
  `ManageInventoryReferenceItem` (`DB::transaction` + `lockForUpdate()` around the usage check and
  the mutation) — added to `update`, `destroy`, and the new `deactivate`/`reactivate` methods, to
  close the same race window the service already closes for the other two entities.

## What's left to the builder

- Exact dependency-count copy in the table ("3 subcategories · 12 items" vs. two columns) — pick
  whichever reads cleanest at the existing table's column widths; no behavior difference.
- Confirmation dialog body copy per action/entity — keep factual and specific (name the counts),
  no need for sign-off on exact wording.
- Whether to update `docs/category-management-admin-guide.md` to mention subcategories and the new
  actions — small, worth doing, not load-bearing.
- Test file organization — extend `tests/Feature/InventoryCategoryManagementTest.php` rather than
  create a new file, to match existing convention (one feature test file already covers all three
  entities).
