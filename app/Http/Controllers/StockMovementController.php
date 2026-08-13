<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\PostStockMovement;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\AuditsLedger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockMovementController extends Controller
{
    use AuditsLedger;

    public function __construct(private PostStockMovement $posting) {}

    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        return view('stock.index', [
            'movements' => StockMovement::with(['item', 'warehouse', 'destinationWarehouse', 'inventoryTransactions', 'reversal'])->whereCompanyId($companyId)->when($request->filled('q'), fn ($query) => $query->where(fn ($search) => $search->where('number', 'like', '%'.$request->q.'%')->orWhere('reference', 'like', '%'.$request->q.'%')->orWhereHas('item', fn ($item) => $item->where('sku', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%'))))->latest('movement_date')->paginate(20)->withQueryString(),
            'items' => Item::whereCompanyId($companyId)->whereActive(true)->where('item_type', 'stock')->orderBy('name')->get(),
            'warehouses' => Warehouse::whereCompanyId($companyId)->whereActive(true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $postImmediately = $request->boolean('posted');
        $movement = $this->posting->create($data, $request->user()->company_id, $request->user()->id, $postImmediately);
        $this->audit($postImmediately ? 'created_and_posted' : 'created_draft', $movement, ['number' => $movement->number, 'kind' => $movement->kind]);

        return back()->with('success', $postImmediately ? 'Stock movement posted and inventory updated.' : 'Stock movement saved as draft.');
    }

    public function post(Request $request, string $movement)
    {
        $record = StockMovement::whereCompanyId($request->user()->company_id)->findOrFail($movement);
        $this->authorize('post', $record);
        $posted = $this->posting->post($movement, $request->user()->company_id, $request->user()->id);
        $this->audit('posted', $posted, ['number' => $posted->number, 'kind' => $posted->kind]);

        return back()->with('success', 'Stock movement posted and inventory updated.');
    }

    public function reverse(Request $request, string $movement)
    {
        $record = StockMovement::whereCompanyId($request->user()->company_id)->findOrFail($movement);
        $this->authorize('reverse', $record);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $reversal = $this->posting->reverse($movement, $request->user()->company_id, $request->user()->id, $data['reason']);
        $this->audit('reversed', $reversal, ['number' => $reversal->number, 'reversal_of_id' => $movement, 'reason' => $data['reason']]);

        return back()->with('success', 'Reversal document '.$reversal->number.' was posted.');
    }

    private function validated(Request $request): array
    {
        $companyId = $request->user()->company_id;

        return $request->validate([
            'number' => ['required', 'max:40', Rule::unique('stock_movements')->where(fn ($query) => $query->where('company_id', $companyId))],
            'movement_date' => ['required', 'date', 'before_or_equal:today'], 'kind' => ['required', Rule::in(['receipt', 'issue', 'adjustment', 'transfer'])],
            'adjustment_direction' => ['nullable', Rule::in(['increase', 'decrease']), 'required_if:kind,adjustment'],
            'item_id' => ['required', Rule::exists('items', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('active', true))],
            'destination_warehouse_id' => ['nullable', 'required_if:kind,transfer', 'different:warehouse_id', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('active', true))],
            'quantity' => ['required', 'numeric', 'gt:0'], 'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'max:100'], 'notes' => ['nullable', 'max:2000'], 'posted' => ['nullable', 'boolean'],
        ]);
    }
}
