<?php

namespace App\Http\Controllers;

use App\Domain\Purchasing\RecordVendorPayment;
use App\Models\Contact;
use App\Models\VendorBill;
use App\Support\AuditsLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayableController extends Controller
{
    use AuditsLedger;

    public function __construct(private RecordVendorPayment $payments) {}

    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        return view('payables.index', [
            'bills' => VendorBill::whereCompanyId($companyId)->with(['vendor', 'allocations'])->latest('bill_date')->paginate(20),
            'vendors' => Contact::whereCompanyId($companyId)->whereKind('vendor')->whereActive(true)->orderBy('name')->get(),
            'openBills' => VendorBill::whereCompanyId($companyId)->where('status', 'open')->with(['vendor', 'allocations'])->orderBy('due_date')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'number' => ['required', 'max:40', Rule::unique('vendor_bills')->where(fn ($query) => $query->where('company_id', $companyId))],
            'vendor_id' => ['required', Rule::exists('contacts', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('kind', 'vendor')->where('active', true))],
            'bill_date' => ['required', 'date', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date', 'after_or_equal:bill_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);
        $bill = DB::transaction(function () use ($companyId, $data, $request) {
            $lines = collect($data['lines'])->values()->map(fn ($line, $index) => [
                'company_id' => $companyId,
                'line_number' => $index + 1,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'line_total' => round((float) $line['quantity'] * (float) $line['unit_cost'], 2),
            ]);
            $bill = VendorBill::create(collect($data)->except('lines')->all() + [
                'company_id' => $companyId,
                'issued_by' => $request->user()->id,
                'total' => $lines->sum('line_total'),
            ]);
            $bill->lines()->createMany($lines->all());

            return $bill;
        });
        $this->audit('vendor_bill_recorded', $bill, ['number' => $bill->number, 'total' => $bill->total]);

        return redirect()->route('payables.index')->with('success', 'Vendor bill recorded.');
    }

    public function payment(Request $request)
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'number' => ['required', 'max:40', Rule::unique('vendor_payments')->where(fn ($query) => $query->where('company_id', $companyId))],
            'vendor_id' => ['required', Rule::exists('contacts', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('kind', 'vendor')->where('active', true))],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.vendor_bill_id' => ['required', 'uuid'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);
        $payment = $this->payments->record($companyId, $request->user()->id, $data);
        $this->audit('vendor_payment_recorded', $payment, ['number' => $payment->number, 'amount' => $payment->amount]);

        return redirect()->route('payables.index')->with('success', 'Vendor payment recorded.');
    }
}
