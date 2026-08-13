<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

class ThrottleWriteOperations
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request, Closure $next)
    {
        // 30 write operations per minute per authenticated user
        $key = 'write-ops:' . auth()->id();

        if (RateLimiter::tooManyAttempts($key, 30)) {
            throw new \Illuminate\Http\Exceptions\ThrottleRequestsException(
                429,
                'Too many write operations. Please try again later.'
            );
        }

        RateLimiter::hit($key, 60); // Reset every 60 seconds

        return $next($request);
    }
}
