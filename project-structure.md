# Barangay Donation Platform - Project Structure

## Root files

- `.editorconfig` - Code style and formatting settings.
- `.env` - Environment configuration (not committed).
- `.env.example` - Example environment file for setup.
- `.gitattributes` - Git attribute settings.
- `.gitignore` - Ignored files and directories.
- `artisan` - Laravel CLI entry point.
- `composer.json` - PHP dependency and package configuration.
- `package.json` - JavaScript dependency and package configuration.
- `phpunit.xml` - PHPUnit configuration for tests.
- `postcss.config.js` - PostCSS configuration.
- `README.md` - Project overview and usage instructions.
- `tailwind.config.js` - Tailwind CSS configuration.
- `vite.config.js` - Vite build tool configuration.

## Directories

### `app/`
Contains application source code.

- `Http/Controllers/` - HTTP controllers handling requests.
- `Http/Middleware/` - Middleware classes.
- `Models/` - Eloquent models representing data entities:
  - `BankReconciliation.php`
  - `CashTransaction.php`
  - `Donation.php`
  - `Donor.php`
  - `InventoryItem.php`
  - `User.php`
- `Providers/` - Service providers such as `AppServiceProvider.php`.

### `bootstrap/`
Bootstrapping configuration for the Laravel application.

- `app.php`
- `providers.php`
- `cache/` - Cached configuration and services.

### `config/`
Application configuration files.

- `app.php`
- `auth.php`
- `cache.php`
- `database.php`
- `filesystems.php`
- `logging.php`
- `mail.php`
- `queue.php`
- `services.php`
- `session.php`

### `database/`
Database migrations, seeders, and factories.

- `factories/` - Model factory definitions.
- `migrations/` - Migration files for database schema.
- `seeders/` - Database seeders.

### `public/`
Public web root files.

- `.htaccess`
- `index.php`
- `robots.txt`

### `resources/`
Frontend assets and Blade views.

- `css/app.css`
- `js/app.js`
- `js/bootstrap.js`
- `views/` - Blade templates, including:
  - `bank-reconciliation.blade.php`
  - `dashboard.blade.php`
  - `role-workflows.blade.php`
  - `welcome.blade.php`
  - `auth/` directory for authentication views.

### `routes/`
Application route definitions.

- `console.php`
- `web.php`

### `storage/`
Storage for application data and compiled views.

- `app/` - Application storage, includes `private/` and `public/`.
- `framework/` - Framework cache, sessions, testing, and views.
- `logs/` - Application log files.

### `tests/`
Automated tests.

- `TestCase.php`
- `Feature/` - Feature tests such as `DonationManagementTest.php`.
- `Unit/` - Unit tests.

### `vendor/`
Composer-managed dependencies.

- Autoload and third-party libraries.
