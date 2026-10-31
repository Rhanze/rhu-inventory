<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureNotMidwife
{
    /**
     * Block midwife-role users from routes that belong to admin/staff only.
     * If the user IS a midwife, redirect them to their own dashboard.
     */
    public function handle(Request $request, Closure $next)
    {
        if (session('role') === 'midwife') {
            return redirect()->route('dashboard.midwife')
                ->with('error', 'You do not have access to that section.');
        }

        return $next($request);
    }
}