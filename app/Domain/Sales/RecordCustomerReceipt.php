<?php

namespace App\Domain\Sales;

use App\Models\CustomerReceipt;
use App\Models\Invoice;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordCustomerReceipt
{
    public function record(string $companyId, string $userId, array $data): CustomerReceipt
    {
        return DB::transaction(function () use ($companyId, $userId, $data) {
            $allocationTotal = round((float) collect($data['allocations'])->sum('amount'), 2);
            if ($allocationTotal !== round((float) $data['amount'], 2)) {
                throw ValidationException::withMessages(['allocations' => 'Receipt allocations must equal the receipt amount.']);
            }
            $invoiceIds = collect($data['allocations'])->pluck('invoice_id');
            if ($invoiceIds->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['allocations' => 'An invoice can be allocated only once per receipt.']);
            }
            $invoices = Invoice::whereCompanyId($companyId)->where('customer_id', $data['customer_id'])->whereIn('id', $invoiceIds)->lockForUpdate()->get()->keyBy('id');
            if ($invoices->count() !== $invoiceIds->count()) {
                throw ValidationException::withMessages(['allocations' => 'Every allocated invoice must be an open invoice for this customer.']);
            }
            foreach ($data['allocations'] as $allocation) {
                $invoice = $invoices->get($allocation['invoice_id']);
                if ($invoice->status !== 'open' || (float) $allocation['amount'] > $invoice->open_balance) {
                    throw ValidationException::withMessages(['allocations' => 'A receipt allocation cannot exceed an invoice open balance.']);
                }
            }

            $receipt = CustomerReceipt::create([
                'company_id' => $companyId,
                'customer_id' => $data['customer_id'],
                'number' => $data['number'],
                'receipt_date' => $data['receipt_date'],
                'amount' => $data['amount'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'received_by' => $userId,
            ]);
            foreach ($data['allocations'] as $allocation) {
                $receipt->allocations()->create([
                    'company_id' => $companyId,
                    'invoice_id' => $allocation['invoice_id'],
                    'amount' => $allocation['amount'],
                ]);
                $invoice = $invoices->get($allocation['invoice_id'])->fresh();
                if ($invoice->open_balance <= 0) {
                    $invoice->update(['status' => 'paid']);
                }
            }
            $this->closePaidSalesOrders($companyId, $invoiceIds);

            return $receipt->fresh(['customer', 'allocations.invoice']);
        });
    }

    private function closePaidSalesOrders(string $companyId, $invoiceIds): void
    {
        $orderIds = Invoice::whereCompanyId($companyId)->whereIn('id', $invoiceIds)->pluck('sales_order_id')->filter()->unique();
        foreach (SalesOrder::whereCompanyId($companyId)->whereIn('id', $orderIds)->lockForUpdate()->get() as $order) {
            $hasOpenInvoice = $order->invoices()->where('status', 'open')->exists();
            if (! $hasOpenInvoice && $order->status === 'invoiced') {
                $order->update(['status' => 'closed']);
            }
        }
    }
}
