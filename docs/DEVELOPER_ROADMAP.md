# BizLedger production developer roadmap

## 1. Product objective

BizLedger is a multi-company business operations system for item master data, inventory, suppliers, customers, purchasing, accounting, and reporting. It must preserve an auditable history, prevent cross-company access, and make financial-impacting actions explicit and reversible only through controlled documents.

The current release is an inventory and procurement foundation. It is **not yet** a complete statutory accounting product.

## 2. Target stack

| Layer | Current / target choice | Responsibility |
|---|---|---|
| Application | Laravel 13, PHP 8.3+ | HTTP, validation, domain services, queues, scheduled work |
| UI | Blade, Bootstrap 5, Font Awesome | Fast server-rendered accounting workspace |
| Database | MySQL 8 or MariaDB 10.6+ | Transactional source of truth |
| Cache / sessions | Database initially; Redis in scaled deployments | Sessions, rate limits, cache, queue coordination |
| Background work | Laravel queues + Supervisor/system service | Imports, exports, reports, notification delivery |
| Files | S3-compatible object storage | Product images, attachments, generated exports |
| Mail | Transactional provider | Password resets, approvals, alerts |
| Observability | Centralized logs + uptime/error monitoring | Error response, audit investigation |
| Deployment | Docker or managed PHP platform behind Nginx/HTTPS | Repeatable releases and rollback |

Do not add React, Livewire, or a separate API until a business requirement needs it. Blade keeps this administrative system simpler to secure and operate.

## 3. Architecture rules

### Delivery status — Sprint 1

Implemented: `InventoryBalance`, `InventoryTransaction`, and `PostStockMovement`; draft-to-post workflow; per-warehouse negative-stock prevention; unit-cost capture and weighted-average updates; immutable inventory ledger rows; warehouse transfers; reasoned reversal documents; approval-controlled purchase orders; and partial goods receipts that post inventory. Bills of materials, sales, journal posting, taxes, banking, and statutory reports remain subsequent roadmap work.

### Company isolation

- Every business table has `company_id` and every query must scope by the authenticated user’s company.
- Route model binding must never resolve a record before the company constraint is applied.
- Cross-company references must fail validation and be covered by tests.
- Database foreign keys protect referential integrity; application validation protects company ownership.

### Accounting integrity

- Never update inventory or account balances directly from a form.
- Business documents transition through states: `draft → approved → posted`, or `draft → cancelled`.
- Posted documents are immutable. Corrections use a reversal or adjustment document linked to the original.
- Every posting happens in a database transaction and writes an audit event.
- Document numbers must be unique per company and document series.

### Domain services

Move posting logic out of controllers into explicit services:

```text
app/Domain/Inventory/PostStockMovement.php
app/Domain/Purchasing/CreatePurchaseOrder.php
app/Domain/Purchasing/ReceivePurchaseOrder.php
app/Domain/Accounting/PostJournalEntry.php
app/Domain/Accounting/CloseAccountingPeriod.php
```

Controllers validate requests, authorize access, call one service, and return a response. Services own database transactions, locks, state transitions, and audit entries.

## 4. Data model roadmap

### Completed foundation

- Companies, users, contacts, warehouses, item groups, items
- Stock movements with draft/posted state
- Purchase-order headers and validated line items
- Chart of accounts and audit logs

### Phase A — inventory correctness

Add these tables before supporting multiple warehouses, transfers, or assemblies:

```text
inventory_balances
  company_id, warehouse_id, item_id, quantity, average_cost, updated_at
  unique(company_id, warehouse_id, item_id)

inventory_transactions
  company_id, stock_movement_id, warehouse_id, item_id,
  quantity_delta, cost_delta, balance_after, created_at

document_sequences
  company_id, document_type, prefix, next_number, fiscal_year
```

Rules:

- Stock quantity becomes warehouse-specific; `items.quantity` is removed or becomes a read-only aggregate.
- Transfers require a source and destination warehouse and produce linked outbound/inbound transactions.
- Assemblies require bills of materials and consume component inventory atomically.
- Costing method must be selected per company (weighted average first; FIFO only when explicitly required).

### Phase B — purchasing lifecycle

Extend purchase orders with:

```text
goods_receipts / goods_receipt_lines
purchase_invoices / purchase_invoice_lines
vendor_payments / payment_allocations
```

Workflow:

```text
Draft PO → Approval → Goods receipt → Vendor invoice → Payment → Closed
```

Goods receipt, not purchase-order approval, increases inventory. Receipts may be partial. A received quantity cannot exceed the approved PO quantity unless an explicit over-receipt policy allows it.

### Phase C — sales and receivables

Add sales orders, invoices, invoice lines, customer receipts, credit notes, and allocation tables.

Workflow:

```text
Quote → Sales order → Delivery / stock issue → Invoice → Customer receipt → Closed
```

Inventory must be issued only from an approved delivery or approved issue document. Customer balance is derived from invoices, credits, and payment allocations—not an editable total.

### Phase D — general ledger

Add:

```text
journal_entries / journal_lines
fiscal_years / accounting_periods
tax_codes / tax_rates
currencies / exchange_rates
bank_accounts / bank_transactions / reconciliations
```

Rules:

- Every posted operational document creates a balanced journal entry (`debits = credits`).
- Journal entries have at least two lines and are immutable after posting.
- Accounts, tax codes, and posting periods are validated per company.
- A closed period blocks posting; reopening requires an owner/admin audit event.

## 5. Module delivery order

| Phase | Deliverable | Definition of done |
|---|---|---|
| 0 | Platform baseline | CI, environment policy, monitoring, backups, secure roles |
| 1 | Item and contact masters | Full CRUD, archive instead of delete, validation, audit trail |
| 2 | Warehouse inventory | Per-warehouse balances, receipt/issue/adjustment, posting/reversal |
| 3 | Procurement | PO lines, approvals, partial receipts, vendor invoice matching |
| 4 | Sales | Customer documents, delivery, invoice, receipts, returns |
| 5 | General ledger | Journal engine, account mapping, period close, trial balance |
| 6 | Tax and banking | Jurisdiction rules, payment allocation, reconciliation |
| 7 | Reporting and exports | Financial statements, inventory valuation, audit/export jobs |
| 8 | Scale and integrations | REST API, webhooks, imports, external accounting/payment integrations |

Do not begin Phase 4 until Phase 2 posting and reversal behavior is proven with automated tests. Do not call the product accounting-ready until Phase 5 is complete and reviewed by an accountant for the target jurisdiction.

## 6. Roles and authorization

| Role | Capabilities |
|---|---|
| Owner | Company settings, users, period close/reopen, all data |
| Admin | Master data, approvals, reports, operational configuration |
| Accountant | Journals, invoices, payments, reports; no user/company ownership changes |
| Operator | Draft operational documents and permitted postings |
| Viewer | Read-only access |

Implement Laravel policies for every model and document action. The `ledger.write` middleware is only the first boundary; it is not sufficient for approval or period-close authority.

## 7. Validation and workflow standards

- Validate on the server; browser validation only improves usability.
- Use `FormRequest` classes for each create, update, approve, post, void, receive, and reverse action.
- Use decimal columns and decimal-safe calculations for money and quantities. Never use floats for persistent financial math.
- Normalize input strings (trim codes, normalize empty values to `null`) before validation.
- Add database unique indexes for business keys, not only application `unique` rules.
- Create idempotency keys for imports, payment webhooks, and retryable posting jobs.
- Add optimistic locking (`version` integer) to editable drafts to prevent lost updates.
- Require a reason on adjustments, voids, reversals, and period reopen actions.

## 8. Security and compliance backlog

1. Force TLS, secure cookies, encrypted sessions, production error handling, and strict `APP_DEBUG=false`.
2. Add password reset, email verification, optional MFA, session management, and login audit events.
3. Add authorization policies and approval thresholds.
4. Encrypt sensitive attachments at rest and enforce upload MIME/size checks.
5. Add retention/export/deletion policy appropriate to the operating jurisdiction.
6. Introduce rate limits on all authenticated write and export endpoints.
7. Run dependency and static security scans in CI.
8. Commission external security review before public internet exposure.

## 9. Quality strategy

### Automated tests

- Unit: posting delta, valuation, tax, document numbering, permissions.
- Feature: validation, company isolation, state transitions, authorization, audit logging.
- Integration: queues, mail, storage, database rollback, imports/exports.
- Browser: core flows for item creation, PO creation, receipt, invoice, payment, close period.

Required financial invariants:

```text
Inventory can never be negative unless a company policy explicitly permits it.
Posted document totals cannot change.
Every journal entry balances.
Payments cannot exceed their open document balance.
No posting occurs in a closed accounting period.
Cross-company records cannot be read or written.
```

### CI pipeline

```text
composer validate
composer install
php artisan test
php artisan migrate --env=testing
php artisan config:cache
php artisan route:cache
php artisan view:cache
static analysis + code style + dependency audit
```

## 10. Environments and release process

### Environments

- Local: seeded demo database only.
- Test: isolated database created for every CI run.
- Staging: production-like services, masked production-shaped data, test integrations.
- Production: backups, monitored queues, least-privilege database user, no demo accounts.

### Release gate

Before a production release:

1. Migration tested against a production-size database copy.
2. Backup completed and restore verified.
3. Roll-forward migration plan documented; do not depend on destructive rollbacks.
4. Smoke test covers login, role permissions, a read flow, and a non-financial draft flow.
5. Queue workers restarted gracefully after deployment.
6. Error rate, slow queries, queue depth, and backup status monitored after release.

## 11. Immediate next sprint

1. Replace controller business logic with inventory and procurement domain services.
2. Add warehouse-level inventory balances and immutable inventory transaction records.
3. Add stock document approval/post/reversal status transitions.
4. Implement PO approval and partial goods receipts with quantity matching.
5. Add Laravel policies and Form Requests.
6. Add browser tests for item and purchase-order flows.
7. Configure CI and a staging environment before inviting production users.

## 12. Non-goals until explicitly approved

- Tax calculation for an unspecified jurisdiction.
- Payroll, fixed assets, manufacturing, or multi-currency revaluation.
- Automated bank posting without reconciliation controls.
- Unrestricted data deletion.
- Multi-warehouse transfer or assembly without warehouse-level inventory balances.
