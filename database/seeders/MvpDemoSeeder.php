<?php

namespace Database\Seeders;

use App\Domain\Purchasing\RecordVendorPayment;
use App\Domain\Sales\DeliverSalesOrder;
use App\Domain\Sales\InvoiceSalesOrder;
use App\Domain\Sales\RecordCustomerReceipt;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\VendorBill;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class MvpDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('name', 'Northstar Trading')->firstOrFail();
        $user = User::where('company_id', $company->id)->where('email', 'admin@bizledger.local')->firstOrFail();
        $warehouse = Warehouse::where('company_id', $company->id)->where('code', 'MAIN')->firstOrFail();
        $customerNames = ['Mekong Market', 'Golden Angkor Grocers', 'Tonle Sap Foods', 'Phnom Penh Pantry', 'Lotus Corner Store', 'Kampot Fresh Mart', 'Riverfront Retail', 'Sokha Family Shop', 'Banteay Mini Mart', 'Sunrise Provisioners'];
        $vendorNames = ['Mekong Wholesale', 'Angkor Distribution', 'Cambodia Pantry Supply', 'Capital Packaging', 'Kampot Commodity Traders', 'Khmer Foods Co.', 'Mekong Logistics', 'Sihanoukville Imports', 'Lotus Office Supply', 'Tonle Trading House'];
        $customers = collect($customerNames)->values()->map(fn ($name, $index) => Contact::firstOrCreate(['company_id' => $company->id, 'code' => 'C-'.(1001 + $index)], ['kind' => 'customer', 'name' => $name, 'email' => 'orders'.($index + 1).'@demo.example', 'opening_balance' => 0, 'active' => true]));
        $vendors = collect($vendorNames)->values()->map(fn ($name, $index) => Contact::firstOrCreate(['company_id' => $company->id, 'code' => 'V-'.(1001 + $index)], ['kind' => 'vendor', 'name' => $name, 'email' => 'accounts'.($index + 1).'@demo.example', 'opening_balance' => 0, 'active' => true]));
        $items = Item::where('company_id', $company->id)->where('for_sale', true)->orderBy('sku')->get();
        $delivery = app(DeliverSalesOrder::class);
        $invoicing = app(InvoiceSalesOrder::class);
        $receipts = app(RecordCustomerReceipt::class);
        $payments = app(RecordVendorPayment::class);

        foreach (range(1, 10) as $index) {
            $item = $items[($index - 1) % $items->count()];
            $order = SalesOrder::firstOrCreate(['company_id' => $company->id, 'number' => 'SO-DEMO-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT)], ['customer_id' => $customers[$index - 1]->id, 'order_date' => today()->subDays($index + 2), 'expected_date' => today()->subDays($index), 'status' => 'approved', 'total' => $item->sale_price, 'approved_at' => now(), 'approved_by' => $user->id, 'notes' => 'Realistic demo sale.']);
            if (! $order->lines()->exists()) {
                $line = $order->lines()->create(['company_id' => $company->id, 'item_id' => $item->id, 'line_number' => 1, 'quantity' => 1, 'unit_price' => $item->sale_price, 'line_total' => $item->sale_price]);
                $date = today()->subDays($index);
                $delivery->deliver($order->id, $company->id, $user->id, ['number' => 'DEL-DEMO-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT), 'delivery_date' => $date->toDateString(), 'warehouse_id' => $warehouse->id, 'lines' => [['sales_order_line_id' => $line->id, 'quantity' => 1]]]);
                $invoice = $invoicing->issue($order->id, $company->id, $user->id, ['number' => 'INV-DEMO-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT), 'invoice_date' => $date->toDateString(), 'due_date' => $date->copy()->addDays(14)->toDateString(), 'lines' => [['sales_order_line_id' => $line->id, 'quantity' => 1]]]);
                if ($index % 2 === 0) {
                    $receipts->record($company->id, $user->id, ['customer_id' => $order->customer_id, 'number' => 'RCT-DEMO-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT), 'receipt_date' => $date->copy()->addDay()->toDateString(), 'amount' => $invoice->total, 'reference' => 'Bank transfer', 'allocations' => [['invoice_id' => $invoice->id, 'amount' => $invoice->total]]]);
                }
            }

            $bill = VendorBill::firstOrCreate(['company_id' => $company->id, 'number' => 'BILL-DEMO-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT)], ['vendor_id' => $vendors[$index - 1]->id, 'bill_date' => today()->subDays($index * 9), 'due_date' => today()->subDays(($index - 1) * 9), 'total' => 45 + ($index * 17), 'notes' => 'Realistic demo supplier bill.', 'issued_by' => $user->id]);
            if (! $bill->lines()->exists()) {
                $bill->lines()->create(['company_id' => $company->id, 'line_number' => 1, 'description' => 'Supplier goods and freight', 'quantity' => 1, 'unit_cost' => $bill->total, 'line_total' => $bill->total]);
                if ($index % 2 === 0) {
                    $payments->record($company->id, $user->id, ['vendor_id' => $bill->vendor_id, 'number' => 'PAY-DEMO-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT), 'payment_date' => today()->subDays(max(0, $index - 2))->toDateString(), 'amount' => $bill->total, 'reference' => 'Bank transfer', 'allocations' => [['vendor_bill_id' => $bill->id, 'amount' => $bill->total]]]);
                }
            }
        }
    }
}
