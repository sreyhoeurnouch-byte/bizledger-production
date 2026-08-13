<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class VendorBillLine extends Model
{
    use UsesUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_cost' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function vendorBill()
    {
        return $this->belongsTo(VendorBill::class);
    }
}
