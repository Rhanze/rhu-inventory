<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
    if (session('role') !== 'admin') {
        return redirect()->route('dashboard')->with('error', 'Only admin can access this.');
    }
    return $next($request);
    }
}
