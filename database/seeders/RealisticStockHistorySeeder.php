<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\MidwifeBarangay;
use App\Models\StockLog;
use App\Models\User;
use App\Services\StockMovementService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** Generated local history with ordinary display values and private provenance. Never rewrites saved stock. */
class RealisticStockHistorySeeder extends Seeder
{
    public array $report = [];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Generated stock history is restricted to local and testing environments.');
        }
        if (config('filesystems.disks.local.driver') !== 'local') {
            throw new \RuntimeException('Generated history requires private local manifest storage.');
        }
        $this->assertLocalDatabase();
        $root = str_replace('\\', '/', rtrim((string) config('filesystems.disks.local.root'), '/\\'));
        $private = str_replace('\\', '/', storage_path('app/private'));
        if (app()->environment('local') && $root !== $private && ! str_starts_with($root, $private.'/')) {
            throw new \RuntimeException('Generated-history manifests must stay beneath private application storage.');
        }

        $disk = Storage::disk('local');
        $disk->makeDirectory('generated-data');
        $fingerprint = $this->databaseFingerprint();
        $manifestPath = 'generated-data/realistic-stock-history-v1-'.$fingerprint.'.json';
        $lock = fopen($disk->path($manifestPath.'.lock'), 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new \RuntimeException('Unable to lock the generated-history manifest.');
        }

        try {
            $previous = $disk->exists($manifestPath) ? $disk->get($manifestPath) : null;
            $manifest = $previous !== null ? json_decode($previous, true, flags: JSON_THROW_ON_ERROR) : [
                'schema' => 1, 'generator' => self::class, 'source' => 'synthetic',
                'database_fingerprint' => $fingerprint, 'created_at' => now()->toIso8601String(),
                'first_week' => CarbonImmutable::today()->startOfWeek(CarbonImmutable::MONDAY)->subWeeks(12)->toDateString(),
                'batches' => [], 'barangay_assignments' => [],
            ];
            if (($manifest['generator'] ?? null) !== self::class || ($manifest['source'] ?? null) !== 'synthetic'
                || ($manifest['database_fingerprint'] ?? null) !== $fingerprint || ($manifest['schema'] ?? null) !== 1) {
                throw new \RuntimeException('The private generated-history manifest does not match this database.');
            }

            $targetId = env('SEED_HEALTH_UNIT_ID');
            $units = HealthUnit::whereIn('facility_type', ['Rural Health Unit', 'RHU'])
                ->when($targetId !== null && $targetId !== '', fn ($query) => $query->whereKey((int) $targetId))
                ->whereHas('users', fn ($query) => $query->whereIn('role', ['admin', 'staff'])->where('is_active', true))
                ->orderBy('id')->get();
            $medicines = $this->medicines();
            if ($units->isEmpty() || $medicines->isEmpty()) {
                throw new \RuntimeException('Provide an RHU with active staff and dispensable non-vaccine medicines with strength, form, and unit.');
            }

            $this->report = ['facilities' => $units->count(), 'batches_created' => 0, 'batches_preserved' => 0,
                'dispensing_created' => 0, 'assignments_created' => 0, 'facilities_without_known_barangay' => [],
                'manifest' => $manifestPath];
            try {
                DB::transaction(function () use ($units, $medicines, &$manifest, $disk, $manifestPath) {
                    foreach ($units as $unit) {
                        HealthUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
                        $staff = User::where('health_unit_id', $unit->id)->whereIn('role', ['admin', 'staff'])
                            ->where('is_active', true)->orderBy('id')->get();
                        $scopes = $this->dispensingScopes($unit, $staff->first());
                        if (collect($scopes)->every(fn ($scope) => $scope['barangay'] === null)) {
                            $this->report['facilities_without_known_barangay'][] = $unit->id;
                        }
                        foreach ($medicines as $index => $medicine) {
                            $key = $unit->id.':'.$medicine->id;
                            $batchNo = self::batchNumber($unit->id, $medicine->id);
                            $existing = Inventory::withTrashed()->where('health_unit_id', $unit->id)->where('batch_no', $batchNo)->first();
                            if ($existing) {
                                $owned = $manifest['batches'][$key] ?? null;
                                if (! $owned || (int) $owned['inventory_id'] !== $existing->id
                                    || (int) $owned['medicine_id'] !== (int) $existing->medicine_id) {
                                    throw new \RuntimeException('Refusing to adopt an existing unowned stock batch: '.$batchNo);
                                }
                                $this->report['batches_preserved']++;
                                continue;
                            }
                            // A prior interrupted transaction may leave a manifest reservation with no saved batch.
                            $manifest['batches'][$key] = $this->createHistory($unit, $medicine, $index, $staff[$index % $staff->count()],
                                $scopes, CarbonImmutable::parse($manifest['first_week']), $batchNo);
                            $this->report['batches_created']++;
                        }
                    }
                    $manifest['updated_at'] = now()->toIso8601String();
                    // A write failure rolls back all new database rows; never commit rows without provenance.
                    $this->writeManifest($disk, $manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                });
            } catch (\Throwable $error) {
                if ($previous === null) {
                    $disk->delete($manifestPath);
                } else {
                    $this->writeManifest($disk, $manifestPath, $previous);
                }
                throw $error;
            }
            $this->command?->info('Created '.$this->report['batches_created'].' batches and '.$this->report['dispensing_created']
                .' dispensing records across '.$this->report['facilities'].' RHUs; preserved '.$this->report['batches_preserved'].' existing owned batches.');
            if ($this->report['facilities_without_known_barangay'] !== []) {
                $this->command?->warn('No existing barangay names were found for RHUs '.implode(', ', $this->report['facilities_without_known_barangay'])
                    .'; their records remain at RHU scope.');
            }
            $this->command?->info('Private source and record ownership: '.$disk->path($manifestPath));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function batchNumber(int $healthUnitId, int $medicineId): string
    {
        return sprintf('ATQ-%03d-%05d-01', $healthUnitId, $medicineId);
    }

    private function databaseFingerprint(): string
    {
        // Laravel resolves DB_URL before returning connection configuration; credentials never enter the manifest.
        $connection = DB::connection()->getConfig();
        return substr(hash('sha256', json_encode([$connection['driver'] ?? null, $connection['host'] ?? null,
            $connection['port'] ?? null, $connection['database'] ?? null], JSON_THROW_ON_ERROR)), 0, 20);
    }

    private function assertLocalDatabase(): void
    {
        $connection = DB::connection()->getConfig();
        if (($connection['driver'] ?? null) === 'sqlite') {
            return;
        }
        if (! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new \RuntimeException('Generated history requires SQLite or a loopback MySQL database.');
        }
        foreach ((array) ($connection['host'] ?? []) as $host) {
            if (! in_array(strtolower(trim((string) $host, '[]')), ['localhost', '127.0.0.1', '::1'], true)) {
                throw new \RuntimeException('Generated history cannot be written to a remote database.');
            }
        }
        if (empty($connection['host'])) {
            throw new \RuntimeException('Generated history requires an explicit loopback database host.');
        }
    }

    private function medicines(): Collection
    {
        $linked = DB::table('disease_medicine_map')->join('diseases', 'diseases.id', '=', 'disease_medicine_map.disease_id')
            ->where('diseases.is_active', true)->pluck('medicine_id')->unique()->all();
        $common = ['paracetamol', 'oral rehydration', 'ors', 'amoxicillin', 'cetirizine', 'loratadine', 'amlodipine',
            'losartan', 'metformin', 'salbutamol', 'zinc', 'ferrous'];
        return Medicine::where('is_dispensable', true)
            ->where(fn ($query) => $query->whereNull('category')->orWhereRaw('LOWER(category) <> ?', ['vaccine']))
            ->whereNotNull('dosage')->whereRaw("TRIM(dosage) <> ''")
            ->whereNotNull('dosage_form')->whereRaw("TRIM(dosage_form) <> ''")
            ->whereNotNull('unit')->whereRaw("TRIM(unit) <> ''")->get()
            ->sort(function ($left, $right) use ($linked, $common) {
                $score = function ($medicine) use ($linked, $common) {
                    $commonName = collect($common)->contains(fn ($name) => str_contains(strtolower($medicine->medicine_name), $name));
                    return (in_array($medicine->id, $linked, true) ? 100 : 0) + ($commonName ? 30 : 0)
                        + (strtolower((string) $medicine->category) === 'medicine' ? 10 : 0);
                };
                return ($score($right) <=> $score($left)) ?: strcasecmp($left->full_label, $right->full_label) ?: ($left->id <=> $right->id);
            })->take(16)->values();
    }

    private function dispensingScopes(HealthUnit $unit, User $staff): array
    {
        $knownAreas = (Schema::hasTable('barangays') ? Barangay::where('health_unit_id', $unit->id)->pluck('barangay_name') : collect())
            ->merge(DB::table('public_users')->where('health_unit_id', $unit->id)->whereNotNull('barangay')->pluck('barangay'))
            ->merge(DispensingRecord::where('health_unit_id', $unit->id)->whereNotNull('barangay')->pluck('barangay'))
            ->map(fn ($name) => trim((string) $name))->filter()->unique()->sort()->values();
        $scopes = [];
        foreach (User::where('health_unit_id', $unit->id)->where('role', 'midwife')->where('is_active', true)->orderBy('id')->get() as $midwife) {
            $assigned = MidwifeBarangay::where('user_id', $midwife->id)->orderByDesc('is_primary')->orderBy('barangay')->pluck('barangay');
            // Assignments are explicit management decisions; generated history never expands a midwife's area.
            foreach ($assigned as $area) {
                $scopes[] = ['actor_id' => $midwife->id, 'barangay' => $area];
            }
        }
        if ($scopes === []) {
            foreach ($knownAreas as $area) {
                $scopes[] = ['actor_id' => $staff->id, 'barangay' => $area];
            }
        }
        return $scopes ?: [['actor_id' => $staff->id, 'barangay' => null]];
    }

    private function createHistory(HealthUnit $unit, Medicine $medicine, int $index, User $staff, array $scopes,
        CarbonImmutable $firstWeek, string $batchNo): array
    {
        $weekly = $this->weeklyQuantities($medicine, $index, $unit->id);
        $minimum = max(1, $medicine->effectiveMinStock());
        $maximum = max($minimum + 1, $medicine->effectiveMaxStock());
        $remaining = match ($index % 4) {
            0 => max(1, (int) round($minimum * 0.6)),
            1 => $minimum + 30 + $index * 3,
            2 => (int) round(($minimum + $maximum) / 2),
            default => $maximum + 40 + $index * 2,
        };
        $batch = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'batch_no' => $batchNo, 'available_stock' => 0, 'allocated_stock' => 0, 'pieces_per_pack' => 1,
            'storage_location' => 'Pharmacy shelf '.chr(65 + $index % 4),
            'expiration_date' => today()->addDays([25, 90, 240, 540][$index % 4])->toDateString()]);
        $events = [
            ['date' => $firstWeek->subDays(3)->toDateString(), 'quantity' => array_sum(array_slice($weekly, 0, 6)) + (int) ceil($remaining / 2),
                'receipt' => true, 'actor_id' => $staff->id, 'barangay' => null, 'note' => 'Received scheduled RHU supply delivery'],
            ['date' => $firstWeek->addWeeks(6)->subDay()->toDateString(), 'quantity' => array_sum(array_slice($weekly, 6)) + (int) floor($remaining / 2),
                'receipt' => true, 'actor_id' => $staff->id, 'barangay' => null, 'note' => 'Received replenishment for outpatient services'],
        ];
        foreach ($weekly as $week => $quantity) {
            foreach ($scopes as $scopeIndex => $scope) {
                $areaQuantity = intdiv($quantity, count($scopes)) + ($scopeIndex < $quantity % count($scopes) ? 1 : 0);
                $weights = [0.29, 0.43, 0.28];
                $offset = ($index + $week + $scopeIndex + $unit->id) % 3;
                $weights = array_merge(array_slice($weights, $offset), array_slice($weights, 0, $offset));
                $parts = [(int) floor($areaQuantity * $weights[0]), (int) floor($areaQuantity * $weights[1])];
                $parts[] = $areaQuantity - array_sum($parts);
                foreach ($parts as $day => $dailyQuantity) {
                    if ($dailyQuantity < 1) {
                        continue;
                    }
                    $events[] = ['date' => $firstWeek->addWeeks($week)->addDays([0, 2, 4][$day])->toDateString(),
                        'quantity' => $dailyQuantity, 'receipt' => false, 'actor_id' => $scope['actor_id'], 'barangay' => $scope['barangay'],
                        'note' => 'Dispensed for outpatient services'.($scope['barangay'] ? ' in '.$scope['barangay'] : '')];
                }
            }
        }
        usort($events, fn ($left, $right) => strcmp($left['date'], $right['date']));
        $owned = ['inventory_id' => $batch->id, 'health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'batch_no' => $batchNo, 'source' => 'synthetic', 'dispensing_ids' => [], 'stock_log_ids' => [], 'stock_movement_ids' => []];
        foreach ($events as $event) {
            $record = $event['receipt'] ? null : DispensingRecord::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
                'stock_source' => 'rhu', 'patient_name' => null, 'doctor_name' => null, 'prescription_image' => null,
                'barangay' => $event['barangay'], 'date_dispensed' => $event['date'], 'dispensed_by' => $event['actor_id'],
                'batch_no' => $batchNo, 'quantity_dispensed' => $event['quantity']]);
            $log = StockLog::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'inventory_id' => $batch->id,
                'quantity_added' => $event['quantity'], 'batch_no' => $batchNo, 'storage_location' => $batch->storage_location,
                'date_added' => $event['date'], 'added_by' => $event['actor_id'], 'movement_type' => $event['receipt'] ? 'receipt' : 'dispensing',
                'dispensing_record_id' => $record?->id, 'remarks' => $event['note'].'.']);
            $delta = $event['quantity'] * ($event['receipt'] ? 1 : -1);
            $batch->increment('available_stock', $delta);
            if ($batch->fresh()->available_stock < 0) {
                throw new \RuntimeException('Generated stock history became negative; transaction rolled back.');
            }
            $movementId = app(StockMovementService::class)->record($record ?? $log, $batch, $delta);
            DB::table('stock_movements')->where('id', $movementId)->update(['created_at' => $event['date'].' 09:00:00']);
            if ($record) {
                $owned['dispensing_ids'][] = $record->id;
                $this->report['dispensing_created']++;
            }
            $owned['stock_log_ids'][] = $log->id;
            $owned['stock_movement_ids'][] = $movementId;
        }
        if ($batch->fresh()->available_stock !== $remaining) {
            throw new \RuntimeException('Generated stock history did not reconcile; transaction rolled back.');
        }
        return $owned;
    }

    private function weeklyQuantities(Medicine $medicine, int $index, int $healthUnitId): array
    {
        $unit = strtolower(trim((string) $medicine->unit));
        $base = match (true) {
            str_contains($unit, 'tablet'), str_contains($unit, 'capsule') => 90 + ($index % 5) * 24,
            str_contains($unit, 'sachet'), str_contains($unit, 'packet') => 24 + ($index % 4) * 8,
            $unit === 'ml', str_contains($unit, 'millil') => 180 + ($index % 4) * 60,
            str_contains($unit, 'inhaler') => 5 + $index % 4,
            str_contains($unit, 'bottle') => 9 + $index % 5,
            default => 12 + ($index % 4) * 5,
        };
        $profile = match ($index % 4) {
            0 => [0.82, 0.91, 0.88, 0.96, 1.02, 0.94, 1.06, 1.01, 1.08, 1.03, 1.10, 1.88],
            1 => [1.00, 1.04, 0.97, 1.02, 0.99, 1.05, 1.01, 0.98, 1.06, 1.02, 1.00, 1.04],
            2 => [1.41, 1.36, 1.30, 1.26, 1.22, 1.18, 1.13, 1.08, 1.03, 0.97, 0.92, 0.87],
            default => [1.02, 0.87, 1.11, 0.98, 1.05, 0.92, 1.14, 0.90, 1.03, 1.08, 0.97, 1.12],
        };
        $facilityFactor = [0.88, 1.00, 1.17, 0.94, 1.26, 0.82][$healthUnitId % 6];
        return array_map(fn ($factor, $week) => max(1, (int) round($base * $factor * $facilityFactor
            * (1 + (($week + $healthUnitId) % 5 - 2) * 0.012))), $profile, array_keys($profile));
    }

    private function writeManifest($disk, string $path, string $contents): void
    {
        $temporary = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
        if (! $disk->put($temporary, $contents) || ! $disk->move($temporary, $path)) {
            throw new \RuntimeException('Unable to save private generated-history provenance.');
        }
    }
}
