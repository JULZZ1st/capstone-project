# Barangay Donation Platform

A web-based donation management system for barangay operations. It helps manage donor information, donation records, inventory, cash transactions, and bank reconciliation in a centralized platform.

## Features

- Donor and donation record management
- Inventory tracking for donated items
- Cash transaction logging
- Bank reconciliation workflow
- Role-based access and workflow management

## Tech Stack

- PHP 8+
- Laravel 11
- Blade templating
- MySQL database
- Tailwind CSS
- Vite
- Composer
- npm

## Getting Started

1. Install PHP dependencies
   ```bash
   composer install
   ```
2. Install frontend dependencies
   ```bash
   npm install
   ```
3. Configure your environment file
   ```bash
   cp .env.example .env
   ```
   Configure `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` with the
   centralized MySQL server used by the barangay deployment. All laptops should
   access the same Laravel application and use that same database; do not run a
   separate SQLite or laptop-local MySQL database for operational data.
4. Run database migrations
   ```bash
   php artisan migrate
   ```
5. Seed the bundled address reference data for donor and fund-request dropdowns
   (safe to rerun; existing locations are preserved):
   ~~~bash
   php artisan db:seed --class=PSGCSeeder
   ~~~
   For a new development database, php artisan db:seed also creates the default
   admin/staff accounts and loads these address options.
6. Start the development servers
   ```bash
   php artisan serve
   npm run dev
   ```

## After pulling changes

Install changed dependencies, apply pending migrations, and rebuild the frontend:
~~~bash
composer install
php artisan migrate
php artisan db:seed --class=PSGCSeeder
npm run build
~~~
Applying migrations keeps existing records and adds the tables and columns
required by the current code. Use migrate, rather than migrate:fresh, on a
database containing records you want to keep.

## License

This project is open-source and available under the MIT license.
