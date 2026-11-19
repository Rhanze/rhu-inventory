<?php

namespace App\Http\Middleware;

use App\Support\ManagementDeviceAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictMobileManagement
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('logout') || $request->user()->role === 'midwife'
            || ! ManagementDeviceAccess::isMobile($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => ManagementDeviceAccess::MOBILE_MESSAGE], 403)
                ->header('Cache-Control', 'private, no-store');
        }

        return response()->view('auth.desktop-required', [], 403)
            ->header('Cache-Control', 'private, no-store');
    }
}
