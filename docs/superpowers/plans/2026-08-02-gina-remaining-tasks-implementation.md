# Gina's Remaining Tasks (A6 + S6 + Dashboard Hook) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close out Gina's two remaining outstanding items from `docs/TEAM_TASK_ASSIGNMENT.md` — A6 (admin-managed inventory categories/units) and S6 (cash disbursement recording) — plus an additive Dashboard hook, per `docs/superpowers/specs/2026-08-02-gina-remaining-tasks-design.md`.

**Architecture:** Two new small Laravel modules following the exact Controller → Form Request → Service → Model → Policy pattern already used by `InventoryController`/`InventoryItemPolicy` and `DonationPortalController`/`ContributionPolicy`. A6 replaces `config('inventory.categories')`/`.units` with two DB-backed reference tables at their existing call sites. S6 adds a fully separate `cash_disbursements` table/module. The Dashboard gets one additive array key.

**Tech Stack:** Laravel 11, Blade components, Tailwind CSS, MySQL, PHPUnit feature tests with `RefreshDatabase`.

## Global Constraints

- Source of truth: `docs/PROJECT_REQUIREMENTS.md`, `docs/TEAM_TASK_ASSIGNMENT.md`, and `docs/superpowers/specs/2026-08-02-gina-remaining-tasks-design.md` — no architecture or features beyond what those documents describe.
- Do not modify any already-`done` feature except the specific call sites listed in Task 6 (all other existing files are read-only for this plan).
- Follow existing conventions exactly: thin controllers, Form Requests own validation + `prepareForValidation` normalization (`nullableTrim`/`nullableLower` private helpers), services own transactional writes, policies use `$user->hasRole([...])`, Blade components accept data via `@props` and never query the database.
- Reuse existing shared components: `x-button`, `x-form.input`, `x-form.label`, `x-form.error`, `x-alert`, `x-dashboard.panel`, `x-dashboard.empty-state`, `layouts.app`, and the existing native `<dialog>` + `data-dialog-open`/`data-dialog-close` JS controller in `resources/js/app.js` (already generic — no JS changes needed).
- No hardcoded values in controllers or Blade — enumerable values come from `config/` files or the new DB tables only.
- Do not touch `bank_ledger_entries`, `FinancialLedgerReportBuilder`, `Contribution`, `LogContribution`, `AdjustInventoryStock`, `CreateInventoryItem`, or any approval/status workflow for disbursements — that is Julius's future A7 work.
- No balance-ceiling validation on disbursement `amount` (only `min:0.01` / `max:99999999.99`) — documented gap pending Julius's Financial Ledger.
- Roles: A6 category/unit `create`/`update`/`delete` = `admin` only; A6 `viewAny` = `admin, secretary, treasurer`. S6 `viewAny`/`view`/`create` = `admin, secretary, treasurer`; no `update`/`delete` policy methods (immutable record).
- Every task that adds backend behavior must have a passing feature test before moving to the next task. Run tests with `php artisan test --filter=<TestClass>`.
- Migration timestamps continue the existing `YYYY_MM_DD_NNNNNN` convention; the newest existing migration is `2026_07_25_000002_...`, so new ones start at `2026_08_02_000001_...`.

---

## Task 1: Migration — `inventory_categories` and `inventory_units` tables, seeded from config

**Files:**
- Create: `database/migrations/2026_08_02_000001_create_inventory_categories_and_units_tables.php`
- Test: `tests/Feature/InventoryCategoryManagementTest.php`

**Interfaces:**
- Produces: `inventory_categories` table (`id, key, label, is_active, timestamps`), `inventory_units` table (same shape), both seeded 1:1 from `config('inventory.categories')`/`config('inventory.units')`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_categories_and_units_are_seeded_from_config(): void
    {
        $this->assertDatabaseHas('inventory_categories', ['key' => 'food', 'label' => 'Food', 'is_active' => true]);
        $this->assertDatabaseHas('inventory_units', ['key' => 'piece', 'label' => 'Piece', 'is_active' => true]);
        $this->assertSame(count(config('inventory.categories')), DB::table('inventory_categories')->count());
        $this->assertSame(count(config('inventory.units')), DB::table('inventory_units')->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: FAIL — `inventory_categories` table does not exist (SQL error).

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('inventory_units', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $now = now();

        DB::table('inventory_categories')->insert(
            collect(config('inventory.categories'))
                ->map(fn (string $label, string $key) => [
                    'key' => $key,
                    'label' => $label,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->values()
                ->all()
        );

        DB::table('inventory_units')->insert(
            collect(config('inventory.units'))
                ->map(fn (string $label, string $key) => [
                    'key' => $key,
                    'label' => $label,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->values()
                ->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_units');
        Schema::dropIfExists('inventory_categories');
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_02_000001_create_inventory_categories_and_units_tables.php tests/Feature/InventoryCategoryManagementTest.php
git commit -m "feat: add inventory_categories and inventory_units tables seeded from config"
```

---

## Task 2: `InventoryCategory` and `InventoryUnit` models

**Files:**
- Create: `app/Models/InventoryCategory.php`
- Create: `app/Models/InventoryUnit.php`
- Test: `tests/Feature/InventoryCategoryManagementTest.php` (append)

**Interfaces:**
- Consumes: `inventory_categories`/`inventory_units` tables from Task 1.
- Produces: `InventoryCategory::options(): array` (`key => label`, active only), `InventoryUnit::options(): array`, `InventoryCategory::scopeActive()`, `InventoryUnit::scopeActive()`, `InventoryCategory::inventoryItemColumn(): string` (returns `'category'`), `InventoryUnit::inventoryItemColumn(): string` (returns `'unit'`).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/InventoryCategoryManagementTest.php` (add these imports at the top: `use App\Models\InventoryCategory;` and `use App\Models\InventoryUnit;`):

```php
    public function test_inventory_category_options_only_include_active_categories(): void
    {
        InventoryCategory::query()->create(['key' => 'test_active', 'label' => 'Test Active', 'is_active' => true]);
        InventoryCategory::query()->create(['key' => 'test_inactive', 'label' => 'Test Inactive', 'is_active' => false]);

        $options = InventoryCategory::options();

        $this->assertArrayHasKey('test_active', $options);
        $this->assertArrayNotHasKey('test_inactive', $options);
    }

    public function test_inventory_unit_options_only_include_active_units(): void
    {
        InventoryUnit::query()->create(['key' => 'test_unit', 'label' => 'Test Unit', 'is_active' => true]);
        InventoryUnit::query()->create(['key' => 'test_unit_inactive', 'label' => 'Test Unit Inactive', 'is_active' => false]);

        $options = InventoryUnit::options();

        $this->assertArrayHasKey('test_unit', $options);
        $this->assertArrayNotHasKey('test_unit_inactive', $options);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: FAIL — `Class "App\Models\InventoryCategory" not found`.

- [ ] **Step 3: Write the models**

`app/Models/InventoryCategory.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function options(): array
    {
        return static::query()->active()->orderBy('label')->pluck('label', 'key')->all();
    }

    public static function inventoryItemColumn(): string
    {
        return 'category';
    }
}
```

`app/Models/InventoryUnit.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function options(): array
    {
        return static::query()->active()->orderBy('label')->pluck('label', 'key')->all();
    }

    public static function inventoryItemColumn(): string
    {
        return 'unit';
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Models/InventoryCategory.php app/Models/InventoryUnit.php tests/Feature/InventoryCategoryManagementTest.php
git commit -m "feat: add InventoryCategory and InventoryUnit models"
```

---

## Task 3: `InventoryCategoryPolicy` and `InventoryUnitPolicy`

**Files:**
- Create: `app/Policies/InventoryCategoryPolicy.php`
- Create: `app/Policies/InventoryUnitPolicy.php`
- Test: `tests/Feature/InventoryCategoryManagementTest.php` (append)

**Interfaces:**
- Consumes: `InventoryCategory`, `InventoryUnit` models from Task 2; `User::hasRole()` from `app/Models/User.php`.
- Produces: policy classes auto-discovered by Laravel's `Model` → `{Model}Policy` convention (already relied on by `InventoryItemPolicy`, `ContributionPolicy`, `BankLedgerEntryPolicy` — no manual registration needed).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/InventoryCategoryManagementTest.php` (add imports `use App\Models\Role;` and `use App\Models\User;` at the top):

```php
    public function test_inventory_category_policy_restricts_mutations_to_admin(): void
    {
        $admin = $this->userWithRole('admin');
        $secretary = $this->userWithRole('secretary');
        $category = InventoryCategory::query()->first();

        $this->assertTrue($admin->can('create', InventoryCategory::class));
        $this->assertFalse($secretary->can('create', InventoryCategory::class));
        $this->assertTrue($admin->can('update', $category));
        $this->assertFalse($secretary->can('update', $category));
        $this->assertTrue($admin->can('delete', $category));
        $this->assertFalse($secretary->can('delete', $category));
        $this->assertTrue($secretary->can('viewAny', InventoryCategory::class));
    }

    public function test_inventory_unit_policy_restricts_mutations_to_admin(): void
    {
        $admin = $this->userWithRole('admin');
        $secretary = $this->userWithRole('secretary');

        $this->assertTrue($admin->can('create', InventoryUnit::class));
        $this->assertFalse($secretary->can('create', InventoryUnit::class));
        $this->assertTrue($secretary->can('viewAny', InventoryUnit::class));
    }

    private function userWithRole(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role],
            config("auth_defaults.roles.{$role}"),
        );

        return User::factory()->create(['role_id' => $roleModel->id]);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: FAIL — `$admin->can('create', InventoryCategory::class)` returns `false` (no policy registered, Gate denies by default).

- [ ] **Step 3: Write the policies**

`app/Policies/InventoryCategoryPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\InventoryCategory;
use App\Models\User;

class InventoryCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'secretary', 'treasurer']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, InventoryCategory $inventoryCategory): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, InventoryCategory $inventoryCategory): bool
    {
        return $user->hasRole('admin');
    }
}
```

`app/Policies/InventoryUnitPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\InventoryUnit;
use App\Models\User;

class InventoryUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'secretary', 'treasurer']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, InventoryUnit $inventoryUnit): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, InventoryUnit $inventoryUnit): bool
    {
        return $user->hasRole('admin');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: PASS (6 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Policies/InventoryCategoryPolicy.php app/Policies/InventoryUnitPolicy.php tests/Feature/InventoryCategoryManagementTest.php
git commit -m "feat: add InventoryCategoryPolicy and InventoryUnitPolicy (admin-only mutations)"
```

---

## Task 4: `ManageInventoryReferenceItem` service

**Files:**
- Create: `app/Services/Inventory/ManageInventoryReferenceItem.php`
- Test: `tests/Feature/InventoryCategoryManagementTest.php` (append)

**Interfaces:**
- Consumes: `InventoryCategory::inventoryItemColumn()`/`InventoryUnit::inventoryItemColumn()` from Task 2; `App\Models\InventoryItem`.
- Produces: `ManageInventoryReferenceItem::create(string $modelClass, array $data): Model`, `::update(Model $item, array $data): Model`, `::delete(Model $item): void` (throws `Illuminate\Validation\ValidationException` if the item's `key` is referenced by any `inventory_items` row).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/InventoryCategoryManagementTest.php` (add imports `use App\Models\InventoryItem;`, `use App\Services\Inventory\ManageInventoryReferenceItem;`, `use Illuminate\Validation\ValidationException;` at the top):

```php
    public function test_manage_inventory_reference_item_service_creates_updates_and_deletes(): void
    {
        $manager = app(ManageInventoryReferenceItem::class);

        $category = $manager->create(InventoryCategory::class, ['key' => 'service_test', 'label' => 'Service Test', 'is_active' => true]);
        $this->assertDatabaseHas('inventory_categories', ['key' => 'service_test', 'label' => 'Service Test']);

        $manager->update($category, ['key' => 'service_test', 'label' => 'Service Test Updated', 'is_active' => false]);
        $this->assertDatabaseHas('inventory_categories', ['key' => 'service_test', 'label' => 'Service Test Updated', 'is_active' => false]);

        $manager->delete($category);
        $this->assertDatabaseMissing('inventory_categories', ['key' => 'service_test']);
    }

    public function test_manage_inventory_reference_item_service_blocks_delete_when_in_use(): void
    {
        $manager = app(ManageInventoryReferenceItem::class);
        $category = InventoryCategory::query()->where('key', 'food')->firstOrFail();

        InventoryItem::query()->create([
            'name' => 'In Use Item',
            'category' => 'food',
            'unit' => 'piece',
            'quantity' => 1,
            'current_quantity' => 1,
            'minimum_reserve_quantity' => 0,
            'storage_location' => 'Main Storage',
            'status' => config('inventory.defaults.status'),
            'received_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        $manager->delete($category);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: FAIL — `Target class [App\Services\Inventory\ManageInventoryReferenceItem] does not exist`.

- [ ] **Step 3: Write the service**

`app/Services/Inventory/ManageInventoryReferenceItem.php`:

```php
<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageInventoryReferenceItem
{
    public function create(string $modelClass, array $data): Model
    {
        return $modelClass::query()->create([
            'key' => $data['key'],
            'label' => $data['label'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(Model $item, array $data): Model
    {
        $item->update([
            'key' => $data['key'],
            'label' => $data['label'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $item;
    }

    public function delete(Model $item): void
    {
        DB::transaction(function () use ($item) {
            $column = $item::inventoryItemColumn();

            $inUse = InventoryItem::query()->where($column, $item->key)->exists();

            if ($inUse) {
                throw ValidationException::withMessages([
                    'key' => "This {$column} is used by existing inventory items. Deactivate it instead of deleting.",
                ]);
            }

            $item->delete();
        });
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: PASS (8 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Inventory/ManageInventoryReferenceItem.php tests/Feature/InventoryCategoryManagementTest.php
git commit -m "feat: add ManageInventoryReferenceItem service with in-use delete guard"
```

---

## Task 5: Form Requests, Controllers, and routes for categories/units CRUD

**Files:**
- Create: `app/Http/Requests/Inventory/StoreInventoryCategoryRequest.php`
- Create: `app/Http/Requests/Inventory/UpdateInventoryCategoryRequest.php`
- Create: `app/Http/Requests/Inventory/StoreInventoryUnitRequest.php`
- Create: `app/Http/Requests/Inventory/UpdateInventoryUnitRequest.php`
- Create: `app/Http/Controllers/InventoryCategoryController.php`
- Create: `app/Http/Controllers/InventoryUnitController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/InventoryCategoryManagementTest.php` (append)

**Interfaces:**
- Consumes: `ManageInventoryReferenceItem` (Task 4), `InventoryCategory`/`InventoryUnit` (Task 2).
- Produces: named routes `inventory.categories.index`, `inventory.categories.store`, `inventory.categories.update`, `inventory.categories.destroy`, `inventory.units.store`, `inventory.units.update`, `inventory.units.destroy`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/InventoryCategoryManagementTest.php`:

```php
    public function test_guest_is_redirected_from_categories_index(): void
    {
        $this->get(route('inventory.categories.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_view_or_manage_categories(): void
    {
        $secretary = $this->userWithRole('secretary');

        $this->actingAs($secretary)->get(route('inventory.categories.index'))->assertForbidden();
        $this->actingAs($secretary)->post(route('inventory.categories.store'), ['key' => 'blocked', 'label' => 'Blocked'])->assertForbidden();
    }

    public function test_admin_can_create_update_and_delete_a_category(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('inventory.categories.store'), [
            'key' => 'new_category',
            'label' => 'New Category',
            'is_active' => '1',
        ])->assertRedirect(route('inventory.categories.index'));

        $this->assertDatabaseHas('inventory_categories', ['key' => 'new_category', 'label' => 'New Category']);

        $category = InventoryCategory::query()->where('key', 'new_category')->firstOrFail();

        $this->actingAs($admin)->patch(route('inventory.categories.update', $category), [
            'key' => 'new_category',
            'label' => 'Renamed Category',
            'is_active' => '1',
        ])->assertRedirect(route('inventory.categories.index'));

        $this->assertDatabaseHas('inventory_categories', ['id' => $category->id, 'label' => 'Renamed Category']);

        $this->actingAs($admin)->delete(route('inventory.categories.destroy', $category))
            ->assertRedirect(route('inventory.categories.index'));

        $this->assertDatabaseMissing('inventory_categories', ['id' => $category->id]);
    }

    public function test_admin_can_create_and_delete_a_unit(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('inventory.units.store'), [
            'key' => 'new_unit',
            'label' => 'New Unit',
            'is_active' => '1',
        ])->assertRedirect(route('inventory.categories.index'));

        $unit = InventoryUnit::query()->where('key', 'new_unit')->firstOrFail();

        $this->actingAs($admin)->delete(route('inventory.units.destroy', $unit))
            ->assertRedirect(route('inventory.categories.index'));

        $this->assertDatabaseMissing('inventory_units', ['id' => $unit->id]);
    }

    public function test_deleting_a_category_in_use_is_blocked_over_http(): void
    {
        $admin = $this->userWithRole('admin');

        InventoryItem::query()->create([
            'name' => 'Blocks Delete',
            'category' => 'food',
            'unit' => 'piece',
            'quantity' => 1,
            'current_quantity' => 1,
            'minimum_reserve_quantity' => 0,
            'storage_location' => 'Main Storage',
            'status' => config('inventory.defaults.status'),
            'received_at' => now(),
        ]);

        $category = InventoryCategory::query()->where('key', 'food')->firstOrFail();

        $this->actingAs($admin)->delete(route('inventory.categories.destroy', $category))
            ->assertSessionHasErrors('key');

        $this->assertDatabaseHas('inventory_categories', ['id' => $category->id]);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: FAIL — `Route [inventory.categories.index] not defined`.

- [ ] **Step 3: Write the Form Requests**

`app/Http/Requests/Inventory/StoreInventoryCategoryRequest.php`:

```php
<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryCategory::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:100', Rule::unique('inventory_categories', 'key')],
            'label' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'key' => $this->normalizedKey('key'),
            'label' => $this->nullableTrim('label'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    private function normalizedKey(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : str($value)->slug('_')->toString();
    }

    private function nullableTrim(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
```

`app/Http/Requests/Inventory/UpdateInventoryCategoryRequest.php`:

```php
<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('inventoryCategory')) ?? false;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:100', Rule::unique('inventory_categories', 'key')->ignore($this->route('inventoryCategory'))],
            'label' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'key' => $this->normalizedKey('key'),
            'label' => $this->nullableTrim('label'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    private function normalizedKey(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : str($value)->slug('_')->toString();
    }

    private function nullableTrim(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
```

`app/Http/Requests/Inventory/StoreInventoryUnitRequest.php`:

```php
<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryUnit::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:100', Rule::unique('inventory_units', 'key')],
            'label' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'key' => $this->normalizedKey('key'),
            'label' => $this->nullableTrim('label'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    private function normalizedKey(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : str($value)->slug('_')->toString();
    }

    private function nullableTrim(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
```

`app/Http/Requests/Inventory/UpdateInventoryUnitRequest.php`:

```php
<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('inventoryUnit')) ?? false;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:100', Rule::unique('inventory_units', 'key')->ignore($this->route('inventoryUnit'))],
            'label' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'key' => $this->normalizedKey('key'),
            'label' => $this->nullableTrim('label'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    private function normalizedKey(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : str($value)->slug('_')->toString();
    }

    private function nullableTrim(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
```

- [ ] **Step 4: Write the controllers**

`app/Http/Controllers/InventoryCategoryController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\StoreInventoryCategoryRequest;
use App\Http\Requests\Inventory\UpdateInventoryCategoryRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryUnit;
use App\Services\Inventory\ManageInventoryReferenceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryCategoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', InventoryCategory::class);

        return view('inventory.categories', [
            'categories' => InventoryCategory::query()->orderBy('label')->get(),
            'units' => InventoryUnit::query()->orderBy('label')->get(),
        ]);
    }

    public function store(StoreInventoryCategoryRequest $request, ManageInventoryReferenceItem $manager): RedirectResponse
    {
        $manager->create(InventoryCategory::class, $request->validated());

        return redirect()->route('inventory.categories.index')->with('success', 'Category created successfully.');
    }

    public function update(UpdateInventoryCategoryRequest $request, InventoryCategory $inventoryCategory, ManageInventoryReferenceItem $manager): RedirectResponse
    {
        $manager->update($inventoryCategory, $request->validated());

        return redirect()->route('inventory.categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy(InventoryCategory $inventoryCategory, ManageInventoryReferenceItem $manager): RedirectResponse
    {
        Gate::authorize('delete', $inventoryCategory);

        $manager->delete($inventoryCategory);

        return redirect()->route('inventory.categories.index')->with('success', 'Category deleted successfully.');
    }
}
```

`app/Http/Controllers/InventoryUnitController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\StoreInventoryUnitRequest;
use App\Http\Requests\Inventory\UpdateInventoryUnitRequest;
use App\Models\InventoryUnit;
use App\Services\Inventory\ManageInventoryReferenceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class InventoryUnitController extends Controller
{
    public function store(StoreInventoryUnitRequest $request, ManageInventoryReferenceItem $manager): RedirectResponse
    {
        $manager->create(InventoryUnit::class, $request->validated());

        return redirect()->route('inventory.categories.index')->with('success', 'Unit created successfully.');
    }

    public function update(UpdateInventoryUnitRequest $request, InventoryUnit $inventoryUnit, ManageInventoryReferenceItem $manager): RedirectResponse
    {
        $manager->update($inventoryUnit, $request->validated());

        return redirect()->route('inventory.categories.index')->with('success', 'Unit updated successfully.');
    }

    public function destroy(InventoryUnit $inventoryUnit, ManageInventoryReferenceItem $manager): RedirectResponse
    {
        Gate::authorize('delete', $inventoryUnit);

        $manager->delete($inventoryUnit);

        return redirect()->route('inventory.categories.index')->with('success', 'Unit deleted successfully.');
    }
}
```

- [ ] **Step 5: Add routes**

In `routes/web.php`, add these imports near the top with the other controller imports:

```php
use App\Http\Controllers\InventoryCategoryController;
use App\Http\Controllers\InventoryUnitController;
```

Then add these routes directly after the existing `inventory.adjustments.store` route (still inside the `['auth', 'role:admin,secretary,treasurer']` group):

```php
    Route::get('/inventory/categories', [InventoryCategoryController::class, 'index'])->name('inventory.categories.index');
    Route::post('/inventory/categories', [InventoryCategoryController::class, 'store'])->name('inventory.categories.store');
    Route::patch('/inventory/categories/{inventoryCategory}', [InventoryCategoryController::class, 'update'])->name('inventory.categories.update');
    Route::delete('/inventory/categories/{inventoryCategory}', [InventoryCategoryController::class, 'destroy'])->name('inventory.categories.destroy');

    Route::post('/inventory/units', [InventoryUnitController::class, 'store'])->name('inventory.units.store');
    Route::patch('/inventory/units/{inventoryUnit}', [InventoryUnitController::class, 'update'])->name('inventory.units.update');
    Route::delete('/inventory/units/{inventoryUnit}', [InventoryUnitController::class, 'destroy'])->name('inventory.units.destroy');
```

Note: `inventory.categories.index` will fail with a missing-view error until Task 7 adds `resources/views/inventory/categories.blade.php` — that's expected; the test in this task only exercises store/update/destroy (which redirect, no view render) plus the guest/forbidden assertions on `index`, which return before rendering a view.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: PASS (13 tests). If `test_non_admin_cannot_view_or_manage_categories` fails on the `index` assertion because it needs the view, that confirms Task 7 is required first for that specific assertion — re-run after Task 7 to confirm full green; the `store`-side assertion in the same test will already pass.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/Inventory/StoreInventoryCategoryRequest.php app/Http/Requests/Inventory/UpdateInventoryCategoryRequest.php app/Http/Requests/Inventory/StoreInventoryUnitRequest.php app/Http/Requests/Inventory/UpdateInventoryUnitRequest.php app/Http/Controllers/InventoryCategoryController.php app/Http/Controllers/InventoryUnitController.php routes/web.php tests/Feature/InventoryCategoryManagementTest.php
git commit -m "feat: add category/unit CRUD controllers, form requests, and routes"
```

---

## Task 6: Update inventory item validation call sites to read from the database

**Files:**
- Modify: `app/Http/Requests/Inventory/StoreInventoryItemRequest.php`
- Modify: `app/Http/Requests/Inventory/UpdateInventoryItemRequest.php`
- Modify: `app/Http/Requests/Inventory/InventoryIndexRequest.php`
- Modify: `app/Models/InventoryItem.php`
- Test: `tests/Feature/InventoryCategoryManagementTest.php` (append)

**Interfaces:**
- Consumes: `InventoryCategory::options()`, `InventoryUnit::options()` from Task 2.
- No new interfaces produced — this task only swaps the source of truth used by existing validation.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/InventoryCategoryManagementTest.php`:

```php
    public function test_newly_created_category_is_immediately_valid_for_inventory_item_creation(): void
    {
        $admin = $this->userWithRole('admin');
        InventoryCategory::query()->create(['key' => 'emergency_kits', 'label' => 'Emergency Kits', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('inventory.store'), [
            'name' => 'Emergency Kit Bundle',
            'category' => 'emergency_kits',
            'unit' => 'piece',
            'initial_quantity' => 5,
            'minimum_reserve_quantity' => 1,
            'storage_location' => 'Main Storage',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('inventory_items', ['name' => 'Emergency Kit Bundle', 'category' => 'emergency_kits']);
    }

    public function test_deactivated_category_is_rejected_for_inventory_item_creation(): void
    {
        $admin = $this->userWithRole('admin');
        $category = InventoryCategory::query()->where('key', 'other')->firstOrFail();
        $category->update(['is_active' => false]);

        $response = $this->actingAs($admin)->post(route('inventory.store'), [
            'name' => 'Should Fail',
            'category' => 'other',
            'unit' => 'piece',
            'initial_quantity' => 5,
            'minimum_reserve_quantity' => 1,
            'storage_location' => 'Main Storage',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('category');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: FAIL — `test_newly_created_category_is_immediately_valid_for_inventory_item_creation` fails because `StoreInventoryItemRequest` still validates against `config('inventory.categories')`, which doesn't include `emergency_kits`.

- [ ] **Step 3: Update `StoreInventoryItemRequest`**

In `app/Http/Requests/Inventory/StoreInventoryItemRequest.php`, add imports and change the two config-based rules:

```php
use App\Models\InventoryCategory;
use App\Models\InventoryUnit;
```

Change:

```php
            'category' => ['required', Rule::in(array_keys(config('inventory.categories')))],
```

to:

```php
            'category' => ['required', Rule::in(array_keys(InventoryCategory::options()))],
```

Change:

```php
            'unit' => ['required', Rule::in(array_keys(config('inventory.units')))],
```

to:

```php
            'unit' => ['required', Rule::in(array_keys(InventoryUnit::options()))],
```

- [ ] **Step 4: Update `UpdateInventoryItemRequest`**

Apply the identical two rule changes (with the identical two `use` imports) to `app/Http/Requests/Inventory/UpdateInventoryItemRequest.php`.

- [ ] **Step 5: Update `InventoryIndexRequest`**

In `app/Http/Requests/Inventory/InventoryIndexRequest.php`, add `use App\Models\InventoryCategory;` and change:

```php
            'category' => ['nullable', Rule::in(array_keys(config('inventory.categories')))],
```

to:

```php
            'category' => ['nullable', Rule::in(array_keys(InventoryCategory::options()))],
```

- [ ] **Step 6: Update `InventoryItem::scopeSearch()`**

In `app/Models/InventoryItem.php`, change:

```php
        $matchingCategories = collect(config('inventory.categories'))
```

to:

```php
        $matchingCategories = collect(InventoryCategory::options())
```

No `use` import needed — `InventoryItem` and `InventoryCategory` are both in the `App\Models` namespace.

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: PASS (15 tests)

Also run the full existing inventory suite to confirm no regression:

Run: `php artisan test --filter=InventoryManagementTest`
Expected: PASS (all previously-passing tests still pass, since seeded DB categories/units are identical to the old config keys)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/Inventory/StoreInventoryItemRequest.php app/Http/Requests/Inventory/UpdateInventoryItemRequest.php app/Http/Requests/Inventory/InventoryIndexRequest.php app/Models/InventoryItem.php tests/Feature/InventoryCategoryManagementTest.php
git commit -m "feat: validate inventory item category/unit against database instead of config"
```

---

## Task 7: Blade UI — "Manage Item Categories" page and inventory index entry point

**Files:**
- Create: `resources/views/inventory/categories.blade.php`
- Create: `resources/views/components/inventory/reference-table.blade.php`
- Create: `resources/views/components/inventory/reference-form-modal.blade.php`
- Modify: `resources/views/inventory/index.blade.php`
- Modify: `resources/views/components/inventory/create-modal.blade.php`
- Modify: `resources/views/components/inventory/item-form.blade.php`
- Modify: `app/Http/Controllers/InventoryController.php`
- Test: `tests/Feature/InventoryCategoryManagementTest.php` (append)

**Interfaces:**
- Consumes: `InventoryCategory`, `InventoryUnit` (Task 2); routes from Task 5.
- No new PHP interfaces — this task is UI-only plus the controller's view data.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/InventoryCategoryManagementTest.php`:

```php
    public function test_manage_categories_page_lists_seeded_categories_and_units(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->get(route('inventory.categories.index'));

        $response->assertOk();
        $response->assertSee('Manage Item Categories');
        $response->assertSee('Food');
        $response->assertSee('Piece');
    }

    public function test_manage_categories_button_hidden_from_non_admin_on_inventory_index(): void
    {
        $secretary = $this->userWithRole('secretary');

        $response = $this->actingAs($secretary)->get(route('inventory.index'));

        $response->assertOk();
        $response->assertDontSee('Manage Categories');
    }

    public function test_manage_categories_button_visible_to_admin_on_inventory_index(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->get(route('inventory.index'));

        $response->assertOk();
        $response->assertSee('Manage Categories');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: FAIL — `inventory.categories.index` errors with "View [inventory.categories] not found", and `inventory.index` doesn't render the "Manage Categories" text yet.

- [ ] **Step 3: Update `InventoryController::index()`**

In `app/Http/Controllers/InventoryController.php`, add imports:

```php
use App\Models\InventoryCategory;
use App\Models\InventoryUnit;
```

Change the `return view('inventory.index', [...])` call to include two new keys:

```php
        return view('inventory.index', [
            'items' => $items,
            'filters' => $filters,
            'alerts' => $alertQuery->get(),
            'categories' => InventoryCategory::query()->active()->orderBy('label')->get(),
            'units' => InventoryUnit::query()->active()->orderBy('label')->get(),
        ]);
```

- [ ] **Step 4: Update `item-form.blade.php` to accept categories/units as props**

Replace the top of `resources/views/components/inventory/item-form.blade.php`:

```php
@props(['item' => null])
```

with:

```php
@props(['item' => null, 'categories', 'units'])
```

Replace the category `<select>` block:

```blade
        <select id="category" name="category" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            <option value="">Select category</option>
            @foreach (config('inventory.categories') as $key => $label)
                <option value="{{ $key }}" @selected(old('category', $item?->category) === $key)>{{ $label }}</option>
            @endforeach
        </select>
```

with:

```blade
        <select id="category" name="category" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            <option value="">Select category</option>
            @foreach ($categories as $category)
                <option value="{{ $category->key }}" @selected(old('category', $item?->category) === $category->key)>{{ $category->label }}</option>
            @endforeach
        </select>
```

Replace the unit `<select>` block:

```blade
        <select id="unit" name="unit" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            @foreach (config('inventory.units') as $key => $label)
                <option value="{{ $key }}" @selected(old('unit', $item?->unit ?? config('inventory.defaults.unit')) === $key)>{{ $label }}</option>
            @endforeach
        </select>
```

with:

```blade
        <select id="unit" name="unit" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            @foreach ($units as $unit)
                <option value="{{ $unit->key }}" @selected(old('unit', $item?->unit ?? config('inventory.defaults.unit')) === $unit->key)>{{ $unit->label }}</option>
            @endforeach
        </select>
```

- [ ] **Step 5: Update `create-modal.blade.php` to pass categories/units through**

Change the top of `resources/views/components/inventory/create-modal.blade.php`:

```php
@php($open = old('_modal') === 'create')
```

to:

```php
@props(['categories', 'units'])

@php($open = old('_modal') === 'create')
```

Change:

```blade
            <x-inventory.item-form />
```

to:

```blade
            <x-inventory.item-form :categories="$categories" :units="$units" />
```

- [ ] **Step 6: Update `inventory/index.blade.php`**

Change the header block:

```blade
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">In-Kind Inventory</h1>
                <p class="mt-1 text-sm text-slate-600">Track donated goods, reserve levels, and stock movement.</p>
            </div>

            @can('create', App\Models\InventoryItem::class)
                <x-button type="button" class="sm:w-auto" data-dialog-open="create-inventory-item-dialog">
                    Add New Inventory Item
                </x-button>
            @endcan
        </div>
```

to:

```blade
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">In-Kind Inventory</h1>
                <p class="mt-1 text-sm text-slate-600">Track donated goods, reserve levels, and stock movement.</p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                @can('create', App\Models\InventoryCategory::class)
                    <a href="{{ route('inventory.categories.index') }}" class="inline-flex items-center justify-center rounded-md border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Manage Categories &amp; Units
                    </a>
                @endcan
                @can('create', App\Models\InventoryItem::class)
                    <x-button type="button" class="sm:w-auto" data-dialog-open="create-inventory-item-dialog">
                        Add New Inventory Item
                    </x-button>
                @endcan
            </div>
        </div>
```

Change:

```blade
    @can('create', App\Models\InventoryItem::class)
        <x-inventory.create-modal />
    @endcan
```

to:

```blade
    @can('create', App\Models\InventoryItem::class)
        <x-inventory.create-modal :categories="$categories" :units="$units" />
    @endcan
```

- [ ] **Step 7: Create `reference-form-modal.blade.php`**

`resources/views/components/inventory/reference-form-modal.blade.php`:

```blade
@props(['dialogId', 'action', 'method', 'item', 'title'])

<dialog id="{{ $dialogId }}" class="w-[min(480px,calc(100vw-2rem))] rounded-lg p-0 shadow-xl backdrop:bg-slate-950/50">
    <form method="POST" action="{{ $action }}" class="bg-white">
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif

        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-950">{{ $title }}</h2>
            <button type="button" class="rounded-md px-2 py-1 text-sm text-slate-500 hover:bg-slate-100" data-dialog-close="{{ $dialogId }}">Close</button>
        </div>

        <div class="space-y-4 px-5 py-4">
            <x-form.input name="key" label="Key" :value="$item?->key" required />
            <x-form.input name="label" label="Label" :value="$item?->label" required />

            <div>
                <input type="hidden" name="is_active" value="0">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', $item?->is_active ?? true))>
                    Active
                </label>
            </div>
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end">
            <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white" data-dialog-close="{{ $dialogId }}">Cancel</button>
            <x-button class="sm:w-auto">Save</x-button>
        </div>
    </form>
</dialog>
```

- [ ] **Step 8: Create `reference-table.blade.php`**

`resources/views/components/inventory/reference-table.blade.php`:

```blade
@props(['title', 'items', 'storeRoute', 'updateRouteName', 'deleteRouteName', 'dialogId'])

<div class="rounded-lg border border-slate-200 bg-white p-4">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-base font-semibold text-slate-950">{{ $title }}</h2>
        <x-button type="button" class="w-auto" data-dialog-open="{{ $dialogId }}-create">Add {{ Illuminate\Support\Str::singular($title) }}</x-button>
    </div>

    @if ($items->isEmpty())
        <x-dashboard.empty-state :message="'No '.Illuminate\Support\Str::lower($title).' recorded yet.'" />
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Key</th>
                        <th class="px-3 py-2">Label</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($items as $item)
                        <tr>
                            <td class="px-3 py-2 font-mono text-xs text-slate-600">{{ $item->key }}</td>
                            <td class="px-3 py-2 text-slate-900">{{ $item->label }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $item->is_active ? 'bg-emerald-100 text-emerald-800 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                                    {{ $item->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" data-dialog-open="{{ $dialogId }}-edit-{{ $item->id }}">Edit</button>
                                    <form method="POST" action="{{ route($deleteRouteName, $item) }}" onsubmit="return confirm('Delete this entry? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-md border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <x-inventory.reference-form-modal
                            :dialog-id="$dialogId.'-edit-'.$item->id"
                            :action="route($updateRouteName, $item)"
                            method="PATCH"
                            :item="$item"
                            :title="'Edit '.Illuminate\Support\Str::singular($title)"
                        />
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<x-inventory.reference-form-modal
    :dialog-id="$dialogId.'-create'"
    :action="$storeRoute"
    method="POST"
    :item="null"
    :title="'Add '.Illuminate\Support\Str::singular($title)"
/>
```

- [ ] **Step 9: Create `inventory/categories.blade.php`**

`resources/views/inventory/categories.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Manage Item Categories')
@section('page-title', 'Manage Item Categories')

@section('content')
    @if ($errors->any())
        <x-alert type="error" class="mb-6">Please review the highlighted fields and try again.</x-alert>
    @endif

    <div class="space-y-6">
        <div>
            <a href="{{ route('inventory.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Back to inventory</a>
            <h1 class="mt-2 text-2xl font-semibold text-slate-950">Manage Item Categories</h1>
            <p class="mt-1 text-sm text-slate-600">Add, rename, or retire the categories and units used across in-kind inventory.</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-inventory.reference-table
                title="Categories"
                :items="$categories"
                :store-route="route('inventory.categories.store')"
                update-route-name="inventory.categories.update"
                delete-route-name="inventory.categories.destroy"
                dialog-id="category"
            />
            <x-inventory.reference-table
                title="Units"
                :items="$units"
                :store-route="route('inventory.units.store')"
                update-route-name="inventory.units.update"
                delete-route-name="inventory.units.destroy"
                dialog-id="unit"
            />
        </div>
    </div>
@endsection
```

- [ ] **Step 10: Run test to verify it passes**

Run: `php artisan test --filter=InventoryCategoryManagementTest`
Expected: PASS (18 tests)

Also re-run to confirm Task 5's `test_non_admin_cannot_view_or_manage_categories` now fully passes (it needed this task's view):

Run: `php artisan test --filter=InventoryManagementTest`
Expected: PASS (full existing suite still green — `item-form.blade.php` now requires `categories`/`units` props, which `InventoryController::index` now always provides)

- [ ] **Step 11: Commit**

```bash
git add resources/views/inventory/categories.blade.php resources/views/components/inventory/reference-table.blade.php resources/views/components/inventory/reference-form-modal.blade.php resources/views/inventory/index.blade.php resources/views/components/inventory/create-modal.blade.php resources/views/components/inventory/item-form.blade.php app/Http/Controllers/InventoryController.php tests/Feature/InventoryCategoryManagementTest.php
git commit -m "feat: add Manage Item Categories admin UI"
```

---

## Task 8: `config/disbursements.php` and `cash_disbursements` migration

**Files:**
- Create: `config/disbursements.php`
- Create: `database/migrations/2026_08_02_000002_create_cash_disbursements_table.php`
- Test: `tests/Feature/CashDisbursementTest.php`

**Interfaces:**
- Produces: `cash_disbursements` table (`id, disbursed_to, purpose, amount, payment_method, reference_number, notes, disbursed_at, created_by, timestamps`); `config('disbursements.purposes')`, `config('disbursements.pagination.per_page')`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CashDisbursementTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_disbursements_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('cash_disbursements', [
            'id', 'disbursed_to', 'purpose', 'amount', 'payment_method',
            'reference_number', 'notes', 'disbursed_at', 'created_by', 'created_at', 'updated_at',
        ]));
    }

    public function test_disbursement_purposes_are_config_backed(): void
    {
        $this->assertArrayHasKey('aid_payout', config('disbursements.purposes'));
        $this->assertArrayHasKey('operational_expense', config('disbursements.purposes'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CashDisbursementTest`
Expected: FAIL — `cash_disbursements` table does not exist.

- [ ] **Step 3: Write the config file**

`config/disbursements.php`:

```php
<?php

return [
    'purposes' => [
        'aid_payout' => 'Aid Payout',
        'operational_expense' => 'Operational Expense',
        'honorarium' => 'Honorarium',
        'utilities' => 'Utilities',
        'supplies_purchase' => 'Supplies Purchase',
        'other' => 'Other',
    ],

    'pagination' => [
        'per_page' => 10,
    ],
];
```

- [ ] **Step 4: Write the migration**

`database/migrations/2026_08_02_000002_create_cash_disbursements_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_disbursements', function (Blueprint $table) {
            $table->id();
            $table->string('disbursed_to');
            $table->string('purpose')->index();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method')->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('disbursed_at')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['purpose', 'disbursed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_disbursements');
    }
};
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=CashDisbursementTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add config/disbursements.php database/migrations/2026_08_02_000002_create_cash_disbursements_table.php tests/Feature/CashDisbursementTest.php
git commit -m "feat: add cash_disbursements table and config/disbursements.php"
```

---

## Task 9: `CashDisbursement` model and `CashDisbursementPolicy`

**Files:**
- Create: `app/Models/CashDisbursement.php`
- Create: `app/Policies/CashDisbursementPolicy.php`
- Test: `tests/Feature/CashDisbursementTest.php` (append)

**Interfaces:**
- Consumes: `cash_disbursements` table (Task 8), `User::hasRole()`.
- Produces: `CashDisbursement::creator(): BelongsTo`, `::scopeSearch()`, `::scopePurpose()`, `::scopeDateRange()`; policy auto-discovered (`viewAny`, `view`, `create` only — no `update`/`delete`).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/CashDisbursementTest.php` (add imports `use App\Models\CashDisbursement;`, `use App\Models\Role;`, `use App\Models\User;` at the top):

```php
    public function test_cash_disbursement_policy_allows_admin_secretary_and_treasurer(): void
    {
        foreach (['admin', 'secretary', 'treasurer'] as $role) {
            $user = $this->userWithRole($role);

            $this->assertTrue($user->can('viewAny', CashDisbursement::class));
            $this->assertTrue($user->can('create', CashDisbursement::class));
        }
    }

    private function userWithRole(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role],
            config("auth_defaults.roles.{$role}"),
        );

        return User::factory()->create(['role_id' => $roleModel->id]);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CashDisbursementTest`
Expected: FAIL — `Class "App\Models\CashDisbursement" not found`.

- [ ] **Step 3: Write the model**

`app/Models/CashDisbursement.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashDisbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'disbursed_to',
        'purpose',
        'amount',
        'payment_method',
        'reference_number',
        'notes',
        'disbursed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'disbursed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function ($query) use ($term) {
            $query
                ->where('disbursed_to', 'like', "%{$term}%")
                ->orWhere('reference_number', 'like', "%{$term}%")
                ->orWhere('notes', 'like', "%{$term}%");
        });
    }

    public function scopePurpose($query, ?string $purpose)
    {
        return $purpose ? $query->where('purpose', $purpose) : $query;
    }

    public function scopeDateRange($query, ?string $from, ?string $to)
    {
        return $query
            ->when($from, fn ($query) => $query->whereDate('disbursed_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('disbursed_at', '<=', $to));
    }
}
```

- [ ] **Step 4: Write the policy**

`app/Policies/CashDisbursementPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\CashDisbursement;
use App\Models\User;

class CashDisbursementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'secretary', 'treasurer']);
    }

    public function view(User $user, CashDisbursement $cashDisbursement): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=CashDisbursementTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Models/CashDisbursement.php app/Policies/CashDisbursementPolicy.php tests/Feature/CashDisbursementTest.php
git commit -m "feat: add CashDisbursement model and policy"
```

---

## Task 10: `RecordCashDisbursement` service and Form Requests

**Files:**
- Create: `app/Services/Disbursements/RecordCashDisbursement.php`
- Create: `app/Http/Requests/Disbursements/StoreCashDisbursementRequest.php`
- Create: `app/Http/Requests/Disbursements/CashDisbursementIndexRequest.php`
- Test: `tests/Feature/CashDisbursementTest.php` (append)

**Interfaces:**
- Consumes: `CashDisbursement` model (Task 9).
- Produces: `RecordCashDisbursement::handle(array $data, User $user): CashDisbursement`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/CashDisbursementTest.php` (add `use App\Services\Disbursements\RecordCashDisbursement;` import at the top):

```php
    public function test_record_cash_disbursement_service_creates_a_disbursement(): void
    {
        $user = User::factory()->create();
        $service = app(RecordCashDisbursement::class);

        $disbursement = $service->handle([
            'disbursed_to' => 'Juan Dela Cruz',
            'purpose' => 'aid_payout',
            'amount' => 1500.50,
            'payment_method' => 'cash',
            'reference_number' => null,
            'notes' => 'Typhoon relief aid',
        ], $user);

        $this->assertDatabaseHas('cash_disbursements', [
            'id' => $disbursement->id,
            'disbursed_to' => 'Juan Dela Cruz',
            'purpose' => 'aid_payout',
            'amount' => 1500.50,
            'created_by' => $user->id,
        ]);
        $this->assertNotNull($disbursement->disbursed_at);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CashDisbursementTest`
Expected: FAIL — `Target class [App\Services\Disbursements\RecordCashDisbursement] does not exist`.

- [ ] **Step 3: Write the service**

`app/Services/Disbursements/RecordCashDisbursement.php`:

```php
<?php

namespace App\Services\Disbursements;

use App\Models\CashDisbursement;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RecordCashDisbursement
{
    public function handle(array $data, User $user): CashDisbursement
    {
        return DB::transaction(fn () => CashDisbursement::query()->create([
            'disbursed_to' => $data['disbursed_to'],
            'purpose' => $data['purpose'],
            'amount' => $data['amount'],
            'payment_method' => Arr::get($data, 'payment_method'),
            'reference_number' => Arr::get($data, 'reference_number'),
            'notes' => Arr::get($data, 'notes'),
            'disbursed_at' => Arr::get($data, 'disbursed_at') ?? now(),
            'created_by' => $user->id,
        ]));
    }
}
```

- [ ] **Step 4: Write the Form Requests**

`app/Http/Requests/Disbursements/StoreCashDisbursementRequest.php`:

```php
<?php

namespace App\Http\Requests\Disbursements;

use App\Models\CashDisbursement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashDisbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CashDisbursement::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'disbursed_to' => ['required', 'string', 'max:255'],
            'purpose' => ['required', Rule::in(array_keys(config('disbursements.purposes')))],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'payment_method' => ['nullable', Rule::in(array_keys(config('donations.payment_methods')))],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'disbursed_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'disbursed_to' => $this->nullableTrim('disbursed_to'),
            'purpose' => $this->nullableLower('purpose'),
            'payment_method' => $this->nullableLower('payment_method'),
            'reference_number' => $this->nullableTrim('reference_number'),
            'notes' => $this->nullableTrim('notes'),
            'disbursed_at' => $this->nullableTrim('disbursed_at'),
        ]);
    }

    private function nullableTrim(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }

    private function nullableLower(string $key): ?string
    {
        $value = $this->nullableTrim($key);

        return $value ? strtolower($value) : null;
    }
}
```

`app/Http/Requests/Disbursements/CashDisbursementIndexRequest.php`:

```php
<?php

namespace App\Http\Requests\Disbursements;

use App\Models\CashDisbursement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashDisbursementIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CashDisbursement::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', Rule::in(array_keys(config('disbursements.purposes')))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->nullableTrim('search'),
            'purpose' => $this->nullableLower('purpose'),
            'from' => $this->nullableTrim('from'),
            'to' => $this->nullableTrim('to'),
        ]);
    }

    private function nullableTrim(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }

    private function nullableLower(string $key): ?string
    {
        $value = $this->nullableTrim($key);

        return $value ? strtolower($value) : null;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=CashDisbursementTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Services/Disbursements/RecordCashDisbursement.php app/Http/Requests/Disbursements/StoreCashDisbursementRequest.php app/Http/Requests/Disbursements/CashDisbursementIndexRequest.php tests/Feature/CashDisbursementTest.php
git commit -m "feat: add RecordCashDisbursement service and disbursement form requests"
```

---

## Task 11: `CashDisbursementController` and routes

**Files:**
- Create: `app/Http/Controllers/CashDisbursementController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CashDisbursementTest.php` (append)

**Interfaces:**
- Consumes: `CashDisbursementIndexRequest`, `StoreCashDisbursementRequest`, `RecordCashDisbursement` (Task 10).
- Produces: named routes `cash-disbursements.index`, `cash-disbursements.store`, `cash-disbursements.show`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/CashDisbursementTest.php`:

```php
    public function test_guest_is_redirected_from_cash_disbursements_index(): void
    {
        $this->get(route('cash-disbursements.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_roles_can_view_and_record_a_disbursement(): void
    {
        foreach (['admin', 'secretary', 'treasurer'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('cash-disbursements.index'))->assertOk();

            $response = $this->actingAs($user)->post(route('cash-disbursements.store'), [
                'disbursed_to' => 'Recipient '.$role,
                'purpose' => 'operational_expense',
                'amount' => 250,
                'payment_method' => 'cash',
            ]);

            $response->assertRedirect(route('cash-disbursements.index'));
            $this->assertDatabaseHas('cash_disbursements', [
                'disbursed_to' => 'Recipient '.$role,
                'created_by' => $user->id,
            ]);
        }
    }

    public function test_disbursement_validation_rejects_invalid_amount_and_purpose(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('cash-disbursements.store'), [
            'disbursed_to' => 'Someone',
            'purpose' => 'not_a_real_purpose',
            'amount' => 0,
        ]);

        $response->assertSessionHasErrors(['purpose', 'amount']);
        $this->assertSame(0, CashDisbursement::query()->count());
    }

    public function test_index_filters_by_search_and_purpose(): void
    {
        $user = User::factory()->create();

        CashDisbursement::query()->create([
            'disbursed_to' => 'Maria Clara',
            'purpose' => 'aid_payout',
            'amount' => 500,
            'disbursed_at' => now(),
            'created_by' => $user->id,
        ]);
        CashDisbursement::query()->create([
            'disbursed_to' => 'Office Supplies Co.',
            'purpose' => 'supplies_purchase',
            'amount' => 800,
            'disbursed_at' => now(),
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('cash-disbursements.index', ['search' => 'Maria']));

        $response->assertOk();
        $response->assertSee('Maria Clara');
        $response->assertDontSee('Office Supplies Co.');
    }

    public function test_show_page_renders_a_single_disbursement(): void
    {
        $user = User::factory()->create();
        $disbursement = CashDisbursement::query()->create([
            'disbursed_to' => 'Pedro Penduko',
            'purpose' => 'honorarium',
            'amount' => 1200,
            'disbursed_at' => now(),
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('cash-disbursements.show', $disbursement));

        $response->assertOk();
        $response->assertSee('Pedro Penduko');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CashDisbursementTest`
Expected: FAIL — `Route [cash-disbursements.index] not defined`.

- [ ] **Step 3: Write the controller**

`app/Http/Controllers/CashDisbursementController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Disbursements\CashDisbursementIndexRequest;
use App\Http\Requests\Disbursements\StoreCashDisbursementRequest;
use App\Models\CashDisbursement;
use App\Services\Disbursements\RecordCashDisbursement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CashDisbursementController extends Controller
{
    public function index(CashDisbursementIndexRequest $request): View
    {
        $filters = $request->validated();

        $disbursements = CashDisbursement::query()
            ->select(['id', 'disbursed_to', 'purpose', 'amount', 'payment_method', 'reference_number', 'notes', 'disbursed_at', 'created_by'])
            ->with('creator:id,first_name,last_name,name')
            ->search($filters['search'] ?? null)
            ->purpose($filters['purpose'] ?? null)
            ->dateRange($filters['from'] ?? null, $filters['to'] ?? null)
            ->latest('disbursed_at')
            ->latest()
            ->paginate(config('disbursements.pagination.per_page'))
            ->withQueryString();

        return view('cash-disbursements.index', [
            'disbursements' => $disbursements,
            'filters' => $filters,
        ]);
    }

    public function store(StoreCashDisbursementRequest $request, RecordCashDisbursement $recorder): RedirectResponse
    {
        $disbursement = $recorder->handle($request->validated(), $request->user());

        return redirect()
            ->route('cash-disbursements.index')
            ->with('success', 'Cash disbursement recorded successfully.')
            ->with('highlight_cash_disbursement', $disbursement->id);
    }

    public function show(CashDisbursement $cashDisbursement): View
    {
        Gate::authorize('view', $cashDisbursement);

        $cashDisbursement->load('creator:id,first_name,last_name,name');

        return view('cash-disbursements.show', compact('cashDisbursement'));
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/web.php`, add this import:

```php
use App\Http\Controllers\CashDisbursementController;
```

Then add these routes inside the existing authenticated group, directly after the `donations.verify` route:

```php
    Route::get('/cash-disbursements', [CashDisbursementController::class, 'index'])->name('cash-disbursements.index');
    Route::post('/cash-disbursements', [CashDisbursementController::class, 'store'])->name('cash-disbursements.store');
    Route::get('/cash-disbursements/{cashDisbursement}', [CashDisbursementController::class, 'show'])->name('cash-disbursements.show');
```

Note: `index` and `show` will error with a missing-view exception until Task 12 adds the Blade views — expected at this point; commit anyway since the store/validation/guest-redirect assertions in this task's test already exercise real, working behavior.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=CashDisbursementTest`
Expected: Most tests PASS; `test_authorized_roles_can_view_and_record_a_disbursement`, `test_index_filters_by_search_and_purpose`, and `test_show_page_renders_a_single_disbursement` will fail on the `assertOk()`/`assertSee()` calls because the views don't exist yet — that's expected and resolved by Task 12.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/CashDisbursementController.php routes/web.php tests/Feature/CashDisbursementTest.php
git commit -m "feat: add CashDisbursementController and routes"
```

---

## Task 12: Blade UI — Cash Disbursements pages, components, and sidebar entry

**Files:**
- Create: `resources/views/cash-disbursements/index.blade.php`
- Create: `resources/views/cash-disbursements/show.blade.php`
- Create: `resources/views/components/cash-disbursements/filter-bar.blade.php`
- Create: `resources/views/components/cash-disbursements/table.blade.php`
- Create: `resources/views/components/cash-disbursements/table-row.blade.php`
- Create: `resources/views/components/cash-disbursements/form.blade.php`
- Create: `resources/views/components/cash-disbursements/log-modal.blade.php`
- Modify: `resources/views/components/app/sidebar.blade.php`
- Test: `tests/Feature/CashDisbursementTest.php` (append)

**Interfaces:**
- Consumes: routes from Task 11; `CashDisbursement` model.
- No new PHP interfaces.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/CashDisbursementTest.php`:

```php
    public function test_cash_disbursements_index_renders_record_button_and_table(): void
    {
        $user = User::factory()->create();
        CashDisbursement::query()->create([
            'disbursed_to' => 'Sample Recipient',
            'purpose' => 'aid_payout',
            'amount' => 300,
            'disbursed_at' => now(),
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('cash-disbursements.index'));

        $response->assertOk();
        $response->assertSee('Record Disbursement');
        $response->assertSee('Sample Recipient');
    }

    public function test_sidebar_links_to_cash_disbursements(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('cash-disbursements.index'), false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CashDisbursementTest`
Expected: FAIL — missing view `cash-disbursements.index`, and the sidebar doesn't yet contain the route.

- [ ] **Step 3: Create the field partial**

`resources/views/components/cash-disbursements/form.blade.php`:

```blade
@props(['disbursement' => null])

<div class="grid gap-4 md:grid-cols-2">
    <x-form.input name="disbursed_to" label="Recipient / Payee" :value="$disbursement?->disbursed_to" required />

    <div>
        <x-form.label for="purpose">Purpose</x-form.label>
        <select id="purpose" name="purpose" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            <option value="">Select purpose</option>
            @foreach (config('disbursements.purposes') as $key => $label)
                <option value="{{ $key }}" @selected(old('purpose', $disbursement?->purpose) === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <x-form.error name="purpose" />
    </div>

    <x-form.input name="amount" label="Amount" type="number" step="0.01" min="0.01" :value="$disbursement?->amount" required />

    <div>
        <x-form.label for="payment_method">Payment Method</x-form.label>
        <select id="payment_method" name="payment_method" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">Not provided</option>
            @foreach (config('donations.payment_methods') as $key => $label)
                <option value="{{ $key }}" @selected(old('payment_method', $disbursement?->payment_method) === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <x-form.error name="payment_method" />
    </div>

    <x-form.input name="reference_number" label="Reference Number" :value="$disbursement?->reference_number" />
    <x-form.input name="disbursed_at" label="Date Disbursed" type="date" :value="$disbursement?->disbursed_at?->format('Y-m-d')" />
</div>

<div>
    <x-form.label for="notes">Notes</x-form.label>
    <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notes', $disbursement?->notes) }}</textarea>
    <x-form.error name="notes" />
</div>
```

- [ ] **Step 4: Create the log modal**

`resources/views/components/cash-disbursements/log-modal.blade.php`:

```blade
<dialog id="log-disbursement-dialog" class="w-[min(760px,calc(100vw-2rem))] rounded-lg p-0 shadow-xl backdrop:bg-slate-950/50" @if($errors->any()) open @endif>
    <form method="POST" action="{{ route('cash-disbursements.store') }}" class="max-h-[90vh] overflow-y-auto bg-white">
        @csrf

        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">Record Cash Disbursement</h2>
                <p class="text-sm text-slate-500">Log an outgoing cash payment such as aid payout or an operational expense.</p>
            </div>
            <button type="button" class="rounded-md px-2 py-1 text-2xl leading-none text-slate-500 hover:bg-slate-100" data-dialog-close="log-disbursement-dialog">&times;</button>
        </div>

        <div class="space-y-5 px-5 py-5">
            <x-cash-disbursements.form />
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end">
            <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white" data-dialog-close="log-disbursement-dialog">Cancel</button>
            <x-button class="sm:w-auto">Save Disbursement</x-button>
        </div>
    </form>
</dialog>
```

- [ ] **Step 5: Create the table components**

`resources/views/components/cash-disbursements/table-row.blade.php`:

```blade
@props(['disbursement'])

@php
    $creatorName = $disbursement->creator?->name
        ?? trim(($disbursement->creator?->first_name ?? '').' '.($disbursement->creator?->last_name ?? ''));
@endphp

<tr @class(['bg-blue-50/60' => session('highlight_cash_disbursement') === $disbursement->id])>
    <td class="whitespace-nowrap px-4 py-4 text-slate-600">{{ optional($disbursement->disbursed_at)->format('M d, Y') }}</td>
    <td class="px-4 py-4">
        <p class="max-w-44 break-words font-medium text-slate-950">{{ $disbursement->disbursed_to }}</p>
        <p class="text-xs text-slate-500">{{ $disbursement->reference_number ?? 'No reference' }}</p>
    </td>
    <td class="px-4 py-4 text-slate-700">{{ config("disbursements.purposes.{$disbursement->purpose}") }}</td>
    <td class="hidden px-4 py-4 text-slate-700 md:table-cell">{{ $disbursement->payment_method ? config("donations.payment_methods.{$disbursement->payment_method}") : 'Not provided' }}</td>
    <td class="whitespace-nowrap px-4 py-4 text-right font-medium text-slate-950">PHP {{ number_format((float) $disbursement->amount, 2) }}</td>
    <td class="hidden px-4 py-4 text-slate-600 lg:table-cell">{{ $creatorName ?: 'System' }}</td>
    <td class="whitespace-nowrap px-4 py-4 text-right">
        <a href="{{ route('cash-disbursements.show', $disbursement) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">View</a>
    </td>
</tr>
```

`resources/views/components/cash-disbursements/table.blade.php`:

```blade
@props(['disbursements'])

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Recipient</th>
                    <th class="px-4 py-3">Purpose</th>
                    <th class="hidden px-4 py-3 md:table-cell">Payment Method</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="hidden px-4 py-3 lg:table-cell">Recorded by</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @foreach ($disbursements as $disbursement)
                    <x-cash-disbursements.table-row :disbursement="$disbursement" />
                @endforeach
            </tbody>
        </table>
    </div>
</div>
```

`resources/views/components/cash-disbursements/filter-bar.blade.php`:

```blade
@props(['filters'])

<form method="GET" action="{{ route('cash-disbursements.index') }}" class="rounded-lg border border-slate-200 bg-white p-4">
    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_180px_160px_160px_auto]">
        <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search recipient, reference, notes" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <select name="purpose" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">All purposes</option>
            @foreach (config('disbursements.purposes') as $value => $label)
                <option value="{{ $value }}" @selected(($filters['purpose'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <div class="flex gap-2">
            <x-button class="w-auto">Filter</x-button>
            <a href="{{ route('cash-disbursements.index') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
        </div>
    </div>
</form>
```

- [ ] **Step 6: Create the index and show pages**

`resources/views/cash-disbursements/index.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Cash Disbursements')
@section('page-title', 'Cash Disbursements')

@section('content')
    @if ($errors->any())
        <x-alert type="error" class="mb-6">Please review the highlighted fields and try again.</x-alert>
    @endif

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">Cash Disbursements</h1>
                <p class="mt-1 text-sm text-slate-600">Record outgoing cash payments such as aid payouts and operational expenses.</p>
            </div>
            <x-button type="button" class="sm:w-auto" data-dialog-open="log-disbursement-dialog">
                Record Disbursement
            </x-button>
        </div>

        <x-cash-disbursements.filter-bar :filters="$filters" />

        @if ($disbursements->count())
            <x-cash-disbursements.table :disbursements="$disbursements" />
            {{ $disbursements->links() }}
        @else
            <div class="rounded-lg border border-slate-200 bg-white p-8">
                <x-dashboard.empty-state :message="request()->hasAny(['search', 'purpose', 'from', 'to'])
                    ? 'No disbursements match the current filters.'
                    : 'No cash disbursements have been recorded yet.'" />
                <div class="mt-4 flex justify-center gap-3">
                    @if (request()->hasAny(['search', 'purpose', 'from', 'to']))
                        <a href="{{ route('cash-disbursements.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Clear filters</a>
                    @endif
                    <button type="button" class="text-sm font-semibold text-blue-700 hover:text-blue-800" data-dialog-open="log-disbursement-dialog">
                        Record Disbursement
                    </button>
                </div>
            </div>
        @endif
    </div>

    <x-cash-disbursements.log-modal />
@endsection
```

`resources/views/cash-disbursements/show.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Disbursement Details')
@section('page-title', 'Cash Disbursements')

@section('content')
    <div class="space-y-6">
        <div>
            <a href="{{ route('cash-disbursements.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Back to disbursements</a>
            <h1 class="mt-2 text-2xl font-semibold text-slate-950">{{ $cashDisbursement->disbursed_to }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ config("disbursements.purposes.{$cashDisbursement->purpose}") }}</p>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase text-slate-500">Date disbursed</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ optional($cashDisbursement->disbursed_at)->format('M d, Y h:i A') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase text-slate-500">Recorded by</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $cashDisbursement->creator?->name ?? trim(($cashDisbursement->creator?->first_name ?? '').' '.($cashDisbursement->creator?->last_name ?? '')) ?: 'System' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase text-slate-500">Payment method</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $cashDisbursement->payment_method ? config("donations.payment_methods.{$cashDisbursement->payment_method}") : 'Not provided' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase text-slate-500">Reference</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $cashDisbursement->reference_number ?? 'Not provided' }}</dd>
                </div>
            </dl>

            <div class="mt-6 rounded-md bg-slate-50 p-4">
                <p class="text-sm font-semibold text-slate-600">Amount</p>
                <p class="mt-1 text-2xl font-bold text-slate-950">PHP {{ number_format((float) $cashDisbursement->amount, 2) }}</p>
            </div>

            @if ($cashDisbursement->notes)
                <div class="mt-6">
                    <h3 class="text-sm font-semibold text-slate-900">Notes</h3>
                    <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $cashDisbursement->notes }}</p>
                </div>
            @endif
        </section>
    </div>
@endsection
```

- [ ] **Step 7: Add the sidebar entry**

In `resources/views/components/app/sidebar.blade.php`, change:

```php
        ['label' => 'Donations Portal', 'route' => 'donations.index', 'active' => 'donations.*'],
        ['label' => 'In-Kind Inventory', 'route' => 'inventory.index', 'active' => 'inventory.*'],
```

to:

```php
        ['label' => 'Donations Portal', 'route' => 'donations.index', 'active' => 'donations.*'],
        ['label' => 'Cash Disbursements', 'route' => 'cash-disbursements.index', 'active' => 'cash-disbursements.*'],
        ['label' => 'In-Kind Inventory', 'route' => 'inventory.index', 'active' => 'inventory.*'],
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --filter=CashDisbursementTest`
Expected: PASS (all tests in the file, including the ones deferred from Task 11)

- [ ] **Step 9: Commit**

```bash
git add resources/views/cash-disbursements resources/views/components/cash-disbursements resources/views/components/app/sidebar.blade.php tests/Feature/CashDisbursementTest.php
git commit -m "feat: add Cash Disbursements UI and sidebar entry"
```

---

## Task 13: Dashboard hook for disbursement data

**Files:**
- Modify: `app/Support/Dashboard/DashboardMetrics.php`
- Modify: `resources/views/dashboard.blade.php`
- Test: `tests/Feature/CashDisbursementTest.php` (append)

**Interfaces:**
- Consumes: `CashDisbursement` model (Task 9).
- Produces: `DashboardMetrics::build()` gains an additive `disbursements` key: `['total_disbursed' => float, 'recent' => Collection]`. No existing keys change shape.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/CashDisbursementTest.php`:

```php
    public function test_dashboard_reflects_recorded_disbursement_totals(): void
    {
        $user = User::factory()->create();
        CashDisbursement::query()->create([
            'disbursed_to' => 'Dashboard Test Recipient',
            'purpose' => 'aid_payout',
            'amount' => 750,
            'disbursed_at' => now(),
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Total cash disbursed');
        $response->assertSee('750.00');
        $response->assertSee('Dashboard Test Recipient');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CashDisbursementTest`
Expected: FAIL — dashboard view has no "Total cash disbursed" text and `$dashboard['disbursements']` key doesn't exist yet.

- [ ] **Step 3: Update `DashboardMetrics`**

In `app/Support/Dashboard/DashboardMetrics.php`, add the import:

```php
use App\Models\CashDisbursement;
```

Add a new `'disbursements'` key to the array returned by `build()`, placed after the existing `'recent_donations'` key (last key in the array — add a trailing comma after the `recent_donations` value's closing `,` if not already present):

```php
            'disbursements' => [
                'total_disbursed' => CashDisbursement::query()->sum('amount'),
                'recent' => CashDisbursement::query()
                    ->select(['id', 'disbursed_to', 'purpose', 'amount', 'disbursed_at'])
                    ->latest('disbursed_at')
                    ->latest()
                    ->limit($limit)
                    ->get(),
            ],
```

- [ ] **Step 4: Update `dashboard.blade.php`**

Change the "Treasurer Action Items" panel body from:

```blade
            <x-dashboard.panel title="Treasurer Action Items" subtitle="Cash and reconciliation work needing attention.">
                @php($actionItems = $dashboard['treasurer_action_items'])
                @if ($actionItems['pending_cash_count'] === 0 && $actionItems['unreconciled_cash_count'] === 0)
```

to:

```blade
            <x-dashboard.panel title="Treasurer Action Items" subtitle="Cash and reconciliation work needing attention.">
                @php($actionItems = $dashboard['treasurer_action_items'])
                <div class="mb-3 flex items-center justify-between rounded-md bg-slate-50 px-3 py-2">
                    <span class="text-sm text-slate-600">Total cash disbursed</span>
                    <span class="text-sm font-semibold text-slate-950">{{ $money($dashboard['disbursements']['total_disbursed']) }}</span>
                </div>
                @if ($actionItems['pending_cash_count'] === 0 && $actionItems['unreconciled_cash_count'] === 0)
```

(The rest of that panel's `@if`/`@else`/`@endif` block is unchanged.)

Add a new panel directly after the "Recent Donations Stream" panel, before the closing `</section>` of the two-column grid:

```blade
            <x-dashboard.panel title="Recent Cash Disbursements" subtitle="Latest outgoing cash payments recorded.">
                @if ($dashboard['disbursements']['recent']->isEmpty())
                    <x-dashboard.empty-state message="No cash disbursements have been recorded yet." />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($dashboard['disbursements']['recent'] as $disbursement)
                            <li class="py-3 first:pt-0 last:pb-0">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-medium text-slate-950">{{ $disbursement->disbursed_to }}</p>
                                        <p class="text-sm text-slate-600">{{ config("disbursements.purposes.{$disbursement->purpose}") }}</p>
                                    </div>
                                    <p class="text-sm font-semibold text-slate-950">{{ $money($disbursement->amount) }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-dashboard.panel>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=CashDisbursementTest`
Expected: PASS (all tests in the file)

Also confirm no regression on the pre-existing dashboard test:

Run: `php artisan test --filter=DonationManagementTest`
Expected: PASS — `test_dashboard_is_accessible` still passes since the change is additive only.

- [ ] **Step 6: Commit**

```bash
git add app/Support/Dashboard/DashboardMetrics.php resources/views/dashboard.blade.php tests/Feature/CashDisbursementTest.php
git commit -m "feat: surface cash disbursement totals and recent activity on the dashboard"
```

---

## Task 14: Full regression pass

**Files:** none (verification only)

**Interfaces:** none

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: PASS — every test file (including `InventoryManagementTest`, `DonationManagementTest`, `BankReconciliationTest`, `SystemSecurityAdministrationTest`, `FinancialLedgerReportBuilderTest`, `ReportGeneratorTest`, `Auth/RegistrationTest`, and the two new files from this plan) is green.

- [ ] **Step 2: Confirm route list is well-formed**

Run: `php artisan route:list --name=inventory.categories`
Run: `php artisan route:list --name=inventory.units`
Run: `php artisan route:list --name=cash-disbursements`
Expected: each command lists the routes added in Tasks 5 and 11, with no duplicate route name errors.

- [ ] **Step 3: Clear cached config/routes/views and re-verify boot**

Run: `php artisan config:clear && php artisan route:clear && php artisan view:clear`
Run: `php artisan route:list`
Expected: full route list renders with no errors (confirms no stale cached config referencing the old static category/unit source).

- [ ] **Step 4: Build frontend assets**

Run: `npm run build`
Expected: build succeeds with no errors (confirms no Blade/JS syntax issues in the new views — the new modal markup reuses the existing `data-dialog-open`/`data-dialog-close` JS controller in `resources/js/app.js` unchanged).

- [ ] **Step 5: Update `docs/TEAM_TASK_ASSIGNMENT.md` status**

In `docs/TEAM_TASK_ASSIGNMENT.md`, under Gina's section:
- Change `| Configure Item Categories (admin-editable) | A6 | 🟡 partial — outstanding |` to `| Configure Item Categories (admin-editable) | A6 | ✅ done |`.
- Change `| Record Cash Receipts (Donation Portal) | S5 | ✅ done |` row's neighbor `| Record Cash Disbursements | S6 | ⏳ outstanding |` to `| Record Cash Disbursements | S6 | ✅ done |`.
- Check off the corresponding boxes in Gina's "Completion Checklist": `Admin-managed item categories/units (A6)`, `Cash disbursement recording (S6)`, `Dashboard widgets updated for disbursement data`, `New feature tests for above`.

Also update `docs/PROJECT_REQUIREMENTS.md`: change the A6 and S6 rows' **Current Status** from `🟡 Partially Implemented` / `⏳ Planned` to `✅ Implemented`, and update the **Notes** column to describe the new DB-backed categories/units and the `cash_disbursements` module (mention that A7 approval workflow remains planned, owned by Julius).

- [ ] **Step 6: Commit the documentation update**

```bash
git add docs/TEAM_TASK_ASSIGNMENT.md docs/PROJECT_REQUIREMENTS.md
git commit -m "docs: mark A6 and S6 as implemented in requirements and task assignment"
```

---

## Self-Review Notes

- **Spec coverage:** A6 (migration/seed, models, policies, service, controllers, routes, call-site updates, Blade UI, tests) → Tasks 1–7. S6 (config, migration, model, policy, service, form requests, controller, routes, Blade UI, tests) → Tasks 8–12. Dashboard hook → Task 13. Documentation and full regression → Task 14. Every section of `docs/superpowers/specs/2026-08-02-gina-remaining-tasks-design.md` maps to a task.
- **Placeholder scan:** no `TBD`/`TODO`/"add appropriate" phrasing anywhere in this plan; every step has literal, runnable code.
- **Type/name consistency verified across tasks:** `InventoryCategory::options()` / `InventoryUnit::options()` (Task 2) are the exact names used in Task 6's call-site edits. `ManageInventoryReferenceItem::create(string $modelClass, array $data)` / `::update(Model $item, array $data)` / `::delete(Model $item)` (Task 4) match exactly how Task 5's controllers invoke them. `RecordCashDisbursement::handle(array $data, User $user): CashDisbursement` (Task 10) matches Task 11's controller call. Route names (`inventory.categories.*`, `inventory.units.*`, `cash-disbursements.*`) are identical between their defining task (5, 11) and every consuming task (6, 7, 12, 13). `CashDisbursement` fillable fields (`disbursed_to, purpose, amount, payment_method, reference_number, notes, disbursed_at, created_by`) are consistent across the model (Task 9), service (Task 10), controller (Task 11), and Blade form (Task 12).
