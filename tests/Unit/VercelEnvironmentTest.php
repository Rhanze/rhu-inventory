<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../deployment/verify-vercel-environment.php';

class VercelEnvironmentTest extends TestCase
{
    private function environment(): array
    {
        return ['APP_KEY' => 'base64:'.base64_encode(str_repeat('k', 32)), 'APP_URL' => 'https://management.example.org', 'APP_DEBUG' => 'false',
            'PUBLIC_DOMAIN' => 'example.org', 'MANAGEMENT_DOMAIN' => 'management.example.org',
            'PORTAL_SURFACE' => 'management', 'SESSION_ENCRYPT' => 'true', 'SESSION_EXPIRE_ON_CLOSE' => 'true', 'SESSION_LIFETIME' => '30',
            'DB_HOST' => 'database.example.org', 'DB_DATABASE' => 'rhu',
            'DB_USERNAME' => 'rhu_fixture', 'REDIS_HOST' => 'redis.example.org',
            'AWS_ACCESS_KEY_ID' => 'fixture', 'AWS_SECRET_ACCESS_KEY' => 'fixture', 'AWS_BUCKET' => 'private-fixture',
            'AWS_DEFAULT_REGION' => 'test-region', 'CRON_SECRET' => str_repeat('a', 48)];
    }

    public function test_managed_services_configuration_passes_without_connecting_to_them(): void
    {
        $this->assertSame([], \vercelEnvironmentProblems($this->environment()));
    }

    public function test_management_requires_explicit_session_encryption_cookie_and_short_idle_lifetime(): void
    {
        foreach (['SESSION_ENCRYPT' => 'false', 'SESSION_EXPIRE_ON_CLOSE' => 'false', 'SESSION_LIFETIME' => '120'] as $key => $unsafe) {
            $env = $this->environment();
            unset($env[$key]);
            $this->assertStringContainsString($key, implode(' ', \vercelEnvironmentProblems($env)));
            $env[$key] = $unsafe;
            $this->assertStringContainsString($key, implode(' ', \vercelEnvironmentProblems($env)));
        }
    }

    public function test_namespaced_storage_credentials_work_when_aws_names_are_empty(): void
    {
        $env = array_replace($this->environment(), ['AWS_ACCESS_KEY_ID' => '', 'AWS_SECRET_ACCESS_KEY' => '',
            'RHU_S3_ACCESS_KEY_ID' => 'fixture-access-key', 'RHU_S3_SECRET_ACCESS_KEY' => 'fixture-secret-key']);
        $this->assertSame([], \vercelEnvironmentProblems($env));
    }

    public function test_management_requires_both_effective_storage_credentials(): void
    {
        $env = $this->environment();
        unset($env['AWS_ACCESS_KEY_ID'], $env['AWS_SECRET_ACCESS_KEY']);
        foreach ([[], ['RHU_S3_ACCESS_KEY_ID' => 'fixture-access-key'], ['RHU_S3_SECRET_ACCESS_KEY' => 'fixture-secret-key']] as $credentials) {
            $this->assertNotEmpty(\vercelEnvironmentProblems(array_replace($env, $credentials)));
        }
        // Explicit empty aliases override fallbacks, matching Laravel's env() resolution.
        foreach (['RHU_S3_ACCESS_KEY_ID', 'RHU_S3_SECRET_ACCESS_KEY'] as $alias) {
            $errors = \vercelEnvironmentProblems(array_replace($this->environment(), [$alias => '']));
            $this->assertStringContainsString($alias, implode(' ', $errors));
        }
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

    public function test_copied_placeholders_and_invalid_encryption_keys_are_rejected(): void
    {
        foreach (['GENERATE_A_NEW_KEY_FOR_THIS_PROJECT', 'base64:not-a-key', 'base64:'.base64_encode('short')] as $key) {
            $this->assertStringContainsString('APP_KEY', implode(' ', \vercelEnvironmentProblems(array_replace($this->environment(), ['APP_KEY' => $key]))));
        }
    }

    public function test_url_configuration_is_validated_after_laravel_overrides(): void
    {
        foreach (['postgres://fixture:secret@database.example.org/rhu', 'mysql://fixture:secret@database.example.org/rhu?driver=pgsql',
            'mysql://fixture:secret@database.example.org/rhu?host=127.0.0.1', 'mysql://fixture:secret@database.example.org:99999/rhu'] as $url) {
            $this->assertNotEmpty(\vercelEnvironmentProblems(array_replace($this->environment(), ['DB_URL' => $url])));
        }
        $env = $this->environment();
        unset($env['DB_HOST'], $env['DB_DATABASE'], $env['DB_USERNAME']);
        $env['DB_URL'] = 'mysql://fixture:secret@database.example.org/rhu';
        $this->assertSame([], \vercelEnvironmentProblems($env));
        $this->assertNotEmpty(\vercelEnvironmentProblems(array_replace($env, ['DB_CONNECTION' => 'pgsql'])));
    }

    public function test_redis_requires_a_remote_tcp_endpoint_and_installed_client(): void
    {
        foreach ([['REDIS_URL' => 'https://redis.example.org'], ['REDIS_HOST' => 'tls://localhost'],
            ['REDIS_URL' => 'rediss://redis.example.org?host=127.0.0.1'], ['REDIS_CLIENT' => 'predis']] as $invalid) {
            $this->assertNotEmpty(\vercelEnvironmentProblems(array_replace($this->environment(), $invalid)));
        }
        foreach ([['REDIS_HOST' => 'tls://redis.example.org'], ['REDIS_URL' => 'rediss://default:secret@redis.example.org:6379/0']] as $valid) {
            $this->assertSame([], \vercelEnvironmentProblems(array_replace($this->environment(), $valid)));
        }
    }

    public function test_public_read_only_database_is_not_used_for_session_or_cache_writes(): void
    {
        $env = array_replace($this->environment(), ['PORTAL_SURFACE' => 'public', 'APP_URL' => 'https://example.org']);
        foreach (['SESSION_DRIVER', 'CACHE_STORE'] as $setting) {
            $errors = \vercelEnvironmentProblems(array_replace($env, [$setting => 'database']));
            $this->assertStringContainsString('read-only', implode(' ', $errors));
        }
    }

    public function test_missing_database_identity_is_reported(): void
    {
        $env = $this->environment();
        unset($env['DB_DATABASE'], $env['DB_USERNAME']);
        $this->assertStringContainsString('DB_DATABASE', implode(' ', \vercelEnvironmentProblems($env)));
    }

    public function test_provider_ca_environment_is_validated_before_startup_without_requiring_it(): void
    {
        $certificate = file_get_contents(__DIR__.'/../Fixtures/database-ca.pem');
        $this->assertSame([], \vercelEnvironmentProblems(array_replace($this->environment(), ['MYSQL_SSL_CA_PEM' => $certificate])));
        $errors = \vercelEnvironmentProblems(array_replace($this->environment(), ['MYSQL_SSL_CA_PEM' => 'private_invalid_value']));
        $this->assertStringContainsString('MYSQL_SSL_CA_PEM', implode(' ', $errors));
        $this->assertStringNotContainsString('private_invalid_value', implode(' ', $errors));
    }
}
