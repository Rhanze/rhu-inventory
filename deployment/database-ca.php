<?php

/** Normalize a provider's public CA bundle without accepting private keys or other text. */
function databaseCaPem(?string $pem): ?string
{
    $pem = trim((string) $pem);
    if ($pem === '') {
        return null;
    }
    if (strlen($pem) > 131072) {
        throw new RuntimeException('MYSQL_SSL_CA_PEM is too large.');
    }
    // Vercel supports multiline values; also accept explicitly escaped newlines.
    $pem = str_replace(['\\r\\n', '\\n'], ["\n", "\n"], $pem);
    $pattern = '/-----BEGIN CERTIFICATE-----\s+[A-Za-z0-9+\/=\s]+-----END CERTIFICATE-----/';
    if (! preg_match_all($pattern, $pem, $matches)
        || trim((string) preg_replace($pattern, '', $pem)) !== '') {
        throw new RuntimeException('MYSQL_SSL_CA_PEM must contain only PEM-encoded X.509 certificates.');
    }
    $normalized = '';
    foreach ($matches[0] as $block) {
        $certificate = @openssl_x509_read($block);
        if ($certificate === false || ! openssl_x509_export($certificate, $exported)) {
            throw new RuntimeException('MYSQL_SSL_CA_PEM contains an invalid X.509 certificate.');
        }
        $normalized .= $exported;
    }

    return $normalized;
}

/** Write only a supplied certificate bundle; an omitted value preserves path-based TLS setup. */
function materializeDatabaseCa(?string $pem, string $path): bool
{
    $normalized = databaseCaPem($pem);
    if ($normalized === null) {
        return false;
    }
    if (is_link($path) || ! is_dir(dirname($path))) {
        throw new RuntimeException('The database CA output directory is unavailable.');
    }
    if (@file_put_contents($path, $normalized, LOCK_EX) !== strlen($normalized)) {
        throw new RuntimeException('Could not write the database CA certificate file.');
    }
    if (PHP_OS_FAMILY !== 'Windows' && ! chmod($path, 0600)) {
        throw new RuntimeException('Could not restrict permissions on the database CA certificate file.');
    }

    return true;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        if (! materializeDatabaseCa(getenv('MYSQL_SSL_CA_PEM') ?: null, '/tmp/rhu-bootstrap/mysql-ca.pem')) {
            throw new RuntimeException('MYSQL_SSL_CA_PEM was not supplied.');
        }
    } catch (Throwable $error) {
        fwrite(STDERR, 'Database TLS configuration: '.$error->getMessage().PHP_EOL);
        exit(1);
    }
}
