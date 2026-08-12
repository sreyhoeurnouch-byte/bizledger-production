# Production deployment

BizLedger is a company-scoped accounting and inventory application. Deploy it behind HTTPS with a managed MySQL 8 or MariaDB 10.6+ database, automated backups, and an application user that has only the database permissions it needs.

## Required environment settings

Set these values in the production environment; never commit a production `.env` file.

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ledger.example.com
LOG_LEVEL=warning
SESSION_DRIVER=database
SESSION_ENCRYPT=true
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Generate a unique application key with `php artisan key:generate --force`. Set a unique, long database password and configure a real mail provider before enabling password-reset or notification workflows.

## Release sequence

```powershell
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Run a long-lived queue worker through the platform supervisor if queued jobs are enabled:

```powershell
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

Configure a scheduled task to execute `php artisan schedule:run` every minute when scheduled work is added. Enable TLS at the reverse proxy, set HTTP-to-HTTPS redirects, and restrict database network access to the application host.

## Operational controls

- Back up the database at least daily; test restore procedures before go-live.
- Monitor failed jobs, application errors, disk usage, and backup failures.
- Keep at least one `owner` account under a controlled company email address.
- Give day-to-day users the lowest suitable role. `viewer` accounts cannot submit ledger writes.
- Audit events are recorded for item, contact, purchase-order, stock-movement, and account creation or change.

## Scope before financial go-live

This release provides inventory, contact, purchase-order, chart-of-account, and stock-movement foundations. It is not a jurisdiction-complete statutory accounting system yet. Before relying on it for books of record, define and implement the required tax rules, journal posting, approvals, period close/reopen controls, document numbering, reconciliation, exports, data-retention policy, and external accounting review for the operating jurisdiction.
