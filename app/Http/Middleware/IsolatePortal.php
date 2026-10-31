<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Runs before StartSession so public and staff sessions use different host-only cookies. */
class IsolatePortal
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('portals.enabled')) {
            abort_if(app()->environment('production'), 503, 'Portal domain separation must be configured.');

            return $next($request);
        }

        $public = (string) config('portals.public_domain');
        $management = (string) config('portals.management_domain');
        foreach ([$public, $management] as $host) {
            if (! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || str_contains($host, '*')) {
                throw new \LogicException('Portal domains must be complete hostnames without schemes, ports, paths or wildcards.');
            }
        }
        if (strtolower($public) === strtolower($management)) {
            throw new \LogicException('Public and management domains must differ.');
        }
        $surface = config('portals.surface');
        if (! in_array($surface, ['both', 'public', 'management'], true)) {
            throw new \LogicException('PORTAL_SURFACE must be public, management or both.');
        }
        abort_if(app()->environment('production') && ($surface === 'both' || ! config('portals.https_only')), 503,
            'Production requires a dedicated public or management deployment with HTTPS.');
        $host = strtolower($request->getHost());
        $side = match ($host) {
            strtolower($public) => 'public',
            strtolower($management) => 'management',
            default => null,
        };
        abort_unless($side && ($surface === 'both' || $surface === $side), 404);
        abort_if(config('portals.https_only') && ! $request->isSecure(), 400, 'HTTPS is required.');

        config([
            'session.cookie' => (config('portals.https_only') ? '__Host-' : '').'rhu_'.$side.'_session',
            'session.domain' => null,
            'session.path' => '/',
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'session.secure' => (bool) config('portals.https_only') || $request->isSecure(),
        ]);
        // A session store may already exist (for example after an earlier error).
        // Update its name as well as config before StartSession reads the cookie.
        $session = app('session')->driver();
        $previousName = $session->getName();
        if ($previousName !== config('session.cookie') && in_array($previousName, [
            'rhu_public_session', 'rhu_management_session', '__Host-rhu_public_session', '__Host-rhu_management_session',
        ], true)) {
            $session->flush();
        }
        $session->setName(config('session.cookie'));

        return $next($request);
    }
}
