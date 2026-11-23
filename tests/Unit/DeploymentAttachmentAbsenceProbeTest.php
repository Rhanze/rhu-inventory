<?php

namespace Tests\Unit;

use Aws\Command;
use Aws\S3\Exception\S3Exception;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__.'/../../deployment/verify-attachment-absence.php';

class DeploymentAttachmentAbsenceProbeTest extends TestCase
{
    private array $events = [];
    private array $requests = [];

    private function settings(array $overrides = []): array
    {
        return array_replace([
            'mode' => 'read', 'expires_at' => '11800', 'environment' => 'production', 'surface' => 'management',
            'prescription_disk' => 's3', 'disk_driver' => 's3', 'disk_visibility' => 'private',
            'endpoint' => 'https://exwmjzryhkcfqyhwkkbm.storage.supabase.co/storage/v1/s3',
            'bucket' => 'rhu-prescriptions', 'region' => 'ap-northeast-2', 'use_path_style_endpoint' => true,
            'key' => 'fictional-access-key', 'secret' => 'fictional-secret-do-not-emit',
        ], $overrides);
    }

    private function error(int $status, string $code): S3Exception
    {
        return new S3Exception('secret=do-not-emit', new Command('HeadObject'), [
            'response' => new Response($status), 'code' => $code,
        ]);
    }

    private function runProbe(array $settings, ?callable $object = null, ?callable $bucket = null): int
    {
        return \deploymentAttachmentAbsenceProbe($settings, function (string $name) use ($bucket) {
            $this->requests[] = ['HeadBucket', $name];

            return $bucket ? $bucket() : ['@metadata' => ['statusCode' => 200]];
        }, function (string $bucket, string $key) use ($object) {
            $this->requests[] = ['HeadObject', $bucket, $key];

            return $object ? $object() : throw $this->error(404, 'NotFound');
        }, function (string $name, bool $passed): void {
            $this->events[] = [$name, $passed];
        }, 10000);
    }

    public function test_disabled_expired_and_invalid_expiry_never_contact_storage(): void
    {
        $this->assertSame(0, $this->runProbe($this->settings(['mode' => ''])));
        $this->assertSame([], $this->events);
        foreach (['9999', '10000'] as $expiry) {
            $this->assertSame(0, $this->runProbe($this->settings(['expires_at' => $expiry])));
            $this->assertSame(['expired', true], $this->events[array_key_last($this->events)]);
        }
        foreach (['', '0', '-1', '11801', '1e4'] as $expiry) {
            $this->assertSame(1, $this->runProbe($this->settings(['expires_at' => $expiry])));
        }
        $this->assertSame(1, $this->runProbe($this->settings(['mode' => 'write'])));
        $this->assertSame([], $this->requests);
    }

    public function test_misconfigured_surface_storage_or_credentials_never_contact_storage(): void
    {
        foreach ([['environment' => 'local'], ['surface' => 'public'], ['prescription_disk' => 'local'],
            ['disk_driver' => 'local'], ['disk_visibility' => 'public'], ['endpoint' => 'https://example.invalid'],
            ['bucket' => 'other'], ['region' => 'ap-southeast-1'], ['use_path_style_endpoint' => false],
            ['key' => ''], ['secret' => ''], ['key' => "invalid\ncredential"]] as $invalid) {
            $this->assertSame(1, $this->runProbe($this->settings($invalid)));
        }
        $this->assertSame([], $this->requests);
    }

    public function test_only_the_two_recorded_keys_are_checked_after_authorized_bucket_access(): void
    {
        $this->assertSame(0, $this->runProbe($this->settings(['object_keys' => ['arbitrary.pdf']])));
        $this->assertSame([
            ['HeadBucket', 'rhu-prescriptions'],
            ['HeadObject', 'rhu-prescriptions', 'prescriptions/60033/bqmKJBX9doFcAdTMRgY4K9Jt0hYJZkYiAffPrl3l.pdf'],
            ['HeadObject', 'rhu-prescriptions', 'prescriptions/60035/qFZkPDwcV7PjwhfIpQTpGpWIh5UkeJ4KBkMNWI9S.pdf'],
        ], $this->requests);
        $this->assertSame([['bucket_access', true], ['fixture_1', true], ['fixture_2', true], ['summary', true]], $this->events);
    }

    public function test_bucket_failure_prevents_object_checks(): void
    {
        foreach ([fn () => ['@metadata' => ['statusCode' => 403]], fn () => throw $this->error(403, 'AccessDenied'),
            fn () => throw new RuntimeException('credential=do-not-emit')] as $bucket) {
            $this->requests = [];
            $this->assertSame(1, $this->runProbe($this->settings(), null, $bucket));
            $this->assertSame([['HeadBucket', 'rhu-prescriptions']], $this->requests);
        }
    }

    public function test_present_objects_forbidden_network_and_unrecognized_errors_are_not_absence(): void
    {
        foreach ([fn () => ['@metadata' => ['statusCode' => 200]], fn () => throw $this->error(403, 'AccessDenied'),
            fn () => throw $this->error(404, 'AccessDenied'), fn () => throw $this->error(500, 'NotFound'),
            fn () => throw new RuntimeException('credential=do-not-emit')] as $object) {
            $this->events = [];
            $this->assertSame(1, $this->runProbe($this->settings(), $object));
            $this->assertContains(['fixture_1', false], $this->events);
            $this->assertContains(['fixture_2', false], $this->events);
            $this->assertContains(['summary', false], $this->events);
        }
        $this->assertStringNotContainsString('do-not-emit', json_encode($this->events));
        $this->assertStringNotContainsString('prescriptions/', json_encode($this->events));
    }

    public function test_provider_no_such_key_is_an_accepted_404(): void
    {
        $this->assertSame(0, $this->runProbe($this->settings(), fn () => throw $this->error(404, 'NoSuchKey')));
        $this->assertSame(['summary', true], $this->events[array_key_last($this->events)]);
    }

    public function test_expiry_during_bucket_request_stops_without_claiming_object_absence(): void
    {
        $objectCalls = 0;
        $events = [];
        $settings = $this->settings(['expires_at' => (string) (time() + 1)]);
        $result = \deploymentAttachmentAbsenceProbe($settings, static function (): array {
            sleep(2);

            return ['@metadata' => ['statusCode' => 200]];
        }, static function () use (&$objectCalls): void {
            $objectCalls++;
        }, static function (string $name, bool $passed) use (&$events): void {
            $events[] = [$name, $passed];
        });
        $this->assertSame(0, $result);
        $this->assertSame(0, $objectCalls);
        $this->assertSame([['bucket_access', true], ['expired', true]], $events);
    }
}
