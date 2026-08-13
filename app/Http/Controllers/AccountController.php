<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Support\AuditsLedger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    use AuditsLedger;

    public function index(Request $r)
    {
        $accounts = Account::whereCompanyId($r->user()->company_id)->when($r->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('code', 'like', '%'.$r->q.'%')->orWhere('name', 'like', '%'.$r->q.'%')->orWhere('category', 'like', '%'.$r->q.'%')))->orderBy('code')->paginate(30)->withQueryString();

        return view('accounting.index', compact('accounts'));
    }

    public function store(Request $r)
    {
        $cid = $r->user()->company_id;
        $data = $r->validate(['code' => ['required', 'max:32', Rule::unique('accounts')->where(fn ($q) => $q->where('company_id', $cid))], 'name' => ['required', 'max:160'], 'category' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])], 'balance' => ['required', 'numeric']]);
        $account = Account::create($data + ['company_id' => $cid]);
        $this->audit('created', $account, ['code' => $account->code, 'category' => $account->category]);

        return back()->with('success','Account created.');
    }
}
