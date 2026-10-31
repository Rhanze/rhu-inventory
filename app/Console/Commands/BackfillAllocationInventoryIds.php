<?php

namespace App\Console\Commands;

use App\Models\Allocation;
use App\Models\Inventory;
use Illuminate\Console\Command;

class BackfillAllocationInventoryIds extends Command
{
    protected $signature = 'allocations:backfill-inventory
                            {--dry-run : Preview what would be updated without saving}';

    protected $description = 'Backfill inventory_id on legacy allocations by matching medicine + RHU';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('▶ DRY RUN — nothing will be written');
        }

        $matched   = 0;
        $unmatched = 0;
        $skipped   = 0;

        Allocation::whereNull('inventory_id')
            ->chunk(200, function ($allocations) use ($dryRun, &$matched, &$unmatched, &$skipped) {
                foreach ($allocations as $a) {
                    // Skip if already has inventory_id (race condition guard)
                    if ($a->inventory_id) {
                        $skipped++;
                        continue;
                    }

                    // Resolve which RHU owned the inventory:
                    //   - provincial allocation → destination RHU
                    //   - RHU allocation → source RHU
                    $rhuId = $a->destination_health_unit_id ?: $a->health_unit_id;

                    $inventory = Inventory::where('health_unit_id', $rhuId)
                        ->where('medicine_id', $a->medicine_id)
                        ->orderByDesc('id')   // pick the most recent batch
                        ->first();

                    if (!$inventory) {
                        $this->line("  · Skipped Allocation #{$a->id} (no matching inventory)");
                        $unmatched++;
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("  → Would set Allocation #{$a->id} inventory_id = {$inventory->id} (batch: " .
                                    ($inventory->batch_no ?: 'none') . ")");
                    } else {
                        $a->update(['inventory_id' => $inventory->id]);
                    }

                    $matched++;
                }
            });

        $this->newLine();
        $this->info('✔ Done.');
        $this->table(
            ['Result', 'Count'],
            [
                ['Matched & updated',  $matched],
                ['No matching inventory', $unmatched],
                ['Skipped',            $skipped],
            ]
        );

        return self::SUCCESS;
    }
}