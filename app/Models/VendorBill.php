<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class VendorBill extends Model
{
    use UsesUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['bill_date' => 'date', 'due_date' => 'date', 'issued_at' => 'datetime', 'total' => 'decimal:2'];
    }

    public function vendor()
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function lines()
    {
        return $this->hasMany(VendorBillLine::class);
    }

    public function allocations()
    {
        return $this->hasMany(VendorPaymentAllocation::class);
    }

    public function getAllocatedAmountAttribute(): float
    {
        return round((float) $this->allocations()->sum('amount'), 2);
    }

    public function getOpenBalanceAttribute(): float
    {
        return round((float) $this->total - $this->allocated_amount, 2);
    }
}
