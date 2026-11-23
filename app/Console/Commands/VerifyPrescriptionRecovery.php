<?php

namespace App\Console\Commands;

use App\Models\DispensingRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class VerifyPrescriptionRecovery extends Command
{
    protected $signature = 'prescriptions:verify-recovery {--expect= : SHA-256 fingerprint recorded from the source snapshot}';

    protected $description = 'Verify live prescription references and bytes without modifying records or files';

    public function handle(): int
    {
        $expected = $this->option('expect');
        if ($expected !== null && ! preg_match('/\A[0-9a-fA-F]{64}\z/', $expected)) {
            $this->error('Use a 64-digit SHA-256 fingerprint.');

            return self::FAILURE;
        }

        $disk = Storage::disk(config('prescriptions.disk'));
        $digest = hash_init('sha256');
        hash_update($digest, "rhu-prescriptions-v1\n");
        $checked = 0;
        $unavailable = 0;

        DispensingRecord::query()->whereNotNull('prescription_image')->where('prescription_image', '<>', '')
            ->select('id', 'health_unit_id', 'prescription_image')
            ->eachById(function (DispensingRecord $record) use ($disk, $digest, &$checked, &$unavailable) {
                $key = $record->prescription_image;
                if (! preg_match('~\Aprescriptions/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_.-]+\z~', $key)
                    || in_array(basename($key), ['.', '..'], true)) {
                    $unavailable++;

                    return;
                }

                try {
                    $stream = $disk->readStream($key);
                    if (! is_resource($stream)) {
                        $unavailable++;

                        return;
                    }

                    try {
                        $fileDigest = hash_init('sha256');
                        $length = hash_update_stream($fileDigest, $stream);
                        if ($length === false || $length === 0 || ! feof($stream)) {
                            $unavailable++;

                            return;
                        }
                        hash_update($digest, json_encode([$record->id, $record->health_unit_id, $key, hash_final($fileDigest)], JSON_THROW_ON_ERROR)."\n");
                        $checked++;
                    } finally {
                        fclose($stream);
                    }
                } catch (Throwable) {
                    $unavailable++;
                }
            });

        if ($unavailable > 0) {
            $this->error("{$unavailable} prescription reference(s) were invalid, missing, or unreadable.");

            return self::FAILURE;
        }

        $fingerprint = hash_final($digest);
        if ($expected !== null && ! hash_equals(strtolower($expected), $fingerprint)) {
            $this->error('Prescription references or file contents differ from the source fingerprint.');

            return self::FAILURE;
        }

        $this->info("Verified {$checked} active prescription reference(s).");
        $this->line('SHA-256: '.$fingerprint);

        return self::SUCCESS;
    }
}
