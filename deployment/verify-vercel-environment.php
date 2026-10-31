<?php

/** Fail startup clearly instead of losing sessions/uploads on an ephemeral filesystem. */
function vercelEnvironmentProblems(array $env): array
{
    $errors = [];
    foreach (['APP_KEY', 'APP_URL', 'PUBLIC_DOMAIN', 'MANAGEMENT_DOMAIN'] as $name) {
        if (empty($env[$name])) {
            $errors[] = $name.' is required.';
        }
    }
    if (! in_array($env['PORTAL_SURFACE'] ?? '', ['public', 'management'], true)) {
        $errors[] = 'PORTAL_SURFACE must be public or management; use separate Vercel projects.';
    }
    if (! filter_var($env['PORTAL_DOMAIN_SPLIT'] ?? true, FILTER_VALIDATE_BOOL) || ! filter_var($env['PORTAL_HTTPS_ONLY'] ?? true, FILTER_VALIDATE_BOOL)) {
        $errors[] = 'Domain separation and HTTPS-only mode must remain enabled.';
    }
    foreach (['PUBLIC_DOMAIN', 'MANAGEMENT_DOMAIN'] as $name) {
        if (! filter_var($env[$name] ?? '', FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $errors[] = $name.' must be a hostname without scheme, port or path.';
        }
    }
    if (strtolower($env['PUBLIC_DOMAIN'] ?? '') === strtolower($env['MANAGEMENT_DOMAIN'] ?? '')) {
        $errors[] = 'Public and management hostnames must differ.';
    }
    if (filter_var($env['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL)) {
        $errors[] = 'APP_DEBUG must be false.';
    }
    if (! in_array($env['SESSION_DRIVER'] ?? 'redis', ['redis', 'database'], true) || ! in_array($env['CACHE_STORE'] ?? 'redis', ['redis', 'database'], true)) {
        $errors[] = 'Sessions and cache require a persistent shared Redis or database store.';
    }
    if (($env['SESSION_DRIVER'] ?? 'redis') === 'redis' || ($env['CACHE_STORE'] ?? 'redis') === 'redis') {
        $redisHost = ! empty($env['REDIS_URL']) ? parse_url($env['REDIS_URL'], PHP_URL_HOST) : ($env['REDIS_HOST'] ?? '');
        if (! $redisHost || in_array(strtolower($redisHost), ['localhost', '127.0.0.1', '::1'], true)) {
            $errors[] = 'A hosted Redis endpoint is required for shared sessions/cache.';
        }
    }
    if (! in_array($env['DB_CONNECTION'] ?? 'mysql', ['mysql', 'mariadb'], true)) {
        $errors[] = 'Use a hosted MySQL/MariaDB database; this application has MySQL-specific queries.';
    }
    $databaseHost = ! empty($env['DB_URL']) ? parse_url($env['DB_URL'], PHP_URL_HOST) : ($env['DB_HOST'] ?? '');
    if (! $databaseHost || in_array(strtolower($databaseHost), ['localhost', '127.0.0.1', '::1'], true)) {
        $errors[] = 'A hosted database endpoint is required; XAMPP localhost is not reachable from Vercel.';
    }
    if (($env['PORTAL_SURFACE'] ?? '') === 'management') {
        if (($env['PRESCRIPTION_DISK'] ?? 's3') !== 's3') {
            $errors[] = 'Management prescription storage must use private S3-compatible storage.';
        }
        foreach (['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_BUCKET', 'AWS_DEFAULT_REGION', 'CRON_SECRET'] as $name) {
            if (empty($env[$name])) {
                $errors[] = $name.' is required for management.';
            }
        }
        if (strlen($env['CRON_SECRET'] ?? '') < 32) {
            $errors[] = 'CRON_SECRET must contain at least 32 characters.';
        }
    }
    $expectedHost = ($env['PORTAL_SURFACE'] ?? '') === 'public' ? ($env['PUBLIC_DOMAIN'] ?? '') : ($env['MANAGEMENT_DOMAIN'] ?? '');
    if (parse_url($env['APP_URL'] ?? '', PHP_URL_SCHEME) !== 'https' || parse_url($env['APP_URL'] ?? '', PHP_URL_HOST) !== $expectedHost) {
        $errors[] = 'APP_URL must be the HTTPS URL of this project’s configured portal domain.';
    }

    return $errors;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $errors = vercelEnvironmentProblems(getenv());
    foreach ($errors as $error) {
        fwrite(STDERR, 'Deployment configuration: '.$error.PHP_EOL);
    }
    exit($errors ? 1 : 0);
}
