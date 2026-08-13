<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IdleSessionTimeout
{
    /**
     * The session timeout in minutes (8 hours = 480 minutes).
     */
    private const SESSION_TIMEOUT = 480;

    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $lastActivity = session('last_activity');
            $currentTime = now()->timestamp;

            if ($lastActivity && ($currentTime - $lastActivity) > (self::SESSION_TIMEOUT * 60)) {
                Auth::logout();
                session()->flush();

                return redirect()->route('login')->with('info', 'Your session has expired due to inactivity. Please log in again.');
            }

            session(['last_activity' => $currentTime]);
        }

        return $next($request);
    }
}
