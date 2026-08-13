<?php

namespace App\Domain\Sales;

use App\Domain\Inventory\PostStockMovement;
use App\Models\Delivery;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliverSalesOrder
{
    public function __construct(private PostStockMovement $posting) {}

    public function deliver(string $orderId, string $companyId, string $userId, array $data): Delivery
    {
        return DB::transaction(function () use ($orderId, $companyId, $userId, $data) {
            $order = SalesOrder::whereCompanyId($companyId)->with('lines')->lockForUpdate()->findOrFail($orderId);
            if (! in_array($order->status, ['approved', 'partially_delivered'], true)) {
                throw ValidationException::withMessages(['sales_order' => 'Only approved sales orders can be delivered.']);
            }
            Warehouse::whereCompanyId($companyId)->whereActive(true)->findOrFail($data['warehouse_id']);

            $lineIds = collect($data['lines'])->pluck('sales_order_line_id');
            if ($lineIds->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Each sales-order line can be delivered only once per delivery document.']);
            }
            $lines = $order->lines->keyBy('id');
            foreach ($data['lines'] as $line) {
                $orderLine = $lines->get($line['sales_order_line_id']);
                if (! $orderLine) {
                    throw ValidationException::withMessages(['lines' => 'A selected line does not belong to this sales order.']);
                }
                $remaining = round((float) $orderLine->quantity - (float) $orderLine->delivered_quantity, 2);
                if ((float) $line['quantity'] > $remaining) {
                    throw ValidationException::withMessages(['lines' => 'Delivered quantity cannot exceed the remaining ordered quantity.']);
                }
            }

            $delivery = Delivery::create([
                'company_id' => $companyId,
                'sales_order_id' => $order->id,
                'warehouse_id' => $data['warehouse_id'],
                'number' => $data['number'],
                'delivery_date' => $data['delivery_date'],
                'notes' => $data['notes'] ?? null,
                'posted_at' => now(),
                'posted_by' => $userId,
            ]);

            foreach ($data['lines'] as $line) {
                $orderLine = $lines->get($line['sales_order_line_id']);
                $movement = $this->posting->create([
                    'number' => 'DL-'.strtoupper(substr(str_replace('-', '', $delivery->id), 0, 16)).'-'.$orderLine->line_number,
                    'movement_date' => $data['delivery_date'],
                    'kind' => 'issue',
                    'warehouse_id' => $data['warehouse_id'],
                    'item_id' => $orderLine->item_id,
                    'quantity' => $line['quantity'],
                    'reference' => $delivery->number,
                    'notes' => 'Delivery against sales order '.$order->number.'.',
                ], $companyId, $userId, true);
                $delivery->lines()->create([
                    'company_id' => $companyId,
                    'sales_order_line_id' => $orderLine->id,
                    'item_id' => $orderLine->item_id,
                    'quantity' => $line['quantity'],
                    'stock_movement_id' => $movement->id,
                ]);
                $orderLine->update(['delivered_quantity' => round((float) $orderLine->delivered_quantity + (float) $line['quantity'], 2)]);
            }

            $order->refresh()->load('lines');
            $complete = $order->lines->every(fn ($line) => (float) $line->delivered_quantity >= (float) $line->quantity);
            $order->update(['status' => $complete ? 'delivered' : 'partially_delivered']);

            return $delivery->fresh(['lines.item', 'warehouse', 'salesOrder']);
        });
    }
}
