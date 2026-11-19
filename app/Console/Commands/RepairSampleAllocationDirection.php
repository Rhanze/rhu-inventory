<?php

namespace App\Console\Commands;

use App\Models\HealthUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RepairSampleAllocationDirection extends Command
{
    protected $signature = 'allocations:repair-sample-direction {--apply : Save the previewed corrections}';

    protected $description = 'Correct reversed PHO destinations in legacy sample allocations without moving stock';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This repair is restricted to local/testing sample databases.');
            return self::FAILURE;
        }

        $phoId = HealthUnit::phoId();
        if (! $phoId) {
            $this->error('PHO facility not found.');
            return self::FAILURE;
        }

        return DB::transaction(function () use ($phoId) {
            $rows = DB::table('allocations')->where('destination_health_unit_id', $phoId)
                ->whereIn('health_unit_id', HealthUnit::rhuOnly()->select('id'))
                ->whereIn('inventory_id', DB::table('inventory')->select('id')->where(function ($q) {
                    $q->where('batch_no', 'like', 'STK-INIT-%')->orWhere('batch_no', 'like', 'BT-%');
                }))->lockForUpdate()->get();

            $this->info($rows->count().' reversed sample allocations found.');
            if ($rows->isEmpty()) {
                return self::SUCCESS;
            }
            // Real stock activity needs a reconciled accounting repair, never a metadata swap.
            $hasLogs = DB::table('stock_logs')->whereIn('allocation_id', $rows->pluck('id'))->exists();
            $hasMovements = Schema::hasTable('stock_movements') && DB::table('stock_movements')
                ->where('owner_type', 'allocations')->whereIn('owner_id', $rows->pluck('id'))->exists();
            if ($hasLogs || $hasMovements) {
                $this->error('Linked stock activity found. No records changed; reconciliation is required.');
                return self::FAILURE;
            }
            if (! $this->option('apply')) {
                $this->line('Preview only. Use --apply to save PHO as source and the original RHU as destination.');
                return self::SUCCESS;
            }

            $backup = storage_path('app/allocation-direction-backup-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json');
            if (file_put_contents($backup, json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
                throw new \RuntimeException('Could not back up allocation records.');
            }
            foreach ($rows as $row) {
                DB::table('allocations')->where('id', $row->id)->update([
                    'health_unit_id' => $phoId,
                    'destination_health_unit_id' => $row->health_unit_id,
                    'destination' => null,
                    'recipient_user_id' => null,
                    'updated_at' => now(),
                ]);
            }
            $this->info('Corrected '.$rows->count().' sample allocations. Inventory quantities unchanged.');
            $this->line('Original records: '.$backup);
            return self::SUCCESS;
        });
    }
}
