<?php

/** Opt-in CLI checks using only isolated, fictional deployment probe artifacts. */
function deploymentServiceProbe(array $settings, callable $databaseCheck, object $cache, object $disk, callable $emit, ?int $now = null): int
{
    $mode = $settings['mode'] ?? '';
    if ($mode === '' || $mode === null) {
        return 0;
    }
    $clock = $now === null ? static fn (): int => time() : static fn (): int => $now;
    $now = $clock();
    $key = $settings['app_key'] ?? '';
    $key = is_string($key) && str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
    $expiry = $settings['expires_at'] ?? '';
    if (! in_array($mode, ['write', 'read', 'cleanup'], true)
        || ($settings['environment'] ?? '') !== 'production'
        || ($settings['surface'] ?? '') !== 'management'
        || ($settings['cache_store'] ?? '') !== 'redis'
        || ($settings['disk_driver'] ?? '') !== 's3'
        || ($settings['disk_visibility'] ?? '') !== 'private'
        || ! is_string($key) || strlen($key) !== 32
        || ! ctype_digit((string) $expiry) || (int) $expiry > $now + 7200) {
        $emit('configuration', false);

        return 1;
    }
    if ((int) $expiry <= $now) {
        $emit('expired', true);

        return 0;
    }

    $identifier = hash_hmac('sha256', 'rhu-deployment-services-v1', $key);
    $markerKey = 'deployment-probe:'.$identifier.':marker';
    $retiredKey = 'deployment-probe:'.$identifier.':retired';
    $lockKey = 'deployment-probe:'.$identifier.':lock';
    $objectKey = 'deployment-probes/'.$identifier.'.txt';
    $contents = "RHU deployment verification: fictional probe only.\n";
    $ttl = (int) $expiry - $now + 3600;

    $run = function () use ($mode, $databaseCheck, $cache, $disk, $emit, $markerKey, $retiredKey, $objectKey, $contents, $ttl, $clock, $expiry): int {
        // Lock acquisition can wait; recheck the deadline before touching probe artifacts.
        if ((int) $expiry <= $clock()) {
            $emit('expired', true);

            return 0;
        }
        $failed = false;
        $check = function (string $name, callable $action) use (&$failed, $emit): bool {
            try {
                $passed = $action() === true;
            } catch (Throwable) {
                $passed = false;
            }
            $emit($name, $passed);
            $failed = $failed || ! $passed;

            return $passed;
        };

        if ($mode === 'cleanup') {
            // Retire first, under the write lock, so older deployments cannot recreate probes.
            if (! $check('retirement', fn () => $cache->put($retiredKey, true, $ttl) === true && $cache->get($retiredKey) === true)) {
                return 1;
            }
        } else {
            try {
                if ($cache->get($retiredKey) === true) {
                    $emit('retired', true);

                    return 0;
                }
            } catch (Throwable) {
                $emit('retirement', false);

                return 1;
            }
        }

        $check('database', $databaseCheck);
        $cleanup = function () use ($check, $cache, $markerKey, $disk, $objectKey): void {
            $check('cache-cleanup', function () use ($cache, $markerKey): bool {
                $cache->forget($markerKey);

                return $cache->get($markerKey) === null;
            });
            $check('storage-cleanup', function () use ($disk, $objectKey): bool {
                $disk->delete($objectKey);

                return ! $disk->exists($objectKey);
            });
        };
        if ($mode === 'write') {
            $check('cache-write-read', fn () => $cache->put($markerKey, $contents, $ttl) === true && $cache->get($markerKey) === $contents);
            $check('storage-write-read', fn () => $disk->put($objectKey, $contents, ['visibility' => 'private']) === true && $disk->get($objectKey) === $contents);
            // Fence a delayed writer even if its lock lease elapsed during a provider stall.
            try {
                $retired = $cache->get($retiredKey) === true;
            } catch (Throwable) {
                $retired = true;
                $failed = true;
                $emit('retirement', false);
            }
            if ($retired) {
                $cleanup();
                $emit('retired', true);
            }
        } elseif ($mode === 'read') {
            // Never recreate missing artifacts: these reads establish persistence across deployments.
            $check('cache-persistence', fn () => $cache->get($markerKey) === $contents);
            $check('storage-persistence', fn () => $disk->get($objectKey) === $contents);
        } else {
            $cleanup();
        }

        return $failed ? 1 : 0;
    };

    try {
        return $mode === 'read' ? $run() : $cache->lock($lockKey, 120)->block(10, $run);
    } catch (Throwable) {
        $emit('probe-lock', false);

        return 1;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    // Runtime output contains only fixed check names and results, including bootstrap failures.
    ini_set('display_errors', '0');
    ini_set('log_errors', '0');
    $emit = static function (string $name, bool $passed): void {
        $status = in_array($name, ['expired', 'retired'], true) ? 'SKIPPED' : ($passed ? 'PASS' : 'FAIL');
        file_put_contents('php://stdout', 'Deployment service probe: '.$name.' '.$status.PHP_EOL);
    };
    if (PHP_SAPI !== 'cli') {
        $emit('cli-only', false);
        exit(1);
    }
    $mode = getenv('DEPLOYMENT_PROBE_MODE');
    if ($mode === false || $mode === '') {
        exit(0);
    }
    try {
        require_once dirname(__DIR__).'/vendor/autoload.php';
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        // Bound probe network waits without changing the cached application configuration.
        $connection = config('database.default');
        config([
            'database.connections.'.$connection.'.options' => array_replace(config('database.connections.'.$connection.'.options', []), [PDO::ATTR_TIMEOUT => 15]),
            'database.redis.default.timeout' => 5, 'database.redis.default.read_timeout' => 5,
            'database.redis.cache.timeout' => 5, 'database.redis.cache.read_timeout' => 5,
            'filesystems.disks.s3.http' => array_replace(config('filesystems.disks.s3.http', []), ['connect_timeout' => 5, 'timeout' => 15]),
            'filesystems.disks.s3.retries' => 0,
        ]);
        $settings = [
            'mode' => $mode, 'expires_at' => getenv('DEPLOYMENT_PROBE_EXPIRES_AT'),
            'environment' => $app->environment(), 'surface' => config('portals.surface'),
            'app_key' => config('app.key'), 'cache_store' => config('cache.default'),
            'disk_driver' => config('filesystems.disks.s3.driver'), 'disk_visibility' => config('filesystems.disks.s3.visibility'),
        ];
        $databaseCheck = static function (): bool {
            $result = Illuminate\Support\Facades\DB::selectOne('SELECT 1 AS probe_check');

            return in_array($result->probe_check ?? null, [1, '1'], true);
        };
        exit(deploymentServiceProbe($settings, $databaseCheck, Illuminate\Support\Facades\Cache::store(), Illuminate\Support\Facades\Storage::disk('s3'), $emit));
    } catch (Throwable) {
        $emit('bootstrap', false);
        exit(1);
    }
}
