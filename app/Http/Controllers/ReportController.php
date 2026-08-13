<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\VendorBill;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function stock(Request $request)
    {
        $items = Item::whereCompanyId($request->user()->company_id)->whereActive(true)
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($search) => $search->where('sku', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')->orWhere('barcode', 'like', '%'.$request->q.'%')))
            ->orderBy('name')->get();

        return view('reports.stock-valuation', compact('items'));
    }

    public function receivables(Request $request)
    {
        $companyId = $request->user()->company_id;
        $invoices = Invoice::whereCompanyId($companyId)->where('status', 'open')->with(['customer', 'allocations'])->get();
        $buckets = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0];
        $rows = $invoices->groupBy('customer_id')->map(function ($customerInvoices) use (&$buckets) {
            $row = ['customer' => $customerInvoices->first()->customer, 'current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0, 'total' => 0.0];
            foreach ($customerInvoices as $invoice) {
                $open = $invoice->open_balance;
                $days = max(0, $invoice->due_date?->diffInDays(today(), false) ?? 0);
                $bucket = match (true) {
                    $days === 0 => 'current',
                    $days <= 30 => '1_30',
                    $days <= 60 => '31_60',
                    $days <= 90 => '61_90',
                    default => 'over_90',
                };
                $row[$bucket] += $open;
                $row['total'] += $open;
                $buckets[$bucket] += $open;
            }

            return $row;
        })->values();
        $openingBalances = Contact::whereCompanyId($companyId)->whereKind('customer')->where('opening_balance', '>', 0)->get();
        foreach ($openingBalances as $customer) {
            $existing = $rows->firstWhere('customer.id', $customer->id);
            if ($existing) {
                $existing['current'] += (float) $customer->opening_balance;
                $existing['total'] += (float) $customer->opening_balance;
            } else {
                $rows->push(['customer' => $customer, 'current' => (float) $customer->opening_balance, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0, 'total' => (float) $customer->opening_balance]);
            }
            $buckets['current'] += (float) $customer->opening_balance;
        }

        return view('reports.receivables-aging', ['rows' => $rows, 'buckets' => $buckets, 'total' => array_sum($buckets)]);
    }

    public function payables(Request $request)
    {
        $companyId = $request->user()->company_id;
        $bills = VendorBill::whereCompanyId($companyId)->where('status', 'open')->with(['vendor', 'allocations'])->get();
        $buckets = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0];
        $rows = $bills->groupBy('vendor_id')->map(function ($vendorBills) use (&$buckets) {
            $row = ['contact' => $vendorBills->first()->vendor, 'current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0, 'total' => 0.0];
            foreach ($vendorBills as $bill) {
                $open = $bill->open_balance;
                $days = max(0, $bill->due_date?->diffInDays(today(), false) ?? 0);
                $bucket = match (true) {
                    $days === 0 => 'current',
                    $days <= 30 => '1_30',
                    $days <= 60 => '31_60',
                    $days <= 90 => '61_90',
                    default => 'over_90',
                };
                $row[$bucket] += $open;
                $row['total'] += $open;
                $buckets[$bucket] += $open;
            }

            return $row;
        })->values();
        foreach (Contact::whereCompanyId($companyId)->whereKind('vendor')->where('opening_balance', '>', 0)->get() as $vendor) {
            $existing = $rows->firstWhere('contact.id', $vendor->id);
            if ($existing) {
                $existing['current'] += (float) $vendor->opening_balance;
                $existing['total'] += (float) $vendor->opening_balance;
            } else {
                $rows->push(['contact' => $vendor, 'current' => (float) $vendor->opening_balance, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0, 'total' => (float) $vendor->opening_balance]);
            }
            $buckets['current'] += (float) $vendor->opening_balance;
        }

        return view('reports.payables-aging', ['rows' => $rows, 'buckets' => $buckets, 'total' => array_sum($buckets)]);
    }
}
