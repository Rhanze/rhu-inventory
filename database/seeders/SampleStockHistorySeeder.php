<?php

namespace Database\Seeders;

use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use App\Services\StockMovementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Isolated, repeatable demonstration batches; never rewrites existing transactions. */
class SampleStockHistorySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Sample stock history is restricted to local and testing environments.');
        }

        $medicines = Medicine::where('is_dispensable', true)->where('category', 'medicine')->orderBy('medicine_name')->limit(12)->get();
        $targetId = env('SEED_HEALTH_UNIT_ID')
            ?? Inventory::where(fn ($q) => $q->where('batch_no', 'like', 'STK-INIT-%')->orWhere('batch_no', 'like', 'BT-%'))->orderBy('id')->value('health_unit_id')
            ?? HealthUnit::whereIn('facility_type', ['Rural Health Unit', 'RHU'])->orderBy('id')->value('id');
        $units = HealthUnit::whereIn('facility_type', ['Rural Health Unit', 'RHU'])->whereKey($targetId)->get();
        if ($medicines->isEmpty() || $units->isEmpty()) {
            throw new \RuntimeException('Create dispensable medicines and a target RHU before adding sample stock history.');
        }
        $created = 0;
        foreach ($units as $unit) {
            $staff = User::where('health_unit_id', $unit->id)->whereIn('role', ['admin', 'staff'])->where('is_active', true)->orderBy('id')->get();
            if ($staff->isEmpty()) {
                throw new \RuntimeException('The sample RHU requires an active admin or staff account.');
            }
            foreach ($medicines as $index => $medicine) {
                $batchNo = 'DEMO-V2-'.$unit->id.'-'.$medicine->id;
                $created += DB::transaction(function () use ($unit, $medicine, $index, $staff, $batchNo) {
                    // A facility lock also makes concurrent reruns safe.
                    HealthUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
                    if (Inventory::withTrashed()->where('health_unit_id', $unit->id)->where('batch_no', $batchNo)->exists()) {
                        return 0;
                    }
                    $actor = $staff[$index % $staff->count()];
                    $weekly = $this->weeklyQuantities($index + (int) $unit->id);
                    $minimum = max(1, (int) ($medicine->min_stock ?? config('inventory.default_min_stock', 50)));
                    $maximum = max($minimum + 1, (int) ($medicine->max_stock ?? config('inventory.default_max_stock', 500)));
                    $remaining = match ($index % 4) {
                        0 => max(1, (int) floor($minimum / 3)),
                        1 => $minimum + 20 + $index,
                        2 => (int) ceil(($minimum + $maximum) / 2),
                        default => $maximum + 100 + 7 * $index,
                    };
                    $batch = Inventory::create([
                        'medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
                        'batch_no' => $batchNo, 'available_stock' => 0, 'allocated_stock' => 0,
                        'expiration_date' => today()->addDays([18, 75, 240, 540][$index % 4]),
                        'storage_location' => 'Demo dispensing shelf '.(1 + $index % 3), 'pieces_per_pack' => 1,
                    ]);
                    $firstReserve = (int) ceil($remaining / 3);
                    $events = [
                        ['date' => today()->subDays(91)->toDateString(), 'quantity' => array_sum($weekly) + $firstReserve,
                            'receipt' => true, 'note' => 'Opening delivery for the demonstration dispensing schedule'],
                        ['date' => today()->subDays(22)->toDateString(), 'quantity' => $remaining - $firstReserve,
                            'receipt' => true, 'note' => 'Supplementary delivery for the next service period'],
                    ];
                    foreach ($weekly as $week => $quantity) {
                        if ($quantity > 0) {
                            $events[] = ['date' => today()->subWeeks(12)->addWeeks($week)->addDays($index % 3)->toDateString(),
                                'quantity' => $quantity, 'receipt' => false, 'note' => 'Demonstration outpatient visit '.($week + 1)];
                        }
                    }
                    usort($events, fn ($a, $b) => strcmp($a['date'], $b['date']));
                    foreach ($events as $eventIndex => $event) {
                        if ($event['quantity'] === 0) {
                            continue;
                        }
                        $owner = null;
                        if (! $event['receipt']) {
                            $owner = DispensingRecord::create([
                                'medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
                                'patient_name' => '[DEMO] Visitor '.$unit->id.'-'.($index + 1).'-'.($eventIndex + 1),
                                'batch_no' => $batchNo, 'quantity_dispensed' => $event['quantity'],
                                'date_dispensed' => $event['date'], 'dispensed_by' => $actor->id,
                                'doctor_name' => '[DEMO] Clinician '.(1 + $index % 3),
                            ]);
                        }
                        $log = StockLog::create([
                            'medicine_id' => $medicine->id, 'health_unit_id' => $unit->id, 'inventory_id' => $batch->id,
                            'quantity_added' => $event['quantity'], 'batch_no' => $batchNo,
                            'storage_location' => $batch->storage_location, 'date_added' => $event['date'], 'added_by' => $actor->id,
                            'movement_type' => $event['receipt'] ? 'receipt' : 'dispensing',
                            'dispensing_record_id' => $owner?->id,
                            'remarks' => ($event['receipt'] ? 'Added' : 'Released - Dispensed').' [DEMO]: '.$event['note'].'; '.$medicine->medicine_name.'.',
                        ]);
                        $delta = $event['receipt'] ? $event['quantity'] : -$event['quantity'];
                        $batch->increment('available_stock', $delta);
                        $movementOwner = $owner ?? $log;
                        app(StockMovementService::class)->record($movementOwner, $batch, $delta);
                        DB::table('stock_movements')->where('owner_type', $movementOwner->getTable())->where('owner_id', $movementOwner->id)
                            ->update(['created_at' => $event['date'].' 09:00:00']);
                    }
                    if ($batch->fresh()->available_stock !== $remaining) {
                        throw new \RuntimeException('Demonstration stock failed reconciliation.');
                    }

                    return 1;
                });
            }
        }
        $this->command?->info('Created '.$created.' distinct demo batches with reconciled receipts, dispensing, and stock movements. Existing batches were preserved.');
    }

    public function weeklyQuantities(int $index): array
    {
        $base = 4 + $index % 7;

        return array_map(fn ($week) => match ($index % 4) {
            0 => $base + 2 * $week,
            1 => $base + 2 * (11 - $week),
            2 => $base + [0, 2, 1, 0, 3, 1][$week % 6],
            default => $week % 3 === 1 ? 0 : $base + $week % 5,
        }, range(0, 11));
    }
}
