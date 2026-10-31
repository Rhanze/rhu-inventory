<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class SamplePatientsSeeder extends Seeder
{
    public function run(): void
    {
        $midwifeId    = (int) env('SEED_MIDWIFE_ID', 14);
        $healthUnitId = (int) env('SEED_HEALTH_UNIT_ID', 1);

        $this->command->info("Seeding for midwife #{$midwifeId}, RHU #{$healthUnitId}");

        // ── Clean up any leftover sample rows from previous attempts ──
        $deleted = DB::table('public_users')
            ->where('assigned_midwife_id', $midwifeId)
            ->where('contact_number', 'like', '09171234%')
            ->delete();
        if ($deleted > 0) {
            $this->command->warn("Cleaned up {$deleted} leftover sample row(s).");
        }

        // ── Inspect schema ──
        $allColumns   = Schema::getColumnListing('public_users');
        $requiredCols = collect(DB::select("
            SELECT COLUMN_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'public_users'
              AND IS_NULLABLE = 'NO'
              AND COLUMN_DEFAULT IS NULL
              AND EXTRA NOT LIKE '%auto_increment%'
        "))->pluck('COLUMN_NAME')->toArray();

        $has   = fn(string $c) => in_array($c, $allColumns, true);
        $now   = now();
        $today = Carbon::now();

        // Unique seed suffix so PhilHealth IDs never collide, even if
        // an old run's rows still exist.
        $seed = substr((string) time(), -6);

        $brgys = [
            'Poblacion 1', 'Poblacion 2', 'Poblacion 3', 'Poblacion 4', 'Poblacion 5',
            'Buhang', 'Apdo', 'Caridad', 'La Paz', 'Malandog',
        ];

        $patients = [
            // 1–3: children (immunization)
            ['first_name'=>'Juan Miguel','last_name'=>'Santos',     'birthdate'=>$today->copy()->subMonths(2)->toDateString(),  'sex'=>'M','barangay'=>$brgys[0],'contact'=>'09171234001','is_infant'=>true],
            ['first_name'=>'Maria Sofia','last_name'=>'Reyes',      'birthdate'=>$today->copy()->subMonths(9)->toDateString(),  'sex'=>'F','barangay'=>$brgys[1],'contact'=>'09171234002','is_infant'=>true],
            ['first_name'=>'Luis Andres','last_name'=>'Cruz',       'birthdate'=>$today->copy()->subYears(3)->toDateString(),   'sex'=>'M','barangay'=>$brgys[2],'contact'=>'09171234003'],

            // 4–6: prenatal (pregnant)
            ['first_name'=>'Ana',        'last_name'=>'Bautista',   'birthdate'=>$today->copy()->subYears(28)->toDateString(),  'sex'=>'F','barangay'=>$brgys[3],'contact'=>'09171234004','is_pregnant'=>true],
            ['first_name'=>'Rosa',       'last_name'=>'Mendoza',    'birthdate'=>$today->copy()->subYears(24)->toDateString(),  'sex'=>'F','barangay'=>$brgys[4],'contact'=>'09171234005','is_pregnant'=>true],
            ['first_name'=>'Liezl',      'last_name'=>'Torres',     'birthdate'=>$today->copy()->subYears(31)->toDateString(),  'sex'=>'F','barangay'=>$brgys[5],'contact'=>'09171234006','is_pregnant'=>true],

            // 7–8: postpartum
            ['first_name'=>'Jenny',      'last_name'=>'Villanueva', 'birthdate'=>$today->copy()->subYears(27)->toDateString(),  'sex'=>'F','barangay'=>$brgys[6],'contact'=>'09171234007','delivery_date'=>$today->copy()->subWeeks(3)->toDateString()],
            ['first_name'=>'Kristine',   'last_name'=>'Aquino',     'birthdate'=>$today->copy()->subYears(22)->toDateString(),  'sex'=>'F','barangay'=>$brgys[7],'contact'=>'09171234008','delivery_date'=>$today->copy()->subMonths(2)->toDateString()],

            // 9–10: family planning
            ['first_name'=>'Marites',    'last_name'=>'Gonzales',   'birthdate'=>$today->copy()->subYears(33)->toDateString(),  'sex'=>'F','barangay'=>$brgys[8],'contact'=>'09171234009','fp_method'=>'Pills'],
            ['first_name'=>'Rowena',     'last_name'=>'Delos Reyes','birthdate'=>$today->copy()->subYears(29)->toDateString(),  'sex'=>'F','barangay'=>$brgys[9],'contact'=>'09171234010','fp_method'=>'Injectable'],
        ];

        foreach ($patients as $i => $p) {
            $purok    = 'Purok ' . (($i % 5) + 1);
            $address  = $purok . ', ' . $p['barangay'] . ', Hamtic, Antique';
            $fullName = $p['first_name'] . ' ' . $p['last_name'];

            // Unique per run: seed prefix + position
            $philhealth = sprintf('%s-%09d-%d', substr($seed, 0, 2), (int) substr($seed, 2) * 100 + $i, ($i % 9) + 1);

            $values = [
                'full_name'              => $fullName,
                'name'                   => $fullName,
                'first_name'             => $p['first_name'],
                'last_name'              => $p['last_name'],
                'middle_name'            => '',

                'contact_number'         => $p['contact'],
                'contact'                => $p['contact'],
                'phone'                  => $p['contact'],
                'phone_number'           => $p['contact'],
                'mobile_number'          => $p['contact'],
                'mobile'                 => $p['contact'],

                'email'                  => 'sample' . $seed . '_' . ($i + 1) . '@example.test',

                'address'                => $address,
                'street'                 => $purok,
                'purok'                  => $purok,
                'barangay'               => $p['barangay'],
                'city'                   => 'Hamtic',
                'municipality'           => 'Hamtic',
                'province'               => 'Antique',
                'zip_code'               => '5715',
                'postal_code'            => '5715',
                'region'                 => 'Region VI',

                'sex'                    => $p['sex'],
                'gender'                 => $p['sex'] === 'M' ? 'male' : 'female',

                'birthdate'              => $p['birthdate'],
                'birth_date'             => $p['birthdate'],
                'date_of_birth'          => $p['birthdate'],
                'dob'                    => $p['birthdate'],
                'age'                    => Carbon::parse($p['birthdate'])->age,

                'philhealth_id'          => $philhealth,
                'philhealth_number'      => $philhealth,

                'is_pregnant'            => !empty($p['is_pregnant']) ? 1 : 0,
                'is_infant'              => !empty($p['is_infant'])   ? 1 : 0,
                'is_postpartum'          => !empty($p['delivery_date']) ? 1 : 0,
                'is_family_planning'     => !empty($p['fp_method'])     ? 1 : 0,
                'is_active'              => 1,
                'is_deceased'            => 0,
                'is_verified'            => 1,
                'is_emergency'           => 0,

                'delivery_date'          => $p['delivery_date'] ?? null,
                'last_delivery_date'     => $p['delivery_date'] ?? null,

                'fp_method'              => $p['fp_method'] ?? null,
                'family_planning_method' => $p['fp_method'] ?? null,
                'fp_status'              => !empty($p['fp_method']) ? 'active' : null,
                'family_planning_status' => !empty($p['fp_method']) ? 'active' : null,

                'patient_type'           => !empty($p['is_infant']) ? 'infant' : (!empty($p['is_pregnant']) ? 'prenatal' : 'general'),
                'type'                   => 'patient',
                'category'               => 'patient',
                'status'                 => 'active',

                'health_unit_id'         => $healthUnitId,
                'registered_by'          => $midwifeId,
                'assigned_midwife_id'    => $midwifeId,
                'user_id'                => $midwifeId,
                'created_by'             => $midwifeId,

                'created_at'             => $now,
                'updated_at'             => $now,
                'verified_at'            => $now,
            ];

            $row = [];
            foreach ($values as $col => $val) {
                if ($has($col)) $row[$col] = $val;
            }

            foreach ($requiredCols as $rc) {
                if (array_key_exists($rc, $row) && $row[$rc] !== null) continue;

                $row[$rc] = match (true) {
                    str_contains($rc, 'email')      => 'sample' . $seed . '_' . ($i + 1) . '@example.test',
                    str_contains($rc, 'contact'),
                    str_contains($rc, 'phone'),
                    str_contains($rc, 'mobile')     => $p['contact'],
                    str_contains($rc, 'address')    => $address,
                    str_contains($rc, 'barangay')   => $p['barangay'],
                    str_contains($rc, 'name')       => $fullName,
                    str_contains($rc, 'sex')        => $p['sex'],
                    str_contains($rc, 'birth')      => $p['birthdate'],
                    str_contains($rc, 'date')       => $p['birthdate'],
                    str_contains($rc, 'age')        => Carbon::parse($p['birthdate'])->age,
                    str_contains($rc, 'status')     => 'active',
                    str_contains($rc, 'type')       => 'patient',
                    str_contains($rc, 'philhealth') => $philhealth,
                    str_contains($rc, '_id')        => null,
                    default                         => '',
                };
            }

            try {
                DB::table('public_users')->insert($row);
                $this->command->info("  ✔ #" . ($i + 1) . " {$fullName} ({$p['barangay']})");
            } catch (\Throwable $e) {
                $this->command->error("  ✘ #" . ($i + 1) . " {$fullName}: " . $e->getMessage());
                throw $e;
            }
        }

        $this->command->info('');
        $this->command->info('✅ Inserted ' . count($patients) . ' sample patients.');
    }
}