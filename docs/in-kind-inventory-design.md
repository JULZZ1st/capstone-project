# In-Kind Inventory Module Design

## Purpose and Scope

This document defines the complete implementation specification for the In-Kind Inventory module in the Barangay Donation Platform.

The project is a Laravel application using Blade, Tailwind CSS, Vite, authenticated web sessions, role-based access, reusable Blade components, and a shared authenticated app shell with Header and Sidebar. The module must reuse the existing Laravel architecture and must not hardcode operational data in controllers, views, migrations, or JavaScript.

The In-Kind Inventory module should provide:

- A searchable, filterable inventory page for donated goods.
- A low-stock alert panel for operational attention.
- Modal-based creation of inventory items.
- Modal-based stock adjustments for stock in, stock out, and stock corrections.
- Automatic stock status calculation.
- Normalized stock movement records for auditability.
- Server-side validation, authorization, and transaction-safe persistence.

This specification is design-only. Do not implement application code until this design is approved.

## Design Principles

- Reuse the existing authenticated `layouts.app` shell, Header, Sidebar, route group, alert, button, form, and empty-state components.
- Keep the first implementation server-rendered with Blade and normal Laravel form submissions.
- Use modals for item creation and stock adjustment. Do not create separate create or adjustment pages.
- Keep controllers thin by using Form Request classes and inventory services.
- Keep inventory status, categories, units, stock movement types, and pagination limits in config, constants, enum-like classes, or normalized reference tables.
- Do not hardcode display data or operational values in Blade or controllers.
- Store stock movements separately from item master data.
- Calculate current stock from transaction-safe persisted values and stock movement records.
- Use database constraints, foreign keys, indexes, and transactions for integrity.
- Minimize duplicated markup through reusable Blade components.

## User Experience

### Shared Shell

The In-Kind Inventory page must render inside:

- `resources/views/layouts/app.blade.php`
- `resources/views/components/app/header.blade.php`
- `resources/views/components/app/sidebar.blade.php`

Header:

- Shows the barangay name from configuration.
- Shows page title: `In-Kind Inventory`.
- Shows authenticated user name and role.
- Provides logout through the existing POST logout form.

Sidebar:

- Reuse the existing sidebar.
- The `In-Kind Inventory` item should route to the inventory index route.
- Active route styling and mobile behavior should follow the existing app shell conventions.

Main content:

- Use the same `max-w-7xl` content width and spacing as other authenticated pages.
- Keep a dense operational UI suitable for repeated barangay staff work.
- Avoid marketing-style hero sections.

## Inventory Page

### Route and View

Recommended route:

```php
Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
```

Recommended view:

- `resources/views/inventory/index.blade.php`

### Page Structure

The page should contain:

- Page title: `In-Kind Inventory`
- Short description explaining that the page tracks donated goods, reserve levels, and stock movement.
- `Add New Inventory Item` button.
- Category filter tabs.
- Search bar.
- Inventory alert panel showing low stock, critical stock, out-of-stock, and expired items.
- Responsive inventory table.
- Pagination.
- Loading, empty, success, and error states.

### Category Filter Tabs

Query parameter:

- `category`

Source:

- Config-backed categories for the first implementation, or a normalized `inventory_categories` table if staff need to manage categories in the UI.

Behavior:

- Include an `All` tab.
- Include one tab per active category.
- Preserve search and status filters when switching category where practical.
- Validate category values server-side.

### Search

Query parameter:

- `search`

Search should match:

- Item name
- Category label or category key
- Description
- Batch number
- Storage location
- Notes

Validation:

```php
'search' => ['nullable', 'string', 'max:255'],
```

Behavior:

- Trim search input.
- Keep search server-rendered for the first implementation.
- Preserve filter state in pagination links.

### Inventory Alert Panel

Purpose:

- Show items requiring operational attention.

Alert groups:

- Out of Stock
- Critical Stock
- Low Stock
- Expired
- Expiring Soon, if an expiration threshold is configured

Query behavior:

- Fetch only the limited set needed for the panel.
- Do not load all inventory records into memory.
- Use configured limits, for example `config('inventory.alerts.limit')`.

Empty state:

- Show a neutral message such as `No inventory alerts right now.`

Error state:

- Show a compact non-blocking error message.
- Log server exceptions without exposing SQL or stack traces.

### Responsive Inventory Table

Each inventory record should display:

- Item Name
- Category
- Current Stock
- Unit
- Minimum Reserve Level
- Stock Status
- Last Audited Date
- Last Updated
- Actions

Recommended responsive behavior:

- Desktop: full table.
- Tablet: hide lower-priority columns such as last audited date if needed.
- Mobile: use horizontal scrolling with stable column widths or transform rows into stacked cards.
- Long names, descriptions, and locations must wrap cleanly.

Recommended actions:

- View
- Adjust Stock
- Edit
- Audit
- Deactivate or Archive, if approved

All actions must be authorized server-side.

### Pagination

Use Laravel pagination:

- Config key: `inventory.pagination.per_page`
- Preserve query strings with `withQueryString()`.
- Do not load all inventory records on the index page.

## Add New Inventory Item Modal

### Modal Behavior

The `Add New Inventory Item` button opens a modal dialog on the same page.

The modal must:

- Use a native `<dialog>` or accessible dialog markup.
- Include a clear title.
- Include a close button.
- Close on cancel button and optionally Escape.
- Return focus to the trigger if custom JavaScript is used.
- Submit through a normal Laravel POST route with CSRF protection.
- Reopen with validation errors.
- Preserve safe old input.
- Avoid local storage for draft data.

Recommended route:

```php
Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
```

### Fields

The modal should include:

- Item Name
  - Input name: `name`
  - Required
  - String, max 255
- Category
  - Input name: `category`
  - Required
  - Allowed values from config or active category records
- Description
  - Input name: `description`
  - Nullable
  - String, max 2000
- Unit
  - Input name: `unit`
  - Required
  - Allowed values from config or active unit records
- Initial Quantity
  - Input name: `initial_quantity`
  - Required
  - Numeric, greater than or equal to zero
- Minimum Reserve Quantity
  - Input name: `minimum_reserve_quantity`
  - Required
  - Numeric, greater than or equal to zero
- Estimated Unit Value
  - Input name: `estimated_unit_value`
  - Nullable
  - Numeric, greater than or equal to zero
- Expiration Date
  - Input name: `expires_at`
  - Nullable
  - Date
- Batch Number
  - Input name: `batch_number`
  - Nullable
  - String, max 100
- Storage Location
  - Input name: `storage_location`
  - Required
  - String, max 255
- Notes
  - Input name: `notes`
  - Nullable
  - String, max 2000
- Status
  - Input name: `status`
  - Required
  - Allowed values from config

### Recommended Validation Rules

Create:

- `App\Http\Requests\Inventory\StoreInventoryItemRequest`

Recommended rules:

```php
'name' => ['required', 'string', 'max:255'],
'category' => ['required', Rule::in(array_keys(config('inventory.categories')))],
'description' => ['nullable', 'string', 'max:2000'],
'unit' => ['required', Rule::in(array_keys(config('inventory.units')))],
'initial_quantity' => ['required', 'numeric', 'min:0', 'max:999999.99'],
'minimum_reserve_quantity' => ['required', 'numeric', 'min:0', 'max:999999.99'],
'estimated_unit_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
'expires_at' => ['nullable', 'date'],
'batch_number' => ['nullable', 'string', 'max:100'],
'storage_location' => ['required', 'string', 'max:255'],
'notes' => ['nullable', 'string', 'max:2000'],
'status' => ['required', Rule::in(array_keys(config('inventory.item_statuses')))],
```

Input normalization:

- Trim text fields.
- Normalize enum-like values to lowercase.
- Convert empty optional fields to null.
- Store dates as date or datetime casts.

## Adjust Stock Modal

### Modal Behavior

The `Adjust Stock` action opens another modal on the same page or on an item detail page.

The modal must:

- Use CSRF protection.
- Submit with a normal POST route.
- Reopen with validation errors.
- Clearly display the item name, current stock, unit, and current stock status.
- Require a movement type, quantity, reason, and reference information where appropriate.

Recommended route:

```php
Route::post('/inventory/{inventoryItem}/adjustments', [InventoryStockAdjustmentController::class, 'store'])
    ->name('inventory.adjustments.store');
```

### Movement Types

Use config-backed movement types:

- `stock_in`
- `stock_out`
- `adjustment`

Recommended labels:

- Stock In
- Stock Out
- Stock Adjustment

### Fields

- Movement Type
  - Input name: `movement_type`
  - Required
  - Allowed values from config
- Quantity
  - Input name: `quantity`
  - Required
  - Numeric, greater than zero
- Reason
  - Input name: `reason`
  - Required
  - Allowed values from config or string with max length if reasons are not standardized
- Reference Number
  - Input name: `reference_number`
  - Nullable, required for external references if policy requires it
  - String, max 100
- Remarks
  - Input name: `remarks`
  - Nullable
  - String, max 2000

### Recommended Validation Rules

Create:

- `App\Http\Requests\Inventory\StoreStockAdjustmentRequest`

Recommended rules:

```php
'movement_type' => ['required', Rule::in(array_keys(config('inventory.movement_types')))],
'quantity' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
'reason' => ['required', Rule::in(array_keys(config('inventory.adjustment_reasons')))],
'reference_number' => ['nullable', 'string', 'max:100'],
'remarks' => ['nullable', 'string', 'max:2000'],
```

Additional validation:

- `stock_out` cannot reduce current stock below zero unless negative inventory is explicitly approved.
- `adjustment` should support either:
  - signed delta quantity, or
  - absolute counted quantity.
- The recommended first implementation is signed delta quantity for simplicity and audit clarity.
- Reject adjustments for archived or inactive items unless admin override is approved.

## Stock Status Calculation

The system must automatically calculate and display stock status.

Recommended statuses:

- `adequate`
- `low_stock`
- `critical_stock`
- `out_of_stock`
- `expired`

Suggested logic:

- Expired: item has an expiration date before today and current stock is greater than zero.
- Out of Stock: current stock is exactly zero.
- Critical Stock: current stock is greater than zero and less than or equal to a configured critical threshold.
- Low Stock: current stock is greater than critical threshold and less than or equal to minimum reserve quantity.
- Adequate: current stock is greater than minimum reserve quantity and not expired.

Recommended config:

```php
'stock_statuses' => [
    'adequate' => ['label' => 'Adequate'],
    'low_stock' => ['label' => 'Low Stock'],
    'critical_stock' => ['label' => 'Critical Stock'],
    'out_of_stock' => ['label' => 'Out of Stock'],
    'expired' => ['label' => 'Expired'],
],
'critical_stock_ratio' => 0.25,
'expiring_soon_days' => 30,
```

Implementation options:

- Store `current_quantity` on `inventory_items` for fast reads.
- Store every quantity change in `inventory_stock_movements`.
- Update `current_quantity`, `last_movement_at`, and stock status inside the same database transaction as the movement.

Do not calculate current stock by summing every movement on every index page request.

## Database Design

### Current Table Considerations

The existing project may already have an `inventory_items` table used by role workflow pages. The first implementation should inspect the current schema before adding columns or replacing behavior.

If the existing table cannot support production inventory tracking safely, add focused migrations rather than overloading unrelated fields.

### Recommended Configuration

Create:

- `config/inventory.php`

Recommended keys:

- `categories`
- `units`
- `item_statuses`
- `stock_statuses`
- `movement_types`
- `adjustment_reasons`
- `defaults.status`
- `pagination.per_page`
- `alerts.limit`
- `critical_stock_ratio`
- `expiring_soon_days`

Use config values for validation and display labels.

### Recommended Migrations

Create migrations only after approval.

#### Inventory Items

If the existing `inventory_items` table is incomplete, add missing production fields.

Recommended columns:

- `id`
- `name`
- `category` indexed string
- `description` nullable text
- `unit` string
- `current_quantity` decimal
- `minimum_reserve_quantity` decimal
- `estimated_unit_value` nullable decimal
- `expires_at` nullable date
- `batch_number` nullable string
- `storage_location` indexed string
- `notes` nullable text
- `status` indexed string
- `last_audited_at` nullable timestamp
- `last_movement_at` nullable timestamp
- `created_by` nullable foreign key to `users`
- `updated_by` nullable foreign key to `users`
- timestamps

Recommended indexes:

- `category`
- `status`
- `storage_location`
- `expires_at`
- `last_audited_at`
- `last_movement_at`
- composite `category, status`
- composite `status, expires_at`

Recommended constraints:

- `current_quantity` must be non-negative.
- `minimum_reserve_quantity` must be non-negative.
- `estimated_unit_value` must be non-negative when present.
- `created_by` and `updated_by` should `nullOnDelete()` to preserve inventory records.

#### Inventory Stock Movements

Create `inventory_stock_movements` for audit-safe stock changes.

Recommended columns:

- `id`
- `inventory_item_id` foreign key to `inventory_items`
- `created_by` nullable foreign key to `users`
- `movement_type` indexed string
- `quantity` decimal
- `quantity_before` decimal
- `quantity_after` decimal
- `reason` indexed string
- `reference_number` nullable string
- `remarks` nullable text
- timestamps

Recommended indexes:

- `inventory_item_id`
- `created_by`
- `movement_type`
- `reason`
- `reference_number`
- `created_at`
- composite `inventory_item_id, created_at`
- composite `movement_type, created_at`

Recommended constraints:

- `inventory_item_id` should restrict deletes for audit integrity.
- `created_by` should `nullOnDelete()`.
- `quantity` must be positive.
- `quantity_before` and `quantity_after` must be non-negative unless negative inventory is approved.

#### Inventory Audits

Create `inventory_audits` only if formal audit history is approved.

Recommended columns:

- `id`
- `inventory_item_id` foreign key to `inventory_items`
- `audited_by` nullable foreign key to `users`
- `expected_quantity` decimal
- `counted_quantity` decimal
- `variance_quantity` decimal
- `notes` nullable text
- `audited_at` timestamp
- timestamps

Indexes:

- `inventory_item_id`
- `audited_by`
- `audited_at`
- composite `inventory_item_id, audited_at`

If formal audits are deferred, keep `last_audited_at` nullable on `inventory_items` and add the audit table later.

## Models and Relationships

Recommended models:

- `InventoryItem`
- `InventoryStockMovement`
- `InventoryAudit`, if audit history is approved
- Existing `User`

Relationships:

- `InventoryItem hasMany InventoryStockMovement`
- `InventoryStockMovement belongsTo InventoryItem`
- `InventoryStockMovement belongsTo User as creator`
- `InventoryItem belongsTo User as creator`
- `InventoryItem belongsTo User as updater`
- `InventoryItem hasMany InventoryAudit`
- `InventoryAudit belongsTo InventoryItem`
- `InventoryAudit belongsTo User as auditor`

Recommended `InventoryItem` behavior:

- Cast quantity fields as decimal.
- Cast `expires_at` as date.
- Cast audit and movement timestamps as datetime.
- Add scopes:
  - `search(?string $term)`
  - `category(?string $category)`
  - `status(?string $status)`
  - `lowStock()`
  - `criticalStock()`
  - `outOfStock()`
  - `expired()`
  - `active()`
- Add methods:
  - `stockStatus(): string`
  - `isExpired(): bool`
  - `canStockOut(float $quantity): bool`

Avoid querying inside accessors used by table loops.

Recommended `InventoryStockMovement` behavior:

- Constants or config-backed helpers for movement types.
- Cast quantity fields as decimal.
- Relationship to creator.

## Controllers

Recommended controllers:

- `App\Http\Controllers\InventoryController`
  - `index(InventoryIndexRequest $request)`
  - `store(StoreInventoryItemRequest $request)`
  - `show(InventoryItem $inventoryItem)`
  - `update(UpdateInventoryItemRequest $request, InventoryItem $inventoryItem)`
- `App\Http\Controllers\InventoryStockAdjustmentController`
  - `store(StoreStockAdjustmentRequest $request, InventoryItem $inventoryItem)`

Controller responsibilities:

- Authorize actions.
- Use validated Form Request data.
- Call focused services for stock creation and adjustment.
- Redirect with flash messages.
- Never perform raw validation inside Blade.
- Never directly calculate stock changes in Blade.

## Services

Recommended services:

- `App\Services\Inventory\CreateInventoryItem`
- `App\Services\Inventory\AdjustInventoryStock`
- Optional: `App\Services\Inventory\InventoryAlertQuery`

### CreateInventoryItem

Responsibilities:

- Create the item in a database transaction.
- Set `current_quantity` from `initial_quantity`.
- Set configured default status.
- Record `created_by`.
- Create an initial `stock_in` movement if `initial_quantity` is greater than zero.
- Set `last_movement_at` when an initial movement exists.

### AdjustInventoryStock

Responsibilities:

- Lock the inventory item row for update.
- Calculate `quantity_before` and `quantity_after`.
- Prevent negative stock unless explicitly approved.
- Create an inventory movement record.
- Update `current_quantity`, `last_movement_at`, and `updated_by`.
- Return the updated inventory item.

Recommended transaction pattern:

```php
DB::transaction(function () use ($inventoryItem, $data, $user) {
    $inventoryItem = InventoryItem::query()
        ->whereKey($inventoryItem->id)
        ->lockForUpdate()
        ->firstOrFail();

    // calculate and persist movement plus item quantity
});
```

## Routes

Use the existing authenticated application route group.

Recommended routes:

```php
Route::middleware(['auth', 'role:admin,staff'])->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::get('/inventory/{inventoryItem}', [InventoryController::class, 'show'])->name('inventory.show');
    Route::patch('/inventory/{inventoryItem}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::post('/inventory/{inventoryItem}/adjustments', [InventoryStockAdjustmentController::class, 'store'])
        ->name('inventory.adjustments.store');
});
```

Do not add API routes unless async search or async stock modals are approved later.

## Form Requests

Recommended request classes:

- `App\Http\Requests\Inventory\InventoryIndexRequest`
- `App\Http\Requests\Inventory\StoreInventoryItemRequest`
- `App\Http\Requests\Inventory\UpdateInventoryItemRequest`
- `App\Http\Requests\Inventory\StoreStockAdjustmentRequest`

Responsibilities:

- Validate query filters.
- Validate creation and adjustment forms.
- Normalize text and enum-like fields.
- Convert blank optional fields to null.
- Authorize before persistence.

## Authorization

Recommended policy:

- `InventoryItemPolicy`
  - `viewAny`
  - `view`
  - `create`
  - `update`
  - `adjustStock`
  - `audit`
  - `archive`

Suggested role behavior:

- `admin`: view, create, edit, adjust, audit, archive all inventory items.
- `staff`: view, create, edit, adjust in-kind inventory, and audit if barangay policy allows it.
- `staff`: view inventory summaries and item details; stock adjustment only if finance workflow requires valuation corrections.

Rules:

- Guests must be redirected to login.
- Only approved operational roles can access inventory pages.
- Stock adjustments must be authorized server-side.
- Do not duplicate role strings across Blade files.
- Use policies, model helpers, config-backed role constants, or gates.

## Blade Components

Recommended new views:

- `resources/views/inventory/index.blade.php`
- `resources/views/inventory/show.blade.php`

Recommended components:

- `resources/views/components/inventory/filter-tabs.blade.php`
- `resources/views/components/inventory/search-bar.blade.php`
- `resources/views/components/inventory/alert-panel.blade.php`
- `resources/views/components/inventory/table.blade.php`
- `resources/views/components/inventory/table-row.blade.php`
- `resources/views/components/inventory/status-badge.blade.php`
- `resources/views/components/inventory/create-modal.blade.php`
- `resources/views/components/inventory/item-form.blade.php`
- `resources/views/components/inventory/adjust-stock-modal.blade.php`
- `resources/views/components/inventory/stock-summary.blade.php`

Reuse existing:

- `layouts.app`
- `components/app/header.blade.php`
- `components/app/sidebar.blade.php`
- `components/alert.blade.php`
- `components/button.blade.php`
- `components/form/input.blade.php`
- `components/form/label.blade.php`
- `components/form/error.blade.php`
- `components/dashboard/empty-state.blade.php`

Component rules:

- Components accept data through props.
- Components do not query the database.
- Modal fields use reusable form components and field-level errors.
- Keep table dimensions stable across empty and populated states.
- Status badges must include text labels, not color alone.

## Page States

### Loading

For server-rendered navigation:

- Normal browser page loading is acceptable.

If async filtering is introduced later:

- Show table skeleton rows.
- Keep filter tabs and search controls stable.
- Do not clear current results until replacement data is ready.

### Empty

No inventory items:

- Show centered empty state.
- Include `Add New Inventory Item` action for authorized users.

No filtered results:

- Show filtered empty state.
- Include clear-filters link.

No alerts:

- Show neutral alert panel message.

### Success

Creation:

- Flash message: `Inventory item created successfully.`

Stock adjustment:

- Flash message: `Stock adjusted successfully.`

Update:

- Flash message: `Inventory item updated successfully.`

### Error

Validation errors:

- Reopen relevant modal.
- Preserve safe old input.
- Show field-level messages.

Server errors:

- Log exception context.
- Show a generic alert.
- Do not expose SQL errors, stack traces, or internal exception messages.

## Query Strategy

Index query should:

- Select only columns required for the table.
- Apply category and search filters at the database level.
- Use pagination.
- Avoid eager-loading stock movement history on the index table.
- Use separate small queries for alert panel counts or alert rows.

Recommended data shape:

```php
[
    'id',
    'name',
    'category',
    'current_quantity',
    'unit',
    'minimum_reserve_quantity',
    'expires_at',
    'last_audited_at',
    'updated_at',
    'status',
]
```

Item detail page may eager-load:

- Recent stock movements
- Creator/updater
- Audit history if implemented

## Business Rules

- Inventory item names should be unique per category and batch number if batch-level tracking is required.
- Initial quantity cannot be negative.
- Minimum reserve quantity cannot be negative.
- Current stock cannot become negative unless explicitly approved.
- Stock out requires a reason.
- Stock adjustments require a reason and remarks for audit clarity.
- Expired items should not be counted as distributable stock unless an admin override workflow is approved.
- Damaged or inactive items should not be available for stock out unless business policy allows it.
- Every stock-changing operation must create a stock movement record.
- Stock movement records should not be edited after creation; corrections should be new adjustment movements.
- Deleting inventory items with movement history should not be allowed in the first implementation.
- Use archive/deactivate status instead of hard delete.

## Performance Recommendations

- Add indexes for category, status, expiration date, and movement timestamps.
- Use pagination for inventory lists.
- Avoid summing all stock movements on each page load.
- Store `current_quantity` on inventory items and update it transactionally.
- Use `lockForUpdate()` during stock adjustment to prevent race conditions.
- Keep alert queries limited.
- Avoid loading full movement history on the index page.
- Use database aggregates for dashboard summaries.
- Use route and config caching in production after routes and config are stable.

## Caching Strategy

Do not cache:

- Authenticated inventory index responses.
- Stock quantities.
- Modal form responses.
- User-specific authorization decisions.
- Notes, remarks, or audit details.

Safe to cache:

- Config-backed category labels through Laravel config cache.
- Config-backed units, movement types, and status labels through Laravel config cache.
- Database-backed category or unit reference lists only if they become stable managed reference data.

If database-backed reference caching is introduced:

- Use short TTLs or explicit invalidation.
- Invalidate cache when categories, units, statuses, or reason codes change.
- Never cache current stock values unless cache invalidation is guaranteed on every stock movement.

## Testing Recommendations

- Guest users are redirected to login.
- Approved roles can view inventory index.
- Unauthorized roles receive `403` if introduced.
- Inventory index renders table columns and pagination.
- Category tabs filter inventory records.
- Search filters by name, description, batch number, storage location, and notes.
- Empty inventory state renders.
- Low stock alert panel renders correct low, critical, out-of-stock, and expired items.
- Add item modal validation shows errors and preserves safe input.
- Valid item creation inserts item and initial movement when quantity is greater than zero.
- Stock out cannot exceed current stock.
- Stock in increases current stock and creates movement.
- Stock adjustment updates stock and creates movement.
- Stock status labels render from config.
- Policy tests cover create, update, adjust, audit, and archive behavior.
- Transaction tests verify failed movements do not partially update item stock.

## Implementation Plan After Approval

1. Add `config/inventory.php`.
2. Inspect existing `inventory_items` schema.
3. Create approved migrations for missing inventory item fields and stock movements.
4. Add `InventoryStockMovement` model and update `InventoryItem` relationships/scopes.
5. Add inventory Form Request classes.
6. Add `InventoryItemPolicy`.
7. Add inventory services for creation and stock adjustment.
8. Add `InventoryController`.
9. Add `InventoryStockAdjustmentController`.
10. Add inventory routes.
11. Update Sidebar `In-Kind Inventory` route to `inventory.index`.
12. Create inventory Blade components and views.
13. Add modal behavior for creation and stock adjustment.
14. Add feature and validation tests.
15. Run migrations, tests, route list, Blade compilation, and Vite build.

## Approval Notes

Before implementation begins, confirm:

- Whether categories and units should remain config-backed or become database-managed records.
- Whether batch number should be unique per item/category.
- Whether expired goods can remain in stock but be blocked from distribution.
- Whether staff users can adjust inventory value fields or only view inventory.
- Whether formal audit history should be implemented now or deferred.
- Whether existing legacy `inventory_items` records should be migrated into the production-ready schema.
