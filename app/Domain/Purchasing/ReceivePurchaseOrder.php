<?php

namespace App\Domain\Purchasing;

use App\Domain\Inventory\PostStockMovement;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceivePurchaseOrder
{
    public function __construct(private PostStockMovement $posting) {}

    public function receive(string $orderId, string $companyId, string $userId, array $data): GoodsReceipt
    {
        return DB::transaction(function () use ($orderId, $companyId, $userId, $data) {
            $order = PurchaseOrder::whereCompanyId($companyId)->with('lines')->lockForUpdate()->findOrFail($orderId);
            if (! in_array($order->status, ['approved', 'partially_received'], true)) {
                throw ValidationException::withMessages(['purchase_order' => 'Only approved purchase orders can be received.']);
            }
            Warehouse::whereCompanyId($companyId)->whereActive(true)->findOrFail($data['warehouse_id']);

            $lineIds = collect($data['lines'])->pluck('purchase_order_line_id');
            if ($lineIds->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Each purchase-order line can be received only once per receipt document.']);
            }
            $lines = $order->lines->keyBy('id');
            foreach ($data['lines'] as $line) {
                $orderLine = $lines->get($line['purchase_order_line_id']);
                if (! $orderLine) {
                    throw ValidationException::withMessages(['lines' => 'A selected line does not belong to this purchase order.']);
                }
                $remaining = round((float) $orderLine->quantity - (float) $orderLine->received_quantity, 2);
                if ((float) $line['quantity'] > $remaining) {
                    throw ValidationException::withMessages(['lines' => 'Received quantity cannot exceed the remaining ordered quantity.']);
                }
            }

            $receipt = GoodsReceipt::create([
                'company_id' => $companyId, 'purchase_order_id' => $order->id, 'warehouse_id' => $data['warehouse_id'],
                'number' => $data['number'], 'receipt_date' => $data['receipt_date'], 'notes' => $data['notes'] ?? null,
                'posted_at' => now(), 'posted_by' => $userId,
            ]);
            foreach ($data['lines'] as $line) {
                $orderLine = $lines->get($line['purchase_order_line_id']);
                $movement = $this->posting->create([
                    'number' => 'GR-'.strtoupper(substr(str_replace('-', '', $receipt->id), 0, 16)).'-'.$orderLine->line_number,
                    'movement_date' => $data['receipt_date'], 'kind' => 'receipt', 'warehouse_id' => $data['warehouse_id'],
                    'item_id' => $orderLine->item_id, 'quantity' => $line['quantity'], 'unit_cost' => $orderLine->unit_cost,
                    'reference' => $receipt->number, 'notes' => 'Goods receipt against purchase order '.$order->number.'.',
                ], $companyId, $userId, true);
                $receipt->lines()->create([
                    'company_id' => $companyId, 'purchase_order_line_id' => $orderLine->id, 'item_id' => $orderLine->item_id,
                    'quantity' => $line['quantity'], 'unit_cost' => $orderLine->unit_cost, 'stock_movement_id' => $movement->id,
                ]);
                $orderLine->update(['received_quantity' => round((float) $orderLine->received_quantity + (float) $line['quantity'], 2)]);
            }
            $order->refresh()->load('lines');
            $complete = $order->lines->every(fn ($line) => (float) $line->received_quantity >= (float) $line->quantity);
            $order->update(['status' => $complete ? 'received' : 'partially_received']);

            return $receipt->fresh(['lines.item', 'warehouse', 'purchaseOrder']);
        });
    }
}
