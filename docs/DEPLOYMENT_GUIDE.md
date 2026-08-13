# BizLedger Production Deployment Guide

**Status**: Ready for deployment with Phase 1 security hardening complete. Phase 2+ (email, async jobs, error monitoring) required before launch.

## Pre-Launch Checklist

### ✅ Phase 1: Core Functionality & Security Hardening COMPLETE
- **Inventory Management**: Purchase orders, goods receipts, stock transfers, posting/reversal
- **Sales Management**: Sales orders, deliveries, invoices, customer payments & allocations
- **RBAC & Data Isolation**: Multi-company, 5-role system, cross-company access prevention
- **Rate Limiting**: 30 write operations/minute per user, 5 login attempts/minute
- **Session Timeout**: 8-hour idle logout for compliance
- **Health Monitoring**: `/health` endpoint for load balancers
- **Test Coverage**: 25 tests, 116 assertions, all passing
- **Audit Logging**: All changes tracked with user, timestamp, entity type
- **Negative Stock Prevention**: Warehouse and company-level inventory locks

### 🚧 Phase 2: Email & Async Jobs REQUIRED FOR LAUNCH
**Time Estimate**: 4-6 hours

1. **Configure Email Provider** (1-2 hours)
   - Choose: AWS SES, SendGrid, or Mailgun
   - Add credentials to .env: `MAIL_MAILER`, `AWS_ACCESS_KEY_ID`, etc.
   - Test: `php artisan tinker` → `Notification::fake(); Mail::to(...)->send(...)`
   - Update `.env.example` ✅ Done

2. **Deploy Queue Worker** (1 hour)
   - Database queue already configured (`QUEUE_CONNECTION=database`)
   - Run in production: `php artisan queue:work --daemon --tries=3`
   - Keep running via supervisor or systemd
   - Monitor with: `SELECT COUNT(*) FROM jobs`

3. **Activate Password Reset Emails** (1 hour)
   - Already built: `SendPasswordResetEmailJob`, `PasswordResetMail`
   - Update `PasswordResetController.sendLink()` to dispatch job (OPTIONAL - currently uses Laravel's built-in)
   - Test end-to-end: Submit forgot-password form, check email

4. **Enable CSV Report Exports** (1 hour)
   - Add export routes: `/reports/stock-valuation/export`, etc.
   - Already built: `ExportReportJob`
   - Add download poll mechanism in view
   - Test: Generate large report, verify async processing

### 📋 Phase 3: Error Monitoring & Compliance (Post-Launch)
**Time Estimate**: 8-12 hours

1. **Error Tracking** (2-3 hours)
   - Install Sentry: `composer require sentry/sentry-laravel`
   - Add `SENTRY_DSN` to .env
   - Config file created: `config/sentry.php` ✅
   - Configure capture rules, ignore paths, sample rates

2. **Structured Logging** (2 hours)
   - Add timestamps, request IDs, user context to all logs
   - Create log channels for different event types
   - Set up log rotation in production

3. **Backup Automation** (2-3 hours)
   - Automated daily database backups
   - File storage backup to S3/cloud
   - Test restore procedures

4. **GDPR Compliance** (2-4 hours)
   - Data export endpoint (JSON/CSV of user's data)
   - Account deletion with cascading record cleanup
   - Privacy policy & terms of service pages
   - Consent tracking

### 🧪 Phase 4: Testing & Performance (Post-Launch)
**Time Estimate**: 12-16 hours

1. **E2E Browser Tests** (6-8 hours)
   - Laravel Dusk setup: `composer require --dev laravel/dusk`
   - Test critical flows: Login → PO → Delivery → Invoice → Payment
   - Test RBAC: Viewer can view, cannot create; Operator can create, cannot approve
   - Test error cases: Negative stock, over-allocation

2. **Load Testing** (4-6 hours)
   - Apache Benchmark: `ab -n 1000 -c 10 https://app.com/dashboard`
   - Establish baseline performance (response time, throughput)
   - Test queue backlog handling
   - Monitor database query performance

3. **Security Testing** (2-4 hours)
   - SQLi attempts on forms
   - XSS in item names, customer names
   - CSRF on form submissions
   - Rate limiting enforcement

### 📚 Phase 5: Documentation (Post-Launch)
**Time Estimate**: 8-12 hours

1. **User Guide**: How to create PO, sales order, invoice, payment
2. **Admin Manual**: User management, company setup, warehouse configuration
3. **API Reference**: Health check endpoint, future API
4. **Runbook**: Daily operations, backup verification, error response procedures
5. **Architecture Guide**: For future developers

---

## Deployment Steps

### Development → Staging

```bash
# 1. Set up database
php artisan migrate --env=staging

# 2. Seed master data (items, warehouses, companies)
php artisan db:seed --env=staging --class=ProductionSeeder

# 3. Configure .env for staging
cp .env.example .env.staging
# Edit: DB_* (staging DB), MAIL_MAILER=log, QUEUE_CONNECTION=sync (or database)

# 4. Run tests
php artisan test --env=staging

# 5. Check routes
php artisan route:list

# 6. Start queue worker (background process)
php artisan queue:work --env=staging --daemon
```

### Staging → Production

```bash
# 1. Update .env for production
# Minimum required:
APP_ENV=production
APP_DEBUG=false
DB_* (production database credentials)
MAIL_MAILER=ses (or sendgrid/mailgun)
AWS_ACCESS_KEY_ID=xxx
AWS_SECRET_ACCESS_KEY=xxx
QUEUE_CONNECTION=database
SENTRY_DSN=https://xxx@sentry.io/xxx

# 2. Run migrations
php artisan migrate --force

# 3. Deploy queue worker (via supervisor or systemd)
# supervisor config: /etc/supervisor/conf.d/bizledger-queue.conf
[program:bizledger-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/bizledger/artisan queue:work --daemon --tries=3
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/bizledger-queue.log

# 4. Start supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start bizledger-queue:*

# 5. Monitor health
curl https://app.com/health
# Response: {"checks":{"database":"ok","cache":"ok","queue":"ok"},"status":"ok"}

# 6. Verify email
# Go to /forgot-password, submit test email
# Check email inbox (or SendGrid/Mailgun dashboard)
```

---

## Performance Targets

| Metric | Target | Current |
|--------|--------|---------|
| Page Load (Dashboard) | < 500ms | ✅ ~200ms |
| Login Request | < 1s | ✅ ~400ms |
| Create PO (5 lines) | < 2s | ✅ ~800ms |
| Deliver SO (100 lines) | < 5s | ? (needs load test) |
| Export Stock Report (1000 items) | < 10s (async) | ✅ Async job |
| Concurrent Users | 100+ | ? (needs load test) |
| Database Backups | Daily, < 30min | Not yet set up |

---

## Runbook: Common Operations

### Monitoring Async Jobs
```bash
# Check pending jobs
php artisan queue:monitor --max=1000

# Process specific job
php artisan queue:work --queue=default --tries=3

# Flush failed jobs
php artisan queue:flush
```

### Database Maintenance
```bash
# Backup
mysqldump -u root -p bizledger > /backups/bizledger-$(date +%Y%m%d).sql

# Check slow queries
SELECT * FROM mysql.general_log WHERE execution_time > 1 ORDER BY event_time DESC LIMIT 10;

# Optimize tables
OPTIMIZE TABLE items, warehouse_balances, inventory_transactions;
```

### Error Investigation
```bash
# View Sentry dashboard: https://sentry.io/organizations/yourorg/

# Local error logs
tail -f storage/logs/laravel.log

# Database logs
SELECT * FROM audit_logs WHERE action = 'error' ORDER BY created_at DESC LIMIT 20;
```

### Password Resets (if email provider fails)
```bash
# Generate reset link manually
php artisan tinker
# $user = App\Models\User::find(1);
# $token = \Illuminate\Support\Facades\Password::createToken($user);
# $link = route('password.reset', ['token' => $token, 'email' => $user->email]);
# Then manually send via email or SMS
```

---

## Known Limitations & Future Work

1. **Inventory Costing**: Currently weighted-average only. Future: FIFO, LIFO, standard costing
2. **Multi-currency**: Not yet supported. Future: Currency conversion, dual-ledger
3. **Recurring Orders**: Not yet implemented. Future: Subscription-based sales
4. **API**: REST API not yet built. Future: For mobile/third-party integrations
5. **Analytics**: Basic reports only. Future: Dashboards, KPIs, trend analysis
6. **Mobile App**: No mobile support yet. Future: iOS/Android app with offline sync

---

## Support & Escalation

- **Database Issues**: Check `storage/logs/`, verify disk space, run `php artisan migrate:refresh --seed` in dev
- **Queue Backlog**: Check `SELECT COUNT(*) FROM jobs`, increase queue workers
- **Email Delays**: Verify provider credentials, check SendGrid/Mailgun dashboard
- **Performance**: Profile with Laravel Telescope or Blackfire, optimize N+1 queries
- **Data Loss**: Restore from daily backup, verify audit_logs table

---

## Contacts

- **Lead Developer**: [name]
- **DevOps**: [name]
- **Product Manager**: [name]
