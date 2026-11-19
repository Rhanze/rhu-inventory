<?php

use Illuminate\Support\ConfigurationUrlParser;

require_once __DIR__.'/database-ca.php';

/** Fail startup clearly instead of losing sessions/uploads on an ephemeral filesystem. */
function vercelEnvironmentProblems(array $env): array
{
    $errors = [];
    try {
        databaseCaPem($env['MYSQL_SSL_CA_PEM'] ?? null);
    } catch (RuntimeException $error) {
        $errors[] = $error->getMessage();
    }
    foreach (['APP_KEY', 'APP_URL', 'PUBLIC_DOMAIN', 'MANAGEMENT_DOMAIN'] as $name) {
        if (empty($env[$name])) {
            $errors[] = $name.' is required.';
        }
    }
    $key = $env['APP_KEY'] ?? '';
    $key = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
    if ($key === false || strlen($key) !== 32) {
        $errors[] = 'APP_KEY must be a valid 32-byte Laravel encryption key; generate it with key:generate --show.';
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
    if (($env['PORTAL_SURFACE'] ?? '') === 'public' && (($env['SESSION_DRIVER'] ?? 'redis') !== 'redis' || ($env['CACHE_STORE'] ?? 'redis') !== 'redis')) {
        $errors[] = 'Public sessions and cache must use Redis because the public business database account is read-only.';
    }
    $parser = new ConfigurationUrlParser;
    if (($env['SESSION_DRIVER'] ?? 'redis') === 'redis' || ($env['CACHE_STORE'] ?? 'redis') === 'redis') {
        try {
            $redis = $parser->parseConfiguration(['url' => $env['REDIS_URL'] ?? null, 'host' => $env['REDIS_HOST'] ?? '']);
        } catch (InvalidArgumentException) {
            $redis = [];
            $errors[] = 'REDIS_URL is malformed.';
        }
        $redisHost = $redis['host'] ?? '';
        if (is_string($redisHost) && str_contains($redisHost, '://')) {
            $redisHost = parse_url($redisHost, PHP_URL_HOST);
        }
        if (! $redisHost || in_array(strtolower($redisHost), ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
            $errors[] = 'A hosted Redis endpoint is required for shared sessions/cache.';
        }
        $driver = strtolower($redis['driver'] ?? '');
        // RedisManager promotes the URL's TCP/TLS driver to its connection scheme.
        $scheme = in_array($driver, ['tcp', 'tls'], true) ? $driver
            : ($redis['scheme'] ?? $driver ?: parse_url($redis['host'] ?? '', PHP_URL_SCHEME));
        if ($scheme && ! in_array($scheme, ['tcp', 'tls'], true)) {
            $errors[] = 'Redis requires a TCP/TLS endpoint (redis:// or rediss:// URL), not a REST/HTTP URL.';
        }
        if (($env['REDIS_CLIENT'] ?? 'phpredis') !== 'phpredis') {
            $errors[] = 'REDIS_CLIENT must be phpredis, which is included in the container.';
        }
    }
    try {
        $database = $parser->parseConfiguration([
            'url' => $env['DB_URL'] ?? null, 'driver' => $env['DB_CONNECTION'] ?? 'mysql',
            'host' => $env['DB_HOST'] ?? '', 'database' => $env['DB_DATABASE'] ?? '',
            'username' => $env['DB_USERNAME'] ?? '',
        ]);
    } catch (InvalidArgumentException) {
        $database = [];
        $errors[] = 'DB_URL is malformed.';
    }
    if (! in_array($env['DB_CONNECTION'] ?? 'mysql', ['mysql', 'mariadb'], true)
        || ! in_array($database['driver'] ?? '', ['mysql', 'mariadb'], true)) {
        $errors[] = 'Use a hosted MySQL/MariaDB database; this application has MySQL-specific queries.';
    }
    $databaseHost = $database['host'] ?? '';
    if (! $databaseHost || in_array(strtolower($databaseHost), ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
        $errors[] = 'A hosted database endpoint is required; XAMPP localhost is not reachable from Vercel.';
    }
    if (empty($database['database']) || empty($database['username'])) {
        $errors[] = 'DB_DATABASE and DB_USERNAME are required, either separately or through DB_URL.';
    }
    if (($env['PORTAL_SURFACE'] ?? '') === 'management') {
        if (($env['PRESCRIPTION_DISK'] ?? 's3') !== 's3') {
            $errors[] = 'Management prescription storage must use private S3-compatible storage.';
        }
        foreach (['RHU_S3_ACCESS_KEY_ID' => 'AWS_ACCESS_KEY_ID', 'RHU_S3_SECRET_ACCESS_KEY' => 'AWS_SECRET_ACCESS_KEY'] as $name => $fallback) {
            if (empty($env[$name] ?? $env[$fallback] ?? null)) {
                $errors[] = $name.' (or '.$fallback.') is required for management.';
            }
        }
        foreach (['AWS_BUCKET', 'AWS_DEFAULT_REGION', 'CRON_SECRET'] as $name) {
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
    require_once dirname(__DIR__).'/vendor/autoload.php';
    if (getenv('DEPLOYMENT_PROBE_MODE') !== false && getenv('DEPLOYMENT_PROBE_MODE') !== '') {
        foreach (['REDIS_PASSWORD', 'RHU_S3_ACCESS_KEY_ID', 'RHU_S3_SECRET_ACCESS_KEY', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'RHU_DEPLOYMENT_SECRET_PROBE'] as $name) {
            $value = getenv($name);
            fwrite(STDOUT, 'Deployment environment probe: '.$name.' '.(is_string($value) && $value !== '' ? 'PRESENT' : 'EMPTY').PHP_EOL);
        }
    }
    $errors = vercelEnvironmentProblems(getenv());
    foreach ($errors as $error) {
        fwrite(STDERR, 'Deployment configuration: '.$error.PHP_EOL);
    }
    exit($errors ? 1 : 0);
}
