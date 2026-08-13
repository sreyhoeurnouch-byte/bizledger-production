<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function create()
    {
        return Auth::check() ? redirect()->route('dashboard') : view('auth.login');
    }

    public function store(Request $r)
    {
        $credentials = $r->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $credentials['active'] = true;
        if (Auth::attempt($credentials, $r->boolean('remember'))) {
            $r->session()->regenerate();
            AuditLog::create(['company_id' => $r->user()->company_id, 'user_id' => $r->user()->id, 'action' => 'signed_in', 'entity_type' => get_class($r->user()), 'entity_id' => $r->user()->id, 'metadata' => ['ip' => $r->ip()]]);

            return redirect()->intended(route('dashboard'));
        }

return back()->withErrors(['email' => 'Email or password is incorrect.'])->onlyInput('email');
    }

    public function destroy(Request $r)
    {
        if ($r->user()) {
            AuditLog::create(['company_id' => $r->user()->company_id, 'user_id' => $r->user()->id, 'action' => 'signed_out', 'entity_type' => get_class($r->user()), 'entity_id' => $r->user()->id, 'metadata' => ['ip' => $r->ip()]]);
        }Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('login');
    }
}
