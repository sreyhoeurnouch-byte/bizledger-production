<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use UsesUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'due_date' => 'date', 'issued_at' => 'datetime', 'total' => 'decimal:2'];
    }

    public function customer()
    {
        return $this->belongsTo(Contact::class, 'customer_id');
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function lines()
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function allocations()
    {
        return $this->hasMany(ReceiptAllocation::class);
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
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
