<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NormalizeGeneratedStock extends Command
{
    protected $signature = 'data:normalize-generated-stock {--apply : Save the previewed presentation changes}';

    protected $description = 'Normalize verified generated stock labels while preserving their private provenance and ledger';

    public function handle(): int
    {
        $lock = null;
        try {
            $this->assertLocalDatabase();
            if ($this->option('apply')) {
                $directory = $this->provenanceDirectory();
                $lock = fopen($directory.'/normalization.lock', 'c');
                if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
                    throw new \RuntimeException('Another generated-stock normalization is running.');
                }
            }

            $result = DB::transaction(function () {
                $plan = $this->plan();
                $counts = array_map('count', $plan['changes']);
                $this->info(count($plan['batches']).' verified generated batches found.');
                foreach ($counts as $table => $count) {
                    $this->line($table.': '.$count.' presentation updates');
                }
                if (! $this->option('apply') || $plan['batches'] === []) {
                    if (! $this->option('apply')) {
                        $this->line('Preview only. Use --apply to save these changes.');
                    }
                    return null;
                }

                $path = $this->provenanceDirectory().'/generated-stock-normalization-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(6)).'.json';
                $snapshot = ['record_origin' => 'synthetic', 'generator' => 'SampleStockHistorySeeder',
                    'purpose' => 'Original rows before presentation-only normalization', 'created_at' => now()->toIso8601String(),
                    'batches' => $plan['batches'], 'original_rows' => $plan['original_rows'], 'changes' => $plan['changes']];
                $file = fopen($path, 'x');
                if (! $file) throw new \RuntimeException('Could not create the private provenance backup.');
                try {
                    $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                    if (fwrite($file, $json) !== strlen($json) || ! fflush($file)) {
                        throw new \RuntimeException('Could not finish the private provenance backup.');
                    }
                } finally {
                    fclose($file);
                }

                foreach ($plan['changes'] as $table => $rows) {
                    foreach ($rows as $id => $changes) DB::table($table)->where('id', $id)->update($changes);
                }

                return $path;
            });
            if ($result !== null) {
                $this->info('Saved presentation changes. Quantities, dates, and movement ledger are unchanged.');
                $this->line('Private provenance backup: '.$result);
            }

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        } finally {
            if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
        }
    }

    private function assertLocalDatabase(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Generated-stock normalization is restricted to local/testing databases.');
        }
        // Laravel resolves URL and query-string overrides here without connecting to the server.
        $configuration = DB::connection()->getConfig();
        if (($configuration['driver'] ?? '') === 'sqlite') {
            if ((! empty($configuration['url']) && parse_url($configuration['url'], PHP_URL_HOST))
                || preg_match('~^(?:\\\\\\\\|//)~', (string) ($configuration['database'] ?? ''))) {
                throw new \RuntimeException('Remote database URLs are not allowed for generated-stock normalization.');
            }
            return;
        }
        $host = $configuration['host'] ?? null;
        if (! in_array($configuration['driver'] ?? '', ['mysql', 'mariadb'], true)
            || ! in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
            throw new \RuntimeException('Only a local SQLite or loopback MySQL database may be normalized.');
        }
    }

    private function provenanceDirectory(): string
    {
        $directory = storage_path('app/private/data-provenance');
        foreach ([storage_path('app'), storage_path('app/private'), $directory] as $path) {
            if (is_link($path) || (file_exists($path) && ! is_dir($path))) {
                throw new \RuntimeException('The private provenance directory must be an ordinary directory.');
            }
            if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
                throw new \RuntimeException('Could not create the private provenance directory.');
            }
        }
        return $directory;
    }

    private function plan(): array
    {
        $plan = ['batches' => [], 'changes' => array_fill_keys(['inventory', 'stock_logs', 'dispensing_records', 'allocations', 'transfer_requests'], []),
            'original_rows' => array_fill_keys(['inventory', 'stock_logs', 'dispensing_records', 'allocations', 'transfer_requests', 'stock_movements'], [])];
        $batches = DB::table('inventory')->where('batch_no', 'like', 'DEMO-V2-%')->orderBy('id')->lockForUpdate()->get();
        foreach ($batches as $batch) {
            if (! preg_match('/^DEMO-V2-(\d+)-(\d+)$/', $batch->batch_no, $match)
                || (int) $match[1] !== (int) $batch->health_unit_id || (int) $match[2] !== (int) $batch->medicine_id) {
                throw new \RuntimeException('A generated-looking batch does not match its original ownership keys. No changes saved.');
            }
            $newBatch = 'ATQ-'.str_pad((string) $batch->id, 6, '0', STR_PAD_LEFT).'-'.$batch->medicine_id;
            if (DB::table('inventory')->where('id', '!=', $batch->id)->where('batch_no', $newBatch)->exists()) {
                throw new \RuntimeException('A replacement batch number is already in use. No changes saved.');
            }
            $logs = DB::table('stock_logs')->where(fn ($query) => $query->where('inventory_id', $batch->id)
                ->orWhere(fn ($legacy) => $legacy->whereNull('inventory_id')->where('batch_no', $batch->batch_no)
                    ->where('medicine_id', $batch->medicine_id)->where('health_unit_id', $batch->health_unit_id)))
                ->orderBy('id')->lockForUpdate()->get();
            $generatedLogs = $logs->filter(fn ($log) => $this->generatedRemark($log->remarks) !== null);
            if (! $generatedLogs->contains('movement_type', 'receipt') || ! $generatedLogs->contains('movement_type', 'dispensing')) {
                throw new \RuntimeException('Generated batch ownership could not be verified from its original stock records. No changes saved.');
            }
            foreach ($generatedLogs as $log) {
                $ownerType = $log->movement_type === 'dispensing' ? 'dispensing_records' : 'stock_logs';
                $ownerId = $log->movement_type === 'dispensing' ? $log->dispensing_record_id : $log->id;
                if ($log->batch_no !== $batch->batch_no || (int) $log->medicine_id !== (int) $batch->medicine_id
                    || (int) $log->health_unit_id !== (int) $batch->health_unit_id
                    || ! DB::table('stock_movements')->where('inventory_id', $batch->id)->where('owner_type', $ownerType)->where('owner_id', $ownerId)->exists()) {
                    throw new \RuntimeException('Generated stock references or movement evidence do not match. No changes saved.');
                }
            }
            $plan['batches'][] = ['inventory_id' => $batch->id, 'old_batch_no' => $batch->batch_no, 'new_batch_no' => $newBatch];
            $changes = ['batch_no' => $newBatch];
            if ($shelf = $this->generatedShelf($batch->storage_location)) $changes['storage_location'] = $shelf;
            $this->includeRow($plan, 'inventory', $batch, $changes);

            foreach ($logs as $log) {
                $changes = [];
                if ($log->batch_no === $batch->batch_no) $changes['batch_no'] = $newBatch;
                if ($generatedLogs->contains('id', $log->id)) {
                    $changes['remarks'] = $this->generatedRemark($log->remarks);
                    if ($shelf = $this->generatedShelf($log->storage_location)) $changes['storage_location'] = $shelf;
                }
                if ($changes !== []) $this->includeRow($plan, 'stock_logs', $log, $changes);
            }
            $dispensing = DB::table('dispensing_records')->where('batch_no', $batch->batch_no)
                ->where('medicine_id', $batch->medicine_id)->where('health_unit_id', $batch->health_unit_id)->lockForUpdate()->get();
            foreach ($dispensing as $record) {
                $changes = ['batch_no' => $newBatch];
                if ($generatedLogs->contains('dispensing_record_id', $record->id)) {
                    if (preg_match('/^\[DEMO\] Visitor '.(int) $batch->health_unit_id.'-\d+-\d+$/', $record->patient_name ?? '')) $changes['patient_name'] = null;
                    if (preg_match('/^\[DEMO\] Clinician [1-3]$/', $record->doctor_name ?? '')) $changes['doctor_name'] = 'RHU Medical Officer';
                }
                $this->includeRow($plan, 'dispensing_records', $record, $changes);
            }
            foreach (['allocations', 'transfer_requests'] as $table) {
                if (! Schema::hasColumn($table, 'batch_no')) {
                    foreach (DB::table($table)->where('inventory_id', $batch->id)->lockForUpdate()->get() as $row) {
                        $plan['original_rows'][$table][$row->id] = (array) $row;
                    }
                    continue;
                }
                $sourceColumn = $table === 'transfer_requests' ? 'from_health_unit_id' : 'health_unit_id';
                foreach (DB::table($table)->where('batch_no', $batch->batch_no)
                    ->where(fn ($query) => $query->where('inventory_id', $batch->id)
                        ->orWhere(fn ($legacy) => $legacy->whereNull('inventory_id')->where('medicine_id', $batch->medicine_id)->where($sourceColumn, $batch->health_unit_id)))
                    ->lockForUpdate()->get() as $row) {
                    $this->includeRow($plan, $table, $row, ['batch_no' => $newBatch]);
                }
            }
            foreach (DB::table('stock_movements')->where('inventory_id', $batch->id)->get() as $movement) {
                $plan['original_rows']['stock_movements'][$movement->id] = (array) $movement;
            }
        }
        return $plan;
    }

    private function includeRow(array &$plan, string $table, object $row, array $changes): void
    {
        $plan['original_rows'][$table][$row->id] = (array) $row;
        $plan['changes'][$table][$row->id] = $changes;
    }

    private function generatedShelf(?string $value): ?string
    {
        return preg_match('/^Demo dispensing shelf ([1-3])$/', $value ?? '', $match) ? 'Pharmacy Shelf '.$match[1] : null;
    }

    private function generatedRemark(?string $value): ?string
    {
        if (preg_match('/^Added \[DEMO\]: Opening delivery for the demonstration dispensing schedule; (.+)\.$/', $value ?? '', $match)) {
            return 'Added: Opening delivery; '.$match[1].'.';
        }
        if (preg_match('/^Added \[DEMO\]: Supplementary delivery for the next service period; (.+)\.$/', $value ?? '', $match)) {
            return 'Added: Supplementary delivery; '.$match[1].'.';
        }
        if (preg_match('/^Released - Dispensed \[DEMO\]: Demonstration outpatient visit (?:[1-9]|1[0-2]); (.+)\.$/', $value ?? '', $match)) {
            return 'Released - Dispensed: Outpatient dispensing; '.$match[1].'.';
        }
        return null;
    }
}
