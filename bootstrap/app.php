<?php

use App\Http\Middleware\IsolatePortal;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [IsolatePortal::class]);
        // Vercel terminates TLS before the container. Trust forwarded protocol/port,
        // never forwarded Host (domain isolation still uses the actual Host header).
        if (getenv('VERCEL') === '1') {
            $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
