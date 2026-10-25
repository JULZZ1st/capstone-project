# Donation Portal Module Design

> Historical design specification: the implemented portal no longer uses campaigns. Campaign records and contribution links have been removed; see [donation-portal-flow.md](./donation-portal-flow.md) for the current workflow.

## Purpose and Scope

This document defines the implementation specification for a production-ready Donation Portal module for the Barangay Donation Platform.

The current project is a Laravel 11 application using Blade, Tailwind CSS, Vite, MySQL, authenticated web sessions, normalized user roles, reusable Blade components, and an authenticated app shell with shared Header and Sidebar. Existing donation-related tables include `donors`, `donations`, `cash_transactions`, and `inventory_items`.

This specification is design-only. Do not implement application code until this design is approved.

## Design Principles

- Reuse the existing authenticated `layouts.app` shell, Header, Sidebar, route group, role middleware, alert, button, form, and empty-state components.
- Keep the first implementation server-rendered with Blade and normal Laravel form submissions.
- Use a modal for logging contributions; do not create a separate contribution page.
- Keep controllers thin by using Form Request classes and focused persistence services where transaction logic grows.
- Use config, constants, enum-like classes, or seeded reference data for statuses, payment methods, item categories, item conditions, and pagination limits.
- Do not hardcode operational data in controllers, Blade views, or migrations except through config-backed defaults.
- Avoid duplicate records across `donations`, `cash_transactions`, and `inventory_items`; choose one normalized source of truth for the portal.
- Use database constraints, foreign keys, indexes, and transactions for data integrity.

## Current Architecture Notes

Existing reusable structure:

- `resources/views/layouts/app.blade.php`
- `resources/views/components/app/header.blade.php`
- `resources/views/components/app/sidebar.blade.php`
- `resources/views/components/alert.blade.php`
- `resources/views/components/button.blade.php`
- `resources/views/components/form/*`
- `resources/views/components/dashboard/empty-state.blade.php`

Existing relevant models:

- `Donor`
- `Donation`
- `CashTransaction`
- `InventoryItem`
- `User`
- `Role`

Existing relevant routes:

```php
Route::middleware(['auth', 'role:admin,staff'])->group(function () {
    Route::get('/donors', [DonorController::class, 'index'])->name('donors.index');
    Route::post('/donations', [DonationController::class, 'storeDonation'])->name('donations.store');
    Route::get('/role-workflows', [InventoryCashController::class, 'index'])->name('workflows.index');
    Route::post('/role-workflows/inventory', [InventoryCashController::class, 'storeInventory'])->name('inventory.store');
    Route::post('/role-workflows/cash', [InventoryCashController::class, 'storeCash'])->name('cash.store');
});
```

The Sidebar already includes a `Donations Portal` entry, but it currently points to `donors.index`. The approved implementation should add a dedicated donations portal route and update the sidebar route target.

## User Experience

### Shared Shell

The Donation Portal must render inside `layouts.app`.

Header:

- Shows the barangay name from config.
- Shows page title: `Donations Portal`.
- Shows authenticated user name and role.
- Provides logout through the existing POST logout form.

Sidebar:

- Reuse the existing sidebar.
- The `Donations Portal` item should route to the new donation portal index route after implementation.
- Sidebar visibility, active route styling, and mobile behavior should follow the existing app shell conventions.

Main content:

- Use the same `max-w-7xl` content width and spacing as the dashboard and donor management pages.
- Keep dense operational UI suitable for repeated barangay staff work.
- Avoid marketing-style hero sections.

## Donation List Page

### Route and View

Recommended route:

```php
Route::get('/donations', [DonationPortalController::class, 'index'])->name('donations.index');
```

Recommended view:

- `resources/views/donations/index.blade.php`

### Page Controls

Top toolbar:

- Search bar
- Donation Type filter
- Campaign filter
- Status filter
- `Log New Contribution` button

Search behavior:

- Query parameter: `search`
- Match donor name, donor contact number, donor email, contribution reference number, item names, campaign name, and administrative notes where appropriate.
- Trim the search value.
- Keep search server-rendered for the first implementation.

Donation Type filter:

- Query parameter: `type`
- Allowed values from config, for example:
  - `cash`
  - `in_kind`

Campaign filter:

- Query parameter: `campaign_id`
- Options loaded from active campaign records if campaigns are implemented.
- If campaigns are deferred, use a nullable free-text campaign/purpose field temporarily only as a migration bridge, not as a permanent hardcoded list.

Status filter:

- Query parameter: `status`
- Allowed values from config, for example:
  - `pending`
  - `verified`
  - `received`
  - `distributed`
  - `cancelled`

Button:

- Label: `Log New Contribution`
- Opens a modal on the same page.
- Does not navigate to a separate page.

### Responsive Donation Table

Recommended columns:

- Date received
- Donor
- Type
- Campaign
- Summary
- Amount / Estimated Value
- Status
- Logged by
- Actions

Responsive behavior:

- Desktop: full table.
- Tablet: hide lower-priority columns such as logged by or notes.
- Mobile: transform rows into stacked cards or use an accessible horizontally scrollable table with stable column widths.
- Long donor names, campaign names, and item summaries must wrap cleanly without overflowing.

Status badges:

- Use config-driven labels and Tailwind classes mapped in one place.
- Do not hardcode badge classes in every row.
- Include text labels, not color alone.

Recommended statuses:

- `pending`: recorded but awaiting review or verification.
- `verified`: approved cash/electronic contribution.
- `received`: accepted in-kind contribution.
- `distributed`: in-kind goods have been distributed or consumed by relief operations.
- `cancelled`: voided record retained for audit.

Action buttons:

- `View`
- `Edit` if the contribution is still editable by policy.
- `Verify` for authorized cash review.
- `Cancel` or `Void` for admin-authorized corrections.

Actions should use icon or icon-plus-text buttons if the existing component system supports it. Every action must be authorized server-side.

### List Page Required Fields

The list page itself does not create records, but filter inputs should be validated:

- `search`: nullable string, max 255.
- `type`: nullable, allowed value from configured donation types.
- `campaign_id`: nullable, exists in campaigns table if campaigns are implemented.
- `status`: nullable, allowed value from configured contribution statuses.
- `page`: nullable integer handled by Laravel pagination.

### List Page Business Rules

- Guests must be redirected to login.
- Only approved operational roles can access the page.
- Query filters must not reveal records the user is not authorized to view.
- Listing should use pagination, not load all contributions.
- The controller should eager-load donor, campaign, creator, and item summaries to avoid N+1 queries.
- Voided or cancelled records should remain visible to authorized users for audit unless filtered out intentionally.

### List Page States

Loading:

- For normal server-rendered navigation, use the browser page load.
- If async filtering is introduced later, show table skeleton rows and keep filters stable.

Empty:

- No contributions at all: show a centered empty state with `Log New Contribution`.
- Filters with no matches: show a filtered empty state with a clear-filters link.

Success:

- After creation: flash `Contribution logged successfully.`
- After update or status change: flash a specific success message.

Error:

- Validation errors should return to the page, reopen the modal if relevant, and show field-level messages.
- Query or server errors should log details and show a generic alert.

## Log New Contribution Modal

### Modal Behavior

- The `Log New Contribution` button opens a modal dialog on the list page.
- The modal must be accessible:
  - Use `role="dialog"` or native `<dialog>`.
  - Include a modal title.
  - Trap focus if custom JavaScript is used.
  - Close on explicit close and cancel buttons.
  - Optionally close on Escape.
  - Return focus to the trigger after closing.
- The form submits with POST and CSRF protection.
- If validation fails, redirect back, preserve safe input, and reopen the modal.
- Do not store contribution draft data in local storage.

Recommended route:

```php
Route::post('/donations', [DonationPortalController::class, 'store'])->name('donations.store');
```

### Donation Type Selector

The modal supports exactly two contribution modes for the first implementation:

- `Cash / Electronic Transfer`
- `In-Kind Goods`

Input name:

- `donation_type`

Recommended values:

- `cash`
- `in_kind`

The visible section changes based on selected type. Validation must be server-side and conditional; client-side toggling is only a usability enhancement.

## Cash / Electronic Transfer Form

### Fields

- Donor
  - Input name: `donor_id`
  - Type: searchable select
  - Required
  - Source: active donors
- Campaign
  - Input name: `campaign_id`
  - Type: select
  - Nullable or required based on approved campaign policy
  - Source: active campaigns
- Donation Amount
  - Input name: `amount`
  - Type: decimal currency input
  - Required
- Payment Method
  - Input name: `payment_method`
  - Type: select
  - Required
  - Source: config
- Reference / Transaction No.
  - Input name: `reference_number`
  - Type: text
  - Required for electronic transfer methods; nullable for cash over the counter if barangay policy allows
- Administrative Notes
  - Input name: `notes`
  - Type: textarea
  - Nullable

Recommended payment methods:

- `cash`
- `bank_transfer`
- `gcash`
- `maya`
- `check`
- `money_order`
- `other`

These values should live in config or an enum-like class, not in Blade.

### Validation Rules

Create `App\Http\Requests\Donations\StoreContributionRequest`.

Recommended conditional rules:

```php
'donation_type' => ['required', Rule::in(config('donations.types'))],
'donor_id' => ['required', 'exists:donors,id'],
'campaign_id' => ['nullable', 'exists:campaigns,id'],
'amount' => ['required_if:donation_type,cash', 'numeric', 'min:0.01', 'max:99999999.99'],
'payment_method' => ['required_if:donation_type,cash', Rule::in(array_keys(config('donations.payment_methods')))],
'reference_number' => ['nullable', 'string', 'max:100'],
'notes' => ['nullable', 'string', 'max:2000'],
```

Additional validation:

- Require `reference_number` when payment method is electronic, check, or money order.
- Ensure `campaign_id` points to an active campaign if campaigns are enabled.
- Normalize blank optional fields to null.

### Business Rules

- Cash amount must be greater than zero.
- Reference number should be unique per payment method when provided.
- New cash contributions default to configured status, typically `pending` or `verified` depending on approval workflow.
- Staff or admin users may verify cash contributions.
- Staff users may log and verify cash contributions.
- Do not automatically create bank reconciliation records from a donation.
- Store all cash contribution creation in a transaction.

## In-Kind Goods Form

### Fields

- Donor
  - Input name: `donor_id`
  - Type: searchable select
  - Required
- Campaign
  - Input name: `campaign_id`
  - Type: select
  - Nullable or required based on approved campaign policy
- Dynamic item list
  - Input namespace: `items`
  - Supports `Add Item Row`
  - At least one item row is required
- Item Name
  - Input name: `items.*.name`
  - Type: text
  - Required
- Category
  - Input name: `items.*.category`
  - Type: select
  - Required
  - Source: config or seeded categories
- Quantity
  - Input name: `items.*.quantity`
  - Type: number or decimal based on unit
  - Required
- Unit
  - Input name: `items.*.unit`
  - Type: select or text constrained by config
  - Required
- Condition
  - Input name: `items.*.condition`
  - Type: select
  - Required
  - Allowed values: `new`, `good`, `used`, `damaged`
- Estimated Value
  - Input name: `items.*.estimated_value`
  - Type: decimal currency input
  - Optional
- Administrative Notes
  - Input name: `notes`
  - Type: textarea
  - Nullable

### Recommended Donation Item Categories

Store categories in config for the first implementation, or in a `donation_item_categories` reference table if staff need to manage them in the UI.

Recommended production-ready categories:

- Food
- Medical Supplies
- Clothing
- Hygiene Supplies
- Baby Supplies
- Shelter Materials
- School Supplies
- Kitchen Supplies
- Water & Beverages
- Disaster Relief Supplies
- Electronics
- Furniture
- Bedding & Linens
- Cleaning Supplies
- Tools & Hardware
- Personal Protective Equipment
- Senior Care Supplies
- Mobility & Assistive Devices
- Animal Feed
- Livelihood Supplies
- Toys & Recreational Items
- Other

Recommended units:

- piece
- pack
- box
- sack
- bottle
- can
- kilogram
- gram
- liter
- meter
- set
- pair
- bundle
- carton
- other

Recommended conditions:

- `new`
- `good`
- `used`
- `damaged`

### Validation Rules

Recommended conditional rules:

```php
'items' => ['required_if:donation_type,in_kind', 'array', 'min:1'],
'items.*.name' => ['required_if:donation_type,in_kind', 'string', 'max:255'],
'items.*.category' => ['required_if:donation_type,in_kind', Rule::in(array_keys(config('donations.item_categories')))],
'items.*.quantity' => ['required_if:donation_type,in_kind', 'numeric', 'min:0.01', 'max:999999.99'],
'items.*.unit' => ['required_if:donation_type,in_kind', Rule::in(array_keys(config('donations.units')))],
'items.*.condition' => ['required_if:donation_type,in_kind', Rule::in(array_keys(config('donations.item_conditions')))],
'items.*.estimated_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
'notes' => ['nullable', 'string', 'max:2000'],
```

Additional validation:

- Reject empty item rows.
- Require at least one complete item row.
- Normalize item names and units by trimming whitespace.
- Convert empty estimated value to null.

### Business Rules

- In-kind contributions must include at least one item.
- Each item row belongs to one contribution.
- In-kind items default to configured status, typically `received`.
- Damaged items may be accepted but should be clearly marked and excluded from distributable stock unless approved.
- Estimated value is optional because barangay staff may not have a reliable appraisal.
- Total estimated value may be calculated from item rows for reporting, but should not be required for record creation.
- Store contribution and item rows in a single database transaction.

## Modal States

Loading:

- If donors or campaigns are server-rendered, no separate loading state is required.
- If searchable selects become async later, show a small loading state inside the select.

Empty:

- If no active donors exist, disable submission and show a link to Donor Management.
- If no active campaigns exist and campaign is optional, show `No campaign`.
- If campaign is required, show an error state telling staff to create or activate a campaign first.

Success:

- Close modal after redirect.
- Show flash alert: `Contribution logged successfully.`
- Optionally highlight the newly created row using a session value.

Error:

- Reopen modal.
- Preserve safe old input.
- Show field-level validation messages.
- For server errors, show a generic modal-level alert and log exception details.

## Database Design

### Current Database

Current tables can partially represent donation activity:

- `donations`: generic donor-linked donation records.
- `cash_transactions`: cash ledger entries using donor name text, not donor foreign key.
- `inventory_items`: inventory records without donor or donation foreign key.

For the Donation Portal, these tables should not continue to diverge as separate sources of truth. A normalized contribution model is recommended so every portal record has one donor, one type, one status, one creator, and optional child rows for in-kind items.

### Recommended Migrations

Create migrations only after approval.

#### Campaigns

Create `campaigns` if campaign filtering and assignment must be a first-class feature.

Fields:

- `id`
- `name`
- `slug` unique
- `description` nullable
- `status` indexed string
- `starts_at` nullable timestamp
- `ends_at` nullable timestamp
- timestamps

Indexes:

- unique `slug`
- `status`
- `starts_at`
- `ends_at`

Relationships:

- `Campaign hasMany Contribution`
- `Contribution belongsTo Campaign`

Do not create this table if campaigns are only a free-text note. The product requirement includes campaign filtering, so a normalized `campaigns` table is recommended.

#### Contributions

Create `contributions` as the portal source of truth.

Fields:

- `id`
- `donor_id` foreign key to `donors`
- `campaign_id` nullable foreign key to `campaigns`
- `created_by` nullable foreign key to `users`
- `verified_by` nullable foreign key to `users`
- `type` indexed string
- `status` indexed string
- `amount` nullable decimal for cash amount
- `payment_method` nullable indexed string
- `reference_number` nullable string
- `notes` nullable text
- `received_at` nullable timestamp
- `verified_at` nullable timestamp
- timestamps

Recommended constraints:

- Foreign key `donor_id` should restrict deletes or cascade only if donor deletion policy is approved. Prefer `restrictOnDelete()` for audit integrity.
- `campaign_id` should `nullOnDelete()` only if campaign deletion is allowed; otherwise restrict deletion.
- `created_by` and `verified_by` should `nullOnDelete()` to preserve records if users are removed.
- Unique nullable composite index on `payment_method` and `reference_number` if the database and business policy support it.

Recommended indexes:

- `type`
- `status`
- `received_at`
- `donor_id`
- `campaign_id`
- `created_by`
- `verified_by`
- composite `type, status, received_at`
- composite `campaign_id, status`

#### Contribution Items

Create `contribution_items` for in-kind rows.

Fields:

- `id`
- `contribution_id` foreign key to `contributions`
- `name`
- `category` indexed string
- `quantity` decimal
- `unit` string
- `condition` indexed string
- `estimated_value` nullable decimal
- `notes` nullable text
- timestamps

Recommended constraints:

- `contribution_id` cascades on delete only if deleting contribution drafts is allowed. If records are audit-retained, prefer status-based cancellation and restrict deletes.
- Quantity must be positive; use application validation and database check constraints where supported.
- Estimated value must be non-negative.

Recommended indexes:

- `contribution_id`
- `category`
- `condition`

### Existing Table Migration Strategy

Avoid duplicate or unnecessary tables by not writing new portal records independently into `cash_transactions` and `inventory_items` after `contributions` is approved.

Recommended strategy:

1. Keep existing tables for legacy dashboards and workflows until the portal is migrated.
2. Add `contributions` and `contribution_items`.
3. Backfill contributions from existing `donations`, `cash_transactions`, and `inventory_items` only if historical continuity is required and mapping rules are approved.
4. Update dashboard and reports to read from `contributions` once the portal becomes the source of truth.
5. Deprecate old write paths or make them write through the same contribution service.
6. Do not drop old tables until all dependent pages are migrated and verified.

## Models and Relationships

Recommended models:

- `Contribution`
- `ContributionItem`
- `Campaign`

Relationships:

- `Donor hasMany Contribution`
- `Contribution belongsTo Donor`
- `Contribution belongsTo Campaign`
- `Contribution belongsTo User as creator`
- `Contribution belongsTo User as verifier`
- `Contribution hasMany ContributionItem`
- `ContributionItem belongsTo Contribution`
- `Campaign hasMany Contribution`
- `User hasMany Contribution as createdContributions`
- `User hasMany Contribution as verifiedContributions`

Recommended model behavior:

- Cast money fields as decimal.
- Cast `received_at` and `verified_at` as datetime.
- Add scopes:
  - `type(?string $type)`
  - `status(?string $status)`
  - `campaign(?int $campaignId)`
  - `search(?string $term)`
  - `cash()`
  - `inKind()`
  - `pending()`
  - `verified()`
- Avoid querying inside accessors used in table loops.

## Backend

### Controllers

Recommended controller:

- `App\Http\Controllers\DonationPortalController`
  - `index(DonationPortalIndexRequest $request)`
  - `store(StoreContributionRequest $request)`
  - `show(Contribution $contribution)`
  - `update(UpdateContributionRequest $request, Contribution $contribution)` if editing is approved
  - `verify(VerifyContributionRequest $request, Contribution $contribution)` if cash verification is approved
  - `destroy` should not be added for the first version; use cancel or void status instead.

Optional service:

- `App\Services\Contributions\LogContribution`
  - Creates cash or in-kind records inside a transaction.
  - Assigns configured default status.
  - Records `created_by`.
  - Creates item rows for in-kind contributions.

### Routes

Recommended route group:

```php
Route::middleware(['auth', 'role:admin,staff'])->group(function () {
    Route::get('/donations', [DonationPortalController::class, 'index'])->name('donations.index');
    Route::post('/donations', [DonationPortalController::class, 'store'])->name('donations.store');
    Route::get('/donations/{contribution}', [DonationPortalController::class, 'show'])->name('donations.show');
    Route::patch('/donations/{contribution}', [DonationPortalController::class, 'update'])->name('donations.update');
    Route::patch('/donations/{contribution}/verify', [DonationPortalController::class, 'verify'])->name('donations.verify');
});
```

Only add update and verify routes if the approved first implementation includes those workflows.

### Form Requests

Recommended request classes:

- `App\Http\Requests\Donations\DonationPortalIndexRequest`
- `App\Http\Requests\Donations\StoreContributionRequest`
- `App\Http\Requests\Donations\UpdateContributionRequest`
- `App\Http\Requests\Donations\VerifyContributionRequest`

Responsibilities:

- Validate query filters.
- Validate conditional modal fields.
- Normalize text fields.
- Normalize enum-like values to lowercase where appropriate.
- Convert empty nullable values to null.
- Ensure authorization is checked before persistence.

### Authorization

Recommended policy:

- `ContributionPolicy`
  - `viewAny`
  - `view`
  - `create`
  - `update`
  - `verify`
  - `cancel`

Suggested role behavior:

- `admin`: view, create, edit, verify, cancel all contributions.
- `staff`: view and create donor and contribution records, verify cash contributions, and view in-kind summaries.

Do not duplicate role strings in views. Use policies, model helpers, config-backed constants, or enum-like role constants.

### Data Flow

Index flow:

1. Authenticated user requests `GET /donations`.
2. `DonationPortalIndexRequest` validates filters.
3. Controller queries contributions with donor, campaign, creator, and item summary data.
4. Controller returns `donations.index` inside `layouts.app`.

Create cash flow:

1. User clicks `Log New Contribution`.
2. Modal opens and user selects `Cash / Electronic Transfer`.
3. User submits POST `/donations` with CSRF token.
4. `StoreContributionRequest` validates cash fields.
5. Service creates a `contributions` row in a transaction.
6. Controller redirects to `donations.index` with success flash.

Create in-kind flow:

1. User opens modal and selects `In-Kind Goods`.
2. User adds one or more item rows.
3. User submits POST `/donations` with CSRF token.
4. Request validates parent and item rows.
5. Service creates contribution and child item rows in a transaction.
6. Controller redirects with success flash.

Verification flow:

1. Authorized staff or admin clicks `Verify`.
2. Request validates permission and current status.
3. Controller/service updates status, verifier, and verified timestamp.
4. Redirects with success flash.

## Reusable Blade Components

Recommended new components:

- `resources/views/donations/index.blade.php`
- `resources/views/components/donations/filter-bar.blade.php`
- `resources/views/components/donations/table.blade.php`
- `resources/views/components/donations/table-row.blade.php`
- `resources/views/components/donations/status-badge.blade.php`
- `resources/views/components/donations/log-modal.blade.php`
- `resources/views/components/donations/cash-fields.blade.php`
- `resources/views/components/donations/in-kind-fields.blade.php`
- `resources/views/components/donations/item-row.blade.php`

Reuse existing components:

- `layouts.app`
- `components/app/header.blade.php`
- `components/app/sidebar.blade.php`
- `components/alert.blade.php`
- `components/button.blade.php`
- `components/form/input.blade.php`
- `components/form/checkbox.blade.php`
- `components/form/error.blade.php`
- `components/dashboard/empty-state.blade.php`

Component rules:

- Components accept data through props.
- Components do not query the database.
- Modal fields use reusable form components and field-level errors.
- Dynamic item rows should use a small JavaScript controller in `resources/js/app.js` only if necessary.

## Query Strategy

Index query should:

- Select only required contribution columns.
- Eager-load `donor`, `campaign`, and `creator`.
- Use `withCount('items')` for in-kind item counts.
- Use `withSum('items', 'estimated_value')` for estimated value where needed.
- Use database filters for search, type, campaign, and status.
- Paginate results using a configured per-page value.

Search should avoid loading full item collections for every row. Use `whereHas` for item name/category matches.

## Configuration

Recommended config file:

- `config/donations.php`

Recommended keys:

- `types`
- `statuses`
- `default_status.cash`
- `default_status.in_kind`
- `payment_methods`
- `electronic_payment_methods`
- `item_categories`
- `item_conditions`
- `units`
- `pagination.per_page`
- `recent_limit`

Use config labels for display text and validation values.

## Performance Considerations

- Do not cache the authenticated donation portal page.
- Use pagination for the contribution table.
- Use eager loading and aggregate subqueries to avoid N+1 queries.
- Add indexes with migrations for filter and sort columns.
- Avoid duplicate queries for donors and campaigns by loading active select options once per request.
- Use short, targeted caching only for non-sensitive reference data such as active campaign options or category labels if they become database-backed.
- Invalidate any reference-data cache when campaigns or categories change.
- Do not cache notes, donor contact details, session data, CSRF tokens, or user-specific authorization decisions.

## Error Handling

- Log server exceptions with contribution context and authenticated user ID, but never log sensitive payment details beyond approved reference metadata.
- Show generic user-facing error messages.
- Preserve safe old input after validation failure.
- Do not expose SQL errors or stack traces.
- Use transactions so partial in-kind item rows are never created without the parent contribution.

## Testing Recommendations

- Guests are redirected to login.
- Approved roles can view donation portal.
- Unauthorized roles receive `403` if introduced.
- Donation list filters by search, type, campaign, and status.
- Empty state renders when no contributions exist.
- Cash contribution validation requires donor, amount, payment method, and conditional reference number.
- Valid cash contribution inserts one contribution row.
- In-kind validation requires at least one item row.
- Valid in-kind contribution inserts one contribution and related item rows.
- Modal reopens with validation errors.
- Status badges render configured labels.
- Verification requires correct role and valid current status.
- Query tests guard against N+1 regressions if query-count tooling is added.

## Implementation Plan After Approval

1. Add `config/donations.php`.
2. Create approved migrations for `campaigns`, `contributions`, and `contribution_items`.
3. Create `Campaign`, `Contribution`, and `ContributionItem` models.
4. Add relationships to `Donor` and `User`.
5. Create Form Request classes.
6. Create `ContributionPolicy`.
7. Create `DonationPortalController`.
8. Add donation portal routes.
9. Update Sidebar `Donations Portal` route to `donations.index`.
10. Create reusable donation Blade components.
11. Add modal behavior for contribution type switching and item rows.
12. Add feature tests and validation tests.
13. Run migrations, tests, route list, Blade compilation, and Vite build.

## Approval Notes

Before implementation, confirm:

- Whether campaigns are required on every contribution or optional.
- Whether new cash contributions default to `pending` or `verified`.
- Whether staff users may log cash contributions.
- Whether staff users may edit in-kind contribution records.
- Whether existing `donations`, `cash_transactions`, and `inventory_items` records should be backfilled into the new contribution tables.
- Whether item categories should be config-backed or managed through a database reference table.
