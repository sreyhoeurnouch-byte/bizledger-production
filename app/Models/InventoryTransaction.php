<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    use UsesUuid;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity_delta' => 'decimal:2', 'unit_cost' => 'decimal:2', 'value_delta' => 'decimal:2', 'balance_quantity' => 'decimal:2', 'balance_average_cost' => 'decimal:2', 'created_at' => 'datetime'];
    }

    public function stockMovement()
    {
        return $this->belongsTo(StockMovement::class);
    }
}
