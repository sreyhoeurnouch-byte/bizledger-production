<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    use UsesUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['receipt_date' => 'date', 'posted_at' => 'datetime'];
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines()
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }
}
