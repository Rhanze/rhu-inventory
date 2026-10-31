<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class CheckSessionRole
{
    public function handle(Request $request, Closure $next)
    {
        if (! session()->has('user_id')) {
            return redirect()->route('login');
        }

        $user = User::find(session('user_id'));
        if (! $user || ! $user->is_active || ! in_array($user->role, ['admin', 'staff', 'midwife'])) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        session(['role' => $user->role, 'health_unit_id' => $user->health_unit_id, 'user_name' => $user->name]);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
