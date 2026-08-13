<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function requestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $request->string('email'))->whereActive(true)->first();
        
        if ($user) {
            // Use Laravel's built-in password reset which sends ResetPassword notification
            Password::sendResetLink(['email' => $user->email]);
            
            // Audit the request
            AuditLog::create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'action' => 'password_reset_requested',
                'entity_type' => User::class,
                'entity_id' => $user->id,
            ]);
        }

        return back()->with('status', 'If that email belongs to an active account, a password-reset link has been sent.');
    }

    public function resetForm(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $credentials = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()],
        ]);
        $status = Password::reset($credentials, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
            AuditLog::create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'action' => 'password_reset_completed',
                'entity_type' => User::class,
                'entity_id' => $user->id,
            ]);
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }
}
