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
4. Run database migrations
   ```bash
   php artisan migrate
   ```
5. Start the development servers
   ```bash
   php artisan serve
   npm run dev
   ```

## License

This project is open-source and available under the MIT license.
