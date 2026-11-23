<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AdminMfa;
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
        // Sessions created before auth versions were introduced belong to version zero.
        if (! $user || ! $user->is_active || ! in_array($user->role, ['admin', 'staff', 'midwife'], true)
            || ($user->role !== 'admin' && ! $user->health_unit_id)
            || (int) $user->auth_version !== (int) $request->session()->get('auth_version', 0)
            || (AdminMfa::required($user) && $request->session()->get('mfa_verified') !== true)) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        session(['role' => $user->role, 'health_unit_id' => $user->health_unit_id, 'user_name' => $user->name]);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
