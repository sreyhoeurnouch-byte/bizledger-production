<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Support\AuditsLedger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    use AuditsLedger;

    public function vendors(Request $request)
    {
        return $this->index($request, 'vendor');
    }

    public function customers(Request $request)
    {
        return $this->index($request, 'customer');
    }

    public function storeVendor(Request $request)
    {
        return $this->store($request, 'vendor');
    }

    public function storeCustomer(Request $request)
    {
        return $this->store($request, 'customer');
    }

    public function showVendor(Request $request, string $contact)
    {
        return $this->show($request, $contact, 'vendor');
    }

    public function showCustomer(Request $request, string $contact)
    {
        return $this->show($request, $contact, 'customer');
    }

    public function editVendor(Request $request, string $contact)
    {
        return $this->edit($request, $contact, 'vendor');
    }

    public function editCustomer(Request $request, string $contact)
    {
        return $this->edit($request, $contact, 'customer');
    }

    public function updateVendor(Request $request, string $contact)
    {
        return $this->update($request, $contact, 'vendor');
    }

    public function updateCustomer(Request $request, string $contact)
    {
        return $this->update($request, $contact, 'customer');
    }

    private function index(Request $request, string $kind)
    {
        $records = Contact::whereCompanyId($request->user()->company_id)->whereKind($kind)
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($search) => $search
                ->where('code', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')->orWhere('email', 'like', '%'.$request->q.'%')))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('contacts.index', compact('kind', 'records'));
    }

    private function show(Request $request, string $id, string $kind)
    {
        $contact = $this->contact($request, $id, $kind);
        $orders = $kind === 'vendor'
            ? PurchaseOrder::whereCompanyId($request->user()->company_id)->where('vendor_id', $contact->id)->latest('order_date')->paginate(15)
            : SalesOrder::whereCompanyId($request->user()->company_id)->where('customer_id', $contact->id)->latest('order_date')->paginate(15);
        $invoices = $kind === 'customer'
            ? Invoice::whereCompanyId($request->user()->company_id)->where('customer_id', $contact->id)->with('allocations')->latest('invoice_date')->get()
            : collect();

        return view('contacts.show', compact('kind', 'contact', 'orders', 'invoices'));
    }

    private function edit(Request $request, string $id, string $kind)
    {
        return view('contacts.edit', ['contact' => $this->contact($request, $id, $kind), 'kind' => $kind]);
    }

    private function store(Request $request, string $kind)
    {
        $contact = Contact::create($this->validated($request, $kind));
        $this->audit('created', $contact, ['kind' => $kind, 'code' => $contact->code]);

        return redirect()->route($kind === 'vendor' ? 'vendors.show' : 'customers.show', $contact)->with('success', ucfirst($kind).' created successfully.');
    }

    private function update(Request $request, string $id, string $kind)
    {
        $contact = $this->contact($request, $id, $kind);
        $contact->update($this->validated($request, $kind, $contact));
        $this->audit('updated', $contact, ['kind' => $kind, 'code' => $contact->code]);

        return redirect()->route($kind === 'vendor' ? 'vendors.show' : 'customers.show', $contact)->with('success', ucfirst($kind).' updated successfully.');
    }

    private function validated(Request $request, string $kind, ?Contact $contact = null): array
    {
        $companyId = $request->user()->company_id;
        $codeRule = Rule::unique('contacts')->where(fn ($query) => $query->where('company_id', $companyId));
        if ($contact) {
            $codeRule->ignore($contact->id);
        }

        return $request->validate([
            'code' => ['required', 'string', 'max:32', $codeRule], 'name' => ['required', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:50'],
            'tax_id' => ['nullable', 'string', 'max:80'], 'address' => ['nullable', 'string', 'max:2000'],
            'opening_balance' => ['required', 'numeric', 'min:0'], 'active' => ['nullable', 'boolean'],
        ]) + ['company_id' => $companyId, 'kind' => $kind, 'active' => $request->boolean('active', true)];
    }

    private function contact(Request $request, string $id, string $kind): Contact
    {
        return Contact::whereCompanyId($request->user()->company_id)->whereKind($kind)->findOrFail($id);
    }
}
