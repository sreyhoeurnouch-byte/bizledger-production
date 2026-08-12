# Business rules implemented

## Item master

- SKU is required and unique within a company.
- Barcode is optional but, when present, is unique within a company.
- An item is either `stock` or `service`.
- Every item must be enabled for purchase, sale/issue, or both.
- A service item must have zero quantity and a zero reorder level.
- Opening quantity is accepted only while a stock item is created. The application records a posted opening receipt in the first active warehouse.
- Item quantity is not editable after creation. It changes only through posted stock movements.
- Once stock is posted, the item SKU and item type are locked to preserve document traceability.
- Archive makes an item inactive without deleting its history.

## Inventory movements

- Only active stock items can be selected.
- Supported movements are receipt, issue, and adjustment. Transfers and assemblies are not exposed until warehouse-level inventory and bill-of-materials workflows exist.
- Inventory is maintained per warehouse in `inventory_balances`; the item quantity is a controlled company-wide aggregate.
- Posting writes one immutable `inventory_transactions` ledger entry containing quantity delta, cost, value delta, and resulting warehouse balance.
- Receipt requires an item enabled for purchase; issue requires an item enabled for sale/issue.
- Adjustment requires an explicit increase or decrease direction.
- Receipts and increasing adjustments require a unit cost; weighted-average cost is recalculated on posting.
- A posted movement cannot produce a negative quantity in its warehouse or for the company total.
- A draft movement does not change inventory. It can be posted once; duplicate posting is blocked.
- Posting date cannot be in the future.

## Purchase orders

- Orders require an active vendor and at least one line.
- Every line must reference an active item enabled for purchase.
- A product can appear once per order; quantities must be combined before saving.
- Quantity must be greater than zero; unit cost cannot be negative.
- The server calculates line totals and the order total. The browser does not submit an authoritative total.
- A purchase order is created as a draft. Only owner, admin, and accountant roles can approve it.
- Goods can be received only against approved or partially received orders.
- A receipt can be partial but cannot exceed the remaining quantity on any purchase-order line.
- Posting a goods receipt creates posted inventory receipts and advances the order to partially received or received.

## Access and audit

- Every operational query is company-scoped.
- `viewer` role cannot submit writes. Owner, admin, accountant, and operator can.
- Item, contact, purchase-order, stock-movement, and account create/change actions create audit records.
