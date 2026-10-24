# Authentication Module Design

## Purpose and Scope

This document defines the implementation guide for a maintainable authentication module for the Barangay Donation Platform.

The current project is a Laravel 11 application using Blade templates, Tailwind CSS, Vite, MySQL, the default `web` session guard, database-backed sessions, an existing `AuthController`, a custom `EnsureRole` middleware, and a `users.role` string column for role-based access. The existing authenticated application routes are protected by `auth` and `role:admin,staff`.

The authentication module should provide:

- Secure login and logout for existing users.
- Public registration through a Sign Up page.
- Email uniqueness and password safety.
- A normalized authorization model that can grow beyond the current string-based role column.
- Clean validation using Form Request classes.
- Reusable Blade components to avoid duplicating form, layout, and alert markup.

This specification does not implement application code. It describes the design that should be approved before code generation begins.

## Functional Requirements

- Users can visit `/login` to authenticate with email address and password.
- Users can visit `/register` to create an account with first name, last name, email address, password, password confirmation, and accepted terms.
- Authenticated users can log out using a POST `/logout` request protected by CSRF.
- Successful login redirects users to the main donation dashboard or the URL they originally attempted to access.
- Successful registration creates a user, assigns the default configured role, starts a session only if the product owner approves immediate access, and optionally triggers email verification.
- Failed login attempts return a generic credential error without revealing whether the email exists.
- Registration validation errors return users to the form with old safe input values.
- Existing role-gated application routes continue to allow only approved roles.
- Authenticated users should not be able to access login or registration pages unless intentionally logging into a different account.
- Guest users should not be able to access dashboard, donor, donation, inventory, cash, or reconciliation pages.

## Non-Functional Requirements

- Use Laravel 11 authentication best practices and the existing `web` session guard.
- Keep all authentication state server-side through Laravel sessions; never expose sensitive state to JavaScript.
- Do not hardcode role IDs, role names in multiple locations, redirect URLs, or validation settings. Prefer config values, constants, enums, seeders, and named routes.
- Keep controllers thin by moving validation to Form Request classes and persistence rules to models/services where needed.
- Preserve compatibility with the current Blade and Tailwind style while moving toward reusable layouts and components.
- Keep migration changes reversible and safe for existing data.
- Normalize role data to support maintainable authorization.
- Use Laravel's built-in CSRF, hashing, validation, session, password reset, and email verification features instead of custom security primitives.
- Support testing with Laravel feature tests for guest, login, registration, logout, validation, verification, and role restrictions.

## Login Page Specification

### UI Layout

- Route: `GET /login`, named `login`.
- View recommendation: `resources/views/auth/login.blade.php`.
- Use a dedicated reusable guest layout, for example `resources/views/layouts/guest.blade.php`.
- Keep the interface consistent with the existing app: clean white panel, slate background, readable spacing, and Tailwind utility classes.
- Include the platform name, a short access-oriented subtitle, and the login form.
- Include a link to the Sign Up page if public registration is approved.
- Include a "Forgot password?" link after password reset routes are implemented.
- Use accessible labels for every field. Placeholders can support labels but must not replace them.
- Show validation and authentication errors near the form, with field-level messages where possible.

### Fields

- Email Address
  - Input name: `email`
  - Type: `email`
  - Required
  - Autocomplete: `email`
- Password
  - Input name: `password`
  - Type: `password`
  - Required
  - Autocomplete: `current-password`
- Remember Me
  - Input name: `remember`
  - Type: `checkbox`
  - Optional
  - Only include if the product owner wants persistent sessions.

### Validation Rules

Use `App\Http\Requests\Auth\LoginRequest`.

Recommended rules:

```php
'email' => ['required', 'string', 'email:rfc,dns', 'max:255'],
'password' => ['required', 'string'],
'remember' => ['nullable', 'boolean'],
```

Additional behavior:

- Normalize email to lowercase before authentication.
- Apply throttling to failed attempts by email and IP address.
- Do not return different messages for missing user, wrong password, disabled account, or unverified email.

### Authentication Flow

1. Guest requests `GET /login`.
2. Controller returns the login view.
3. User submits `POST /login` with CSRF token.
4. `LoginRequest` validates input and checks throttling.
5. Controller attempts authentication through `Auth::attempt($credentials, $remember)`.
6. On success:
   - Clear failed login throttle counter.
   - Regenerate the session ID.
   - Redirect to `intended()` route, falling back to the dashboard route.
7. On failure:
   - Increment the failed login throttle counter.
   - Return to the form with a generic error.

### Error Handling

- Use one generic top-level message for credential failures: "The provided credentials are incorrect."
- Preserve only the email value with `old('email')`; never repopulate passwords.
- Return `422` validation responses for invalid form data.
- Return `403` for authenticated users without required roles.
- Redirect guests attempting protected pages to the named `login` route.
- Log suspicious repeated failures through Laravel logging, without storing passwords or raw tokens.

## Sign Up Page Specification

### UI Layout

- Route: `GET /register`, named `register`.
- View recommendation: `resources/views/auth/register.blade.php`.
- Use the same guest layout as the login page for consistency.
- The user mentioned an inspiration image; the implementation should use it as mood guidance only, not as a copy. Recommended direction:
  - A responsive two-column layout on desktop.
  - Left side: calm barangay/community donation visual treatment, platform name, and short trust message.
  - Right side: registration panel with compact fields and clear call to action.
  - Single-column stacked layout on mobile.
  - Use the existing app's restrained slate/blue/green palette rather than introducing a disconnected visual language.
- Include a link back to login for users who already have an account.
- Include Terms & Conditions link text connected to the checkbox.
- Avoid decorative complexity that distracts from form completion.

### Fields

- First Name
  - Input name: `first_name`
  - Type: `text`
  - Required
  - Autocomplete: `given-name`
- Last Name
  - Input name: `last_name`
  - Type: `text`
  - Required
  - Autocomplete: `family-name`
- Email Address
  - Input name: `email`
  - Type: `email`
  - Required
  - Autocomplete: `email`
- Password
  - Input name: `password`
  - Type: `password`
  - Required
  - Autocomplete: `new-password`
- Confirm Password
  - Input name: `password_confirmation`
  - Type: `password`
  - Required
  - Autocomplete: `new-password`
- Terms & Conditions
  - Input name: `terms`
  - Type: `checkbox`
  - Required

### Validation Rules

Use `App\Http\Requests\Auth\RegisterRequest`.

Recommended rules:

```php
'first_name' => ['required', 'string', 'max:100'],
'last_name' => ['required', 'string', 'max:100'],
'email' => ['required', 'string', 'email:rfc,dns', 'max:255', 'unique:users,email'],
'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()],
'terms' => ['accepted'],
```

Additional behavior:

- Trim names.
- Normalize email to lowercase.
- Reject passwords found in known breaches using Laravel's `uncompromised()` rule.
- Use localized validation messages later if the app adds Filipino/local language support.

### Registration Flow

1. Guest requests `GET /register`.
2. Controller returns the registration view.
3. User submits `POST /register` with CSRF token.
4. `RegisterRequest` validates input.
5. Store the user in a database transaction.
6. Hash the password through Laravel's hashed cast or `Hash::make()`, not both.
7. Assign the default role from configuration, not a hardcoded controller value.
8. Dispatch `Registered` event for email verification support.
9. Recommended production behavior:
   - If email verification is enabled, redirect to an email verification notice page.
   - If registration requires admin approval, create the account as pending and redirect with a clear message.
   - If immediate access is approved, log the user in, regenerate the session, and redirect to the dashboard.

### Success and Error States

- Success with email verification:
  - "Account created. Please verify your email address before continuing."
- Success with admin approval:
  - "Account created. An administrator will review access before you can sign in."
- Success with immediate login:
  - Redirect to dashboard with a short success flash message.
- Validation errors:
  - Field-level messages under each field.
  - Keep safe old values: first name, last name, email, and terms checkbox.
- Duplicate email:
  - Use Laravel validation message and avoid exposing extra account metadata.
- Server error:
  - Log the exception and show a generic failure message.

## Database Design

### Recommended Migrations

Current schema:

- `users` table includes `name`, `email`, `email_verified_at`, `password`, `remember_token`, timestamps.
- A later migration adds `users.role` as a string with default `staff`.
- `password_reset_tokens` and `sessions` tables already exist in the default users migration.

Recommended migration path:

1. Add profile-safe user columns:
   - `first_name`
   - `last_name`
   - optional `status`
   - optional `last_login_at`
2. Create normalized `roles` table.
3. Add nullable `role_id` foreign key to `users`.
4. Backfill `roles` from existing distinct `users.role` values.
5. Backfill `users.role_id`.
6. Keep `users.role` temporarily during migration for backward compatibility.
7. Update application code to use `role_id` relationship.
8. Drop `users.role` in a later migration after tests and deployment verification.

### Complete Production-Ready Users Table

Recommended final `users` table:

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->foreignId('role_id')->constrained()->restrictOnDelete();
    $table->string('first_name', 100);
    $table->string('last_name', 100);
    $table->string('name')->nullable();
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->string('status', 50)->default(config('auth_defaults.user_status'));
    $table->timestamp('last_login_at')->nullable();
    $table->rememberToken();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['role_id', 'status']);
});
```

Notes:

- `name` can be retained temporarily for compatibility with current dashboard code using `auth()->user()->name`.
- Prefer an accessor `getNameAttribute()` that combines `first_name` and `last_name` once all writes move to split names.
- `status` should be controlled by config or enum-like constants, for example `active`, `pending`, `suspended`.
- Use soft deletes if the system needs auditability for accounts. Otherwise omit `softDeletes()` and preserve audit logs separately.

### Roles Table

Recommended `roles` table:

```php
Schema::create('roles', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->string('display_name');
    $table->text('description')->nullable();
    $table->timestamps();
});
```

Recommended seeded roles:

- `admin`
- `staff`
- `staff`

Role names should be seeded through a seeder or enum/config source and referenced by constants, not duplicated across controllers, middleware, and views.

### Relationships

- `User belongsTo Role`
- `Role hasMany User`
- Existing domain models remain unchanged:
  - `Donor hasMany Donation`
  - `Donation belongsTo Donor`

Optional future relationships:

- Add `created_by` or `updated_by` foreign keys to donation, donor, inventory, cash transaction, and reconciliation records if audit trails become a requirement.

## Routes

Recommended route names and middleware:

```php
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
```

Protected application routes should continue using a grouped structure:

```php
Route::middleware(['auth', 'verified', 'role:admin,staff'])->group(function () {
    // Existing donation, inventory, cash, and reconciliation routes.
});
```

Use `verified` only after email verification is enabled. If registration requires admin approval, add an `active` or `approved` middleware.

## Controllers

Recommended controller split:

- `App\Http\Controllers\Auth\AuthenticatedSessionController`
  - `create()`: show login page.
  - `store(LoginRequest $request)`: authenticate.
  - `destroy(Request $request)`: log out.
- `App\Http\Controllers\Auth\RegisteredUserController`
  - `create()`: show registration page.
  - `store(RegisterRequest $request)`: create user.
- Future:
  - `PasswordResetLinkController`
  - `NewPasswordController`
  - `EmailVerificationPromptController`
  - `VerifyEmailController`

The existing `AuthController` can be refactored into these focused controllers during implementation. Avoid growing one controller with every auth concern.

## Models

### User Model

Recommended updates:

- Add fillable fields: `role_id`, `first_name`, `last_name`, `email`, `password`, `status`.
- Keep hidden fields: `password`, `remember_token`.
- Keep casts: `email_verified_at => datetime`, `password => hashed`, `last_login_at => datetime`.
- Add `role()` relationship.
- Add role helper methods only if they simplify middleware and views, for example `hasRole(string|array $roles): bool`.
- Implement `MustVerifyEmail` when email verification is enabled.

### Role Model

Recommended fields:

- `name`
- `display_name`
- `description`

Recommended behavior:

- `users()` relationship.
- Constants or enum-like methods for supported role names.

## Form Request Validation

Create:

- `App\Http\Requests\Auth\LoginRequest`
- `App\Http\Requests\Auth\RegisterRequest`

Responsibilities:

- Validation rules.
- Input normalization through `prepareForValidation()`.
- Authentication attempt logic may live in `LoginRequest::authenticate()` if following Laravel Breeze's pattern.
- Authorization should return `true` for guest auth forms.

Benefits:

- Keeps controllers readable.
- Makes validation testable.
- Prevents duplicate validation logic if views or endpoints grow.

## Middleware

Existing middleware:

- `App\Http\Middleware\EnsureRole`, aliased as `role` in `bootstrap/app.php`.

Recommended changes:

- Update `EnsureRole` to check normalized `Role` relationship rather than `users.role` string after migration.
- Preserve support for multiple roles: `role:admin,staff`.
- Add optional middleware:
  - `EnsureUserIsActive` for `status === active`.
  - Laravel `verified` middleware for email verification.
- Continue using Laravel's built-in `auth` and `guest` middleware.

## Authentication and Authorization Strategy

- Authentication uses Laravel's default `web` guard and Eloquent user provider.
- Authorization starts with role-based middleware because the app currently has clear operational roles.
- Normalize roles into a dedicated table to avoid repeated string values in `users`.
- Avoid hardcoding permissions directly in Blade views. Use helpers, policies, gates, or view conditionals backed by model methods.
- For the current role model:
  - `admin`: full system access and user management.
  - `staff`: donor records and in-kind inventory workflows.
  - `staff`: cash transactions and bank reconciliation workflows.
- Future fine-grained permissions can be added with policies or a `permissions` table if role checks become too coarse.

## Security Considerations

- Use POST requests with CSRF tokens for login, registration, logout, and password actions.
- Regenerate the session after login and registration login.
- Invalidate and regenerate CSRF token on logout.
- Use generic login errors.
- Throttle login and password reset requests.
- Use secure cookies in production through `SESSION_SECURE_COOKIE=true`.
- Keep `SESSION_HTTP_ONLY=true`.
- Use `SameSite=Lax` or stricter if deployment allows it.
- Do not cache authenticated pages or user-specific authentication state.
- Do not log passwords, password confirmation, remember tokens, reset tokens, or verification tokens.
- Validate email uniqueness at both validation and database levels.
- Consider account status checks for suspended or pending users.
- Protect admin-only user management routes separately.
- Avoid CDN Tailwind in production auth pages; use the existing Vite/Tailwind build pipeline when implementation begins.

## Session Management

Current project configuration uses `SESSION_DRIVER=database` by default, and the `sessions` table already exists.

Recommended behavior:

- Keep database sessions for the current app unless traffic grows enough to justify Redis.
- Regenerate session ID on successful login.
- Invalidate session and regenerate token on logout.
- Use Laravel's intended redirect support for protected pages.
- Consider session lifetime based on the barangay office workflow. The current default is 120 minutes.
- Use remember-me only if longer-lived sessions are acceptable for office devices.

## Password Hashing

- Use Laravel's default hashing service.
- The current `User` model already casts `password` as `hashed`; this is recommended.
- If using the hashed cast, assign the plain password once and let Eloquent hash it.
- Do not call `Hash::make()` and also rely on the hashed cast for the same assignment.
- Configure hashing through Laravel config, not controller code.
- Use strong registration password rules with `Password::defaults()` configured in a service provider if shared across the app.

## Email Verification Recommendation

Email verification is recommended before granting access to operational donation data.

Implementation direction:

- Make `User` implement `MustVerifyEmail`.
- Use Laravel's `Registered` event.
- Add verification routes and `verified` middleware.
- Redirect newly registered users to a verification notice page.
- Keep login allowed for unverified users only enough to resend verification email; block protected application routes with `verified`.

If the barangay workflow requires staff approval, combine email verification with admin approval instead of treating verification as authorization.

## Password Reset Recommendation

Password reset is recommended for production.

Current schema already includes `password_reset_tokens`.

Implementation direction:

- Add forgot password page.
- Add reset link request endpoint.
- Add reset password page.
- Use Laravel's password broker.
- Throttle reset link requests.
- Never reveal whether an email exists.
- Invalidate other sessions after password reset if the app later tracks active sessions by user.

## Performance Considerations

- Authentication queries should rely on the unique index on `users.email`.
- Add an index on `users.role_id` and `users.status` for authorization-heavy queries.
- Eager-load `role` only where role display is needed.
- Avoid loading large user lists on auth pages.
- Keep login and registration pages lightweight.
- Use Vite-built assets in production rather than runtime CDN dependencies.
- Do not add unnecessary external scripts to auth pages.

## Caching Strategy

- Never cache login responses, registration responses, password reset pages, verification links, session data, or user-specific authenticated pages.
- It is safe to cache non-sensitive role metadata if loaded frequently, but invalidate cache when roles change.
- Prefer config or enum constants for stable role names instead of runtime cache.
- Use Laravel config cache in production.
- Use route cache only after route definitions are stable and closure routes are avoided.
- Do not store passwords, reset tokens, verification hashes, remember tokens, or session payloads in application cache.

## Reusable Components

Recommended Blade structure:

- `resources/views/layouts/guest.blade.php`
- `resources/views/components/auth-card.blade.php`
- `resources/views/components/form/input.blade.php`
- `resources/views/components/form/label.blade.php`
- `resources/views/components/form/error.blade.php`
- `resources/views/components/form/checkbox.blade.php`
- `resources/views/components/button.blade.php`
- `resources/views/components/alert.blade.php`

Implementation goals:

- One guest layout for login, sign up, forgot password, reset password, and email verification screens.
- Shared field components with consistent labels, errors, old values, and accessibility attributes.
- Shared alert component for success, warning, and error states.
- Reuse route names instead of literal URLs in views.

## Implementation Plan

1. Add authentication route names and decide whether to keep or refactor the existing `AuthController`.
2. Create reusable guest layout and form components.
3. Create `LoginRequest` and move login validation/throttling out of the controller.
4. Refactor login flow to use named routes, intended redirects, session regeneration, and generic errors.
5. Create roles migration and `Role` model.
6. Add user profile columns and `role_id`, then backfill from existing `users.role`.
7. Update `User` model with relationships, casts, fillable fields, and name accessor compatibility.
8. Update `EnsureRole` middleware to use normalized roles.
9. Create `RegisterRequest` and registration controller.
10. Create Sign Up Blade view based on the approved visual direction.
11. Add email verification routes and middleware if approved.
12. Add password reset routes, controllers, and views.
13. Update seeders to create roles first, then users with `role_id`; remove hardcoded production credentials.
14. Update feature tests for login, logout, registration, validation, role restrictions, email verification, and password reset.
15. Run migrations and tests locally.
16. After production verification, remove the legacy `users.role` column in a separate migration.

## Approval Notes

Before implementation begins, confirm:

- Whether public registration should create active accounts immediately, require email verification, require admin approval, or both verification and approval.
- Which role new registrants should receive by default.
- Whether "Remember Me" should be enabled.
- Whether the current simple Tailwind style should remain or be upgraded into a fuller shared layout during auth implementation.
