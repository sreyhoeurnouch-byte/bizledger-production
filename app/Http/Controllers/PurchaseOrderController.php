<?php

namespace App\Http\Controllers;

use App\Domain\Purchasing\ReceivePurchaseOrder;
use App\Models\{Contact, Item, PurchaseOrder, Warehouse};
use App\Support\AuditsLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    use AuditsLedger;
    public function __construct(private ReceivePurchaseOrder $receiving) {}

    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        return view('purchasing.index', [
            'orders' => PurchaseOrder::with(['vendor', 'lines'])->whereCompanyId($companyId)->when($request->filled('q'), fn ($query) => $query->where(fn ($search) => $search->where('number', 'like', '%'.$request->q.'%')->orWhereHas('vendor', fn ($vendor) => $vendor->where('code', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%'))))->latest('order_date')->paginate(20)->withQueryString(),
            'vendors' => Contact::whereCompanyId($companyId)->whereKind('vendor')->whereActive(true)->orderBy('name')->get(),
            'items' => Item::whereCompanyId($companyId)->whereActive(true)->where('for_purchase', true)->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, string $order)
    {
        $order = PurchaseOrder::with(['vendor', 'lines.item', 'goodsReceipts.warehouse', 'goodsReceipts.lines.item'])->whereCompanyId($request->user()->company_id)->findOrFail($order);
        $warehouses = Warehouse::whereCompanyId($request->user()->company_id)->whereActive(true)->orderBy('name')->get();
        return view('purchasing.show', compact('order', 'warehouses'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', PurchaseOrder::class);
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'number' => ['required', 'max:40', Rule::unique('purchase_orders')->where(fn ($query) => $query->where('company_id', $companyId))],
            'vendor_id' => ['required', Rule::exists('contacts', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('kind', 'vendor')->where('active', true))],
            'order_date' => ['required', 'date', 'before_or_equal:today'], 'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'status' => ['required', Rule::in(['draft'])], 'notes' => ['nullable', 'string', 'max:2000'], 'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.item_id' => ['required', Rule::exists('items', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('active', true)->where('for_purchase', true))],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'], 'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);
        $itemIds = collect($data['lines'])->pluck('item_id');
        if ($itemIds->duplicates()->isNotEmpty()) throw ValidationException::withMessages(['lines' => 'Each product can appear only once on a purchase order. Combine duplicate quantities instead.']);
        $order = DB::transaction(function () use ($data, $companyId) {
            $lines = collect($data['lines'])->values()->map(fn ($line, $index) => ['company_id' => $companyId, 'item_id' => $line['item_id'], 'line_number' => $index + 1, 'quantity' => $line['quantity'], 'unit_cost' => $line['unit_cost'], 'line_total' => round((float) $line['quantity'] * (float) $line['unit_cost'], 2)]);
            $order = PurchaseOrder::create(collect($data)->except('lines')->all() + ['company_id' => $companyId, 'total' => $lines->sum('line_total')]);
            $order->lines()->createMany($lines->all());
            return $order;
        });
        $this->audit('created_draft', $order, ['number' => $order->number, 'line_count' => count($data['lines']), 'total' => $order->total]);
        return redirect()->route('purchasing.show', $order)->with('success', 'Purchase order created as a draft.');
    }

    public function approve(Request $request, string $order)
    {
        $pendingOrder = PurchaseOrder::whereCompanyId($request->user()->company_id)->findOrFail($order);
        $this->authorize('approve', $pendingOrder);
        $order = DB::transaction(function () use ($request, $order) {
            $record = PurchaseOrder::whereCompanyId($request->user()->company_id)->lockForUpdate()->findOrFail($order);
            if ($record->status !== 'draft') throw ValidationException::withMessages(['order' => 'Only draft purchase orders can be approved.']);
            $record->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $request->user()->id]);
            return $record;
        });
        $this->audit('approved', $order, ['number' => $order->number]);
        return back()->with('success', 'Purchase order approved for receiving.');
    }

    public function receive(Request $request, string $order)
    {
        $companyId = $request->user()->company_id;
        $purchaseOrder = PurchaseOrder::whereCompanyId($companyId)->findOrFail($order);
        $this->authorize('receive', $purchaseOrder);
        $data = $request->validate([
            'number' => ['required', 'max:40', Rule::unique('goods_receipts')->where(fn ($query) => $query->where('company_id', $companyId))],
            'receipt_date' => ['required', 'date', 'before_or_equal:today'],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('active', true))],
            'notes' => ['nullable', 'string', 'max:2000'], 'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'uuid'], 'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);
        $receipt = $this->receiving->receive($order, $companyId, $request->user()->id, $data);
        $this->audit('goods_received', $receipt, ['number' => $receipt->number, 'purchase_order_id' => $order, 'line_count' => count($data['lines'])]);
        return redirect()->route('purchasing.show', $order)->with('success', 'Goods receipt posted and inventory updated.');
    }
}
