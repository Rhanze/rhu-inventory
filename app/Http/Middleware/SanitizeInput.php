<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SanitizeInput
{
    public function handle(Request $request, Closure $next)
    {
        $input = $request->all();

        array_walk_recursive($input, function (&$value) {
            // Trim whitespace and remove HTML tags (but allow harmless content)
            $value = trim(strip_tags($value));
        });

        $request->merge($input);

        return $next($request);
    }
}