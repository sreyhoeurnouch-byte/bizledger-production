<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLedgerWriteAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() && in_array($request->user()->role, ['owner', 'admin', 'accountant', 'operator'], true), 403);

        return $next($request);
    }
}
