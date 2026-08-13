<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Item;
use App\Models\StockMovement;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $cid = auth()->user()->company_id;

        return view('dashboard', ['receivables' => Contact::whereCompanyId($cid)->whereKind('customer')->sum('opening_balance'), 'payables' => Contact::whereCompanyId($cid)->whereKind('vendor')->sum('opening_balance'), 'inventory' => Item::whereCompanyId($cid)->selectRaw('COALESCE(SUM(quantity*cost),0) total')->value('total'), 'alerts' => Item::whereCompanyId($cid)->whereColumn('quantity', '<=', 'reorder_level')->count(), 'recent' => StockMovement::with(['item', 'warehouse'])->whereCompanyId($cid)->latest()->limit(6)->get()]);
    }
}
