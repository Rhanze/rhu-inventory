<?php

/** A short-lived, read-only diagnostic for two already deleted fictional attachments. */
function deploymentAttachmentAbsenceGate(mixed $mode, mixed $expiry, int $now): string
{
    if ($mode === '' || $mode === null || $mode === false) {
        return 'disabled';
    }
    if ($mode !== 'read' || (! is_string($expiry) && ! is_int($expiry))
        || ! ctype_digit((string) $expiry) || (int) $expiry <= 0 || (int) $expiry > $now + 1800) {
        return 'configuration';
    }

    return (int) $expiry <= $now ? 'expired' : 'enabled';
}

function deploymentAttachmentAbsenceProbe(array $settings, callable $headBucket, callable $headObject, callable $emit, ?int $now = null): int
{
    $clock = $now === null ? static fn (): int => time() : static fn (): int => $now;
    $gate = deploymentAttachmentAbsenceGate($settings['mode'] ?? '', $settings['expires_at'] ?? '', $clock());
    if ($gate === 'disabled') {
        return 0;
    }
    if ($gate === 'expired') {
        $emit('expired', true);

        return 0;
    }
    $credentialValid = static function (mixed $value, int $minimum, int $maximum): bool {
        return is_string($value) && strlen($value) >= $minimum && strlen($value) <= $maximum
            && preg_match('/[\x00-\x20\x7f]/', $value) === 0;
    };
    if ($gate !== 'enabled' || ($settings['environment'] ?? '') !== 'production'
        || ($settings['surface'] ?? '') !== 'management'
        || ($settings['prescription_disk'] ?? '') !== 's3'
        || ($settings['disk_driver'] ?? '') !== 's3'
        || ($settings['disk_visibility'] ?? '') !== 'private'
        || ($settings['endpoint'] ?? '') !== 'https://exwmjzryhkcfqyhwkkbm.storage.supabase.co/storage/v1/s3'
        || ($settings['bucket'] ?? '') !== 'rhu-prescriptions'
        || ($settings['region'] ?? '') !== 'ap-northeast-2'
        || ($settings['use_path_style_endpoint'] ?? false) !== true
        || ! $credentialValid($settings['key'] ?? null, 8, 256)
        || ! $credentialValid($settings['secret'] ?? null, 16, 1024)) {
        $emit('configuration', false);
        $emit('summary', false);

        return 1;
    }

    if ((int) $settings['expires_at'] <= $clock()) {
        $emit('expired', true);

        return 0;
    }
    // A successful signed bucket HEAD distinguishes authorized checks from denied access.
    try {
        $bucket = $headBucket($settings['bucket']);
        $bucketPassed = ($bucket['@metadata']['statusCode'] ?? null) === 200;
    } catch (Throwable) {
        $bucketPassed = false;
    }
    $emit('bucket_access', $bucketPassed);
    if (! $bucketPassed) {
        $emit('summary', false);

        return 1;
    }

    // These keys come from the two retained fixture recovery records. No caller can supply keys.
    $fixtures = [
        'fixture_1' => 'prescriptions/60033/bqmKJBX9doFcAdTMRgY4K9Jt0hYJZkYiAffPrl3l.pdf',
        'fixture_2' => 'prescriptions/60035/qFZkPDwcV7PjwhfIpQTpGpWIh5UkeJ4KBkMNWI9S.pdf',
    ];
    $passed = true;
    foreach ($fixtures as $name => $key) {
        if ((int) $settings['expires_at'] <= $clock()) {
            $emit('expired', true);

            return 0;
        }
        $absent = false;
        try {
            // Success means the supposedly deleted object still exists.
            $headObject($settings['bucket'], $key);
        } catch (Aws\S3\Exception\S3Exception $error) {
            $absent = $error->getStatusCode() === 404
                && in_array($error->getAwsErrorCode(), ['NotFound', 'NoSuchKey'], true);
        } catch (Throwable) {
            // Authentication, TLS/network failures and unexpected exceptions are never absence.
        }
        $emit($name, $absent);
        $passed = $passed && $absent;
    }
    $emit('summary', $passed);

    return $passed ? 0 : 1;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '0');
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit(1);
    }
    $mode = getenv('DEPLOYMENT_ATTACHMENT_ABSENCE_MODE');
    $expiry = getenv('DEPLOYMENT_ATTACHMENT_ABSENCE_EXPIRES_AT');
    $gate = deploymentAttachmentAbsenceGate($mode, $expiry, time());
    if ($gate === 'disabled') {
        exit(0);
    }
    // Only fixed labels, a timestamp and results are emitted, never provider diagnostics.
    file_put_contents('php://stdout', 'Attachment absence probe: checked_at '.gmdate('Y-m-d\TH:i:s\Z').PHP_EOL);
    $emit = static function (string $name, bool $passed): void {
        $status = $name === 'expired' ? 'SKIPPED' : ($passed ? 'PASS' : 'FAIL');
        file_put_contents('php://stdout', 'Attachment absence probe: '.$name.' '.$status.PHP_EOL);
    };
    // An expired or invalid opt-in never boots Laravel or contacts a provider.
    if ($gate !== 'enabled') {
        $emit($gate, $gate === 'expired');
        exit($gate === 'expired' ? 0 : 1);
    }
    try {
        require_once dirname(__DIR__).'/vendor/autoload.php';
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $disk = config('filesystems.disks.s3', []);
        $settings = [
            'mode' => $mode, 'expires_at' => $expiry,
            'environment' => $app->environment(), 'surface' => config('portals.surface'),
            'prescription_disk' => config('prescriptions.disk'),
            'disk_driver' => $disk['driver'] ?? null, 'disk_visibility' => $disk['visibility'] ?? null,
            'endpoint' => $disk['endpoint'] ?? null, 'bucket' => $disk['bucket'] ?? null,
            'region' => $disk['region'] ?? null, 'use_path_style_endpoint' => $disk['use_path_style_endpoint'] ?? null,
            'key' => $disk['key'] ?? null, 'secret' => $disk['secret'] ?? null,
        ];
        // The client is lazy: configuration validation runs before either signed request.
        $client = null;
        $getClient = static function () use (&$client, $settings): Aws\S3\S3Client {
            return $client ??= new Aws\S3\S3Client([
                'version' => '2006-03-01', 'region' => $settings['region'], 'endpoint' => $settings['endpoint'],
                'use_path_style_endpoint' => true, 'signature_version' => 'v4',
                'credentials' => ['key' => $settings['key'], 'secret' => $settings['secret']],
                'retries' => 0, 'debug' => false,
                'http' => ['verify' => true, 'allow_redirects' => false, 'connect_timeout' => 5, 'timeout' => 15],
            ]);
        };
        exit(deploymentAttachmentAbsenceProbe($settings,
            static fn (string $bucket) => $getClient()->headBucket(['Bucket' => $bucket]),
            static fn (string $bucket, string $key) => $getClient()->headObject(['Bucket' => $bucket, 'Key' => $key]),
            $emit));
    } catch (Throwable) {
        $emit('bootstrap', false);
        $emit('summary', false);
        exit(1);
    }
}
