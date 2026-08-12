<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\PostStockMovement;
use App\Models\{Item, ItemGroup, Warehouse};
use App\Support\AuditsLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ItemController extends Controller
{
    use AuditsLedger;

    public function __construct(private PostStockMovement $posting) {}

    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $items = Item::with('group')->whereCompanyId($companyId)
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($search) => $search
                ->where('sku', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')->orWhere('barcode', 'like', '%'.$request->q.'%')))
            ->when($request->state === 'low', fn ($query) => $query->where('active', true)->whereColumn('quantity', '<=', 'reorder_level'))
            ->when(in_array($request->state, ['active', 'inactive'], true), fn ($query) => $query->where('active', $request->state === 'active'))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('items.index', compact('items'));
    }

    public function create(Request $request)
    {
        return view('items.create', ['item' => new Item(['unit' => 'Each', 'item_type' => 'stock', 'for_purchase' => true, 'for_sale' => true]), 'groups' => $this->groups($request)]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $openingQuantity = (float) $data['quantity'];
        $item = DB::transaction(function () use ($request, $data, $openingQuantity) {
            $item = Item::create($data + ['quantity' => 0]);

            if ($item->item_type === 'stock' && $openingQuantity > 0) {
                $warehouse = Warehouse::whereCompanyId($request->user()->company_id)->whereActive(true)->orderBy('code')->first();
                if (! $warehouse) {
                    throw ValidationException::withMessages(['quantity' => 'Create an active warehouse before entering an opening stock quantity.']);
                }
                $this->posting->create([
                    'warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'number' => 'OPEN-'.strtoupper(substr(str_replace('-', '', $item->id), 0, 12)),
                    'movement_date' => now()->toDateString(), 'kind' => 'receipt', 'reference' => 'Opening balance', 'quantity' => $openingQuantity,
                    'unit_cost' => $item->cost, 'notes' => 'System-created opening stock balance.',
                ], $item->company_id, $request->user()->id, true);
            }

            return $item;
        });
        $this->audit('created', $item, ['sku' => $item->sku, 'item_type' => $item->item_type]);

        return redirect()->route('items.index')->with('success', 'Item created successfully.');
    }

    public function edit(Request $request, string $item)
    {
        $item = $this->item($request, $item);
        $this->authorize('update', $item);
        return view('items.edit', ['item' => $item, 'groups' => $this->groups($request), 'hasPostedMovements' => $item->stockMovements()->wherePosted(true)->exists()]);
    }

    public function update(Request $request, string $item)
    {
        $record = $this->item($request, $item);
        $this->authorize('update', $record);
        $hasPostedMovements = $record->stockMovements()->wherePosted(true)->exists();
        $record->update($this->validated($request, $record, $hasPostedMovements));
        $this->audit('updated', $record, ['sku' => $record->sku, 'item_type' => $record->item_type]);

        return redirect()->route('items.index')->with('success', 'Item updated successfully. Inventory quantity is controlled by stock movements.');
    }

    public function archive(Request $request, string $item)
    {
        $record = $this->item($request, $item);
        $this->authorize('update', $record);
        $record->update(['active' => false]);
        $this->audit('archived', $record, ['sku' => $record->sku]);

        return redirect()->route('items.index')->with('success', 'Item archived. Historical movements remain intact.');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);
        $columns = array_map(fn ($value) => strtolower(trim((string) $value)), $header ?: []);
        $required = ['sku', 'name', 'unit', 'cost', 'sale_price'];
        if (array_diff($required, $columns)) return back()->withErrors(['file' => 'CSV must include: '.implode(', ', $required).'.']);

        $rows = []; $errors = []; $seenSkus = []; $line = 1; $companyId = $request->user()->company_id;
        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            if (!array_filter($values, fn ($value) => trim((string) $value) !== '')) continue;
            if (count($rows) >= 250) { $errors[] = 'A CSV import is limited to 250 items.'; break; }
            $source = array_combine($columns, array_pad($values, count($columns), null));
            $row = [
                'sku' => trim((string) ($source['sku'] ?? '')), 'name' => trim((string) ($source['name'] ?? '')),
                'unit' => trim((string) ($source['unit'] ?? '')), 'cost' => $source['cost'] ?? null, 'sale_price' => $source['sale_price'] ?? null,
                'barcode' => trim((string) ($source['barcode'] ?? '')) ?: null,
                'item_type' => in_array($source['item_type'] ?? 'stock', ['stock', 'service'], true) ? ($source['item_type'] ?? 'stock') : 'invalid',
            ];
            $validator = Validator::make($row, ['sku' => ['required', 'string', 'max:64'], 'name' => ['required', 'string', 'max:180'], 'unit' => ['required', 'string', 'max:32'], 'cost' => ['required', 'numeric', 'min:0'], 'sale_price' => ['required', 'numeric', 'min:0'], 'barcode' => ['nullable', 'string', 'max:80'], 'item_type' => ['required', Rule::in(['stock', 'service'])]]);
            if ($validator->fails()) { $errors[] = "Row {$line}: ".implode(' ', $validator->errors()->all()); continue; }
            if (isset($seenSkus[$row['sku']]) || Item::whereCompanyId($companyId)->where('sku', $row['sku'])->exists()) { $errors[] = "Row {$line}: SKU {$row['sku']} already exists."; continue; }
            if ($row['barcode'] && Item::whereCompanyId($companyId)->where('barcode', $row['barcode'])->exists()) { $errors[] = "Row {$line}: barcode {$row['barcode']} already exists."; continue; }
            $seenSkus[$row['sku']] = true; $rows[] = $row;
        }
        fclose($handle);
        if ($errors) return back()->withErrors(['file' => $errors]);
        if (!$rows) return back()->withErrors(['file' => 'The CSV contains no importable items.']);

        DB::transaction(function () use ($rows, $companyId) {
            foreach ($rows as $row) Item::create($row + ['company_id' => $companyId, 'quantity' => 0, 'reorder_level' => 0, 'active' => true, 'for_purchase' => true, 'for_sale' => true]);
        });
        $this->audit('imported', new Item, ['count' => count($rows)]);
        return redirect()->route('items.index')->with('success', count($rows).' items imported successfully. Opening stock remains zero; post a receipt to add inventory.');
    }

    private function validated(Request $request, ?Item $item = null, bool $hasPostedMovements = false): array
    {
        $request->mergeIfMissing(['item_type' => 'stock', 'for_purchase' => true, 'for_sale' => true]);
        $companyId = $request->user()->company_id;
        $skuRule = Rule::unique('items')->where(fn ($query) => $query->where('company_id', $companyId));
        $barcodeRule = Rule::unique('items')->where(fn ($query) => $query->where('company_id', $companyId));
        if ($item) { $skuRule->ignore($item->id); $barcodeRule->ignore($item->id); }

        $data = $request->validate([
            'sku' => ['required', 'string', 'max:64', $skuRule], 'name' => ['required', 'string', 'max:180'],
            'item_type' => ['required', Rule::in(['stock', 'service'])], 'for_purchase' => ['nullable', 'boolean'], 'for_sale' => ['nullable', 'boolean'],
            'group_id' => ['nullable', Rule::exists('item_groups', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'barcode' => ['nullable', 'string', 'max:80', $barcodeRule], 'unit' => ['required', 'string', 'max:32'],
            'cost' => ['required', 'numeric', 'min:0'], 'sale_price' => ['required', 'numeric', 'min:0'],
            'quantity' => [$item ? 'prohibited' : 'required', 'numeric', 'min:0'], 'reorder_level' => ['required', 'numeric', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['for_purchase'] = $request->boolean('for_purchase');
        $data['for_sale'] = $request->boolean('for_sale');
        $data['active'] = $request->boolean('active', true);
        if ($item) $data['quantity'] = $item->quantity;

        $errors = [];
        if (! $data['for_purchase'] && ! $data['for_sale']) $errors['for_purchase'] = 'An item must be enabled for purchase, sale, or both.';
        if ($data['item_type'] === 'service' && ((float) $data['quantity'] !== 0.0 || (float) $data['reorder_level'] !== 0.0)) $errors['item_type'] = 'Service items cannot carry quantity or reorder levels.';
        if ($hasPostedMovements && ($data['item_type'] !== $item->item_type || $data['sku'] !== $item->sku)) $errors['sku'] = 'SKU and item type are locked after stock has been posted.';
        if ($errors) throw ValidationException::withMessages($errors);

        return $data + ['company_id' => $companyId];
    }

    private function item(Request $request, string $id): Item { return Item::whereCompanyId($request->user()->company_id)->findOrFail($id); }
    private function groups(Request $request) { return ItemGroup::whereCompanyId($request->user()->company_id)->orderBy('name')->get(); }
}
