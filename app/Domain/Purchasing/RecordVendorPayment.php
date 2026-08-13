<?php

namespace App\Domain\Purchasing;

use App\Models\VendorBill;
use App\Models\VendorPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordVendorPayment
{
    public function record(string $companyId, string $userId, array $data): VendorPayment
    {
        return DB::transaction(function () use ($companyId, $userId, $data) {
            $allocated = round((float) collect($data['allocations'])->sum('amount'), 2);
            if ($allocated !== round((float) $data['amount'], 2)) {
                throw ValidationException::withMessages(['allocations' => 'Payment allocations must equal the payment amount.']);
            }
            $billIds = collect($data['allocations'])->pluck('vendor_bill_id');
            if ($billIds->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['allocations' => 'A bill can be allocated only once per payment.']);
            }
            $bills = VendorBill::whereCompanyId($companyId)->where('vendor_id', $data['vendor_id'])->whereIn('id', $billIds)->lockForUpdate()->get()->keyBy('id');
            if ($bills->count() !== $billIds->count()) {
                throw ValidationException::withMessages(['allocations' => 'Every allocated bill must be open for the selected vendor.']);
            }
            foreach ($data['allocations'] as $allocation) {
                $bill = $bills->get($allocation['vendor_bill_id']);
                if ($bill->status !== 'open' || (float) $allocation['amount'] > $bill->open_balance) {
                    throw ValidationException::withMessages(['allocations' => 'A payment allocation cannot exceed a bill open balance.']);
                }
            }

            $payment = VendorPayment::create([
                'company_id' => $companyId,
                'vendor_id' => $data['vendor_id'],
                'number' => $data['number'],
                'payment_date' => $data['payment_date'],
                'amount' => $data['amount'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_by' => $userId,
            ]);
            foreach ($data['allocations'] as $allocation) {
                $payment->allocations()->create([
                    'company_id' => $companyId,
                    'vendor_bill_id' => $allocation['vendor_bill_id'],
                    'amount' => $allocation['amount'],
                ]);
                $bill = $bills->get($allocation['vendor_bill_id'])->fresh();
                if ($bill->open_balance <= 0) {
                    $bill->update(['status' => 'paid']);
                }
            }

            return $payment->fresh(['vendor', 'allocations.bill']);
        });
    }
}
