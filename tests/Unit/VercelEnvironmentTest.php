<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../deployment/verify-vercel-environment.php';

class VercelEnvironmentTest extends TestCase
{
    private function environment(): array
    {
        return ['APP_KEY' => 'test-only-key', 'APP_URL' => 'https://management.example.org', 'APP_DEBUG' => 'false',
            'PUBLIC_DOMAIN' => 'example.org', 'MANAGEMENT_DOMAIN' => 'management.example.org',
            'PORTAL_SURFACE' => 'management', 'DB_HOST' => 'database.example.org', 'REDIS_HOST' => 'redis.example.org',
            'AWS_ACCESS_KEY_ID' => 'fixture', 'AWS_SECRET_ACCESS_KEY' => 'fixture', 'AWS_BUCKET' => 'private-fixture',
            'AWS_DEFAULT_REGION' => 'test-region', 'CRON_SECRET' => str_repeat('a', 48)];
    }

    public function test_managed_services_configuration_passes_without_connecting_to_them(): void
    {
        $this->assertSame([], \vercelEnvironmentProblems($this->environment()));
    }

    public function test_local_disks_database_and_wrong_surface_are_rejected(): void
    {
        $errors = \vercelEnvironmentProblems(array_replace($this->environment(), [
            'SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'array', 'DB_HOST' => '127.0.0.1',
            'PRESCRIPTION_DISK' => 'local', 'APP_DEBUG' => 'true', 'APP_URL' => 'http://localhost',
        ]));
        $this->assertGreaterThanOrEqual(5, count($errors));
        $this->assertNotEmpty(\vercelEnvironmentProblems(array_replace($this->environment(), ['PORTAL_SURFACE' => 'both'])));
    }

    public function test_public_instance_does_not_require_private_bucket_credentials(): void
    {
        $env = $this->environment();
        foreach (['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_BUCKET', 'AWS_DEFAULT_REGION', 'CRON_SECRET'] as $key) {
            unset($env[$key]);
        }
        $env['PORTAL_SURFACE'] = 'public';
        $env['APP_URL'] = 'https://example.org';
        $this->assertSame([], \vercelEnvironmentProblems($env));
    }
}
