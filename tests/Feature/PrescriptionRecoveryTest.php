<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrescriptionRecoveryTest extends TestCase
{
    public function test_database_and_private_objects_restore_together_and_corruption_is_detected(): void
    {
        $original = DB::getDefaultConnection();
        $source = tempnam(sys_get_temp_dir(), 'rhu-recovery-');
        $backup = tempnam(sys_get_temp_dir(), 'rhu-recovery-');
        $restored = tempnam(sys_get_temp_dir(), 'rhu-recovery-');
        $key = 'prescriptions/5/fixture.pdf';
        $bytes = "%PDF-1.4\nFictional recovery fixture.\n";

        try {
            config(['database.connections.recovery_source' => ['driver' => 'sqlite', 'database' => $source],
                'database.connections.recovery_restored' => ['driver' => 'sqlite', 'database' => $restored]]);
            DB::setDefaultConnection('recovery_source');
            Schema::create('dispensing_records', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('health_unit_id');
                $table->string('prescription_image');
                $table->softDeletes();
            });
            DB::table('dispensing_records')->insert(['id' => 4, 'health_unit_id' => 5, 'prescription_image' => $key]);
            $sourceDisk = Storage::fake('recovery_source');
            $backupDisk = Storage::fake('recovery_backup');
            $destinationDisk = Storage::fake('recovery_destination');
            $sourceDisk->put($key, $bytes);
            config(['prescriptions.disk' => 'recovery_source']);
            $this->assertSame(0, Artisan::call('prescriptions:verify-recovery'));
            $this->assertSame(1, preg_match('/SHA-256: ([0-9a-f]{64})/', Artisan::output(), $match));
            $expected = $match[1];

            DB::purge('recovery_source');
            $this->assertTrue(copy($source, $backup));
            $backupDisk->put($key, $sourceDisk->get($key));
            $this->assertTrue(copy($backup, $restored));
            $destinationDisk->put($key, $backupDisk->get($key));
            DB::setDefaultConnection('recovery_restored');
            config(['prescriptions.disk' => 'recovery_destination']);
            $this->assertSame($key, DB::table('dispensing_records')->find(4)->prescription_image);
            $this->artisan('prescriptions:verify-recovery', ['--expect' => $expected])
                ->expectsOutput('Verified 1 active prescription reference(s).')->assertSuccessful();

            $destinationDisk->put($key, 'Different fixture data');
            $this->artisan('prescriptions:verify-recovery', ['--expect' => $expected])
                ->expectsOutput('Prescription references or file contents differ from the source fingerprint.')->assertFailed();
            $destinationDisk->delete($key);
            $this->artisan('prescriptions:verify-recovery', ['--expect' => $expected])
                ->expectsOutput('1 prescription reference(s) were invalid, missing, or unreadable.')->assertFailed();
        } finally {
            DB::purge('recovery_source');
            DB::purge('recovery_restored');
            DB::setDefaultConnection($original);
            foreach ([$source, $backup, $restored] as $file) {
                unlink($file);
            }
        }
    }
}
