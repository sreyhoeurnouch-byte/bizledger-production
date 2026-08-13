<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_operator_can_create_an_item_and_an_audit_event_is_recorded(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'operator']);
        Warehouse::create(['company_id' => $company->id, 'code' => 'MAIN', 'name' => 'Main', 'active' => true]);

        $this->actingAs($user)->post(route('items.store'), [
            'sku' => 'SKU-100', 'name' => 'Production Widget', 'unit' => 'Each',
            'cost' => 12.5, 'sale_price' => 18.75, 'quantity' => 8, 'reorder_level' => 2,
            'active' => 1,
        ])->assertRedirect(route('items.index'));

        $this->assertDatabaseHas('items', ['company_id' => $company->id, 'sku' => 'SKU-100']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'created', 'entity_type' => Item::class]);
    }

    public function test_a_viewer_cannot_write_to_the_ledger(): void
    {
        $company = Company::factory()->create();
        $viewer = User::factory()->create(['company_id' => $company->id, 'role' => 'viewer']);

        $this->actingAs($viewer)->post(route('items.store'), [
            'sku' => 'SKU-101', 'name' => 'Blocked Widget', 'unit' => 'Each',
            'cost' => 1, 'sale_price' => 2, 'quantity' => 0, 'reorder_level' => 0,
        ])->assertForbidden();
    }

    public function test_an_item_from_another_company_cannot_be_edited(): void
    {
        $ownerCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $ownerCompany->id, 'role' => 'admin']);
        $item = Item::create(['company_id' => $otherCompany->id, 'sku' => 'PRIVATE-1', 'name' => 'Private Item', 'unit' => 'Each', 'cost' => 1, 'sale_price' => 2, 'quantity' => 1, 'reorder_level' => 0]);

        $this->actingAs($user)->get(route('items.edit', $item))->assertNotFound();
    }

    public function test_posting_an_issue_cannot_reduce_stock_below_zero(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'operator']);
        $item = Item::create(['company_id' => $company->id, 'sku' => 'STOCK-1', 'name' => 'Stock Item', 'unit' => 'Each', 'cost' => 1, 'sale_price' => 2, 'quantity' => 2, 'reorder_level' => 0]);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'code' => 'MAIN', 'name' => 'Main', 'active' => true]);

        $this->actingAs($user)->post(route('stock.store'), [
            'number' => 'ISSUE-001', 'movement_date' => now()->toDateString(), 'kind' => 'issue',
            'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 3, 'posted' => 1,
        ])->assertSessionHasErrors('quantity');

        $this->assertDatabaseHas('items', ['id' => $item->id, 'quantity' => 2]);
        $this->assertDatabaseMissing('stock_movements', ['number' => 'ISSUE-001']);
    }

    public function test_a_service_item_cannot_have_opening_inventory(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'operator']);

        $this->actingAs($user)->post(route('items.store'), [
            'sku' => 'SVC-001', 'name' => 'Consulting', 'item_type' => 'service', 'unit' => 'Hour',
            'cost' => 0, 'sale_price' => 50, 'quantity' => 1, 'reorder_level' => 0,
        ])->assertSessionHasErrors('item_type');

        $this->assertDatabaseMissing('items', ['company_id' => $company->id, 'sku' => 'SVC-001']);
    }

    public function test_a_barcode_must_be_unique_within_a_company(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'operator']);
        Item::create(['company_id' => $company->id, 'sku' => 'SKU-201', 'name' => 'Existing', 'barcode' => '8990001', 'unit' => 'Each', 'cost' => 1, 'sale_price' => 2, 'quantity' => 0, 'reorder_level' => 0]);

        $this->actingAs($user)->post(route('items.store'), [
            'sku' => 'SKU-202', 'name' => 'Duplicate barcode', 'barcode' => '8990001', 'unit' => 'Each',
            'cost' => 1, 'sale_price' => 2, 'quantity' => 0, 'reorder_level' => 0,
        ])->assertSessionHasErrors('barcode');
    }

    public function test_item_quantity_cannot_be_changed_from_the_edit_endpoint(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'operator']);
        $item = Item::create(['company_id' => $company->id, 'sku' => 'SKU-301', 'name' => 'Protected quantity', 'unit' => 'Each', 'cost' => 1, 'sale_price' => 2, 'quantity' => 4, 'reorder_level' => 0]);

        $this->actingAs($user)->put(route('items.update', $item), [
            'sku' => $item->sku, 'name' => $item->name, 'item_type' => 'stock', 'unit' => 'Each',
            'cost' => 1, 'sale_price' => 2, 'quantity' => 99, 'reorder_level' => 0,
        ])->assertSessionHasErrors('quantity');

        $this->assertDatabaseHas('items', ['id' => $item->id, 'quantity' => 4]);
    }

    public function test_purchase_order_total_is_calculated_from_validated_lines(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'operator']);
        $vendor = Contact::create(['company_id' => $company->id, 'kind' => 'vendor', 'code' => 'V-01', 'name' => 'Supplier', 'opening_balance' => 0, 'active' => true]);
        $item = Item::create(['company_id' => $company->id, 'sku' => 'BUY-1', 'name' => 'Purchase Item', 'unit' => 'Each', 'cost' => 1, 'sale_price' => 2, 'quantity' => 0, 'reorder_level' => 0, 'for_purchase' => true]);

        $this->actingAs($user)->post(route('purchasing.store'), [
            'number' => 'PO-100', 'vendor_id' => $vendor->id, 'order_date' => now()->toDateString(), 'status' => 'draft',
            'lines' => [['item_id' => $item->id, 'quantity' => 2.5, 'unit_cost' => 10]],
        ])->assertRedirect();

        $this->assertDatabaseHas('purchase_orders', ['company_id' => $company->id, 'number' => 'PO-100', 'total' => 25]);
        $this->assertDatabaseHas('purchase_order_lines', ['company_id' => $company->id, 'item_id' => $item->id, 'quantity' => 2.5, 'unit_cost' => 10, 'line_total' => 25]);
    }

    public function test_purchase_order_rejects_a_product_not_enabled_for_purchase(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'operator']);
        $vendor = Contact::create(['company_id' => $company->id, 'kind' => 'vendor', 'code' => 'V-02', 'name' => 'Supplier', 'opening_balance' => 0, 'active' => true]);
        $item = Item::create(['company_id' => $company->id, 'sku' => 'SALE-1', 'name' => 'Sale only', 'unit' => 'Each', 'cost' => 1, 'sale_price' => 2, 'quantity' => 0, 'reorder_level' => 0, 'for_purchase' => false, 'for_sale' => true]);

        $this->actingAs($user)->post(route('purchasing.store'), [
            'number' => 'PO-101', 'vendor_id' => $vendor->id, 'order_date' => now()->toDateString(), 'status' => 'draft',
            'lines' => [['item_id' => $item->id, 'quantity' => 1, 'unit_cost' => 10]],
        ])->assertSessionHasErrors('lines.0.item_id');
    }

    public function test_posting_a_receipt_updates_warehouse_balance_cost_and_inventory_ledger(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'operator']);
        $item = Item::create(['company_id' => $company->id, 'sku' => 'LEDGER-1', 'name' => 'Ledger Item', 'unit' => 'Each', 'cost' => 2, 'sale_price' => 4, 'quantity' => 0, 'reorder_level' => 0]);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'code' => 'MAIN', 'name' => 'Main', 'active' => true]);

        $this->actingAs($user)->post(route('stock.store'), [
            'number' => 'REC-001', 'movement_date' => now()->toDateString(), 'kind' => 'receipt',
            'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 5, 'unit_cost' => 3.5, 'posted' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('inventory_balances', ['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'quantity' => 5, 'average_cost' => 3.5]);
        $this->assertDatabaseHas('items', ['id' => $item->id, 'quantity' => 5, 'cost' => 3.5]);
        $this->assertDatabaseHas('inventory_transactions', ['company_id' => $company->id, 'item_id' => $item->id, 'quantity_delta' => 5, 'unit_cost' => 3.5, 'value_delta' => 17.5]);
    }

    public function test_a_draft_has_no_inventory_effect_until_it_is_posted_once(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'operator']);
        $item = Item::create(['company_id' => $company->id, 'sku' => 'DRAFT-1', 'name' => 'Draft Item', 'unit' => 'Each', 'cost' => 1, 'sale_price' => 2, 'quantity' => 0, 'reorder_level' => 0]);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'code' => 'MAIN', 'name' => 'Main', 'active' => true]);

        $this->actingAs($user)->post(route('stock.store'), [
            'number' => 'REC-DRAFT', 'movement_date' => now()->toDateString(), 'kind' => 'receipt',
            'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 2, 'unit_cost' => 4, 'posted' => 0,
        ])->assertSessionHasNoErrors();
        $movement = StockMovement::where('number', 'REC-DRAFT')->firstOrFail();
        $this->assertDatabaseMissing('inventory_transactions', ['stock_movement_id' => $movement->id]);
        $this->assertDatabaseMissing('inventory_balances', ['item_id' => $item->id]);

        $this->actingAs($user)->post(route('stock.post', $movement))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inventory_balances', ['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 2, 'average_cost' => 4]);
        $this->assertDatabaseHas('inventory_transactions', ['stock_movement_id' => $movement->id]);
        $this->actingAs($user)->post(route('stock.post', $movement))->assertSessionHasErrors('movement');
    }

    public function test_a_transfer_moves_quantity_between_warehouses_without_changing_company_quantity(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'operator']);
        $item = Item::create(['company_id' => $company->id, 'sku' => 'MOVE-1', 'name' => 'Move Item', 'unit' => 'Each', 'cost' => 2, 'sale_price' => 4, 'quantity' => 5, 'reorder_level' => 0]);
        $source = Warehouse::create(['company_id' => $company->id, 'code' => 'MAIN', 'name' => 'Main', 'active' => true]);
        $destination = Warehouse::create(['company_id' => $company->id, 'code' => 'BRANCH', 'name' => 'Branch', 'active' => true]);
        InventoryBalance::create(['company_id' => $company->id, 'warehouse_id' => $source->id, 'item_id' => $item->id, 'quantity' => 5, 'average_cost' => 2]);

        $this->actingAs($user)->post(route('stock.store'), [
            'number' => 'TRF-001', 'movement_date' => now()->toDateString(), 'kind' => 'transfer', 'item_id' => $item->id,
            'warehouse_id' => $source->id, 'destination_warehouse_id' => $destination->id, 'quantity' => 3, 'posted' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('inventory_balances', ['warehouse_id' => $source->id, 'item_id' => $item->id, 'quantity' => 2, 'average_cost' => 2]);
        $this->assertDatabaseHas('inventory_balances', ['warehouse_id' => $destination->id, 'item_id' => $item->id, 'quantity' => 3, 'average_cost' => 2]);
        $this->assertDatabaseHas('items', ['id' => $item->id, 'quantity' => 5]);
        $this->assertSame(2, InventoryTransaction::where('company_id', $company->id)->where('stock_movement_id', StockMovement::where('number', 'TRF-001')->value('id'))->count());
    }

    public function test_a_posted_receipt_can_be_reversed_with_a_reason(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
        $item = Item::create(['company_id' => $company->id, 'sku' => 'REV-1', 'name' => 'Reversal Item', 'unit' => 'Each', 'cost' => 3, 'sale_price' => 6, 'quantity' => 0, 'reorder_level' => 0]);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'code' => 'MAIN', 'name' => 'Main', 'active' => true]);
        $original = StockMovement::create(['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'number' => 'REC-REV', 'movement_date' => now()->toDateString(), 'kind' => 'receipt', 'quantity' => 4, 'unit_cost' => 3, 'posted' => false]);

        $this->actingAs($user)->post(route('stock.post', $original))->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('stock.reverse', $original), ['reason' => 'Supplier shipment was entered twice.'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('inventory_balances', ['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'quantity' => 0]);
        $this->assertDatabaseHas('stock_movements', ['reversal_of_id' => $original->id, 'kind' => 'issue', 'posted' => true]);
    }

    public function test_only_an_approval_role_can_approve_a_purchase_order(): void
    {
        $company = Company::factory()->create();
        $operator = User::factory()->create(['company_id' => $company->id, 'role' => 'operator']);
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
        $vendor = Contact::create(['company_id' => $company->id, 'kind' => 'vendor', 'code' => 'V-APP', 'name' => 'Supplier', 'opening_balance' => 0, 'active' => true]);
        $order = PurchaseOrder::create(['company_id' => $company->id, 'vendor_id' => $vendor->id, 'number' => 'PO-APP', 'order_date' => now()->toDateString(), 'status' => 'draft', 'total' => 0]);

        $this->actingAs($operator)->post(route('purchasing.approve', $order))->assertForbidden();
        $this->actingAs($admin)->post(route('purchasing.approve', $order))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'status' => 'approved', 'approved_by' => $admin->id]);
    }

    public function test_an_approved_purchase_order_can_be_partially_received_once_per_line_quantity(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
        $operator = User::factory()->create(['company_id' => $company->id, 'role' => 'operator']);
        $vendor = Contact::create(['company_id' => $company->id, 'kind' => 'vendor', 'code' => 'V-GR', 'name' => 'Supplier', 'opening_balance' => 0, 'active' => true]);
        $item = Item::create(['company_id' => $company->id, 'sku' => 'GR-ITEM', 'name' => 'Receipt Item', 'unit' => 'Each', 'cost' => 3, 'sale_price' => 6, 'quantity' => 0, 'reorder_level' => 0]);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'code' => 'MAIN', 'name' => 'Main', 'active' => true]);
        $order = PurchaseOrder::create(['company_id' => $company->id, 'vendor_id' => $vendor->id, 'number' => 'PO-GR', 'order_date' => now()->toDateString(), 'status' => 'draft', 'total' => 30]);
        $line = $order->lines()->create(['company_id' => $company->id, 'item_id' => $item->id, 'line_number' => 1, 'quantity' => 10, 'unit_cost' => 3, 'line_total' => 30]);

        $this->actingAs($admin)->post(route('purchasing.approve', $order))->assertSessionHasNoErrors();
        $this->actingAs($operator)->post(route('purchasing.receive', $order), [
            'number' => 'GR-001', 'receipt_date' => now()->toDateString(), 'warehouse_id' => $warehouse->id,
            'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => 4]],
        ])->assertRedirect(route('purchasing.show', $order));

        $this->assertDatabaseHas('purchase_order_lines', ['id' => $line->id, 'received_quantity' => 4]);
        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'status' => 'partially_received']);
        $this->assertDatabaseHas('inventory_balances', ['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'quantity' => 4, 'average_cost' => 3]);
        $this->assertDatabaseHas('goods_receipts', ['company_id' => $company->id, 'purchase_order_id' => $order->id, 'number' => 'GR-001']);
    }
}
