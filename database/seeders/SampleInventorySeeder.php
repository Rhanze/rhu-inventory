<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class SampleInventorySeeder extends Seeder
{
    private const BATCH_PREFIX = 'STK-INIT-';

    public function run(): void
    {
        // ─────────────────────────────────────────────────────────
        // 1. Pick a target RHU
        // ─────────────────────────────────────────────────────────
        $rhu = DB::table('health_units')
            ->whereIn('facility_type', ['Rural Health Unit', 'RHU'])
            ->orderBy('id')
            ->first();

        if (!$rhu) {
            $this->command->error('No RHU found in health_units. Aborting.');
            return;
        }

        // ─────────────────────────────────────────────────────────
        // 2. Pick an admin user at that RHU
        // ─────────────────────────────────────────────────────────
        $admin = DB::table('users')
            ->where('health_unit_id', $rhu->id)
            ->where('role', 'admin')
            ->first()
            ?? DB::table('users')->where('health_unit_id', $rhu->id)->first()
            ?? DB::table('users')->first();

        if (!$admin) {
            $this->command->error('No users found. Create at least one user first.');
            return;
        }

        // ─────────────────────────────────────────────────────────
        // 2b. Load midwives — used ONLY for supply-request allocations
        // ─────────────────────────────────────────────────────────
        $midwives = DB::table('users')
            ->where('health_unit_id', $rhu->id)
            ->where('role', 'midwife')
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($midwives->isEmpty()) {
            $this->command->warn('⚠  No midwife users found at this RHU.');
        } else {
            $this->command->info('Found ' . $midwives->count() . ' midwife/midwives: ' . $midwives->pluck('name')->implode(', '));
        }

        // ─────────────────────────────────────────────────────────
        // 3. Pick 20 medicines
        // ─────────────────────────────────────────────────────────
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

        if ($medicines->isEmpty()) {
            $this->command->error('No medicines in DB. Seed medicines first.');
            return;
        }

        // ─────────────────────────────────────────────────────────
        // 4. Wipe previous rows this seeder created
        // ─────────────────────────────────────────────────────────
        $prefix = self::BATCH_PREFIX;

        $oldInvIds = DB::table('inventory')
            ->where('batch_no', 'like', $prefix . '%')
            ->pluck('id');

        if ($oldInvIds->isNotEmpty()) {
            DB::table('allocations')->whereIn('inventory_id', $oldInvIds)->delete();
        }

        DB::table('dispensing_records')->where('batch_no', 'like', $prefix . '%')->delete();
        DB::table('stock_logs')->where('batch_no', 'like', $prefix . '%')->delete();
        DB::table('inventory')->where('batch_no', 'like', $prefix . '%')->delete();

        $this->command->info('Cleared previous seeded rows.');

        // ─────────────────────────────────────────────────────────
        // 5. INVENTORY — 20 rows
        // ─────────────────────────────────────────────────────────
        $today = Carbon::today();
        $now   = Carbon::now();

        $storageLocations = [
            'Main Cabinet', 'Shelf A', 'Shelf B', 'Refrigerator',
            'Cold Room', 'Locked Cabinet', 'Pharmacy Shelf 1', 'Pharmacy Shelf 2',
        ];

        $expiryPlan = [
            [-60,  'Expired'],
            [-15,  'Expired'],
            [+10,  'Near expiry'],
            [+30,  'Near expiry'],
            [+55,  'Near expiry'],
            [+72,  'Near expiry'],
            [+85,  'Near expiry'],
            [+90,  'Near expiry'],
            [+110, 'Monitor'],
            [+130, 'Monitor'],
            [+155, 'Monitor'],
            [+175, 'Monitor'],
            [+210, 'Good'],
            [+300, 'Good'],
            [+400, 'Good'],
            [+500, 'Good'],
            [+600, 'Good'],
            [+730, 'Good'],
            [+900, 'Good'],
            [null, 'No expiry'],
        ];

        $inventoryRows = [];
        foreach ($expiryPlan as $i => [$offset, $label]) {
            $medicine = $medicines[$i % $medicines->count()];
            $batchNo  = $prefix . str_pad($i + 1, 4, '0', STR_PAD_LEFT);

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

        $this->command->info('Inserted 20 inventory rows.');

        // ─────────────────────────────────────────────────────────
        // 6. STOCK LOGS — 20 rows
        // ─────────────────────────────────────────────────────────
        $stockLogRows = [];
        foreach ($inventories as $idx => $inv) {
            $row = [
                'medicine_id'      => $inv->medicine_id,
                'health_unit_id'   => $rhu->id,
                'quantity_added'   => $inv->available_stock,
                'batch_no'         => $inv->batch_no,
                'storage_location' => $inv->storage_location,
                'date_added'       => $now->copy()->subDays(30 - $idx)->toDateString(),
                'added_by'         => $admin->id,
            ];
            if (Schema::hasColumn('stock_logs', 'remarks')) {
                $row['remarks'] = 'Added — Initial stock intake';
            }
            $stockLogRows[] = $row;
        }
        DB::table('stock_logs')->insert($stockLogRows);

        $this->command->info('Inserted 20 stock log entries.');

        // ─────────────────────────────────────────────────────────
        // 7. ALLOCATIONS
        //
        //   Only the PENDING rows are supply requests FROM a midwife.
        //   Those get a recipient_user_id + Approve/Reject buttons.
        //
        //   All other rows are resolved requests OR direct allocations
        //   issued by the admin — no midwife recipient.
        //
        //   Mix (20 rows):
        //     4 × pending    → midwife recipient + Approve/Reject
        //     4 × approved   → midwife recipient (already approved)
        //     2 × rejected   → midwife recipient (already rejected)
        //     6 × received   → midwife recipient (already received)
        //     4 × completed  → NO recipient (direct admin allocation)
        // ─────────────────────────────────────────────────────────
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

        $recipientCol = null;
        foreach (['recipient_user_id', 'recipient_id', 'to_user_id', 'midwife_id'] as $c) {
            if (Schema::hasColumn('allocations', $c)) { $recipientCol = $c; break; }
        }

        if (!$recipientCol) {
            $this->command->warn('⚠  No recipient column found on allocations.');
        }

        $statusPlan = [
            'pending', 'pending', 'pending', 'pending',
            'approved', 'approved', 'approved', 'approved',
            'rejected', 'rejected',
            'received', 'received', 'received', 'received', 'received', 'received',
            'completed', 'completed', 'completed', 'completed',
        ];

        $midwifeStatuses = ['pending', 'approved', 'rejected', 'received'];

        // ─────────────────────────────────────────────────────────
        // Build the column list FIRST so every row has identical keys.
        // This is the fix for the "Column count doesn't match" error —
        // bulk inserts require every row to have the same shape.
        // ─────────────────────────────────────────────────────────
        $optionalColumns = [
            'allocated_by'       => Schema::hasColumn('allocations', 'allocated_by'),
            'status'             => Schema::hasColumn('allocations', 'status'),
            'remarks'            => Schema::hasColumn('allocations', 'remarks'),
            'created_at'         => Schema::hasColumn('allocations', 'created_at'),
            'updated_at'         => Schema::hasColumn('allocations', 'updated_at'),
            'received_at'        => Schema::hasColumn('allocations', 'received_at'),
        ];

        $allocationRows = [];
        $midwifeCounter = 0;

        foreach ($inventories as $i => $inv) {
            $status = $statusPlan[$i] ?? 'completed';
            $qty    = rand(5, 80);

            $isMidwifeRequest = in_array($status, $midwifeStatuses, true) && $midwives->isNotEmpty();

            // Build a row with ALL possible keys, using null where not applicable.
            $row = [
                'medicine_id'        => $inv->medicine_id,
                'health_unit_id'     => $rhu->id,
                'inventory_id'       => $inv->id,
                'quantity_allocated' => $qty,
                'date_allocated'     => $now->copy()->subDays(20 - $i)->toDateString(),
            ];

            // Always include destination column if it exists
            if ($destCol) {
                $row[$destCol] = $pho->id ?? null;
            }

            // Always include recipient column if it exists — null for direct allocations
            if ($recipientCol) {
                if ($isMidwifeRequest) {
                    $recipient = $midwives[$midwifeCounter % $midwives->count()];
                    $midwifeCounter++;
                    $row[$recipientCol] = $recipient->id;
                } else {
                    $row[$recipientCol] = null;
                }
            }

            // Optional columns — always set to a value (never conditionally omit)
            if ($optionalColumns['allocated_by']) {
                $row['allocated_by'] = $admin->id;
            }
            if ($optionalColumns['status']) {
                $row['status'] = $status;
            }
            if ($optionalColumns['remarks']) {
                $row['remarks'] = $isMidwifeRequest
                    ? 'Supply request from midwife'
                    : 'Direct allocation';
            }
            if ($optionalColumns['created_at']) {
                $row['created_at'] = $now->copy()->subDays(20 - $i);
            }
            if ($optionalColumns['updated_at']) {
                $row['updated_at'] = $now->copy()->subDays(20 - $i);
            }
            if ($optionalColumns['received_at']) {
                $row['received_at'] = $status === 'received'
                    ? $now->copy()->subDays(19 - $i)
                    : null;
            }

            $allocationRows[] = $row;
        }

        // Safety: verify every row has the same column count before inserting
        if (!empty($allocationRows)) {
            $firstKeys = array_keys($allocationRows[0]);
            foreach ($allocationRows as $idx => $r) {
                if (array_keys($r) !== $firstKeys) {
                    $this->command->error('Row ' . ($idx + 1) . ' has mismatched columns. Aborting.');
                    $this->command->line('Expected: ' . implode(', ', $firstKeys));
                    $this->command->line('Got:      ' . implode(', ', array_keys($r)));
                    return;
                }
            }
        }

        DB::table('allocations')->insert($allocationRows);

        $statusCounts = array_count_values($statusPlan);
        $this->command->info('Inserted 20 allocation records:');
        foreach ($statusCounts as $status => $count) {
            $tag = in_array($status, $midwifeStatuses, true) ? ' (from midwife)' : ' (direct — no recipient)';
            $this->command->line('  · ' . str_pad($count, 2) . ' × ' . $status . $tag);
        }

        // ─────────────────────────────────────────────────────────
        // 8. DISPENSING — 20 rows
        // ─────────────────────────────────────────────────────────
        $doctors = [
            'Dr. Anna Reyes',
            'Dr. Marco Santos',
            'Dr. Liza Cruz',
            'Dr. Paolo Garcia',
            'Dr. Maria Mendoza',
            'Dr. Rafael Bautista',
            'Dr. Carmen Del Rosario',
            'Dr. Juan Villanueva',
            'Dr. Sofia Ramos',
            'Dr. Gabriel Torres',
        ];

        $dispensingRows = [];
        $hasDispensedBy = Schema::hasColumn('dispensing_records', 'dispensed_by');
        $hasRemarks     = Schema::hasColumn('dispensing_records', 'remarks');
        $hasTimestamps  = Schema::hasColumn('dispensing_records', 'created_at');

        foreach ($inventories as $i => $inv) {
            $med = $medicines->firstWhere('id', $inv->medicine_id);

            if ($med && $this->isEquipment($med->medicine_name ?? '')) {
                continue;
            }

            $qty = rand(1, 20);

            $dispensedBy = $midwives->isNotEmpty()
                ? $midwives[$i % $midwives->count()]
                : $admin;

            $row = [
                'medicine_id'        => $inv->medicine_id,
                'health_unit_id'     => $rhu->id,
                'batch_no'           => $inv->batch_no,
                'quantity_dispensed' => $qty,
                'date_dispensed'     => $now->copy()->subDays(15 - $i)->toDateString(),
                'doctor_name'        => $doctors[$i % count($doctors)],
            ];

            if ($hasDispensedBy) {
                $row['dispensed_by'] = $dispensedBy->id;
            }
            if ($hasRemarks) {
                $row['remarks'] = 'Routine dispensing';
            }
            if ($hasTimestamps) {
                $row['created_at'] = $now->copy()->subDays(15 - $i);
                $row['updated_at'] = $now->copy()->subDays(15 - $i);
            }

            $dispensingRows[] = $row;
        }

        if (!empty($dispensingRows)) {
            DB::table('dispensing_records')->insert($dispensingRows);
        }

        $this->command->info('Inserted ' . count($dispensingRows) . ' dispensing records.');

        // ─────────────────────────────────────────────────────────
        // Summary
        // ─────────────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info("✔ Seeded for RHU: {$rhu->name}");
        $this->command->info('  · ' . count($inventoryRows) . ' inventory rows');
        $this->command->info('  · ' . count($stockLogRows) . ' stock log entries');
        $this->command->info('  · ' . count($allocationRows) . ' allocations (midwife recipients on supply-request rows only)');
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