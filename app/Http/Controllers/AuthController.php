<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request; use Illuminate\Support\Facades\Auth;
class AuthController extends Controller {
 public function create(){return Auth::check()?redirect()->route('dashboard'):view('auth.login');}
 public function store(Request $r){$credentials=$r->validate(['email'=>['required','email'],'password'=>['required','string']]);$credentials['active']=true;if(Auth::attempt($credentials,$r->boolean('remember'))){$r->session()->regenerate();return redirect()->intended(route('dashboard'));}return back()->withErrors(['email'=>'Email or password is incorrect.'])->onlyInput('email');}
 public function destroy(Request $r){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect()->route('login');}
}
