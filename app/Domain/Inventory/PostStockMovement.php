<?php

namespace App\Domain\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostStockMovement
{
    public function create(array $data, string $companyId, string $userId, bool $postImmediately): StockMovement
    {
        return DB::transaction(function () use ($data, $companyId, $userId, $postImmediately) {
            $movement = StockMovement::create($data + ['company_id' => $companyId, 'posted' => false]);
            if ($postImmediately) {
                $this->postLocked($movement, $companyId, $userId);
            }

            return $movement->fresh(['item', 'warehouse', 'destinationWarehouse']);
        });
    }

    public function post(string $movementId, string $companyId, string $userId): StockMovement
    {
        return DB::transaction(function () use ($movementId, $companyId, $userId) {
            $movement = StockMovement::whereCompanyId($companyId)->lockForUpdate()->findOrFail($movementId);
            if ($movement->posted) {
                throw ValidationException::withMessages(['movement' => 'This stock movement has already been posted and cannot be posted again.']);
            }
            $this->postLocked($movement, $companyId, $userId);

            return $movement->fresh(['item', 'warehouse', 'destinationWarehouse']);
        });
    }

    public function reverse(string $movementId, string $companyId, string $userId, string $reason): StockMovement
    {
        return DB::transaction(function () use ($movementId, $companyId, $userId, $reason) {
            $movement = StockMovement::whereCompanyId($companyId)->lockForUpdate()->findOrFail($movementId);
            if (! $movement->posted) {
                throw ValidationException::withMessages(['movement' => 'Only posted stock movements can be reversed.']);
            }
            if (StockMovement::whereCompanyId($companyId)->where('reversal_of_id', $movement->id)->exists()) {
                throw ValidationException::withMessages(['movement' => 'This stock movement already has a reversal document.']);
            }

            $reversal = StockMovement::create([
                'company_id' => $companyId, 'warehouse_id' => $movement->kind === 'transfer' ? $movement->destination_warehouse_id : $movement->warehouse_id,
                'destination_warehouse_id' => $movement->kind === 'transfer' ? $movement->warehouse_id : null,
                'item_id' => $movement->item_id, 'number' => 'REV-'.strtoupper(substr(str_replace('-', '', $movement->id), 0, 16)),
                'movement_date' => now()->toDateString(), 'kind' => $this->reversalKind($movement),
                'adjustment_direction' => $movement->kind === 'adjustment' ? ($movement->adjustment_direction === 'increase' ? 'decrease' : 'increase') : null,
                'reference' => $movement->number, 'quantity' => $movement->quantity, 'unit_cost' => $movement->unit_cost,
                'reversal_of_id' => $movement->id, 'reversal_reason' => $reason, 'notes' => 'System-created reversal of '.$movement->number.'.', 'posted' => false,
            ]);
            $this->postLocked($reversal, $companyId, $userId);

            return $reversal->fresh(['item', 'warehouse', 'destinationWarehouse']);
        });
    }

    private function postLocked(StockMovement $movement, string $companyId, string $userId): void
    {
        $item = Item::whereCompanyId($companyId)->lockForUpdate()->findOrFail($movement->item_id);
        if (! $item->active || $item->item_type !== 'stock') {
            throw ValidationException::withMessages(['item_id' => 'Only active stock items can be posted to inventory.']);
        }
        if ($movement->kind === 'receipt' && ! $item->for_purchase) {
            throw ValidationException::withMessages(['item_id' => 'This item is not enabled for purchasing.']);
        }
        if ($movement->kind === 'issue' && ! $item->for_sale) {
            throw ValidationException::withMessages(['item_id' => 'This item is not enabled for sale or issue.']);
        }

        if ($movement->kind === 'transfer') {
            $this->postTransfer($movement, $item, $companyId, $userId);

            return;
        }

        $delta = $this->quantityDelta($movement);
        $unitCost = $this->unitCost($movement, $item);
        $balance = $this->balance($movement->warehouse_id, $item);
        $newBalanceQuantity = round((float) $balance->quantity + $delta, 2);
        if ($newBalanceQuantity < 0) {
            throw ValidationException::withMessages(['quantity' => 'This posted movement would make inventory negative in the selected warehouse.']);
        }

        $newBalanceCost = $this->averageCost($balance, $delta, $unitCost, $newBalanceQuantity);
        $newItemQuantity = round((float) $item->quantity + $delta, 2);
        if ($newItemQuantity < 0) {
            throw ValidationException::withMessages(['quantity' => 'This posted movement would make company inventory negative.']);
        }
        $newItemCost = $this->itemAverageCost($item, $delta, $unitCost, $newItemQuantity);

        $balance->update(['quantity' => $newBalanceQuantity, 'average_cost' => $newBalanceCost]);
        $item->update(['quantity' => $newItemQuantity, 'cost' => $newItemCost]);
        $this->markPosted($movement, $userId, $unitCost);
        $this->transaction($movement, $item, $movement->warehouse_id, $delta, $unitCost, $newBalanceQuantity, $newBalanceCost);
    }

    private function postTransfer(StockMovement $movement, Item $item, string $companyId, string $userId): void
    {
        if (! $movement->destination_warehouse_id || $movement->destination_warehouse_id === $movement->warehouse_id) {
            throw ValidationException::withMessages(['destination_warehouse_id' => 'Choose a different active destination warehouse for a transfer.']);
        }
        Warehouse::whereCompanyId($companyId)->whereActive(true)->findOrFail($movement->destination_warehouse_id);
        $source = $this->balance($movement->warehouse_id, $item);
        $destination = $this->balance($movement->destination_warehouse_id, $item);
        $quantity = (float) $movement->quantity;
        $newSourceQuantity = round((float) $source->quantity - $quantity, 2);
        if ($newSourceQuantity < 0) {
            throw ValidationException::withMessages(['quantity' => 'This transfer would make the source warehouse inventory negative.']);
        }
        $unitCost = $movement->reversal_of_id && $movement->unit_cost !== null ? (float) $movement->unit_cost : (float) $source->average_cost;
        $newDestinationQuantity = round((float) $destination->quantity + $quantity, 2);
        $newDestinationCost = $this->averageCost($destination, $quantity, $unitCost, $newDestinationQuantity);

        $source->update(['quantity' => $newSourceQuantity]);
        $destination->update(['quantity' => $newDestinationQuantity, 'average_cost' => $newDestinationCost]);
        $this->markPosted($movement, $userId, $unitCost);
        $this->transaction($movement, $item, $movement->warehouse_id, -$quantity, $unitCost, $newSourceQuantity, (float) $source->average_cost);
        $this->transaction($movement, $item, $movement->destination_warehouse_id, $quantity, $unitCost, $newDestinationQuantity, $newDestinationCost);
    }

    private function markPosted(StockMovement $movement, string $userId, float $unitCost): void
    {
        $movement->update(['posted' => true, 'posted_at' => now(), 'posted_by' => $userId, 'unit_cost' => $unitCost]);
    }

    private function transaction(StockMovement $movement, Item $item, string $warehouseId, float $delta, float $unitCost, float $quantity, float $averageCost): void
    {
        InventoryTransaction::create([
            'company_id' => $item->company_id, 'stock_movement_id' => $movement->id, 'warehouse_id' => $warehouseId, 'item_id' => $item->id,
            'quantity_delta' => $delta, 'unit_cost' => $unitCost, 'value_delta' => round($delta * $unitCost, 2),
            'balance_quantity' => $quantity, 'balance_average_cost' => $averageCost,
        ]);
    }

    private function balance(string $warehouseId, Item $item): InventoryBalance
    {
        $balance = InventoryBalance::where('company_id', $item->company_id)->where('warehouse_id', $warehouseId)->where('item_id', $item->id)->lockForUpdate()->first();
        if ($balance) {
            return $balance;
        }
        $hasExistingBalance = InventoryBalance::where('company_id', $item->company_id)->where('item_id', $item->id)->exists();
        InventoryBalance::create(['company_id' => $item->company_id, 'warehouse_id' => $warehouseId, 'item_id' => $item->id, 'quantity' => $hasExistingBalance ? 0 : $item->quantity, 'average_cost' => $item->cost]);

        return InventoryBalance::where('company_id', $item->company_id)->where('warehouse_id', $warehouseId)->where('item_id', $item->id)->lockForUpdate()->firstOrFail();
    }

    private function quantityDelta(StockMovement $movement): float
    {
        return match ($movement->kind) {
            'receipt' => (float) $movement->quantity, 'issue' => -(float) $movement->quantity,
            'adjustment' => $movement->adjustment_direction === 'decrease' ? -(float) $movement->quantity : (float) $movement->quantity,
            default => throw ValidationException::withMessages(['kind' => 'Unsupported stock movement type.']),
        };
    }

    private function unitCost(StockMovement $movement, Item $item): float
    {
        $requiresCost = $movement->kind === 'receipt' || ($movement->kind === 'adjustment' && $movement->adjustment_direction === 'increase');
        if ($requiresCost && $movement->unit_cost === null) {
            throw ValidationException::withMessages(['unit_cost' => 'A unit cost is required for receipts and increasing adjustments.']);
        }
        if ($movement->reversal_of_id && $movement->unit_cost !== null) {
            return (float) $movement->unit_cost;
        }

        return $requiresCost ? (float) $movement->unit_cost : (float) $item->cost;
    }

    private function averageCost(InventoryBalance $balance, float $delta, float $unitCost, float $newQuantity): float
    {
        if ($delta <= 0 || $newQuantity <= 0) {
            return (float) $balance->average_cost;
        }

        return round((((float) $balance->quantity * (float) $balance->average_cost) + ($delta * $unitCost)) / $newQuantity, 2);
    }

    private function itemAverageCost(Item $item, float $delta, float $unitCost, float $newQuantity): float
    {
        if ($delta <= 0 || $newQuantity <= 0) {
            return (float) $item->cost;
        }

        return round((((float) $item->quantity * (float) $item->cost) + ($delta * $unitCost)) / $newQuantity, 2);
    }

    private function reversalKind(StockMovement $movement): string
    {
        return match ($movement->kind) {
            'receipt' => 'issue', 'issue' => 'receipt', 'adjustment' => 'adjustment', 'transfer' => 'transfer'
        };
    }
}
