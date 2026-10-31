<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateSession
{
    public function handle(Request $request, Closure $next)
    {
        // Must be logged in
        if (!session()->has('user_id')) {
            return redirect()->route('login');
        }

        // Must be admin or staff
        if (!in_array(session('role'), ['admin', 'staff'])) {
            return redirect()->route('login');
        }

        return $next($request);  
    }
}