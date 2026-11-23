<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Content-Security-Policy', "base-uri 'self'; frame-ancestors 'self'; object-src 'none'");

        if ($request->routeIs('login', 'login.*')
            || ($request->hasSession() && $request->session()->has('user_id'))) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
