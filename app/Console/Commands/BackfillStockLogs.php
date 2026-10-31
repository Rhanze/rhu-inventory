<?php

namespace App\Console\Commands;

use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\Inventory;
use App\Models\StockLog;
use App\Models\TransferRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillStockLogs extends Command
{
    protected $signature = 'stock-logs:backfill
                            {--dry-run : Preview what would be created without writing to the database}';

    protected $description = 'Create missing stock log entries for historical dispensing, transfer, and allocation records';

    private int $dispensingCreated = 0;
    private int $transfersCreated  = 0;
    private int $allocationsCreated = 0;
    private int $skipped            = 0;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('▶ DRY RUN — nothing will be written to the database');
        }

        $this->info('▶ Backfilling stock logs...');

        $this->backfillDispensing($dryRun);
        $this->backfillTransfers($dryRun);
        $this->backfillAllocations($dryRun);

        $this->newLine();
        $this->info('✔ Done.');
        $this->table(
            ['Source', 'Created', 'Skipped'],
            [
                ['Dispensing',  $this->dispensingCreated,  0],
                ['Transfers',   $this->transfersCreated,   0],
                ['Allocations', $this->allocationsCreated, 0],
                ['Total',       $this->dispensingCreated + $this->transfersCreated + $this->allocationsCreated, $this->skipped],
            ]
        );

        return self::SUCCESS;
    }

    // ================================================================
    // Dispensing backfill
    // ================================================================
    private function backfillDispensing(bool $dryRun): void
    {
        $this->info('  · Processing dispensing records...');

        DispensingRecord::whereDoesntHave('stockLogs')
            ->with('medicine')
            ->chunk(200, function ($records) use ($dryRun) {
                foreach ($records as $r) {
                    $doctorLabel = $r->doctor_name ?: 'Walk-in';

                    if ($dryRun) {
                        $this->line("    → Would create log for Dispensing #{$r->id} ({$r->quantity_dispensed} units)");
                        $this->dispensingCreated++;
                        continue;
                    }

                    StockLog::create([
                        'medicine_id'          => $r->medicine_id,
                        'health_unit_id'       => $r->health_unit_id,
                        'quantity_added'       => $r->quantity_dispensed,
                        'batch_no'             => null,
                        'storage_location'     => null,
                        'date_added'           => $r->date_dispensed,
                        'added_by'             => $r->dispensed_by,
                        'remarks'              => 'Released — Dispensed to ' . $doctorLabel .
                                                  ' (Dispensing #' . $r->id . ', backfilled)',
                        'dispensing_record_id' => $r->id,
                    ]);

                    $this->dispensingCreated++;
                }
            });
    }

    // ================================================================
    // Transfer backfill — 2 logs per received transfer
    // ================================================================
    private function backfillTransfers(bool $dryRun): void
    {
        $this->info('  · Processing received transfers...');

        TransferRequest::where('status', 'received')
            ->whereDoesntHave('stockLogs')
            ->with(['fromHealthUnit', 'toHealthUnit', 'medicine'])
            ->chunk(100, function ($transfers) use ($dryRun) {
                foreach ($transfers as $t) {
                    $qtyLabel = $t->formatQuantity($t->quantity);
                    $date     = $t->received_at?->toDateString()
                             ?? $t->completed_at?->toDateString()
                             ?? now()->toDateString();

                    if ($dryRun) {
                        $this->line("    → Would create 2 logs for Transfer #{$t->id}");
                        $this->transfersCreated += 2;
                        continue;
                    }

                    DB::transaction(function () use ($t, $qtyLabel, $date) {
                        // Source: stock OUT
                        StockLog::create([
                            'medicine_id'      => $t->medicine_id,
                            'health_unit_id'   => $t->from_health_unit_id,
                            'quantity_added'   => $t->quantity,
                            'batch_no'         => $t->batch_no,
                            'storage_location' => null,
                            'date_added'       => $date,
                            'added_by'         => $t->received_by ?? $t->approved_by ?? $t->requested_by,
                            'remarks'          => 'Released — Transferred to ' .
                                                  ($t->toHealthUnit->name ?? 'RHU') .
                                                  ' (Transfer #' . $t->id . ', ' . $qtyLabel . ', backfilled)',
                            'transfer_id'      => $t->id,
                        ]);

                        // Destination: stock IN
                        StockLog::create([
                            'medicine_id'      => $t->medicine_id,
                            'health_unit_id'   => $t->to_health_unit_id,
                            'quantity_added'   => $t->quantity,
                            'batch_no'         => $t->batch_no,
                            'storage_location' => null,
                            'date_added'       => $date,
                            'added_by'         => $t->received_by ?? $t->approved_by ?? $t->requested_by,
                            'remarks'          => 'Added — Received from ' .
                                                  ($t->fromHealthUnit->name ?? 'RHU') .
                                                  ' (Transfer #' . $t->id . ', ' . $qtyLabel . ', backfilled)',
                            'transfer_id'      => $t->id,
                        ]);
                    });

                    $this->transfersCreated += 2;
                }
            });
    }

    // ================================================================
    // Allocation backfill
    // ================================================================
    private function backfillAllocations(bool $dryRun): void
    {
        $this->info('  · Processing allocations...');

        Allocation::whereDoesntHave('stockLogs')
            ->with(['medicine', 'healthUnit', 'destinationHealthUnit'])
            ->chunk(200, function ($allocations) use ($dryRun) {
                foreach ($allocations as $a) {
                    // Determine destination label
                    if ($a->destination_health_unit_id) {
                        $destLabel = $a->destinationHealthUnit->name ?? 'RHU';
                        $logHealthUnitId = $a->destination_health_unit_id;
                        $remarksPrefix   = 'Added';
                        $remarksTail     = ' — Provincial allocation from PHO to ' . $destLabel;
                    } else {
                        $destLabel       = $a->destination ?? 'Unknown destination';
                        $logHealthUnitId = $a->health_unit_id;
                        $remarksPrefix   = 'Released';
                        $remarksTail     = ' — Allocated to ' . $destLabel;
                    }

                    if ($dryRun) {
                        $this->line("    → Would create log for Allocation #{$a->id}");
                        $this->allocationsCreated++;
                        continue;
                    }

                    StockLog::create([
                        'medicine_id'      => $a->medicine_id,
                        'health_unit_id'   => $logHealthUnitId,
                        'quantity_added'   => $a->quantity_allocated,
                        'batch_no'         => null,
                        'storage_location' => null,
                        'date_added'       => $a->date_allocated,
                        'added_by'         => $a->allocated_by,
                        'remarks'          => $remarksPrefix . $remarksTail .
                                              ' (Allocation #' . $a->id . ', backfilled)',
                        'allocation_id'    => $a->id,
                    ]);

                    $this->allocationsCreated++;
                }
            });
    }
}