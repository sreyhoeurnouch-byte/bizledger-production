<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use UsesUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'for_purchase' => 'boolean', 'for_sale' => 'boolean', 'cost' => 'decimal:2', 'sale_price' => 'decimal:2', 'quantity' => 'decimal:2', 'reorder_level' => 'decimal:2'];
    }

    public function group()
    {
        return $this->belongsTo(ItemGroup::class, 'group_id');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function inventoryBalances()
    {
        return $this->hasMany(InventoryBalance::class);
    }
}
