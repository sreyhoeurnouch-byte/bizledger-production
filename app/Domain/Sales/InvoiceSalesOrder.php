<?php

namespace App\Domain\Sales;

use App\Models\Invoice;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceSalesOrder
{
    public function issue(string $orderId, string $companyId, string $userId, array $data): Invoice
    {
        return DB::transaction(function () use ($orderId, $companyId, $userId, $data) {
            $order = SalesOrder::whereCompanyId($companyId)->with('lines')->lockForUpdate()->findOrFail($orderId);
            if (! in_array($order->status, ['partially_delivered', 'delivered', 'invoiced'], true)) {
                throw ValidationException::withMessages(['sales_order' => 'A sales order must have a posted delivery before it can be invoiced.']);
            }

            $lineIds = collect($data['lines'])->pluck('sales_order_line_id');
            if ($lineIds->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Each sales-order line can be invoiced only once per invoice document.']);
            }
            $lines = $order->lines->keyBy('id');
            foreach ($data['lines'] as $line) {
                $orderLine = $lines->get($line['sales_order_line_id']);
                if (! $orderLine) {
                    throw ValidationException::withMessages(['lines' => 'A selected line does not belong to this sales order.']);
                }
                $remaining = round((float) $orderLine->delivered_quantity - (float) $orderLine->invoiced_quantity, 2);
                if ((float) $line['quantity'] > $remaining) {
                    throw ValidationException::withMessages(['lines' => 'Invoiced quantity cannot exceed the delivered, uninvoiced quantity.']);
                }
            }

            $invoiceLines = collect($data['lines'])->values()->map(function ($line, $index) use ($lines, $companyId) {
                $orderLine = $lines->get($line['sales_order_line_id']);

                return [
                    'company_id' => $companyId,
                    'sales_order_line_id' => $orderLine->id,
                    'item_id' => $orderLine->item_id,
                    'line_number' => $index + 1,
                    'quantity' => $line['quantity'],
                    'unit_price' => $orderLine->unit_price,
                    'line_total' => round((float) $line['quantity'] * (float) $orderLine->unit_price, 2),
                ];
            });
            $invoice = Invoice::create([
                'company_id' => $companyId,
                'customer_id' => $order->customer_id,
                'sales_order_id' => $order->id,
                'number' => $data['number'],
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'issued_at' => now(),
                'issued_by' => $userId,
                'total' => $invoiceLines->sum('line_total'),
            ]);
            $invoice->lines()->createMany($invoiceLines->all());

            foreach ($data['lines'] as $line) {
                $orderLine = $lines->get($line['sales_order_line_id']);
                $orderLine->update(['invoiced_quantity' => round((float) $orderLine->invoiced_quantity + (float) $line['quantity'], 2)]);
            }
            $order->refresh()->load('lines');
            $complete = $order->lines->every(fn ($line) => (float) $line->invoiced_quantity >= (float) $line->quantity);
            if ($complete) {
                $order->update(['status' => 'invoiced']);
            }

            return $invoice->fresh(['customer', 'lines.item', 'salesOrder']);
        });
    }
}
