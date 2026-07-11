# Donor Management Module Design

## Purpose and Scope

This document defines the implementation specification for the Donor Management module in the Barangay Donation Platform.

The current project is a Laravel 11 application using Blade templates, Tailwind CSS, Vite, MySQL, authenticated web sessions, role-based middleware, and an existing shared authenticated layout with Header and Sidebar. The current donor implementation uses a `donors` table, a `Donor` model, a `DonationController`, and `donations` records related to donors.

This document is a specification only. Do not implement application code until this design is approved.

## Design Principles

- Reuse the existing authenticated `layouts.app` shell, Header, Sidebar, route group, and dashboard-style components.
- Keep the module server-rendered with Blade for the first implementation.
- Use a modal dialog for donor creation rather than a separate page.
- Keep controllers thin by moving validation into Form Request classes.
- Keep donor data normalized and avoid creating extra tables unless a field needs a controlled vocabulary or many-to-many relationship.
- Do not hardcode donor types, statuses, pagination limits, route URLs, validation values, or display labels across multiple files.
- Use named routes and reusable Blade components.
- Avoid loading all donors if the dataset grows; use pagination and aggregate queries.

## Layout and User Experience

### Shared Shell

The Donor Management screen must render inside the existing authenticated layout:

- `resources/views/layouts/app.blade.php`
- Existing Header with barangay name, logged-in user information, role, and profile menu.
- Existing Sidebar with navigation items.

The page title should be `Donor Management`.

### Page Structure

Top area:

- Search bar at the top.
- `Add New Donor Profile` button aligned with the search controls on desktop and stacked on mobile.
- Optional filter controls for donor type and status if those fields are implemented.

Main content:

- Responsive donor cards displayed below the search area.
- Cards should use a single-column layout on mobile, two columns on tablet, and three columns on wide desktop if space allows.
- Cards must keep stable spacing and readable typography for long names, emails, and addresses.

### Donor Card Content

Each donor card should display:

- Donor name
- Donor type: Individual or Organization
- Email
- Contact number
- Address
- Total Cash Donations
- Total In-Kind Donations
- Number of Donations
- `View History` action
- `Edit Profile` action

Card empty field behavior:

- Use neutral text such as `Not provided` for optional empty contact fields.
- Do not hide required operational fields unless the value is truly unavailable from legacy data.

### Loading, Empty, and Error States

Loading:

- For server-rendered pagination, show the normal page load.
- If future async search is introduced, show card skeletons and keep the search controls stable.

Empty:

- No donors at all: show a centered empty state with an `Add New Donor Profile` action.
- Search with no matches: show a search-specific empty state and a clear-search link.

Error:

- Validation errors should show inside the modal near each field.
- Server errors should return a generic page-level or modal-level alert.
- Log internal exceptions without exposing SQL or stack traces.

## Add New Donor Profile Modal

### Modal Behavior

- The `Add New Donor Profile` button opens a modal dialog on the same page.
- The modal must be accessible:
  - Uses `role="dialog"` or native `<dialog>` where appropriate.
  - Has a clear title.
  - Has a close button.
  - Returns focus to the trigger after close if JavaScript is used.
  - Closes on cancel button and optionally Escape.
- The form submits to a normal Laravel POST route with CSRF protection.
- If validation fails, the page should reopen the modal with old safe input and field-level errors.
- Do not store donor form state in local storage.

### Required Fields

Recommended fields for first implementation:

- Donor Type
  - Input name: `donor_type`
  - Type: select
  - Required
  - Allowed values from config: `individual`, `organization`
- Donor Name
  - Input name: `full_name`
  - Type: text
  - Required
  - Max: 255
- Email
  - Input name: `email`
  - Type: email
  - Nullable
  - Max: 255
- Contact Number
  - Input name: `contact_number`
  - Type: text or tel
  - Required
  - Max: 30
- Address
  - Input name: `address`
  - Type: textarea
  - Required
  - Max: 500
- Notes
  - Input name: `notes`
  - Type: textarea
  - Nullable
  - Max: 2000

Recommended production-ready optional fields:

- Organization Contact Person
  - Input name: `contact_person`
  - Nullable
  - Required only when donor type is organization if the barangay workflow needs a named representative.
- Preferred Contact Method
  - Input name: `preferred_contact_method`
  - Nullable
  - Allowed values from config, for example `phone`, `email`, `sms`, `in_person`.
- Donor Status
  - Input name: `status`
  - Default from config, for example `active`.
  - Allowed values from config, for example `active`, `inactive`, `blocked`.
- Barangay / Area
  - Input name: `barangay_area`
  - Nullable text field if the platform serves multiple zones or puroks.

### Validation Rules

Create `App\Http\Requests\Donors\StoreDonorRequest`.

Recommended rules:

```php
'donor_type' => ['required', Rule::in(config('donors.types'))],
'full_name' => ['required', 'string', 'max:255'],
'email' => ['nullable', 'string', config('donors.email_rule'), 'max:255'],
'contact_number' => ['required', 'string', 'max:30'],
'address' => ['required', 'string', 'max:500'],
'contact_person' => ['nullable', 'string', 'max:255'],
'preferred_contact_method' => ['nullable', Rule::in(config('donors.contact_methods'))],
'status' => ['nullable', Rule::in(config('donors.statuses'))],
'barangay_area' => ['nullable', 'string', 'max:255'],
'notes' => ['nullable', 'string', 'max:2000'],
```

Create `App\Http\Requests\Donors\UpdateDonorRequest` with the same rules, adjusted for partial updates only if the edit form supports PATCH semantics.

Input normalization:

- Trim all text fields.
- Normalize email to lowercase.
- Normalize configured enum-like fields to lowercase.
- Convert empty strings to null where fields are nullable.

### Business Rules

- Donor type must be one of the configured donor types.
- Do not create separate donor tables for individuals and organizations.
- Email can be nullable because not all barangay donors may have email access.
- If email uniqueness is required, enforce it through a nullable unique index and validation; otherwise allow shared organization inboxes.
- Contact number is required for operational follow-up.
- Address is required for barangay record keeping.
- Do not delete donors with donation history during the first implementation; prefer status changes or soft deletes if removal is required later.
- Editing a donor profile must not modify historical donation amounts.
- Creation and update should use database transactions if future audit logs are added.

## Database Design

### Current Tables

Existing `donors` table:

- `id`
- `full_name`
- `address`
- `contact_number`
- `email`
- `notes`
- timestamps

Existing `donations` table:

- `id`
- `donor_id`
- `donation_type`
- `amount`
- `description`
- `received_at`
- timestamps

### Recommended Migration

A migration is recommended because the requested card requires donor type, and production donor profiles benefit from status and contact metadata.

Recommended migration: add profile fields to `donors`.

```php
Schema::table('donors', function (Blueprint $table) {
    $table->string('donor_type', 50)->default(config('donors.defaults.type'))->after('id');
    $table->string('contact_person')->nullable()->after('full_name');
    $table->string('preferred_contact_method', 50)->nullable()->after('contact_number');
    $table->string('barangay_area')->nullable()->after('address');
    $table->string('status', 50)->default(config('donors.defaults.status'))->after('barangay_area');

    $table->index(['donor_type', 'status']);
    $table->index('full_name');
    $table->index('contact_number');
});
```

Optional migration if donor removal/auditability becomes a requirement:

```php
Schema::table('donors', function (Blueprint $table) {
    $table->softDeletes();
});
```

Do not add soft deletes unless the product owner wants donor profile recovery or account-like lifecycle handling.

### Optional Donation Type Normalization

The donor card needs:

- Total Cash Donations
- Total In-Kind Donations
- Number of Donations

The current `donations.donation_type` string can support this if values are normalized through config and validation. A new table is not required for the first donor management implementation.

If donation types later need managed labels, reporting categories, or translations, create a `donation_types` table and add `donation_type_id` to `donations` in a separate approved design.

## Models and Relationships

### Donor Model

Recommended updates:

- Add fillable fields:
  - `donor_type`
  - `full_name`
  - `contact_person`
  - `email`
  - `contact_number`
  - `preferred_contact_method`
  - `address`
  - `barangay_area`
  - `status`
  - `notes`
- Keep relationship:
  - `Donor hasMany Donation`
- Add query scopes:
  - `search(string $term)`
  - `type(?string $type)`
  - `active()`
- Avoid computing donation totals in PHP loops; use aggregate queries.

### Donation Model

Existing relationship:

- `Donation belongsTo Donor`

Recommended additions:

- Constants or config-backed scopes for cash and in-kind donation categories.
- Cast `amount` as decimal and `received_at` as datetime, already present in the current code.

## Backend Query Strategy

The donor index should fetch donors with aggregate metrics:

- `donations_count`
- `total_cash_donations`
- `total_in_kind_donations`

Recommended approach:

- Use `withCount('donations')`.
- Use `withSum()` or `selectSub()` for cash totals and in-kind totals.
- Use database-level filtering for search.
- Use pagination.
- Do not eager-load all donations on the card list.

Example data shape for each card:

```php
[
    'id',
    'donor_type',
    'full_name',
    'email',
    'contact_number',
    'address',
    'donations_count',
    'total_cash_donations',
    'total_in_kind_donations',
]
```

Search should match:

- Donor name
- Email
- Contact number
- Address
- Contact person
- Barangay area

## Routes

Use the existing authenticated application route group.

Recommended routes:

```php
Route::middleware(['auth', 'role:admin,secretary,treasurer'])->group(function () {
    Route::get('/donors', [DonorController::class, 'index'])->name('donors.index');
    Route::post('/donors', [DonorController::class, 'store'])->name('donors.store');
    Route::get('/donors/{donor}', [DonorController::class, 'show'])->name('donors.show');
    Route::patch('/donors/{donor}', [DonorController::class, 'update'])->name('donors.update');
});
```

Route behavior:

- `donors.index`: searchable card list and add donor modal.
- `donors.store`: modal form submission.
- `donors.show`: donor donation history view or modal/panel route.
- `donors.update`: edit profile action.

Do not add API routes unless the modal becomes asynchronous.

## Controllers

Recommended controller:

- `App\Http\Controllers\DonorController`
  - `index(Request $request)`
  - `store(StoreDonorRequest $request)`
  - `show(Donor $donor)`
  - `update(UpdateDonorRequest $request, Donor $donor)`

Current implementation note:

- Donor creation currently lives in `DonationController::storeDonor`.
- During implementation, move donor-specific logic into `DonorController`.
- Keep donation recording in a separate donation-focused controller or method.

Controller responsibilities:

- Authorize the action.
- Call validated request data.
- Persist donor data.
- Redirect using named routes.
- Flash success or error messages.
- Never perform raw validation inside Blade.

## Authorization

Base route access:

- `admin`, `secretary`, and `treasurer` can view donor cards if current product policy allows all operational roles to inspect donor records.

Recommended action-level rules:

- `admin`: view, create, edit all donor profiles.
- `secretary`: view, create, edit donor profiles.
- `treasurer`: view donor profiles and history; editing can be allowed only if finance workflow requires it.

Implementation options:

- Use a `DonorPolicy` for `viewAny`, `view`, `create`, and `update`.
- Or use role helper checks for the first implementation if policies are not yet used elsewhere.
- Do not duplicate role strings in multiple Blade files; use model helpers, config constants, or policies.

## Reusable Blade Components

Recommended components:

- `resources/views/donors/index.blade.php`
- `resources/views/components/donors/card.blade.php`
- `resources/views/components/donors/search-bar.blade.php`
- `resources/views/components/donors/create-modal.blade.php`
- `resources/views/components/donors/form.blade.php`
- `resources/views/components/donors/history-summary.blade.php`
- Reuse existing:
  - `layouts.app`
  - `components/app/header.blade.php`
  - `components/app/sidebar.blade.php`
  - `components/alert.blade.php`
  - `components/button.blade.php`
  - `components/form/*`
  - `components/dashboard/empty-state.blade.php`

Component requirements:

- Components accept data through props.
- Components do not query the database.
- Donor cards must be responsive and not overflow with long donor names or addresses.
- Form components must show field-level validation errors.

## Modal State Handling

Recommended first implementation:

- Use lightweight Blade plus minimal JavaScript in `resources/js/app.js`.
- Open modal when:
  - User clicks `Add New Donor Profile`.
  - Session has validation errors for donor creation.
- Close modal on:
  - Close button.
  - Cancel button.
  - Escape key if implemented.

Alternative no-JavaScript fallback:

- Use a query parameter such as `?create=1` and CSS/Blade conditionals to render the modal open.
- This is acceptable if JavaScript is intentionally kept minimal.

## Success and Error States

Create success:

- Flash message: `Donor profile created successfully.`
- Redirect to `donors.index`.
- The newly created donor may be highlighted if a `created_donor` session value is used.

Update success:

- Flash message: `Donor profile updated successfully.`

Validation errors:

- Reopen the modal.
- Preserve safe old input.
- Show field-level messages.

Duplicate or conflicting data:

- If email uniqueness is enabled, use Laravel validation messages.
- If contact number uniqueness is enabled, use Laravel validation messages.
- Avoid exposing other donor metadata in conflict messages.

Server error:

- Log exception.
- Show generic error message.

## Caching Recommendations

- Do not cache the authenticated donor index response.
- Do not cache search results in the first implementation.
- Do not cache donor cards if the card includes operational or personally identifying contact data.
- It is safe to use config cache for donor type/status/contact-method values.
- If future aggregate metrics become expensive, consider short-lived cache per aggregate only when invalidated on donor and donation writes.

## Performance Considerations

- Use pagination for donor cards.
- Use aggregate SQL for donation totals.
- Avoid loading full donation history for every donor card.
- Add indexes only where needed by approved migrations.
- Keep search simple with indexed columns first.
- For large deployments, consider full-text search on donor name, email, contact number, and address in a later migration.
- Use eager loading only on pages that need related records, such as donor history.

## Data Flow

Index flow:

1. Authenticated user requests `GET /donors`.
2. Controller validates/reads search and filter query parameters.
3. Controller queries donors with aggregate counts and sums.
4. Controller returns donor card view inside `layouts.app`.

Create flow:

1. User clicks `Add New Donor Profile`.
2. Modal opens.
3. User submits `POST /donors` with CSRF token.
4. `StoreDonorRequest` validates and normalizes input.
5. Controller creates donor.
6. Controller redirects to `donors.index` with success flash.
7. Validation failure redirects back, reopens modal, and shows field-level errors.

History flow:

1. User clicks `View History`.
2. App loads `GET /donors/{donor}`.
3. Controller loads donor with paginated donation history.
4. View shows donor profile and donation records.

Edit flow:

1. User clicks `Edit Profile`.
2. App opens edit modal or loads edit panel.
3. User submits `PATCH /donors/{donor}`.
4. `UpdateDonorRequest` validates and normalizes input.
5. Controller updates donor and redirects with success flash.

## Testing Recommendations

- Guest users are redirected to login.
- Approved roles can view donor management.
- Unauthorized roles receive `403` if such roles are introduced.
- Donor cards render donor profile fields and aggregate donation metrics.
- Search filters donors by name, email, contact number, and address.
- Create donor modal validation shows errors and preserves safe input.
- Valid donor creation inserts a donor and redirects to index.
- Edit donor updates only profile fields and does not alter donation history.
- Donor history loads donations for only the selected donor.
- Query tests should guard against N+1 regressions if query-count tooling is introduced.

## Implementation Plan After Approval

1. Add donor config for types, statuses, contact methods, and pagination limits.
2. Add migration for donor profile fields if approved.
3. Create `DonorController`.
4. Create `StoreDonorRequest` and `UpdateDonorRequest`.
5. Update `Donor` model fillable fields, casts, and scopes.
6. Update donor routes to use `DonorController`.
7. Build donor card, search bar, modal, and form components.
8. Update `donors.index` to use cards and modal creation.
9. Add donor history route/view.
10. Add feature tests.
11. Run migrations, tests, route list, Blade compilation, and Vite build.

## Approval Notes

Before implementation, confirm:

- Whether donor email should be unique or allowed to repeat.
- Whether contact number should be unique or allowed to repeat.
- Whether treasurer users may edit donor profiles or view-only.
- Whether donor deletion should exist now, and if so whether soft deletes are required.
- Whether `View History` should be a separate page, modal, or slide-over panel.
