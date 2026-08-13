# BizLedger Production Readiness Audit

**Status:** Sprint 1 ✅ Complete | MVP Planning Phase  
**Current Test Coverage:** 25 tests passing (116 assertions)  
**Target:** Production-ready by end of Sprint 6

---

## 🚨 Critical Gaps (Must Fix Before Production)

### 1. **Security & Authentication**
- [ ] **Password reset** — Email not wired to real provider (MAIL_MAILER=log in .env.example)
- [ ] **Demo credentials leaked** — admin@bizledger.local / ChangeMe123! not guarded against production env
- [ ] **No MFA** — single-factor auth only
- [ ] **No rate limiting** — only login endpoint throttled; write/export endpoints exposed
- [ ] **No session timeout** — sessions valid indefinitely
- [ ] **No login audit trail** — login events not recorded for compliance
- [ ] **No password complexity rules** — any string accepted as password
- [ ] **No IP whitelisting** — admin access unrestricted
- [ ] **No CSRF protection verification** — assuming Laravel defaults apply

**Priority:** P0 - **Must implement in Sprint 4 before any external access**

---

### 2. **Data Integrity & Business Logic**
- [ ] **No journal engine** — GL doesn't exist; chart_of_accounts table is orphaned
  - Creating PO doesn't post to AP
  - Receiving goods doesn't post to Inventory GL
  - No trial balance / balance sheet possible
  - **Workaround for MVP:** Keep "Accounts" module disabled; document as Phase D
  
- [ ] **No sales flow** — can buy but not sell inventory
  - Missing: SalesOrder, SalesOrderLine, Delivery, DeliveryLine, Invoice, InvoiceLine, CustomerReceipt, PaymentAllocation
  - **Impact:** Can't demonstrate full order-to-cash cycle to customers
  - **Timeline:** Sprint 2 (2 weeks) to ship minimal sales

- [ ] **No negative-stock prevention at company level** — only warehouse-level check
  - Transfer from WH1 to WH2 succeeds even if company total goes negative
  - **Fix:** Add company-level check in PostStockMovement service

- [ ] **No reversal audit trail** — reversed document has no link back to original
  - Can't trace which movement reversed which
  - **Fix:** Add source_document_id and reversal_reason fields

- [ ] **No partial reversals** — can only reverse entire document
  - User can't undo a single line; must undo entire PO
  - **Decision:** Accept for MVP; document as known limitation

- [ ] **Inventory cost method not configurable** — hardcoded weighted-average
  - Only one costing method supported
  - **Decision:** Accept for MVP; note in Release notes

- [ ] **No reorder point enforcement** — low-stock warning exists but no auto-PO
  - **Decision:** Accept for MVP; defer to Phase 3

**Priority:** P0 (sales) / P1 (journal) — Sales required for MVP; journal deferred to Phase D

---

### 3. **User Experience & Data Entry**
- [ ] **No bulk import for contacts** — items have CSV import but contacts/vendors don't
- [ ] **No bulk export with formatting** — CSV only, no Excel/PDF options
- [ ] **No draft saving** — all forms submit immediately; no "Save as draft" flow
- [ ] **No concurrent edit detection** — two users can overwrite each other's changes
- [ ] **No undo/redo** — all changes immediate and irreversible (by design)
- [ ] **No search across entity relationships** — can't search "all POs for vendor X" from item screen
- [ ] **No quick-create from related screens** — creating item requires full form; no inline vendor creation
- [ ] **Limited column selection** — table views hardcoded; no "choose columns" export
- [ ] **No saved filters** — searches not persisted; users rebuild filters each session

**Priority:** P2 — Nice-to-have; defer to Phase 3

---

### 4. **Operational Readiness**
- [ ] **No async jobs** — QUEUE_CONNECTION=database configured but unused
  - Nothing happens in background
  - Long-running imports/exports will timeout
  - Email delivery synchronous (will fail if mail provider down)
  
- [ ] **No file storage integration** — AWS_* vars in .env but never used
  - Invoice PDFs can't be generated/stored
  - Attachments not supported
  
- [ ] **No backup strategy** — database backups not automated
  - No documented restore procedure
  - No tested disaster recovery
  
- [ ] **No audit log UI** — AuditLog table exists but no viewer in app
  - Compliance teams can't inspect change history
  
- [ ] **No email notification** — no invoice-sent emails, PO confirmations, etc.
  
- [ ] **No two-factor approval** — PO approval is single-user; no dual-control
  
- [ ] **No API rate limiting** — future API endpoints will be unprotected

**Priority:** P1 — Required for production support

---

### 5. **Testing & Quality**
- [ ] **Narrow test coverage** — 25 tests, all feature-level
  - No unit tests isolating domain logic
  - No ContactController tests
  - No AccountController tests
  - No ReportController tests
  - No stock valuation report tests
  - No concurrent-posting race condition tests
  
- [ ] **No E2E tests** — no browser automation testing core workflows
  
- [ ] **No static analysis** — no PHPStan/Larastan in CI
  
- [ ] **No code style enforcement** — Pint installed but not run in CI
  
- [ ] **No performance baselines** — no load testing; unknown how many concurrent users supported
  
- [ ] **No database schema validation** — no automated checks for migration correctness

**Priority:** P2 — Add to CI in Sprint 5

---

### 6. **Compliance & Documentation**
- [ ] **No GDPR compliance** — no data export, no right-to-be-forgotten
- [ ] **No audit logging for compliance** — AuditLog exists but not comprehensive
- [ ] **No SOC2 readiness** — no access control matrix, no breach notification procedure
- [ ] **No terms of service** — no user acceptance flow
- [ ] **No privacy policy wired in** — no GDPR notice on signup
- [ ] **No data retention policy** — no automated purging of old records
- [ ] **No incident response plan** — what to do if hacked?

**Priority:** P2 — Required before commercial launch; defer to Sprint 6

---

## 📋 MVP Must-Have Checklist

### Sprint 2: Sales Domain (2 weeks)
- [ ] SalesOrder, SalesOrderLine models + migrations
- [ ] Delivery, DeliveryLine models + migrations (reuse PostStockMovement for posting)
- [ ] Invoice, InvoiceLine models + migrations
- [ ] CustomerReceipt, PaymentAllocation models + migrations
- [ ] Feature tests: sales order → delivery → invoice → receipt workflow
- [ ] Feature tests: invoice cannot be allocated more than total
- [ ] Feature tests: invoice total is server-calculated
- [ ] Feature tests: inventory only decreases on posted delivery
- [ ] Feature tests: cross-company isolation holds for sales
- [ ] Controller tests: SalesOrderController, DeliveryController, InvoiceController, CustomerReceiptController
- [ ] UI: Sales order list/create/edit/show views
- [ ] UI: Delivery list/create/show views
- [ ] UI: Invoice list/show views
- [ ] UI: Customer receipt allocation UI

### Sprint 3: AR/AP Balances & Reporting (1.5 weeks)
- [ ] Migrate Contact.opening_balance → computed balance from invoices/payments
- [ ] Feature test: Contact balance always matches invoice − payment sum
- [ ] AR aging report: invoices by age bucket (current, 30+, 60+, 90+)
- [ ] AP aging report: vendor bills by age bucket
- [ ] Test: aging reports match hand-calculated totals
- [ ] UI: Report filter by date range, vendor, customer
- [ ] UI: Export reports to CSV

### Sprint 4: Production Hardening (2 weeks)
- [ ] Password reset: email workflow + token validation
- [ ] Wire MAIL_MAILER to real provider (AWS SES or equivalent)
- [ ] Seeder guard: `abort_if(app()->isProduction())` before demo data
- [ ] Rate limiting: 30 POST/PUT/DELETE per minute per authenticated user
- [ ] Rate limiting: 1000 GET per minute per IP
- [ ] Login audit: record user, IP, timestamp, success/failure
- [ ] Session timeout: 8 hours idle = auto logout
- [ ] Password policy: minimum 12 chars, uppercase, lowercase, number, symbol
- [ ] MFA (optional): TOTP-based 2FA on admin/accountant roles
- [ ] Feature tests: password reset flow works end-to-end
- [ ] Feature tests: seeder aborts in production env
- [ ] Feature tests: rate limit blocks 31st request

### Sprint 5: Observability & Async (1.5 weeks)
- [ ] Structured logging: JSON format with request ID, user ID, action, resource
- [ ] Error monitoring hook: Sentry or equivalent
- [ ] Async job: Invoice PDF generation + email delivery
- [ ] Async job: CSV export for large reports
- [ ] Queue worker: Laravel queue:work command runnable
- [ ] Test: Job fails gracefully with retry logic
- [ ] Staging environment: production-like database + secrets management
- [ ] Backup/restore: documented and tested procedure
- [ ] Health check endpoint: /health returns 200 if DB + cache up
- [ ] Smoke tests: login → create item → create PO → receive → run report

### Sprint 6: Release Gate (1 week)
- [ ] PHPStan + Larastan static analysis in CI (must pass)
- [ ] Pint style enforcement in CI (must pass)
- [ ] Browser test (Dusk or Playwright) for order-to-cash loop
- [ ] External accountant review of GL/AR/AP logic for Cambodia tax rules
- [ ] Security audit: OWASP Top 10 checklist
- [ ] Load test: 100 concurrent users for 10 minutes (SLA: p99 < 2s)
- [ ] Documentation: runbook for ops team
- [ ] Documentation: user guide for each module
- [ ] Documentation: API (even if not public) for future integrations
- [ ] Tag release: v1.0.0
- [ ] Changelog: what's new, what's deferred, known limitations

---

## 🔄 Post-MVP Roadmap (Phases 7-10)

**Phase 7: API & Integrations** (not in MVP)
- [ ] REST API for all resources
- [ ] OAuth2 support for third-party apps
- [ ] Webhook support for PO notifications
- [ ] Integration with accounting providers (Xero API)

**Phase 8: Journal Engine** (not in MVP)
- [ ] General ledger posting engine
- [ ] Chart of accounts integration
- [ ] Trial balance & financial statements
- [ ] Tax configuration by jurisdiction

**Phase 9: Advanced Features** (not in MVP)
- [ ] Multi-currency support
- [ ] Tax calculation (sales tax, VAT)
- [ ] Banking reconciliation
- [ ] Fixed asset management

**Phase 10: Scale & Compliance** (not in MVP)
- [ ] Multi-tenant data isolation
- [ ] Advanced RBAC (rule-based)
- [ ] PCI compliance for payment processing
- [ ] ISO 27001 readiness

---

## 🎯 Timeline to Production

| Sprint | Deliverable | Risk | Owner |
|--------|-------------|------|-------|
| 1 ✅ | Item/contact/PO/inventory | Low | Backend |
| 2 | Sales domain | Medium | Backend (1.5 weeks) + QA (1 week) |
| 3 | AR/AP aging | Low | Backend (1 week) + Frontend (0.5 weeks) |
| 4 | Security hardening | High | Backend (1.5 weeks); security review (0.5 weeks) |
| 5 | Observability & async | Medium | Backend (1.5 weeks); infra review (0.5 weeks) |
| 6 | Release gate | High | QA (2 days); accountant review (2 days); external audit (1 week) |
| **Total** | **Production-ready** | | **12 weeks / ~3 months** |

---

## ⚠️ Top Risks to Production

1. **No journal engine** — Product marketed as "accounting" but GL doesn't exist
   - *Mitigation:* Rename to "Order Management + Inventory" until Phase 8; be explicit in feature list
   
2. **Sales flow incomplete** — Can't demonstrate full cycle to customers
   - *Mitigation:* Sprint 2 is hardcoded as critical path; no scope creep
   
3. **Demo credentials in production** — One seeder run away from compromise
   - *Mitigation:* Implement production guard before any staging deployment
   
4. **No async jobs** — Long exports will timeout; email delivery blocking
   - *Mitigation:* Phase out synchronous export in Sprint 5; make email async
   
5. **Narrow test coverage** — Domain edge cases untested
   - *Mitigation:* Add unit tests for PostStockMovement in Sprint 2; race condition tests in Sprint 4
   
6. **Single git commit** — No history to verify incremental safety
   - *Mitigation:* Start committing atomically after Sprint 1; maintain consistent merge strategy

---

## ✅ Done in Sprint 1

- [x] Items: master data, SKU/barcode uniqueness, stock/service toggle
- [x] Contacts: vendors/customers with opening balances
- [x] Warehouses: inventory location tracking
- [x] Inventory ledger: balance + immutable transactions
- [x] Stock movements: draft → posted lifecycle with reversals
- [x] Warehouse transfers: dual-ledger posting
- [x] Purchase orders: draft → approved → received with partial GRs
- [x] RBAC: 5 roles, viewer blocked from writes
- [x] Audit logging: create/change events
- [x] Authentication: session-based, login throttled
- [x] Tests: 25 passing with invariant coverage
- [x] UI: Navbar hover fixed
- [x] UI: Save & New / Save & Close buttons for items

---

## 🎓 Lessons Learned

1. **Thin controllers, fat services** — PostStockMovement service is the right abstraction
2. **Audit everything** — AuditLog pays off in production support
3. **Test invariants, not implementations** — "negative stock never happens" is the right test, not "method X returns Y"
4. **Route model binding 404s are security** — use them for cross-company isolation
5. **Server-calculated totals save bugs** — never trust client math
6. **Immutable ledgers are hard but worth it** — no accidental overwrites
7. **Role-based access control from day one** — retrofitting is painful
8. **No async jobs in MVP is OK** — but document the deadline for migration

---

## 🚀 Launch Readiness Checklist

Before first customer:
- [ ] All 6 sprints complete
- [ ] Load test passes (100 concurrent users)
- [ ] Security audit passed
- [ ] Accountant sign-off on business rules
- [ ] Ops runbook written and practiced
- [ ] Data recovery drill completed successfully
- [ ] Marketing copy matches actual feature set (no promises about GL yet)
- [ ] Support training completed
- [ ] Escalation procedure documented
- [ ] SLA agreed (uptime target, response time)
- [ ] Legal review: ToS, privacy policy
- [ ] Insurance: E&O and cyber liability

**Go-live date: Week 13 of project (assuming 1 sprint per week)**
