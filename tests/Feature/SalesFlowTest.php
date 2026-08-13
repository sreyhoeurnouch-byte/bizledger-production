<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\InventoryBalance;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_order_total_is_calculated_from_validated_lines(): void
    {
        [$company, $user, $customer, $item] = $this->salesSetup();

        $this->actingAs($user)->post(route('sales.store'), [
            'number' => 'SO-100', 'customer_id' => $customer->id, 'order_date' => now()->toDateString(), 'status' => 'draft',
            'lines' => [['item_id' => $item->id, 'quantity' => 2.5, 'unit_price' => 12]],
        ])->assertRedirect();

        $this->assertDatabaseHas('sales_orders', ['company_id' => $company->id, 'number' => 'SO-100', 'total' => 30]);
        $this->assertDatabaseHas('sales_order_lines', ['company_id' => $company->id, 'item_id' => $item->id, 'quantity' => 2.5, 'unit_price' => 12, 'line_total' => 30]);
    }

    public function test_posted_delivery_reduces_inventory_and_only_allows_remaining_quantity(): void
    {
        [$company, $user, $customer, $item, $warehouse] = $this->salesSetup(5);
        $order = $this->approvedOrder($company, $customer, $item, 4, 10);
        $line = $order->lines()->firstOrFail();

        $this->actingAs($user)->post(route('sales.deliver', $order), [
            'number' => 'DEL-100', 'delivery_date' => now()->toDateString(), 'warehouse_id' => $warehouse->id,
            'lines' => [['sales_order_line_id' => $line->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('items', ['id' => $item->id, 'quantity' => 3]);
        $this->assertDatabaseHas('inventory_transactions', ['company_id' => $company->id, 'item_id' => $item->id, 'quantity_delta' => -2]);
        $this->assertDatabaseHas('sales_order_lines', ['id' => $line->id, 'delivered_quantity' => 2]);

        $this->actingAs($user)->post(route('sales.deliver', $order), [
            'number' => 'DEL-101', 'delivery_date' => now()->toDateString(), 'warehouse_id' => $warehouse->id,
            'lines' => [['sales_order_line_id' => $line->id, 'quantity' => 3]],
        ])->assertSessionHasErrors('lines');
    }

    public function test_invoice_is_calculated_from_delivered_lines_and_receipts_cannot_overallocate(): void
    {
        [$company, $user, $customer, $item, $warehouse] = $this->salesSetup(5);
        $order = $this->approvedOrder($company, $customer, $item, 2, 10);
        $line = $order->lines()->firstOrFail();
        $this->actingAs($user)->post(route('sales.deliver', $order), [
            'number' => 'DEL-200', 'delivery_date' => now()->toDateString(), 'warehouse_id' => $warehouse->id,
            'lines' => [['sales_order_line_id' => $line->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('sales.invoice', $order), [
            'number' => 'INV-200', 'invoice_date' => now()->toDateString(),
            'lines' => [['sales_order_line_id' => $line->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();
        $invoice = Invoice::where('number', 'INV-200')->firstOrFail();
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'total' => 20, 'status' => 'open']);

        $this->actingAs($user)->post(route('sales.receipt', $order), [
            'number' => 'RCT-200', 'receipt_date' => now()->toDateString(), 'amount' => 21,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => 21]],
        ])->assertSessionHasErrors('allocations');
        $this->assertDatabaseMissing('customer_receipts', ['number' => 'RCT-200']);

        $this->actingAs($user)->post(route('sales.receipt', $order), [
            'number' => 'RCT-201', 'receipt_date' => now()->toDateString(), 'amount' => 20,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => 20]],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid']);
        $this->assertDatabaseHas('receipt_allocations', ['invoice_id' => $invoice->id, 'amount' => 20]);
    }

    public function test_sales_orders_are_isolated_between_companies(): void
    {
        [$company, $user, $customer, $item] = $this->salesSetup();
        $order = $this->approvedOrder($company, $customer, $item, 1, 10);
        $other = User::factory()->create(['company_id' => Company::factory()->create()->id, 'role' => 'operator']);

        $this->actingAs($other)->get(route('sales.show', $order))->assertNotFound();
    }

    private function salesSetup(float $quantity = 0): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'operator']);
        $customer = Contact::create(['company_id' => $company->id, 'kind' => 'customer', 'code' => 'C-01', 'name' => 'Customer', 'opening_balance' => 0, 'active' => true]);
        $item = Item::create(['company_id' => $company->id, 'sku' => 'SALE-100', 'name' => 'Sale item', 'unit' => 'Each', 'cost' => 4, 'sale_price' => 10, 'quantity' => $quantity, 'reorder_level' => 0, 'for_sale' => true]);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'code' => 'MAIN', 'name' => 'Main', 'active' => true]);
        if ($quantity > 0) {
            InventoryBalance::create(['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'quantity' => $quantity, 'average_cost' => 4]);
        }

        return [$company, $user, $customer, $item, $warehouse];
    }

    private function approvedOrder(Company $company, Contact $customer, Item $item, float $quantity, float $price): SalesOrder
    {
        $order = SalesOrder::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'number' => 'SO-'.str()->upper(str()->random(8)), 'order_date' => now()->toDateString(), 'status' => 'approved', 'total' => $quantity * $price]);
        $order->lines()->create(['company_id' => $company->id, 'item_id' => $item->id, 'line_number' => 1, 'quantity' => $quantity, 'unit_price' => $price, 'line_total' => $quantity * $price]);

        return $order;
    }
}
