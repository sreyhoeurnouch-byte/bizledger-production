<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class VendorPaymentAllocation extends Model
{
    use UsesUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payment()
    {
        return $this->belongsTo(VendorPayment::class, 'vendor_payment_id');
    }

    public function bill()
    {
        return $this->belongsTo(VendorBill::class, 'vendor_bill_id');
    }
}
