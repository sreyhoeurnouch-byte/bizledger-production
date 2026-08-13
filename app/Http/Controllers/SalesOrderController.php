<?php

namespace App\Http\Controllers;

use App\Domain\Sales\DeliverSalesOrder;
use App\Domain\Sales\InvoiceSalesOrder;
use App\Domain\Sales\RecordCustomerReceipt;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Support\AuditsLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesOrderController extends Controller
{
    use AuditsLedger;

    public function __construct(
        private DeliverSalesOrder $delivery,
        private InvoiceSalesOrder $invoicing,
        private RecordCustomerReceipt $receipts,
    ) {}

    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        return view('sales.index', [
            'orders' => SalesOrder::with(['customer', 'lines'])->whereCompanyId($companyId)
                ->when($request->filled('q'), fn ($query) => $query->where(fn ($search) => $search->where('number', 'like', '%'.$request->q.'%')->orWhereHas('customer', fn ($customer) => $customer->where('code', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%'))))
                ->latest('order_date')->paginate(20)->withQueryString(),
            'customers' => Contact::whereCompanyId($companyId)->whereKind('customer')->whereActive(true)->orderBy('name')->get(),
            'items' => Item::whereCompanyId($companyId)->whereActive(true)->where('for_sale', true)->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, string $order)
    {
        $companyId = $request->user()->company_id;
        $order = SalesOrder::with([
            'customer', 'lines.item', 'deliveries.warehouse', 'deliveries.lines.item',
            'invoices.lines.item', 'invoices.allocations',
        ])->whereCompanyId($companyId)->findOrFail($order);
        $warehouses = Warehouse::whereCompanyId($companyId)->whereActive(true)->orderBy('name')->get();
        $openInvoices = Invoice::whereCompanyId($companyId)->where('customer_id', $order->customer_id)->where('status', 'open')->with('allocations')->orderBy('invoice_date')->get();

        return view('sales.show', compact('order', 'warehouses', 'openInvoices'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', SalesOrder::class);
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'number' => ['required', 'max:40', Rule::unique('sales_orders')->where(fn ($query) => $query->where('company_id', $companyId))],
            'customer_id' => ['required', Rule::exists('contacts', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('kind', 'customer')->where('active', true))],
            'order_date' => ['required', 'date', 'before_or_equal:today'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'status' => ['required', Rule::in(['draft'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.item_id' => ['required', Rule::exists('items', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('active', true)->where('for_sale', true))],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
        $itemIds = collect($data['lines'])->pluck('item_id');
        if ($itemIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['lines' => 'Each product can appear only once on a sales order. Combine duplicate quantities instead.']);
        }

        $order = DB::transaction(function () use ($data, $companyId) {
            $lines = collect($data['lines'])->values()->map(fn ($line, $index) => [
                'company_id' => $companyId,
                'item_id' => $line['item_id'],
                'line_number' => $index + 1,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => round((float) $line['quantity'] * (float) $line['unit_price'], 2),
            ]);
            $order = SalesOrder::create(collect($data)->except('lines')->all() + ['company_id' => $companyId, 'total' => $lines->sum('line_total')]);
            $order->lines()->createMany($lines->all());

            return $order;
        });
        $this->audit('created_draft', $order, ['number' => $order->number, 'line_count' => count($data['lines']), 'total' => $order->total]);

        return redirect()->route('sales.show', $order)->with('success', 'Sales order created as a draft.');
    }

    public function approve(Request $request, string $order)
    {
        $pendingOrder = SalesOrder::whereCompanyId($request->user()->company_id)->findOrFail($order);
        $this->authorize('approve', $pendingOrder);
        $order = DB::transaction(function () use ($request, $order) {
            $record = SalesOrder::whereCompanyId($request->user()->company_id)->lockForUpdate()->findOrFail($order);
            if ($record->status !== 'draft') {
                throw ValidationException::withMessages(['order' => 'Only draft sales orders can be approved.']);
            }
            $record->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $request->user()->id]);

            return $record;
        });
        $this->audit('approved', $order, ['number' => $order->number]);

        return back()->with('success', 'Sales order approved for delivery.');
    }

    public function deliver(Request $request, string $order)
    {
        $companyId = $request->user()->company_id;
        $salesOrder = SalesOrder::whereCompanyId($companyId)->findOrFail($order);
        $this->authorize('deliver', $salesOrder);
        $data = $request->validate([
            'number' => ['required', 'max:40', Rule::unique('deliveries')->where(fn ($query) => $query->where('company_id', $companyId))],
            'delivery_date' => ['required', 'date', 'before_or_equal:today'],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('active', true))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sales_order_line_id' => ['required', 'uuid'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);
        $delivery = $this->delivery->deliver($order, $companyId, $request->user()->id, $data);
        $this->audit('delivered', $delivery, ['number' => $delivery->number, 'sales_order_id' => $order, 'line_count' => count($data['lines'])]);

        return redirect()->route('sales.show', $order)->with('success', 'Delivery posted and inventory updated.');
    }

    public function invoice(Request $request, string $order)
    {
        $companyId = $request->user()->company_id;
        $salesOrder = SalesOrder::whereCompanyId($companyId)->findOrFail($order);
        $this->authorize('invoice', $salesOrder);
        $data = $request->validate([
            'number' => ['required', 'max:40', Rule::unique('invoices')->where(fn ($query) => $query->where('company_id', $companyId))],
            'invoice_date' => ['required', 'date', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sales_order_line_id' => ['required', 'uuid'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);
        $invoice = $this->invoicing->issue($order, $companyId, $request->user()->id, $data);
        $this->audit('invoiced', $invoice, ['number' => $invoice->number, 'sales_order_id' => $order, 'total' => $invoice->total]);

        return redirect()->route('sales.show', $order)->with('success', 'Invoice issued from delivered quantities.');
    }

    public function receipt(Request $request, string $order)
    {
        $companyId = $request->user()->company_id;
        $salesOrder = SalesOrder::whereCompanyId($companyId)->findOrFail($order);
        $this->authorize('invoice', $salesOrder);
        $data = $request->validate([
            'number' => ['required', 'max:40', Rule::unique('customer_receipts')->where(fn ($query) => $query->where('company_id', $companyId))],
            'receipt_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => ['required', 'uuid'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ]) + ['customer_id' => $salesOrder->customer_id];
        $receipt = $this->receipts->record($companyId, $request->user()->id, $data);
        $this->audit('customer_receipt_recorded', $receipt, ['number' => $receipt->number, 'sales_order_id' => $order, 'amount' => $receipt->amount]);

        return redirect()->route('sales.show', $order)->with('success', 'Customer receipt recorded.');
    }
}
