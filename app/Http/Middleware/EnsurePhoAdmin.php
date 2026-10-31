<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePhoAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
    if (session('health_unit_id') !== null) {
        return back()->with('error', 'Only PHO admin can perform this action.');
    }
    return $next($request);
    }
}
