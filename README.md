# BizLedger — Laravel + Bootstrap Edition

BizLedger is an accounting and inventory foundation built with Laravel 13, Blade, MySQL, Bootstrap 5.3.8, and plain JavaScript.

There is **no Livewire, React, Vue, Inertia, Alpine, Tailwind, jQuery, Vite, or npm build step**. Bootstrap CSS and `bootstrap.bundle.min.js` are stored locally in `public/assets`.

## Included

- Laravel session authentication, CSRF protection, validation, and hashed passwords
- Company-scoped queries and UUID primary keys
- Dashboard, vendor/customer centers, item catalog, purchase orders, stock movements, chart of accounts, and stock valuation
- Transactional posted stock receipts/issues with negative-stock prevention
- Bootstrap Blade cards, forms, modals, pagination, tables, dropdowns, and alerts
- MySQL migrations, repeatable demo seeder, and feature tests

## Requirements

- PHP 8.3+ with `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, and `curl`
- Composer 2
- MySQL 8 or MariaDB 10.6+

## Windows installation

Extract the ZIP and run inside the project folder:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Create a blank MySQL database named `bizledger` with phpMyAdmin. Confirm `.env` contains:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bizledger
DB_USERNAME=root
DB_PASSWORD=
```

Then run:

```powershell
php artisan migrate --seed
php artisan serve
```

Open `http://127.0.0.1:8000`.

```text
Email: admin@bizledger.local
Password: ChangeMe123!
```

You may configure `.env` and double-click `START_WINDOWS.bat` instead. For the production release checklist, deployment commands, and financial-control scope, see [docs/PRODUCTION.md](docs/PRODUCTION.md).

The currently enforced item, inventory, procurement, access, and audit rules are documented in [docs/BUSINESS_RULES.md](docs/BUSINESS_RULES.md).

The planned production architecture, module sequence, quality gates, and release controls are in [docs/DEVELOPER_ROADMAP.md](docs/DEVELOPER_ROADMAP.md).
