# System Security Administration Design

## Goal

Create a production-ready System Security Administration module for administrative users to review authorized personnel, inspect two-factor enforcement, manage security permissions, and audit security events from a dedicated ledger-style page.

The page should match the provided government administration dashboard reference while staying consistent with the existing Laravel 11, Blade, Tailwind CSS, Eloquent, policy, service, and component patterns already used by the project.

## Architecture

- Add a new authenticated, admin-only page at `/system-security`.
- Keep the controller thin. The controller delegates dashboard data assembly and state changes to services.
- Use Eloquent models and relationships for all displayed data.
- Use Form Requests for filter and toggle validation.
- Use a Policy for authorization.
- Use focused Blade components for repeatable UI sections.
- Reuse `layouts.app`, `x-alert`, `x-button`, and existing dashboard empty-state/panel styling conventions.

## Database Design

The current schema supports users and roles, but it does not support per-user two-factor enforcement state, permission assignments, or security activity audit rows. Add only the required normalized tables:

### `security_permissions`

Stores the permission checklist options.

- `id`
- `key` unique string
- `name`
- `description` nullable
- `sort_order` indexed unsigned integer
- timestamps

### `user_security_profiles`

Stores per-user security state.

- `id`
- `user_id` unique foreign key to `users`
- `two_factor_enabled` boolean
- `two_factor_enforced` boolean
- `two_factor_verified_at` nullable timestamp
- `security_key_suffix` nullable string
- timestamps

### `security_permission_user`

Normalized pivot table for user permission assignments.

- `id`
- `user_id` foreign key to `users`
- `security_permission_id` foreign key to `security_permissions`
- `granted_by` nullable foreign key to `users`
- timestamps
- unique index on `user_id`, `security_permission_id`

### `security_activity_events`

Stores the security activity trail ledger.

- `id`
- `user_id` nullable foreign key to the affected user
- `actor_id` nullable foreign key to the acting user
- `action` indexed string
- `summary`
- `metadata` nullable JSON
- `ip_address` nullable string
- `occurred_at` indexed timestamp
- timestamps

## Routes

Inside the existing authenticated role-protected group:

- `GET /system-security` named `system-security.index`
- `PATCH /system-security/users/{user}/two-factor-enforcement` named `system-security.two-factor-enforcement.update`

## Controllers

### `SystemSecurityController`

- `index(SystemSecurityIndexRequest $request, SystemSecurityDashboardQuery $query)`
  - Authorizes `viewAny` on `SecurityActivityEvent`.
  - Passes filters to the dashboard query service.
  - Returns the `system-security.index` view.
- `updateTwoFactorEnforcement(ToggleTwoFactorEnforcementRequest $request, User $user, ToggleTwoFactorEnforcement $toggle)`
  - Authorizes through the Form Request.
  - Delegates the state change and audit event recording to the service.
  - Redirects back with a success message.

## Services

### `SystemSecurityDashboardQuery`

Builds all page data:

- Administrative personnel with role, security profile, and assigned permissions.
- Permission checklist definitions.
- Activity trail events filtered by action and search query.
- Available action filters derived from security activity events.

### `ToggleTwoFactorEnforcement`

Toggles a user security profile's `two_factor_enforced` value and records a `security_activity_events` row with actor, target, action, IP address, and metadata.

## Policies

### `SecurityActivityEventPolicy`

- `viewAny`: only `admin`.
- `updateSecuritySettings`: only `admin`.

The existing route middleware already restricts authenticated app access to `admin`, `secretary`, and `treasurer`; this policy tightens the security module to admin users only.

## Blade Components

Create reusable components under `resources/views/components/system-security`:

- `section-header.blade.php`
- `user-security-card.blade.php`
- `permission-checklist.blade.php`
- `status-badge.blade.php`
- `activity-log-row.blade.php`
- `search-bar.blade.php`
- `filter-dropdown.blade.php`

## Data Flow

1. Admin opens `/system-security`.
2. `SystemSecurityIndexRequest` validates `action` and `search` filters.
3. `SystemSecurityController@index` authorizes access.
4. `SystemSecurityDashboardQuery` queries users, profiles, permissions, and activity events.
5. Blade renders personnel cards, permission checklists, 2FA status controls, and the ledger.
6. Admin submits a 2FA enforce toggle.
7. `ToggleTwoFactorEnforcementRequest` authorizes and validates the new state.
8. `ToggleTwoFactorEnforcement` updates the profile and records an audit event.

## Permission Model

The module uses two levels of permission:

- Access to the module itself is controlled by `SecurityActivityEventPolicy` and limited to admin users.
- Displayed personnel permissions are stored as data in `security_permissions` and assigned through `security_permission_user`.

This preserves the current role model while adding security-specific checklist data without changing existing authentication behavior.

## Audit Logging Strategy

Security activity is stored in `security_activity_events`.

Events are created for:

- Initial security profile and permission provisioning by seeders.
- Two-factor enforcement changes from the page.
- Future security-sensitive actions can reuse the model/service.

The ledger stores action keys for filtering and human-readable summaries for display. Structured metadata remains JSON for future reporting without requiring schema changes.

## UI Component Breakdown

- Header band: title, description, system/security status badges.
- Authorized Administrative Personnel panel:
  - responsive 1/2/3-column grid
  - personnel identity, role badge, 2FA status, toggle form, permission checklist, simulated security key suffix
- Security Activity Trail Ledger panel:
  - section header
  - action filter dropdown
  - search input
  - scrollable ledger rows with actor/user/action/timestamp/IP metadata

## Reusable Components

Reuse:

- `layouts.app`
- `x-alert`
- `x-button`
- `x-dashboard.empty-state`
- Tailwind border, rounded, shadow, typography conventions from dashboard, bank reconciliation, reports, and inventory modules.

New components are scoped under `system-security` and can be reused by future security pages.

## Edge Cases

- No administrative users exist: render empty state.
- User lacks a security profile: service creates or queries with defaults through provisioning.
- No permissions assigned: render unchecked checklist.
- No activity events match filters: render empty state.
- Invalid action filter: Form Request rejects it.
- Non-admin access: policy returns 403.
- Repeated toggle submissions: service updates idempotently based on requested state.

## Testing Strategy

Feature tests:

- Admin can view the System Security Administration page.
- Non-admin authenticated user receives 403.
- Action/search filters narrow activity rows.
- Admin can toggle two-factor enforcement.
- Toggle records a security activity event.

Model/service tests:

- Dashboard query returns users with profiles and permissions without N+1 behavior.
- Toggle service creates missing profiles if needed and records metadata.

Verification:

- `php artisan test --filter=SystemSecurityAdministrationTest`
- `php artisan route:list --except-vendor`
- `php artisan migrate:fresh --seed` where safe for local validation.

## Files To Be Created

- `app/Http/Controllers/SystemSecurityController.php`
- `app/Http/Requests/SystemSecurity/SystemSecurityIndexRequest.php`
- `app/Http/Requests/SystemSecurity/ToggleTwoFactorEnforcementRequest.php`
- `app/Models/SecurityActivityEvent.php`
- `app/Models/SecurityPermission.php`
- `app/Models/UserSecurityProfile.php`
- `app/Policies/SecurityActivityEventPolicy.php`
- `app/Services/SystemSecurity/SystemSecurityDashboardQuery.php`
- `app/Services/SystemSecurity/ToggleTwoFactorEnforcement.php`
- `config/system_security.php`
- migration for security administration tables
- `database/seeders/SystemSecuritySeeder.php`
- `resources/views/system-security/index.blade.php`
- `resources/views/components/system-security/*.blade.php`
- `tests/Feature/SystemSecurityAdministrationTest.php`

## Files To Be Modified

- `routes/web.php`
- `resources/views/components/app/sidebar.blade.php`
- `app/Models/User.php`
- `database/seeders/DatabaseSeeder.php`
