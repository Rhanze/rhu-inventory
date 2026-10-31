<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class SampleDiseaseReportsSeeder extends Seeder
{
    public function run(): void
    {
        // ─────────────────────────────────────────────────────────
        // 1. Target RHU
        // ─────────────────────────────────────────────────────────
        $rhu = DB::table('health_units')
            ->whereIn('facility_type', ['Rural Health Unit', 'RHU'])
            ->orderBy('id')
            ->first();

        if (!$rhu) {
            $this->command->error('No RHU found. Aborting.');
            return;
        }

        $this->command->info("Target RHU: [{$rhu->id}] {$rhu->name}");

        // ─────────────────────────────────────────────────────────
        // 2. Midwife reporter
        // ─────────────────────────────────────────────────────────
        $midwife = DB::table('users')
            ->where('health_unit_id', $rhu->id)
            ->where('role', 'midwife')
            ->orderBy('id')
            ->first();

        if (!$midwife) {
            $midwife = DB::table('users')
                ->where('health_unit_id', $rhu->id)
                ->orderBy('id')
                ->first();
        }

        if (!$midwife) {
            $this->command->error('No user at this RHU to attribute as reporter.');
            return;
        }

        $this->command->info("Reporter: {$midwife->name}");

        // ─────────────────────────────────────────────────────────
        // 3. Diseases
        // ─────────────────────────────────────────────────────────
        if (!Schema::hasTable('diseases')) {
            $this->command->error('No `diseases` table. Run DiseaseSeeder first.');
            return;
        }

        $nameCol = 'name';
        foreach (['name', 'disease_name', 'title'] as $c) {
            if (Schema::hasColumn('diseases', $c)) { $nameCol = $c; break; }
        }

        $diseases = DB::table('diseases')
            ->orderBy('id')
            ->limit(6)
            ->get(['id', $nameCol]);

        if ($diseases->isEmpty()) {
            $this->command->error('No diseases in DB. Run DiseaseSeeder first.');
            return;
        }

        $this->command->info('Using ' . $diseases->count() . ' diseases.');

        // ─────────────────────────────────────────────────────────
        // 4. Wipe previous seeded reports (last 90 days)
        // ─────────────────────────────────────────────────────────
        DB::table('disease_reports')
            ->where('health_unit_id', $rhu->id)
            ->where('report_date', '>=', now()->subDays(90)->toDateString())
            ->delete();

        $this->command->info('Cleared previous seeded reports (last 90 days).');

        // ─────────────────────────────────────────────────────────
        // 5. Build reports
        //    Weekly reports: 4 weeks back for each disease
        //    Plus a monthly summary for the current month
        //    Plus 2 outbreak flags on the disease with the highest count
        // ─────────────────────────────────────────────────────────
        $now   = Carbon::now();
        $rows  = [];

        // Case-count profile per disease — realistic weekly ranges
        // index 0 = "most prevalent", index 5 = "least"
        $caseProfiles = [
            [8, 12],   // disease 1 — highest
            [5, 9],    // disease 2
            [3, 6],    // disease 3
            [2, 4],    // disease 4
            [1, 3],    // disease 5
            [0, 2],    // disease 6
        ];

        // 4 weekly reports: weeks ending 7, 14, 21, 28 days ago
        foreach ($diseases as $idx => $disease) {
            [$min, $max] = $caseProfiles[$idx] ?? [1, 3];

            for ($week = 1; $week <= 4; $week++) {
                $reportDate = $now->copy()->subDays($week * 7)->toDateString();
                $cases      = rand($min, $max);

                // Slight upward trend in recent weeks for more realistic trending
                if ($week === 1) $cases += 2; // most recent week has a few extra

                $rows[] = [
                    'disease_id'     => $disease->id,
                    'health_unit_id' => $rhu->id,
                    'report_date'    => $reportDate,
                    'period_type'    => 'weekly',
                    'case_count'     => $cases,
                    'is_outbreak'    => 0,
                    'remarks'        => null,
                    'reported_by'    => $midwife->id,
                    'created_at'     => $now->copy()->subDays($week * 7),
                    'updated_at'     => $now->copy()->subDays($week * 7),
                ];
            }
        }

        // Monthly summary for the current month (disease 1 only)
        $firstDisease = $diseases->first();
        [$min, $max]  = $caseProfiles[0];
        $monthlyTotal = rand($min * 3, $max * 4);

        $rows[] = [
            'disease_id'     => $firstDisease->id,
            'health_unit_id' => $rhu->id,
            'report_date'    => $now->copy()->startOfMonth()->toDateString(),
            'period_type'    => 'monthly',
            'case_count'     => $monthlyTotal,
            'is_outbreak'    => 0,
            'remarks'        => null,
            'reported_by'    => $midwife->id,
            'created_at'     => $now->copy()->startOfMonth(),
            'updated_at'     => $now->copy()->startOfMonth(),
        ];

        // 2 outbreak flags — flag the most prevalent disease in its two most
        // recent weeks (simulates a spike that the RHU decided to classify)
        $outbreakDates = [
            $now->copy()->subDays(7)->toDateString(),
            $now->copy()->subDays(14)->toDateString(),
        ];

        foreach ($outbreakDates as $idx => $date) {
            $rows[] = [
                'disease_id'     => $firstDisease->id,
                'health_unit_id' => $rhu->id,
                'report_date'    => $date,
                'period_type'    => 'weekly',
                'case_count'     => rand(15, 25),   // spike
                'is_outbreak'    => 1,
                'remarks'        => 'Outbreak declaration — ' . $firstDisease->{$nameCol},
                'reported_by'    => $midwife->id,
                'created_at'     => $now->copy()->subDays(7 - $idx),
                'updated_at'     => $now->copy()->subDays(7 - $idx),
            ];
        }

        // ─────────────────────────────────────────────────────────
        // 6. Insert
        // ─────────────────────────────────────────────────────────
        if (empty($rows)) {
            $this->command->error('No rows generated. Aborting.');
            return;
        }

        // Safety: identical columns on every row
        $firstKeys = array_keys($rows[0]);
        foreach ($rows as $idx => $r) {
            if (array_keys($r) !== $firstKeys) {
                $this->command->error('Row ' . ($idx + 1) . ' has mismatched columns. Aborting.');
                return;
            }
        }

        DB::table('disease_reports')->insert($rows);

        // ─────────────────────────────────────────────────────────
        // 7. Report
        // ─────────────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('Inserted ' . count($rows) . ' disease reports.');
        $this->command->info('');

        // Summary by disease
        $totals = [];
        $nameById = $diseases->pluck($nameCol, 'id');
        foreach ($rows as $r) {
            $totals[$r['disease_id']] = ($totals[$r['disease_id']] ?? 0) + $r['case_count'];
        }
        arsort($totals);

        $this->command->info('Total cases by disease (last 30 days):');
        foreach ($totals as $id => $cases) {
            $this->command->line('  · ' . str_pad($cases, 4) . ' × ' . ($nameById[$id] ?? "Disease #{$id}"));
        }

        $outbreaks = collect($rows)->where('is_outbreak', 1)->count();
        $this->command->info('');
        $this->command->info('Outbreak flags: ' . $outbreaks);
        $this->command->info('');
        $this->command->info('Reload /forecast — "Recommended Stock-up Based on Trending Diseases" should now populate.');
    }
}