<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use UsesUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'opening_balance' => 'decimal:2'];
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class, 'customer_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }

    public function customerReceipts()
    {
        return $this->hasMany(CustomerReceipt::class, 'customer_id');
    }

    public function vendorBills()
    {
        return $this->hasMany(VendorBill::class, 'vendor_id');
    }

    public function vendorPayments()
    {
        return $this->hasMany(VendorPayment::class, 'vendor_id');
    }

    public function getOpenBalanceAttribute(): float
    {
        if ($this->kind === 'vendor') {
            $billed = (float) $this->vendorBills()->where('status', '!=', 'void')->sum('total');
            $paid = (float) VendorPaymentAllocation::where('company_id', $this->company_id)
                ->whereIn('vendor_bill_id', $this->vendorBills()->select('id'))->sum('amount');

            return round((float) $this->opening_balance + $billed - $paid, 2);
        }

        if ($this->kind !== 'customer') {
            return 0.0;
        }

        $invoiced = (float) $this->invoices()->where('status', '!=', 'void')->sum('total');
        $allocated = (float) ReceiptAllocation::where('company_id', $this->company_id)
            ->whereIn('invoice_id', $this->invoices()->select('id'))->sum('amount');

        return round((float) $this->opening_balance + $invoiced - $allocated, 2);
    }
}
