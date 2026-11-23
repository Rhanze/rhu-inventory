<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__.'/../../deployment/verify-services.php';

class DeploymentServicesProbeTest extends TestCase
{
    private array $events = [];
    private int $databaseCalls = 0;

    private function settings(array $overrides = []): array
    {
        return array_replace(['mode' => 'write', 'expires_at' => '10600', 'environment' => 'production',
            'surface' => 'management', 'app_key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'cache_store' => 'redis', 'disk_driver' => 's3', 'disk_visibility' => 'private'], $overrides);
    }

    private function runProbe(array $settings, ProbeCacheFixture $cache, ProbeDiskFixture $disk, ?callable $database = null): int
    {
        return \deploymentServiceProbe($settings, $database ?? function (): bool {
            $this->databaseCalls++;

            return true;
        }, $cache, $disk, function (string $name, bool $passed): void {
            $this->events[] = [$name, $passed];
        }, 10000);
    }

    public function test_disabled_invalid_and_expired_modes_do_not_call_services(): void
    {
        $cache = new ProbeCacheFixture;
        $disk = new ProbeDiskFixture;
        $this->assertSame(0, $this->runProbe($this->settings(['mode' => '']), $cache, $disk));
        foreach ([['mode' => 'unknown'], ['surface' => 'public'], ['environment' => 'local'], ['app_key' => ''],
            ['expires_at' => ''], ['expires_at' => '17201'], ['disk_visibility' => 'public'], ['cache_store' => 'array']] as $invalid) {
            $this->assertSame(1, $this->runProbe($this->settings($invalid), $cache, $disk));
        }
        $this->assertSame(0, $this->runProbe($this->settings(['expires_at' => '9999']), $cache, $disk));
        $this->assertSame(['expired', true], $this->events[array_key_last($this->events)]);
        $this->assertSame(0, $this->databaseCalls);
        $this->assertSame([], $cache->calls);
        $this->assertSame([], $disk->calls);
    }

    public function test_write_read_cleanup_and_retirement_preserve_the_probe_lifecycle(): void
    {
        $cache = new ProbeCacheFixture;
        $disk = new ProbeDiskFixture;
        $this->assertSame(0, $this->runProbe($this->settings(), $cache, $disk));
        $writeLockKey = $cache->lastLockKey;
        $this->assertCount(1, $disk->values);
        $this->assertSame(['visibility' => 'private'], $disk->lastOptions);
        $this->assertLessThan(100, strlen(reset($disk->values)));
        $this->assertSame(4200, $cache->lastTtl);

        $cache->calls = $disk->calls = [];
        $this->assertSame(0, $this->runProbe($this->settings(['mode' => 'read']), $cache, $disk));
        $this->assertSame(['get', 'get'], $cache->calls);
        $this->assertSame(['get'], $disk->calls);

        $this->assertSame(0, $this->runProbe($this->settings(['mode' => 'cleanup']), $cache, $disk));
        $this->assertSame($writeLockKey, $cache->lastLockKey);
        $this->assertSame([], $disk->values);
        $this->assertCount(1, $cache->values);
        $this->assertTrue(reset($cache->values));
        $this->assertSame(0, $this->runProbe($this->settings(['mode' => 'cleanup']), $cache, $disk));

        $cache->calls = $disk->calls = [];
        $this->assertSame(0, $this->runProbe($this->settings(), $cache, $disk));
        $this->assertSame(['lock', 'get'], $cache->calls);
        $this->assertSame([], $disk->calls);
        $this->assertSame(['retired', true], $this->events[array_key_last($this->events)]);
        $this->assertSame(0, $this->runProbe($this->settings(['mode' => 'read']), $cache, $disk));
        $this->assertSame([], $disk->values);
    }

    public function test_read_rejects_missing_or_changed_artifacts_without_recreating_them(): void
    {
        $cache = new ProbeCacheFixture;
        $disk = new ProbeDiskFixture;
        $this->assertSame(1, $this->runProbe($this->settings(['mode' => 'read']), $cache, $disk));
        $this->assertSame([], $cache->values);
        $this->assertSame([], $disk->values);
        $this->assertSame(0, $this->runProbe($this->settings(), $cache, $disk));
        $disk->values[array_key_first($disk->values)] = 'unexpected fictional bytes';
        $cache->calls = $disk->calls = [];
        $this->assertSame(1, $this->runProbe($this->settings(['mode' => 'read']), $cache, $disk));
        $this->assertSame(['get', 'get'], $cache->calls);
        $this->assertSame(['get'], $disk->calls);
    }

    public function test_a_delayed_writer_removes_artifacts_if_cleanup_retired_it_during_the_write(): void
    {
        $cache = new ProbeCacheFixture;
        $disk = new ProbeDiskFixture;
        $disk->beforePut = function () use ($cache, $disk): void {
            $this->assertSame(0, $this->runProbe($this->settings(['mode' => 'cleanup']), $cache, $disk));
        };
        $this->assertSame(0, $this->runProbe($this->settings(), $cache, $disk));
        $this->assertSame([], $disk->values);
        $this->assertCount(1, $cache->values);
        $this->assertTrue(reset($cache->values));
        $this->assertSame(['retired', true], $this->events[array_key_last($this->events)]);
    }

    public function test_false_writes_and_secret_bearing_exceptions_report_only_fixed_labels(): void
    {
        $cache = new ProbeCacheFixture;
        $disk = new ProbeDiskFixture;
        $cache->putResult = false;
        $this->assertSame(1, $this->runProbe($this->settings(), $cache, $disk));
        $cache->putResult = true;
        $disk->putResult = false;
        $this->assertSame(1, $this->runProbe($this->settings(), $cache, $disk));
        $disk->putResult = true;
        $disk->throw = true;
        $this->assertSame(1, $this->runProbe($this->settings(), $cache, $disk, fn () => throw new RuntimeException('credential=fixture-private-value')));
        $output = json_encode($this->events);
        $this->assertStringNotContainsString('fixture-private-value', $output);
        $this->assertStringNotContainsString('deployment-probe:', $output);
        $this->assertStringNotContainsString(str_repeat('k', 32), $output);
        $this->assertContains(['database', false], $this->events);
        $this->assertContains(['storage-write-read', false], $this->events);
    }
}

class ProbeCacheFixture
{
    public array $values = [];
    public array $calls = [];
    public bool $putResult = true;
    public int $lastTtl = 0;
    public string $lastLockKey = '';

    public function get(string $key): mixed
    {
        $this->calls[] = 'get';

        return $this->values[$key] ?? null;
    }

    public function put(string $key, mixed $value, int $ttl): bool
    {
        $this->calls[] = 'put';
        $this->lastTtl = $ttl;
        if ($this->putResult) {
            $this->values[$key] = $value;
        }

        return $this->putResult;
    }

    public function forget(string $key): void
    {
        $this->calls[] = 'forget';
        unset($this->values[$key]);
    }

    public function lock(string $key, int $seconds): object
    {
        $this->calls[] = 'lock';
        $this->lastLockKey = $key;

        return new class
        {
            public function block(int $seconds, callable $callback): int
            {
                return $callback();
            }
        };
    }
}

class ProbeDiskFixture
{
    public array $values = [];
    public array $calls = [];
    public array $lastOptions = [];
    public bool $putResult = true;
    public bool $throw = false;
    public ?\Closure $beforePut = null;

    public function put(string $key, string $contents, array $options): bool
    {
        $this->calls[] = 'put';
        $this->lastOptions = $options;
        if ($this->throw) {
            throw new RuntimeException('credential=fixture-private-value');
        }
        if ($this->beforePut) {
            ($this->beforePut)();
        }
        if ($this->putResult) {
            $this->values[$key] = $contents;
        }

        return $this->putResult;
    }

    public function get(string $key): ?string
    {
        $this->calls[] = 'get';

        return $this->values[$key] ?? null;
    }

    public function delete(string $key): bool
    {
        $this->calls[] = 'delete';
        unset($this->values[$key]);

        return true;
    }

    public function exists(string $key): bool
    {
        $this->calls[] = 'exists';

        return array_key_exists($key, $this->values);
    }
}
