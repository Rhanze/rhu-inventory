<?php

return [
    'enabled' => env('PORTAL_DOMAIN_SPLIT', true),
    'public_domain' => env('PUBLIC_DOMAIN', 'rhu-inventory.test'),
    'management_domain' => env('MANAGEMENT_DOMAIN', 'management.rhu-inventory.test'),
    // Separate deployments can expose only their own surface; both is for local development.
    'surface' => env('PORTAL_SURFACE', 'both'),
    'https_only' => env('PORTAL_HTTPS_ONLY', env('APP_ENV') === 'production'),
];
