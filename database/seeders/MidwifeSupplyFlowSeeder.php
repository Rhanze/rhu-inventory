<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class MidwifeSupplyFlowSeeder extends Seeder
{
    private const BATCH_PREFIX = 'BT-';

    public function run(): void
    {
        // 1. Target RHU
        $targetMidwifeId = env('SEED_MIDWIFE_ID');
        $targetRhuId     = env('SEED_HEALTH_UNIT_ID');
        $targetMidwife   = null;

        if ($targetMidwifeId) {
            $targetMidwife = DB::table('users')
                ->where('id', (int) $targetMidwifeId)
                ->where('role', 'midwife')
                ->first();

            if (!$targetMidwife) {
                $this->command->error("No midwife with id = {$targetMidwifeId}.");
                return;
            }
            $targetRhuId = $targetMidwife->health_unit_id;
            $this->command->info("→ Targeting midwife #{$targetMidwife->id} ({$targetMidwife->name}) at RHU #{$targetRhuId}");
        }

        $rhu = $targetRhuId
            ? DB::table('health_units')->where('id', (int) $targetRhuId)->first()
            : DB::table('health_units')
                ->whereIn('facility_type', ['Rural Health Unit', 'RHU'])
                ->orderBy('id')
                ->first();

        if (!$rhu) {
            $this->command->error('No RHU found. Aborting.');
            return;
        }

        // 2. Admin user
        $admin = DB::table('users')
            ->where('health_unit_id', $rhu->id)
            ->where('role', 'admin')
            ->first()
            ?? DB::table('users')->where('health_unit_id', $rhu->id)->first()
            ?? DB::table('users')->first();

        if (!$admin) {
            $this->command->error('No users found.');
            return;
        }

        // 2b. Midwives
        $midwives = $targetMidwife
            ? collect([$targetMidwife])
            : DB::table('users')
                ->where('health_unit_id', $rhu->id)
                ->where('role', 'midwife')
                ->orderBy('name')
                ->get(['id', 'name']);

        if ($midwives->isEmpty()) {
            $this->command->warn('⚠  No midwives at this RHU.');
        } else {
            $this->command->info('Midwife: ' . $midwives->pluck('name')->implode(', '));
        }

        // 3. Pick medicines — deduped, capped to distinct count
        $medicines = DB::table('medicines')
            ->when(Schema::hasColumn('medicines', 'is_dispensable'), function ($q) {
                $q->where('is_dispensable', true);
            })
            ->orderBy('id')
            ->limit(20)
            ->get();

        if ($medicines->count() < 20) {
            $medicines = DB::table('medicines')->orderBy('id')->limit(20)->get();
        }

        // ═══ KEY FIX: dedupe by medicine id and cap row count ═══
        $medicines = $medicines->unique('id')->values();

        if ($medicines->isEmpty()) {
            $this->command->error('No medicines in DB.');
            return;
        }

        $inventoryCount = min(20, $medicines->count());

        $this->command->info("Distinct medicines: {$medicines->count()} → creating {$inventoryCount} inventory rows");

        // 4. Wipe previous
        $prefix = self::BATCH_PREFIX;

        $oldInvIds = DB::table('inventory')
            ->where('batch_no', 'like', $prefix . '%')
            ->pluck('id');

        if ($oldInvIds->isNotEmpty()) {
            DB::table('allocations')->whereIn('inventory_id', $oldInvIds)->delete();
        }

        DB::table('allocations')
            ->whereIn('allocated_by', $midwives->pluck('id')->toArray())
            ->whereNull('destination_health_unit_id')
            ->whereNull('inventory_id')
            ->delete();

        DB::table('dispensing_records')->where('batch_no', 'like', $prefix . '%')->delete();
        DB::table('stock_logs')->where('batch_no', 'like', $prefix . '%')->delete();
        DB::table('inventory')->where('batch_no', 'like', $prefix . '%')->delete();

        $this->command->info('Cleared previous seeded rows.');

        // 5. INVENTORY
        $today = Carbon::today();
        $now   = Carbon::now();

        // ── Random-time helper: 8am–5pm business hours ──
        $ts = function (int $daysAgo) use ($now): Carbon {
            return $now->copy()
                ->subDays($daysAgo)
                ->setTime(rand(8, 17), rand(0, 59), rand(0, 59));
        };

        $storageLocations = [
            'Main Cabinet', 'Shelf A', 'Shelf B', 'Refrigerator',
            'Cold Room', 'Locked Cabinet', 'Pharmacy Shelf 1', 'Pharmacy Shelf 2',
        ];

        $fullExpiryPlan = [
            [-60,  'Expired'], [-15,  'Expired'],
            [+10,  'Near expiry'], [+30,  'Near expiry'], [+55,  'Near expiry'],
            [+72,  'Near expiry'], [+85,  'Near expiry'], [+90,  'Near expiry'],
            [+110, 'Monitor'], [+130, 'Monitor'], [+155, 'Monitor'], [+175, 'Monitor'],
            [+210, 'Good'], [+300, 'Good'], [+400, 'Good'], [+500, 'Good'],
            [+600, 'Good'], [+730, 'Good'], [+900, 'Good'], [null, 'No expiry'],
        ];

        // Slice the expiry plan so we only produce as many rows as we have medicines
        $expiryPlan = array_slice($fullExpiryPlan, 0, $inventoryCount);

        $inventoryRows = [];
        foreach ($expiryPlan as $i => [$offset, $label]) {
            $medicine = $medicines[$i];   // ← straight index, no modulo, no dupes
            $batchNo  = $prefix . now()->format('Ym') . '-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT);

            $expiration = $offset === null
                ? null
                : $today->copy()->addDays($offset)->toDateString();

            $available = rand(20, 500);
            $allocated = rand(0, min(30, $available));

            $inventoryRows[] = [
                'medicine_id'      => $medicine->id,
                'health_unit_id'   => $rhu->id,
                'available_stock'  => $available,
                'allocated_stock'  => $allocated,
                'batch_no'         => $batchNo,
                'expiration_date'  => $expiration,
                'storage_location' => $storageLocations[$i % count($storageLocations)],
                'pack_type'        => null,
                'pieces_per_pack'  => 1,
            ];
        }

        DB::table('inventory')->insert($inventoryRows);

        $inventories = DB::table('inventory')
            ->where('batch_no', 'like', $prefix . '%')
            ->orderBy('id')
            ->get();

        $this->command->info("Inserted {$inventoryCount} inventory rows.");

        // 6. STOCK LOGS
        $stockLogRows = [];
        foreach ($inventories as $idx => $inv) {
            $row = [
                'medicine_id'      => $inv->medicine_id,
                'health_unit_id'   => $rhu->id,
                'quantity_added'   => $inv->available_stock,
                'batch_no'         => $inv->batch_no,
                'storage_location' => $inv->storage_location,
                'date_added'       => $ts(30 - $idx)->toDateString(),
                'added_by'         => $admin->id,
            ];
            if (Schema::hasColumn('stock_logs', 'remarks')) {
                $row['remarks'] = 'Added — Initial stock intake';
            }
            $stockLogRows[] = $row;
        }
        DB::table('stock_logs')->insert($stockLogRows);

        $this->command->info("Inserted {$inventoryCount} stock log entries.");

        // 7. ALLOCATIONS — 60% midwife requests, 40% direct admin
        $pho = DB::table('health_units')
            ->where(function ($q) {
                $q->where('facility_type', 'like', '%Provincial%')
                  ->orWhere('facility_type', 'like', '%PHO%')
                  ->orWhere('name', 'like', '%Provincial Health%');
            })
            ->orderBy('id')
            ->first();

        $destCol = null;
        foreach (['destination_health_unit_id', 'to_health_unit_id', 'recipient_health_unit_id'] as $c) {
            if (Schema::hasColumn('allocations', $c)) { $destCol = $c; break; }
        }

        $hasDestinationText = Schema::hasColumn('allocations', 'destination');
        $hasReviewedBy      = Schema::hasColumn('allocations', 'reviewed_by');
        $hasReviewedAt      = Schema::hasColumn('allocations', 'reviewed_at');
        $hasRemarks         = Schema::hasColumn('allocations', 'remarks');
        $hasReceivedAt      = Schema::hasColumn('allocations', 'received_at');
        $hasCreatedAt       = Schema::hasColumn('allocations', 'created_at');
        $hasUpdatedAt       = Schema::hasColumn('allocations', 'updated_at');

        // Scale the plan to however many inventory rows we made
        $fullStatusPlan = [
            'pending',   'pending',   'pending',   'pending',
            'approved',  'approved',  'approved',  'approved',
            'rejected',  'rejected',
            'received',  'received',  'received',  'received', 'received', 'received',
            'completed', 'completed', 'completed', 'completed',
        ];
        $statusPlan = array_slice($fullStatusPlan, 0, $inventoryCount);

        $midwifeStatuses = ['pending', 'approved', 'rejected', 'received'];

        $requestReasons = [
            'Running low on stock — projected to run out in 3 days.',
            'Increased caseload this week at the RHU.',
            'Regular resupply for the coming immunization drive.',
            'Outbreak response — need extra stock immediately.',
            'PRENATAL outreach next week needs iron + folic acid.',
            'Family planning resupply for barangay session.',
            'Postpartum mothers need vitamin A capsules.',
            'Child immunization catch-up campaign.',
            'Restock for the upcoming EPI day.',
            'Emergency — supply exhausted.',
        ];

        $urgencyPool = ['normal', 'normal', 'normal', 'urgent', 'normal', 'urgent', 'normal', 'normal', 'urgent', 'emergency'];

        $allocationRows  = [];
        $midwifeCounter  = 0;
        $midwifeRequests = 0;

        foreach ($inventories as $i => $inv) {
            $status = $statusPlan[$i] ?? 'completed';
            $qty    = rand(5, 80);

            $isMidwifeRequest = in_array($status, $midwifeStatuses, true) && $midwives->isNotEmpty();

            $row = [
                'medicine_id'        => $inv->medicine_id,
                'health_unit_id'     => $rhu->id,
                'quantity_allocated' => $qty,
                'date_allocated'     => $ts(20 - $i)->toDateString(),
            ];

            if ($isMidwifeRequest) {
                $mw = $midwives[$midwifeCounter % $midwives->count()];
                $midwifeCounter++;
                $midwifeRequests++;

                $row['allocated_by'] = $mw->id;
                $row['inventory_id'] = null;

                if ($destCol) {
                    $row[$destCol] = null;
                }
                if ($hasDestinationText) {
                    $urgency = $urgencyPool[$i % count($urgencyPool)];
                    $tag     = $urgency !== 'normal' ? '[' . strtoupper($urgency) . '] ' : '';
                    $row['destination'] = $tag . $requestReasons[$i % count($requestReasons)];
                }
            } else {
                $row['allocated_by'] = $admin->id;
                $row['inventory_id'] = $inv->id;

                if ($destCol) {
                    $row[$destCol] = $pho->id ?? null;
                }
                if ($hasDestinationText) {
                    $row['destination'] = 'Direct allocation';
                }
            }

            if (Schema::hasColumn('allocations', 'status')) {
                $row['status'] = $status;
            }
            if ($hasRemarks) {
                $row['remarks'] = $isMidwifeRequest
                    ? 'SEED-Supply request from midwife'
                    : 'SEED-Direct allocation';
            }
            if ($hasReviewedBy && in_array($status, ['approved', 'rejected', 'received'], true)) {
                $row['reviewed_by'] = $admin->id;
            }
            if ($hasReviewedAt && in_array($status, ['approved', 'rejected', 'received'], true)) {
                $row['reviewed_at'] = $ts(14 - $i);
            }
            if ($hasReceivedAt) {
                $row['received_at'] = $status === 'received'
                    ? $ts(10 - $i)
                    : null;
            }
            if ($hasCreatedAt) {
                $row['created_at'] = $ts(20 - $i);
            }
            if ($hasUpdatedAt) {
                $row['updated_at'] = $ts(19 - $i);
            }

            $allocationRows[] = $row;
        }

        // Normalize keys
        if (!empty($allocationRows)) {
            $allKeys = [];
            foreach ($allocationRows as $rr) foreach (array_keys($rr) as $k) $allKeys[$k] = true;
            $allKeys = array_keys($allKeys);

            $allocationRows = array_map(function ($rr) use ($allKeys) {
                $out = [];
                foreach ($allKeys as $k) $out[$k] = $rr[$k] ?? null;
                return $out;
            }, $allocationRows);
        }

        DB::table('allocations')->insert($allocationRows);

        $statusCounts = array_count_values($statusPlan);
        $this->command->info("Inserted {$inventoryCount} allocation records:");
        foreach ($statusCounts as $status => $count) {
            $tag = in_array($status, $midwifeStatuses, true) ? ' (midwife supply request)' : ' (direct admin allocation)';
            $this->command->line('  · ' . str_pad($count, 2) . ' × ' . $status . $tag);
        }
        $this->command->info("  → {$midwifeRequests} rows will appear on the midwife's Supply Requests page.");

        // 8. DISPENSING
        $doctors = [
            'Dr. Anna Reyes', 'Dr. Marco Santos', 'Dr. Liza Cruz', 'Dr. Paolo Garcia',
            'Dr. Maria Mendoza', 'Dr. Rafael Bautista', 'Dr. Carmen Del Rosario',
            'Dr. Juan Villanueva', 'Dr. Sofia Ramos', 'Dr. Gabriel Torres',
        ];

        $dispensingRows = [];
        $hasDispensedBy = Schema::hasColumn('dispensing_records', 'dispensed_by');
        $hasRemarks     = Schema::hasColumn('dispensing_records', 'remarks');
        $hasTimestamps  = Schema::hasColumn('dispensing_records', 'created_at');

        foreach ($inventories as $i => $inv) {
            $med = $medicines->firstWhere('id', $inv->medicine_id);
            if ($med && $this->isEquipment($med->medicine_name ?? '')) continue;

            $qty = rand(1, 20);
            $dispensedBy = $midwives->isNotEmpty() ? $midwives[$i % $midwives->count()] : $admin;

            $row = [
                'medicine_id'        => $inv->medicine_id,
                'health_unit_id'     => $rhu->id,
                'batch_no'           => $inv->batch_no,
                'quantity_dispensed' => $qty,
                'date_dispensed'     => $ts(15 - $i)->toDateString(),
                'doctor_name'        => $doctors[$i % count($doctors)],
            ];

            if ($hasDispensedBy) $row['dispensed_by'] = $dispensedBy->id;
            if ($hasRemarks)     $row['remarks']      = 'Routine dispensing';
            if ($hasTimestamps) {
                $row['created_at'] = $ts(15 - $i);
                $row['updated_at'] = $ts(15 - $i);
            }

            $dispensingRows[] = $row;
        }

        if (!empty($dispensingRows)) {
            DB::table('dispensing_records')->insert($dispensingRows);
        }

        $this->command->info('Inserted ' . count($dispensingRows) . ' dispensing records.');

        // Summary
        $this->command->info('');
        $this->command->info("✔ Seeded for RHU: {$rhu->name}");
        $this->command->info('  · ' . count($inventoryRows) . ' inventory rows');
        $this->command->info('  · ' . count($stockLogRows) . ' stock log entries');
        $this->command->info('  · ' . count($allocationRows) . ' allocations');
        $this->command->info('  · ' . $midwifeRequests . ' midwife supply requests');
        $this->command->info('  · ' . count($dispensingRows) . ' dispensing records');
    }

    private function isEquipment(string $name): bool
    {
        foreach (['wheelchair', 'quad cane', 'crutch', 'walker', 'thermohygrometer',
                  'vaccine carrier', 'sprayer', 'spray can', 'infrared thermometer',
                  'compression sprayer'] as $kw) {
            if (str_contains(strtolower($name), $kw)) return true;
        }
        return false;
    }
}