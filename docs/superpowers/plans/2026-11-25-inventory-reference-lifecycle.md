# Inventory Reference Lifecycle (Deactivate/Reactivate) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the Manage Item Categories page (categories, subcategories, units) a dedicated Deactivate/Reactivate action, dependency-count visibility, and confirmation dialogs, while closing two data-integrity gaps in the existing delete safeguards.

**Architecture:** Extend the existing pieces rather than add new patterns: `is_active` (already present on all three models) gets two new mutators (`deactivate`/`reactivate`) alongside the existing `create`/`update`/`delete`; dependency counts are computed once per page load via grouped aggregate queries and attached to the already-loaded collections; one new shared `<dialog>`-based Blade component replaces the generic `confirm()` calls, reusing the existing `data-dialog-open`/`data-dialog-close` JS verbatim.

**Tech Stack:** Laravel (PHP), Blade views, Tailwind, PHPUnit feature tests. No new JS, no new migrations.

**Spec:** `docs/superpowers/specs/2026-11-25-inventory-reference-lifecycle-design.md`

## Global Constraints

- Admin-only for all mutations (create/update/delete/deactivate/reactivate) on all three entities; staff remain view/select-only. Do not change this boundary.
- No new database columns or migrations — reuse the existing `is_active` boolean on `inventory_categories`, `inventory_subcategories`, `inventory_units`.
- `is_active` becomes settable **only** via Deactivate/Reactivate (and the initial value at creation). The Update route/request for all three entities stops accepting `is_active` entirely.
- Every dependency count shown in the UI must be computed with the **same scope** as the check that actually blocks the action it describes, so a row never shows "0 dependents" while the action is still blocked:
  - Category/unit item counts: cross-workspace key match (`InventoryItem::withoutGlobalScope('active_workspace')`), exactly like `ManageInventoryReferenceItem::isInUse()` already does.
  - Category's subcategory count: **all** subcategories regardless of `is_active` (the FK cascade doesn't care about active state, so neither does the count or the delete guard).
  - Subcategory's usage count: both `InventoryItem.inventory_subcategory_id` (FK) and `ContributionItem` (category+subcategory key match, donations) — shown as two distinct numbers.
- All new dialogs reuse the existing native `<dialog>` + `data-dialog-open`/`data-dialog-close` click handlers (`resources/js/app.js` ~L380-412) and the existing "reopen on validation error via `showModal()`" script (`resources/js/app.js` ~L503). **No new JavaScript.**
- Hard Delete stays blocked (via `ValidationException` on the `key` field, same as today) whenever any dependency exists. "Safe deletion for unused records only" is literal: the Delete button/route must never succeed against a record with any dependency.
- **Per explicit instruction for this task: do not run `git commit` or `git push` at any point.** Every task below replaces the usual "Commit" step with "leave the changes staged/unstaged for the user to review" — do not stage-and-commit even locally.

## Review Focus

- A category with subcategories (even unused, even inactive ones) must still be blocked from hard delete — not just categories with direct `InventoryItem` usage. (Task 1)
- A subcategory tied only to `InventoryItem.inventory_subcategory_id`, with zero `ContributionItem` rows, must still be blocked from hard delete. (Task 3)
- Posting `is_active` to any of the three Update routes must have **zero** effect — not reset to `true`, not changed at all. (Tasks 2 & 3)
- Dependency counts shown on the page must never read "0" for a row that is actually still blocked from deletion (cross-workspace matching for category/unit; all-subcategories for category; FK-or-donation for subcategory). (Task 4)
- Staff (non-admin) must get `403` on all six new deactivate/reactivate routes. (Tasks 2 & 3)

---

## File Structure

- Modify: `app/Services/Inventory/ManageInventoryReferenceItem.php` — add `deactivate()`/`reactivate()`; stop touching `is_active` in `update()`; block category delete when it has subcategories.
- Modify: `app/Http/Controllers/InventoryCategoryController.php` — add `deactivate()`/`reactivate()` actions; rewrite `index()` to compute and attach dependency counts.
- Modify: `app/Http/Controllers/InventoryUnitController.php` — add `deactivate()`/`reactivate()` actions.
- Modify: `app/Http/Controllers/InventorySubcategoryController.php` — add `deactivate()`/`reactivate()` actions; fix `isReferenced()` to also check the `InventoryItem` FK; wrap `update`/`destroy`/new actions in `DB::transaction` + `lockForUpdate()`.
- Modify: `app/Http/Requests/Inventory/UpdateInventoryCategoryRequest.php`, `UpdateInventoryUnitRequest.php`, `UpdateInventorySubcategoryRequest.php` — remove `is_active` from `rules()` and `prepareForValidation()`.
- Modify: `routes/web.php` — add 6 new `PATCH` routes (deactivate/reactivate × category/subcategory/unit).
- Create: `resources/views/components/inventory/confirm-action-dialog.blade.php` — shared confirmation modal (Cancel/Confirm).
- Modify: `resources/views/components/inventory/reference-table.blade.php` — dependency-count display, 4-state action buttons, per-row confirm dialogs. Shared by categories and units.
- Modify: `resources/views/components/inventory/subcategory-table.blade.php` — same treatment for subcategories (two dependency numbers).
- Modify: `resources/views/components/inventory/reference-form-modal.blade.php`, `subcategory-form-modal.blade.php` — remove the Active checkbox when editing (keep it when creating).
- Modify: `docs/category-management-admin-guide.md` — document subcategories and the new actions (small, folded into Task 5).
- Modify: `tests/Feature/InventoryCategoryManagementTest.php` — all new/updated tests (existing convention: one feature test file covers all three entities).

---

### Task 1: Service layer — deactivate/reactivate, stop resetting `is_active` on update, block category delete when subcategories exist

**Files:**
- Modify: `app/Services/Inventory/ManageInventoryReferenceItem.php`
- Test: `tests/Feature/InventoryCategoryManagementTest.php`

**Interfaces:**
- Produces: `ManageInventoryReferenceItem::deactivate(Model $item): Model`, `ManageInventoryReferenceItem::reactivate(Model $item): Model` — both lock the row (`lockForUpdate()`), flip `is_active`, return the fresh model. Used by Task 2's category/unit controllers.
- Modifies existing: `update(Model $item, array $data): Model` no longer reads `$data['is_active']` — only `key`/`label` change.
- Modifies existing: `delete(Model $item): void` additionally throws `ValidationException` (message key `key`) when `$item instanceof InventoryCategory && $item->subcategories()->exists()`.

- [ ] **Step 1: Write the failing tests**

```php
public function test_manage_inventory_reference_item_service_can_deactivate_and_reactivate_a_category(): void
{
    $manager = app(ManageInventoryReferenceItem::class);
    $category = InventoryCategory::query()->where('key', 'food')->firstOrFail();

    $manager->deactivate($category);
    $this->assertFalse($category->fresh()->is_active);

    $manager->reactivate($category);
    $this->assertTrue($category->fresh()->is_active);
}

public function test_updating_key_and_label_does_not_change_is_active(): void
{
    $manager = app(ManageInventoryReferenceItem::class);
    $category = InventoryCategory::query()->create(['key' => 'toggle_test', 'label' => 'Toggle Test', 'is_active' => false]);

    $manager->update($category, ['key' => 'toggle_test', 'label' => 'Toggle Test Renamed']);

    $this->assertFalse($category->fresh()->is_active);
}

public function test_manage_inventory_reference_item_service_blocks_delete_when_category_has_a_subcategory(): void
{
    $manager = app(ManageInventoryReferenceItem::class);
    $category = InventoryCategory::query()->create(['key' => 'has_subcat', 'label' => 'Has Subcat']);
    InventorySubcategory::query()->create([
        'inventory_category_id' => $category->id, 'key' => 'unused_child', 'label' => 'Unused Child', 'is_active' => false,
    ]);

    $this->expectException(ValidationException::class);
    $manager->delete($category);
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=test_manage_inventory_reference_item_service_can_deactivate_and_reactivate_a_category`
Run: `php artisan test --filter=test_updating_key_and_label_does_not_change_is_active`
Run: `php artisan test --filter=test_manage_inventory_reference_item_service_blocks_delete_when_category_has_a_subcategory`
Expected: FAIL (`deactivate`/`reactivate` undefined; `is_active` still gets reset; category delete still succeeds)

- [ ] **Step 3: Implement in `app/Services/Inventory/ManageInventoryReferenceItem.php`**

Add `use App\Models\InventoryCategory;` to the imports. In `update()`, remove the `'is_active' => $data['is_active'] ?? true,` line from the `$item->update([...])` call — only `key` and `label` remain. Add the two new methods following the existing `lockForUpdate()` + `DB::transaction` shape already used by `update()`/`delete()`. In `delete()`, after the existing `isInUse()` check and before `$item->delete()`, add the category-specific subcategory guard described in Interfaces, with a message such as "This category has subcategories and cannot be deleted. Deactivate it instead, or remove its subcategories first."

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: PASS (including all pre-existing tests in this file — this step also catches any regression from the `update()` change)

- [ ] **Step 5: Do not commit (user instruction) — leave changes staged for review.**

---

### Task 2: Category & Unit controllers — deactivate/reactivate routes, stop accepting `is_active` on update

**Files:**
- Modify: `app/Http/Controllers/InventoryCategoryController.php`
- Modify: `app/Http/Controllers/InventoryUnitController.php`
- Modify: `app/Http/Requests/Inventory/UpdateInventoryCategoryRequest.php`
- Modify: `app/Http/Requests/Inventory/UpdateInventoryUnitRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/InventoryCategoryManagementTest.php`

**Interfaces:**
- Consumes: `ManageInventoryReferenceItem::deactivate()`/`reactivate()` from Task 1.
- Produces routes: `inventory.categories.deactivate`, `inventory.categories.reactivate`, `inventory.units.deactivate`, `inventory.units.reactivate` (all `PATCH`, model-bound, redirect to `inventory.categories.index` with a `success` flash message, same shape as the existing `update`/`destroy` actions).

- [ ] **Step 1: Write the failing tests**

```php
public function test_admin_can_deactivate_and_reactivate_a_category_over_http(): void
{
    $admin = $this->userWithRole('admin');
    $category = InventoryCategory::query()->where('key', 'food')->firstOrFail();

    $this->actingAs($admin)->patch(route('inventory.categories.deactivate', $category))
        ->assertRedirect(route('inventory.categories.index'));
    $this->assertFalse($category->fresh()->is_active);

    $this->actingAs($admin)->patch(route('inventory.categories.reactivate', $category))
        ->assertRedirect(route('inventory.categories.index'));
    $this->assertTrue($category->fresh()->is_active);
}

public function test_non_admin_cannot_deactivate_or_reactivate_a_category(): void
{
    $secretary = $this->userWithRole('staff');
    $category = InventoryCategory::query()->where('key', 'food')->firstOrFail();

    $this->actingAs($secretary)->patch(route('inventory.categories.deactivate', $category))->assertForbidden();
    $this->actingAs($secretary)->patch(route('inventory.categories.reactivate', $category))->assertForbidden();
}

public function test_admin_can_deactivate_and_reactivate_a_unit_over_http(): void
{
    $admin = $this->userWithRole('admin');
    $unit = InventoryUnit::query()->where('key', 'piece')->firstOrFail();

    $this->actingAs($admin)->patch(route('inventory.units.deactivate', $unit))->assertRedirect(route('inventory.categories.index'));
    $this->assertFalse($unit->fresh()->is_active);

    $this->actingAs($admin)->patch(route('inventory.units.reactivate', $unit))->assertRedirect(route('inventory.categories.index'));
    $this->assertTrue($unit->fresh()->is_active);
}

public function test_updating_a_category_over_http_no_longer_accepts_is_active(): void
{
    $admin = $this->userWithRole('admin');
    $category = InventoryCategory::query()->where('key', 'food')->firstOrFail();
    $this->assertTrue($category->is_active);

    $this->actingAs($admin)->patch(route('inventory.categories.update', $category), [
        'key' => 'food', 'label' => 'Food', 'is_active' => '0',
    ])->assertRedirect(route('inventory.categories.index'));

    $this->assertTrue($category->fresh()->is_active);
}
```

(Mirror the last test as `test_updating_a_unit_over_http_no_longer_accepts_is_active` using `inventory.units.update` and the `piece` unit.)

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: FAIL (routes `inventory.categories.deactivate` etc. don't exist; `is_active` still changes via update)

- [ ] **Step 3: Add routes in `routes/web.php`**

Immediately after the existing `inventory.categories.destroy` line, add:
```php
Route::patch('/inventory/categories/{inventoryCategory}/deactivate', [InventoryCategoryController::class, 'deactivate'])->name('inventory.categories.deactivate');
Route::patch('/inventory/categories/{inventoryCategory}/reactivate', [InventoryCategoryController::class, 'reactivate'])->name('inventory.categories.reactivate');
```
Immediately after the existing `inventory.units.destroy` line, add the equivalent pair for units.

- [ ] **Step 4: Implement `deactivate()`/`reactivate()` in both controllers**

Each: `Gate::authorize('update', $model)` then `$manager->deactivate($model)` (or `reactivate`), then `redirect()->route('inventory.categories.index')->with('success', '<Entity> deactivated successfully.')` (or `reactivated`) — same shape as the existing `destroy()` action in each controller.

- [ ] **Step 5: Remove `is_active` from `UpdateInventoryCategoryRequest::rules()`/`prepareForValidation()` and `UpdateInventoryUnitRequest::rules()`/`prepareForValidation()`**

Delete the `'is_active' => ['boolean'],` rule line and the `'is_active' => $this->boolean('is_active'),` merge line from both files. Leave `StoreInventoryCategoryRequest`/`StoreInventoryUnitRequest` untouched — creation still accepts `is_active`.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: PASS

- [ ] **Step 7: Do not commit (user instruction) — leave changes staged for review.**

---

### Task 3: Subcategory controller — fix FK usage check, add locking, add deactivate/reactivate

**Files:**
- Modify: `app/Http/Controllers/InventorySubcategoryController.php`
- Modify: `app/Http/Requests/Inventory/UpdateInventorySubcategoryRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/InventoryCategoryManagementTest.php`

**Interfaces:**
- Produces routes: `inventory.subcategories.deactivate`, `inventory.subcategories.reactivate` (`PATCH`, same redirect/flash shape as category/unit).
- `isReferenced(InventorySubcategory $inventorySubcategory): bool` now returns true if either the existing `ContributionItem` match **or** `InventoryItem::withoutGlobalScope('active_workspace')->where('inventory_subcategory_id', $inventorySubcategory->id)->exists()` is true.

- [ ] **Step 1: Write the failing tests**

```php
public function test_subcategory_referenced_only_by_an_inventory_item_cannot_be_deleted(): void
{
    $admin = $this->userWithRole('admin');
    $food = InventoryCategory::query()->where('key', 'food')->firstOrFail();
    $subcategory = InventorySubcategory::query()->where('inventory_category_id', $food->id)->where('key', 'rice_grains')->firstOrFail();

    InventoryItem::query()->create([
        'name' => 'FK-Linked Item', 'category' => 'food', 'inventory_subcategory_id' => $subcategory->id, 'unit' => 'piece',
        'quantity' => 1, 'current_quantity' => 1, 'minimum_reserve_quantity' => 0,
        'storage_location' => 'Main Storage', 'status' => config('inventory.defaults.status'), 'received_at' => now(),
    ]);

    $this->actingAs($admin)->delete(route('inventory.subcategories.destroy', $subcategory))->assertSessionHasErrors('key');
    $this->assertDatabaseHas('inventory_subcategories', ['id' => $subcategory->id]);
}

public function test_admin_can_deactivate_and_reactivate_a_subcategory_over_http(): void
{
    $admin = $this->userWithRole('admin');
    $subcategory = InventorySubcategory::query()->where('key', 'rice_grains')->firstOrFail();

    $this->actingAs($admin)->patch(route('inventory.subcategories.deactivate', $subcategory))->assertRedirect(route('inventory.categories.index'));
    $this->assertFalse($subcategory->fresh()->is_active);

    $this->actingAs($admin)->patch(route('inventory.subcategories.reactivate', $subcategory))->assertRedirect(route('inventory.categories.index'));
    $this->assertTrue($subcategory->fresh()->is_active);
}

public function test_non_admin_cannot_deactivate_or_reactivate_a_subcategory(): void
{
    $secretary = $this->userWithRole('staff');
    $subcategory = InventorySubcategory::query()->where('key', 'rice_grains')->firstOrFail();

    $this->actingAs($secretary)->patch(route('inventory.subcategories.deactivate', $subcategory))->assertForbidden();
    $this->actingAs($secretary)->patch(route('inventory.subcategories.reactivate', $subcategory))->assertForbidden();
}

public function test_updating_a_subcategory_over_http_no_longer_accepts_is_active(): void
{
    $admin = $this->userWithRole('admin');
    $food = InventoryCategory::query()->where('key', 'food')->firstOrFail();
    $subcategory = InventorySubcategory::query()->where('inventory_category_id', $food->id)->where('key', 'rice_grains')->firstOrFail();
    $this->assertTrue($subcategory->is_active);

    $this->actingAs($admin)->patch(route('inventory.subcategories.update', $subcategory), [
        'inventory_category_id' => $food->id, 'key' => 'rice_grains', 'label' => 'Rice & Grains', 'is_active' => '0',
    ])->assertRedirect(route('inventory.categories.index'));

    $this->assertTrue($subcategory->fresh()->is_active);
}
```

Also update the existing `test_admin_can_create_update_and_delete_a_subcategory` (around line 492): replace its `is_active => '0'` update-assertion block with a call to the new deactivate route instead (assert the created subcategory can be deactivated, then proceed to the existing delete assertions unchanged).

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: FAIL

- [ ] **Step 3: Add routes in `routes/web.php`**

Immediately after the existing `inventory.subcategories.destroy` line, add the deactivate/reactivate pair, matching Task 2's pattern.

- [ ] **Step 4: Implement in `app/Http/Controllers/InventorySubcategoryController.php`**

- Fix `isReferenced()` per Interfaces (add `use App\Models\InventoryItem;` and `use Illuminate\Support\Facades\DB;` — the latter not yet imported in this controller).
- Wrap `update()` and `destroy()` bodies in `DB::transaction(function () use (...) { ... $inventorySubcategory->newQuery()->lockForUpdate()->findOrFail($inventorySubcategory->getKey()); ... })`, matching the locking shape already used in `ManageInventoryReferenceItem` — re-fetch the locked row before re-checking `isReferenced()` and before mutating.
- Add `deactivate()`/`reactivate()`: `Gate::authorize('update', $inventorySubcategory->category)`, then the same transaction+lock shape, setting `is_active` to `false`/`true`, then redirect with a `success` flash message — mirroring Task 2's controllers.

- [ ] **Step 5: Remove `is_active` from `UpdateInventorySubcategoryRequest::rules()`/`prepareForValidation()`**

Same treatment as Task 2's category/unit update requests. Leave `StoreInventorySubcategoryRequest` untouched.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: PASS

- [ ] **Step 7: Do not commit (user instruction) — leave changes staged for review.**

---

### Task 4: Dependency counts on the Manage Categories page

**Files:**
- Modify: `app/Http/Controllers/InventoryCategoryController.php` (the `index()` method)
- Test: `tests/Feature/InventoryCategoryManagementTest.php`

**Interfaces:**
- Produces: every `InventoryCategory` passed to the `inventory.categories` view carries `->inventory_item_count` (int) and `->subcategory_count` (int, counts **all** loaded subcategories regardless of `is_active`); every eager-loaded `InventorySubcategory` on `$category->subcategories` carries `->inventory_item_count` (int, FK-based) and `->donation_item_count` (int, `ContributionItem` key-match); every `InventoryUnit` carries `->inventory_item_count` (int). Task 5's views read these properties directly.

- [ ] **Step 1: Write the failing tests**

These read the controller's view data directly (`$response->viewData(...)`) rather than scanning rendered HTML, so this task is verifiable independently of Task 5's templates.

```php
public function test_manage_categories_page_computes_category_and_unit_dependency_counts(): void
{
    $admin = $this->userWithRole('admin');
    $food = InventoryCategory::query()->where('key', 'food')->firstOrFail();

    InventoryItem::query()->create([
        'name' => 'Count Fixture', 'category' => 'food', 'unit' => 'piece',
        'quantity' => 1, 'current_quantity' => 1, 'minimum_reserve_quantity' => 0,
        'storage_location' => 'Main Storage', 'status' => config('inventory.defaults.status'), 'received_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('inventory.categories.index'));
    $response->assertOk();

    $categories = $response->viewData('categories');
    $units = $response->viewData('units');
    $freshFood = $categories->firstWhere('key', 'food');
    $piece = $units->firstWhere('key', 'piece');

    $this->assertSame(1, $freshFood->inventory_item_count);
    $this->assertSame($food->subcategories()->count(), $freshFood->subcategory_count);
    $this->assertSame(1, $piece->inventory_item_count);
}

public function test_manage_categories_page_computes_subcategory_dependency_counts(): void
{
    $admin = $this->userWithRole('admin');
    $food = InventoryCategory::query()->where('key', 'food')->firstOrFail();
    $subcategory = InventorySubcategory::query()->where('inventory_category_id', $food->id)->where('key', 'rice_grains')->firstOrFail();

    InventoryItem::query()->create([
        'name' => 'Subcat FK Fixture', 'category' => 'food', 'inventory_subcategory_id' => $subcategory->id, 'unit' => 'piece',
        'quantity' => 1, 'current_quantity' => 1, 'minimum_reserve_quantity' => 0,
        'storage_location' => 'Main Storage', 'status' => config('inventory.defaults.status'), 'received_at' => now(),
    ]);
    $this->createContributionItem($admin, 'food', 'rice_grains');

    $response = $this->actingAs($admin)->get(route('inventory.categories.index'));
    $response->assertOk();

    $categories = $response->viewData('categories');
    $freshSubcategory = $categories->firstWhere('key', 'food')->subcategories->firstWhere('key', 'rice_grains');

    $this->assertSame(1, $freshSubcategory->inventory_item_count);
    $this->assertSame(1, $freshSubcategory->donation_item_count);
}

public function test_category_dependency_count_matches_the_cross_workspace_delete_guard(): void
{
    $admin = $this->userWithRole('admin');
    $workspace = Workspace::query()->create(['slug' => 'count-other', 'name' => 'Other Workspace', 'status' => 'active']);
    $this->referenceItem(['workspace_id' => $workspace->id]); // category 'food', unit 'piece', in a different workspace

    $response = $this->actingAs($admin)->get(route('inventory.categories.index'));
    $response->assertOk();

    $food = $response->viewData('categories')->firstWhere('key', 'food');
    $piece = $response->viewData('units')->firstWhere('key', 'piece');

    $this->assertGreaterThan(0, $food->inventory_item_count);
    $this->assertGreaterThan(0, $piece->inventory_item_count);
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: FAIL (`inventory_item_count`/`subcategory_count`/`donation_item_count` are not set on the models yet)

- [ ] **Step 3: Rewrite `InventoryCategoryController::index()`**

Add `use App\Models\ContributionItem;` and `use App\Models\InventoryItem;`. Compute four grouped aggregates (category item counts by `category` key, unit item counts by `unit` key, subcategory item counts by `inventory_subcategory_id`, subcategory donation counts by `category`+`subcategory` key pair — each via `withoutGlobalScope('active_workspace')`, per Global Constraints), then loop the loaded `$categories` (and their already-eager-loaded `subcategories`) and `$units` collections attaching the properties listed in Interfaces. Pass `categories`/`units` to the view exactly as today.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: PASS

- [ ] **Step 5: Do not commit (user instruction) — leave changes staged for review.**

---

### Task 5: Confirmation dialogs, 4-state action buttons, Edit-modal checkbox removal

**Files:**
- Create: `resources/views/components/inventory/confirm-action-dialog.blade.php`
- Modify: `resources/views/components/inventory/reference-table.blade.php`
- Modify: `resources/views/components/inventory/subcategory-table.blade.php`
- Modify: `resources/views/components/inventory/reference-form-modal.blade.php`
- Modify: `resources/views/components/inventory/subcategory-form-modal.blade.php`
- Modify: `resources/views/inventory/categories.blade.php` (pass the two new route-name props through to `x-inventory.reference-table`)
- Modify: `docs/category-management-admin-guide.md`
- Test: `tests/Feature/InventoryCategoryManagementTest.php`

**Interfaces:**
- Consumes: `$item->inventory_item_count`, `$item->subcategory_count` (category only), `$subcategory->inventory_item_count`/`->donation_item_count` from Task 4; routes `*.deactivate`/`*.reactivate` from Tasks 2-3.
- `x-inventory.confirm-action-dialog` props: `dialogId`, `action`, `method`, `title`, `confirmLabel`, `confirmVariant` (`'primary'` default | `'danger'`), default slot = explanatory body (caller supplies, naming the record and its counts). Markup: same `<dialog>` chrome as `reference-form-modal.blade.php` (`m-auto w-[min(440px,calc(100vw-2rem))] rounded-lg p-0 shadow-xl backdrop:bg-slate-950/50`), a `Cancel` button (`data-dialog-close`) and a submit button styled `ui-button {{ $confirmVariant === 'danger' ? 'ui-button--danger' : 'ui-button--primary' }}`.
- `x-inventory.reference-table` gains two new required props: `deactivateRouteName`, `reactivateRouteName` (alongside the existing `deleteRouteName`).

- [ ] **Step 1: Create `confirm-action-dialog.blade.php`**

Build per the Interfaces spec above. Follow `reference-form-modal.blade.php`'s existing header/close-button/footer chrome exactly (don't invent new modal chrome).

- [ ] **Step 2: Write the failing view tests**

```php
public function test_in_use_active_category_shows_deactivate_not_delete(): void
{
    $admin = $this->userWithRole('admin');
    $this->referenceItem(); // uses category 'food', unit 'piece'
    $food = InventoryCategory::query()->where('key', 'food')->firstOrFail();

    $response = $this->actingAs($admin)->get(route('inventory.categories.index'));

    $response->assertOk();
    $response->assertSee("data-dialog-open=\"category-deactivate-{$food->id}\"", false);
    $response->assertDontSee("data-dialog-open=\"category-delete-{$food->id}\"", false);
}

public function test_unused_inactive_category_shows_reactivate_and_delete(): void
{
    $admin = $this->userWithRole('admin');
    $category = InventoryCategory::query()->create(['key' => 'retired_unused', 'label' => 'Retired Unused', 'is_active' => false]);

    $response = $this->actingAs($admin)->get(route('inventory.categories.index'));

    $response->assertOk();
    $response->assertSee("data-dialog-open=\"category-reactivate-{$category->id}\"", false);
    $response->assertSee("data-dialog-open=\"category-delete-{$category->id}\"", false);
}

public function test_edit_modals_no_longer_have_an_active_checkbox(): void
{
    $admin = $this->userWithRole('admin');

    $response = $this->actingAs($admin)->get(route('inventory.categories.index'));
    $response->assertOk();

    // Exactly 3 "create" dialogs (category/unit/subcategory) should still carry the
    // is_active control — each renders 2 inputs named is_active (hidden "0" + checkbox
    // "1", see reference-form-modal.blade.php). No "edit" dialog should contribute any,
    // regardless of how many categories/units/subcategories exist, so the total must
    // stay exactly 6 — not "zero", which would wrongly break the Create forms too.
    $this->assertSame(6, substr_count($response->getContent(), 'name="is_active"'));
}
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: FAIL

- [ ] **Step 4: Wire `reference-table.blade.php`**

Add the two new props. Per row, compute `$inUse = $item->inventory_item_count > 0 || ($item->subcategory_count ?? 0) > 0`. Show: a small dependency-count line under the label (subcategory count — only when `isset($item->subcategory_count)` — and item count, e.g. via `Illuminate\Support\Str::plural`); a status badge (`Active`/`Retired`, replacing "Inactive" copy); the Edit button (unchanged); a Deactivate button (`data-dialog-open="{{ $dialogId }}-deactivate-{{ $item->id }}"`) only when `$item->is_active && $inUse`; a Reactivate button only when `! $item->is_active`; a Delete button only when `! $inUse`. After the row loop, render one `x-inventory.confirm-action-dialog` per row per available action (mirroring the existing per-row Edit-modal loop below it), each naming the record's label and relevant counts in its body, `confirmVariant="danger"` only for Delete.

- [ ] **Step 5: Wire `subcategory-table.blade.php`**

Same treatment, using `$subcategory->inventory_item_count` and `$subcategory->donation_item_count` (both named explicitly in the dependency line and the dialog bodies, e.g. "2 inventory items · 1 donation record"); `$inUse = $subcategory->inventory_item_count > 0 || $subcategory->donation_item_count > 0`. Route names: `inventory.subcategories.deactivate`/`.reactivate`/`.destroy`.

- [ ] **Step 6: Remove the Active checkbox from edit mode in both form modals**

In `reference-form-modal.blade.php` and `subcategory-form-modal.blade.php`, wrap the existing `is_active` checkbox block in `@if (! $item)` / `@if (! $subcategory)` respectively, so it still renders for the Create dialog but not for Edit.

- [ ] **Step 7: Pass the new props through `categories.blade.php`**

Add `deactivate-route-name="inventory.categories.deactivate"` / `reactivate-route-name="inventory.categories.reactivate"` to the Categories `x-inventory.reference-table` call, and the `inventory.units.*` equivalents to the Units call.

- [ ] **Step 8: Update `docs/category-management-admin-guide.md`**

Add a short section documenting subcategories (currently undocumented) and the new Deactivate/Reactivate actions, replacing the stale "Deactivate (edit → uncheck Active)" line under "Deactivating vs. deleting" with the new dedicated-action description.

- [ ] **Step 9: Run the tests to verify they pass**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: PASS — this also confirms Task 4's count-display tests now pass.

- [ ] **Step 10: Do not commit (user instruction) — leave changes staged for review.**

---

### Task 6: Full regression run and manual UI verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: PASS, zero failures, zero regressions outside this feature.

- [ ] **Step 2: Manually verify in a real browser** (per project convention for UI changes)

Start `npm run dev` + the Laravel dev server if not already running. As an admin, visit `/inventory/categories` and confirm: dependency counts are visible and correct for a known category/subcategory/unit; an in-use active row shows Deactivate (no Delete); deactivating it opens a centered confirmation dialog naming the counts, and confirming flips its badge to "Retired" and removes it from the inventory item create form's category dropdown; an inactive row shows Reactivate; an unused row shows Delete with its own confirmation dialog; Edit opens a centered modal with no Active checkbox; attempting to delete a category that has a subcategory is blocked with a clear error. Check responsiveness at a narrow viewport (per "keep all modals responsive").

- [ ] **Step 3: Do not commit (user instruction) — leave changes staged for review.**
