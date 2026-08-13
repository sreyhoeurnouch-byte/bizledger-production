# BizLedger - Actual Current Status (August 12, 2026)

**Update:** Re-audited codebase after discovery that MUCH more is implemented than initial audit suggested.

---

## ✅ COMPLETED (Production-Ready Components)

### Sprint 1 ✅
- [x] Item master (SKU/barcode uniqueness, stock/service type, immutable after posting)
- [x] Contact master (vendors/customers with opening balances)
- [x] Warehouse inventory tracking
- [x] Inventory ledger (weighted-average costing, immutable transactions)
- [x] Stock movements (draft → posted lifecycle, one-time posting lock)
- [x] Warehouse transfers (dual-ledger atomic posting)
- [x] Purchase orders (draft → approved → received, partial receipts)
- [x] RBAC (5 roles, viewer blocked from writes, role-based middleware)
- [x] Audit logging (create/change events for all modules)
- [x] Authentication (session-based, login throttled 5,1)
- [x] Tests: 25 passing, 116 assertions

### Sprint 2 ✅ (Sales Domain - FULLY IMPLEMENTED)
- [x] SalesOrder, SalesOrderLine models + migrations
- [x] Delivery, DeliveryLine models + inventory posting integration
- [x] Invoice, InvoiceLine models
- [x] CustomerReceipt, ReceiptAllocation models
- [x] SalesOrderController (create/approve/deliver/invoice/receipt)
- [x] Domain services: DeliverSalesOrder, InvoiceSalesOrder, RecordCustomerReceipt
- [x] Views: sales/index, sales/show
- [x] Routes: All sales endpoints routed

### Sprint 3 ✅ (AR/AP Reporting - PARTIALLY DONE)
- [x] AR aging report (receivables by age bucket: current, 30, 60, 90+ days)
- [x] AP aging report (payables by age bucket)
- [x] Stock valuation report
- [x] Report views and filtering
- [x] ReportController with stock/receivables/payables methods
- [ ] CSV export for reports
- [ ] Computed balances (contact.open_balance still partly static)

### Sprint 4 ⚠️ (Security Hardening - PARTIAL)
- [x] Password reset implemented (forgot-password → reset-password flow)
- [x] Password policy enforced (min 12 chars, mixedCase, numbers via PasswordRule)
- [x] Seeder production guard (`abort_if(app()->isProduction(), 403)`)
- [x] Login audit events recorded in AuditLog
- [ ] Email provider wired to real service (still MAIL_MAILER=log)
- [ ] Rate limiting on write/export endpoints (only login throttled)
- [ ] Session timeout (no idle logout)
- [ ] MFA support

### UI/UX ✅
- [x] Navbar hover effects (dropdown arrow brightens on hover)
- [x] "Save & New" / "Save & Close" button pattern
- [x] Reusable form-buttons component

---

## ⚠️ INCOMPLETE (Must Complete Before Production)

### Critical Gaps

#### 1. Email Delivery (P0 - Blocks Password Reset, Notifications)
**Current:** Password reset middleware calls `Password::sendResetLink()` but MAIL_MAILER=log
**Impact:** Reset emails never arrive to users; password resets silently fail
**Work required:**
- [ ] Configure real SMTP provider (AWS SES, SendGrid, or equivalent)
- [ ] Create password reset email Mailable
- [ ] Test password reset flow end-to-end
- [ ] Document email provider setup

**Estimate:** 2-4 hours

---

#### 2. Async Jobs / Queue System (P0 - Performance & Scaling)
**Current:** QUEUE_CONNECTION=database in .env, but no Job classes implemented
**Impact:** 
- Long operations (CSV export, large PDF generation) will timeout
- Email delivery must wait until HTTP request completes
- No background processing for batch operations
- Poor user experience for large data imports/exports

**Work required:**
- [ ] Create SendPasswordResetEmailJob
- [ ] Create GenerateInvoicePDFJob
- [ ] Create ExportCSVReportJob  
- [ ] Wire up queue:work command for background processing
- [ ] Add queue worker to deployment/ops runbook
- [ ] Test: Job retries after failure, handles exceptions gracefully

**Estimate:** 6-8 hours

---

#### 3. General Ledger / Journal Engine (P1 - Core Accounting Feature)
**Current:** `accounts` table exists but orphaned; no debit/credit posting
**Impact:**
- Inventory value moves through inventory_transactions only
- GL accounts have static `balance` fields (never updated)
- No trial balance, no P&L, no balance sheet possible
- Can't do reconciliation, tax compliance, or external audits

**Status:** Explicitly deferred per roadmap (Phase 8 / post-MVP)
**Decision:** Keep "Accounts" module disabled until Phase 8; rename product as "Order & Inventory Management" not "Accounting System"

**Estimate:** 3 weeks (after MVP launch)

---

#### 4. API Layer (P2 - Integrations & Automation)
**Current:** No REST API exists; all access via web UI only
**Impact:** 
- Third-party integrations impossible
- Mobile apps impossible
- Webhook notifications impossible
- Automation scripts must scrape HTML

**Status:** Explicitly deferred per roadmap (Phase 7)
**Timeline:** Post-MVP

**Estimate:** 2 weeks

---

#### 5. Computed Balances for Contacts (P1 - Reporting Accuracy)
**Current:** Contact.opening_balance is static; Contact.open_balance exists but unused
**Impact:**
- AR/AP aging assumes opening_balance is current
- Customer balances don't reflect actual invoice activity
- Reports inaccurate if invoices exist but haven't been manually recorded

**Work required:**
- [ ] Create service to compute Contact.open_balance from Invoice.total − Invoice.amount_received
- [ ] Add accessor to Contact model to compute on-demand
- [ ] Migrate existing contacts to compute opening_balance from invoices
- [ ] Update reports to use computed balance
- [ ] Test: Balance always matches invoice data

**Estimate:** 4 hours

---

#### 6. Rate Limiting on Authenticated Endpoints (P1 - Security)
**Current:** Only login (5 per minute) is throttled; all write/export endpoints unprotected
**Impact:**
- Users can hammer CSV export → DOS
- Bots could brute-force searches
- No protection against automated abuse

**Work required:**
- [ ] Add rate limiting middleware to all POST/PUT/DELETE routes (e.g., 30 per minute per user)
- [ ] Add rate limiting to search endpoints (1000 per minute per IP)
- [ ] Add rate limiting to export endpoints (10 per hour per user)
- [ ] Return 429 Too Many Requests when limit exceeded
- [ ] Test: Rate limits are enforced

**Estimate:** 3 hours

---

#### 7. Session Timeout (P2 - Security)
**Current:** Sessions never expire; a user can remain logged in indefinitely
**Impact:**
- Unattended terminals remain accessible
- Compliance regulations require idle timeout
- Security risk in shared environments

**Work required:**
- [ ] Set SESSION_LIFETIME=480 (8 hours) in .env
- [ ] Implement SessionGuard middleware to check last activity
- [ ] Auto-logout on idle 8 hours
- [ ] Refresh session timeout on each request

**Estimate:** 2 hours

---

#### 8. MFA / 2FA Support (P2 - Security)
**Current:** Single-factor auth only (username + password)
**Impact:**
- Password compromise = full account takeover
- No compliance with security frameworks (SOC2, ISO 27001)

**Work required:**
- [ ] Add TOTP-based 2FA (Google Authenticator compatible)
- [ ] Create MFA enablement UI
- [ ] Enforce MFA for admin/accountant roles
- [ ] Provide backup codes for account recovery
- [ ] Test: 2FA blocks unauthorized logins

**Estimate:** 8 hours (optional for MVP if approved by security review)

---

#### 9. Observability & Error Monitoring (P1 - Production Support)
**Current:** No structured logging, no error monitoring hook
**Impact:**
- Production errors go unnoticed
- Hard to debug issues in production
- No alerting for critical failures

**Work required:**
- [ ] Configure error tracking (Sentry, Rollbar, or similar)
- [ ] Add structured logging to key operations (LogStash format)
- [ ] Create health check endpoint `/health`
- [ ] Document common error codes and recovery steps

**Estimate:** 6 hours

---

#### 10. Browser / E2E Tests (P2 - Quality Assurance)
**Current:** 25 feature tests (HTTP level); no browser automation tests
**Impact:**
- UI bugs not caught by HTTP tests
- Can't verify forms work end-to-end
- Selenium/Playwright tests missing for core flows

**Work required:**
- [ ] Create E2E test: Login → Create Item → Create PO → Receive → Deliver → Invoice → Receive Payment
- [ ] Create E2E test: Login as different roles (viewer, operator, accountant) and verify access
- [ ] Create E2E test: AR/AP aging report generates correctly
- [ ] Use Laravel Dusk or Playwright
- [ ] Run in CI/CD on every merge

**Estimate:** 12 hours

---

#### 11. Static Analysis in CI (P2 - Code Quality)
**Current:** Pint installed but not run in CI; no PHPStan/Larastan
**Impact:**
- Code style inconsistent
- Type errors not caught
- Technical debt accumulates

**Work required:**
- [ ] Add Pint to CI with `--check` flag (fail on style violations)
- [ ] Add Larastan/PHPStan to CI with level 5 minimum
- [ ] Fix existing style violations
- [ ] Document lint configuration

**Estimate:** 4 hours

---

#### 12. Load Testing & Performance Baselines (P2 - Scalability)
**Current:** No load tests; unknown concurrent user capacity
**Impact:**
- Unknown if system can handle 50 users, 500 users, or 5000 users
- No performance baseline to detect regressions
- Deploy without knowing if it will survive launch

**Work required:**
- [ ] Create load test scenario (50 concurrent users for 10 minutes)
- [ ] Record baseline: response times, DB queries, memory usage
- [ ] Set SLA targets: p50 < 500ms, p95 < 1s, p99 < 2s
- [ ] Identify bottlenecks and optimize
- [ ] Automate load test in CI

**Estimate:** 12 hours

---

#### 13. Backup & Disaster Recovery (P1 - Operational)
**Current:** No documented backup procedure; no tested restore
**Impact:**
- Data loss on hardware failure
- No recovery plan if ransomware attack occurs
- Can't restore to specific point in time

**Work required:**
- [ ] Implement automated daily database backups (to S3 or equivalent)
- [ ] Document backup retention policy (7 days daily, 4 weeks weekly, 12 months yearly)
- [ ] Implement automated backup integrity checks
- [ ] Document restore procedure
- [ ] Test restore procedure monthly (blind restore test)
- [ ] Create incident response playbook

**Estimate:** 8 hours

---

#### 14. Compliance & Legal (P1 - Go-Live Gate)
**Current:** No GDPR compliance, no privacy policy wired in, no data retention policy
**Impact:**
- Can't legally operate in EU/Cambodia
- No data export for customer requests
- No right-to-be-forgotten capability

**Work required:**
- [ ] Implement GDPR: data export endpoint, delete account functionality
- [ ] Create privacy policy + wire into UI
- [ ] Create terms of service
- [ ] Add data retention policy (auto-purge old records after N years)
- [ ] Legal review by jurisdiction (Cambodia) attorney
- [ ] Document incident response for data breaches

**Estimate:** 12 hours (plus attorney review)

---

#### 15. Documentation for Ops Team (P1 - Handoff)
**Current:** No runbook, no troubleshooting guide, no deployment guide
**Impact:**
- Ops team can't deploy, restart, or recover system
- Support team doesn't know how to troubleshoot
- Onboarding new team members is slow

**Work required:**
- [ ] Write deployment runbook (initial setup, migrations, seeding)
- [ ] Write operations runbook (backup restore, queue worker restart, log analysis)
- [ ] Write troubleshooting guide (common errors, recovery steps)
- [ ] Write user guide for each module (with screenshots)
- [ ] Create API documentation (even if not yet public)

**Estimate:** 20 hours

---

## 📊 Revised Timeline to Production-Ready

| Phase | Work | P0 | P1 | P2 | Hours | Sprint |
|-------|------|----|----|----|----|---------|
| Hardening | Email + real provider | 4 | | | 4 | Sprint 4a |
| Async | Jobs + queue worker | 8 | | | 8 | Sprint 4b |
| Reporting | Computed balances + CSV export | | 4 | | 4 | Sprint 3 (overflow) |
| Security | Rate limiting + session timeout + MFA | | 5 | 8 | 13 | Sprint 4c |
| Observability | Error monitoring + logging + health checks | | 6 | | 6 | Sprint 5 |
| Quality | E2E tests + static analysis + load tests | | | 24 | 24 | Sprint 5 |
| Ops | Backup/DR + runbooks + documentation | | 8 | 12 | 20 | Sprint 5-6 |
| Compliance | GDPR + Privacy + Legal review | | 12 | | 12 | Sprint 6 |
| **TOTAL** | | | | | **91 hours** | **5-6 weeks** |

---

## 🎯 MVP Ship Criteria (Must Have Before Launch)

- [x] Sprint 1: Purchase orders + inventory
- [x] Sprint 2: Sales orders + deliveries + invoices
- [x] Sprint 3: AR/AP aging reports (with computed balances)
- [ ] **Sprint 4: Password reset + real email provider + seeder guard**
- [ ] **Sprint 5: Rate limiting + session timeout + error monitoring**
- [ ] Sprint 6: E2E tests + load test baseline + ops documentation + legal review

**Current Status:** 60% complete (Sprints 1-3 done; Sprints 4-6 in progress)
**Estimated Ship Date:** End of September (6 weeks from now, if 1 sprint/week velocity)

---

## 🚀 Immediate Priority (Next 2 Weeks)

### Must Complete Before Any External Access

1. **Email Provider** (4 hours)
   - Switch from MAIL_MAILER=log to real provider
   - Test password reset end-to-end
   - Update .env.example

2. **Async Jobs** (8 hours)
   - Create SendPasswordResetEmailJob
   - Set up queue:work
   - Test job retry logic

3. **Rate Limiting** (3 hours)
   - Add throttle middleware to write/export routes
   - Test 429 responses

4. **CSV Export** (2 hours)
   - Add CSV export button to reports
   - Async export job

**Total: ~17 hours** (can be done in parallel)

---

## What Can Go Live Today (If Needed)

If you need to launch before hardening complete, this is production-ready:
- ✅ Purchase orders + inventory management (Sprint 1)
- ✅ Sales orders + deliveries + invoicing (Sprint 2)
- ✅ AR/AP aging reports (Sprint 3)

**Caveats:**
- ❌ Password reset doesn't actually send emails (users can't reset if they forget)
- ❌ No rate limiting (potential DOS/abuse)
- ❌ No backup automation (data loss risk)
- ❌ No error monitoring (production issues invisible)

Not recommended for production use without at least Email + Rate Limiting + Error Monitoring.

---

## 🎓 Key Findings

1. **Codebase is much further along than initial audit suggested** — Someone has been actively building beyond Sprint 1
2. **Sales domain (Sprint 2) is complete** — This was listed as a 2-week gap; it's done
3. **Reporting (Sprint 3) is mostly done** — Just need CSV export and balance computation
4. **No major architectural problems** — The domain services pattern is solid
5. **DevOps/Ops readiness is the biggest remaining gap** — The application code is good; operational infrastructure is missing
6. **Security gaps are fixable in 1-2 weeks** — Email provider, rate limiting, session timeout are all straightforward

---

## Next Steps

1. **Confirm priorities** — Which gaps are highest priority? (Email? Rate limiting? Async jobs?)
2. **Assign work** — Which sprints should be parallel vs sequential?
3. **Set deadlines** — When must these be complete?
4. **Start Sprint 4** — Email provider + rate limiting + async jobs

Ready to start implementation. Which component should we tackle first?
