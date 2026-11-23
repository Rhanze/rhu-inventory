<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__.'/../../deployment/database-ca.php';

class DatabaseCaTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'rhu-ca-test-');
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function test_provider_certificate_bundles_can_be_written_from_multiline_or_escaped_values(): void
    {
        $certificate = file_get_contents(__DIR__.'/../Fixtures/database-ca.pem');
        foreach ([$certificate, str_replace("\n", '\\n', $certificate)] as $input) {
            $this->assertTrue(\materializeDatabaseCa($input, $this->path));
            $this->assertNotFalse(openssl_x509_read(file_get_contents($this->path)));
            $this->assertSame(\databaseCaPem($certificate), file_get_contents($this->path));
        }
        $this->assertTrue(\materializeDatabaseCa($certificate.$certificate, $this->path));
        $this->assertSame(2, substr_count(file_get_contents($this->path), '-----BEGIN CERTIFICATE-----'));
    }

    public function test_omitting_certificate_environment_value_preserves_an_existing_ca_file(): void
    {
        file_put_contents($this->path, 'existing configured CA');
        $this->assertFalse(\materializeDatabaseCa(null, $this->path));
        $this->assertSame('existing configured CA', file_get_contents($this->path));
    }

    public function test_invalid_certificate_values_and_private_keys_are_rejected_without_being_logged(): void
    {
        $certificate = file_get_contents(__DIR__.'/../Fixtures/database-ca.pem');
        foreach (['private_input_sentinel', "-----BEGIN CERTIFICATE-----\nYWJj\n-----END CERTIFICATE-----",
            $certificate."\n-----BEGIN PRIVATE KEY-----\nprivate_input_sentinel\n-----END PRIVATE KEY-----"] as $input) {
            try {
                \materializeDatabaseCa($input, $this->path);
                $this->fail('An invalid provider certificate must not be written.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('MYSQL_SSL_CA_PEM', $error->getMessage());
                $this->assertStringNotContainsString('private_input_sentinel', $error->getMessage());
                $this->assertSame('', file_get_contents($this->path));
            }
        }
    }
}
