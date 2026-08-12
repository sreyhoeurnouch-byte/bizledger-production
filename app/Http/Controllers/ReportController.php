<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $items = Item::whereCompanyId($request->user()->company_id)->whereActive(true)
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($search) => $search->where('sku', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')->orWhere('barcode', 'like', '%'.$request->q.'%')))
            ->orderBy('name')->get();

        return view('reports.stock-valuation', compact('items'));
    }
}
