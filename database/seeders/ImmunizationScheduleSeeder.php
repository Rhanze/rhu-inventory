<?php

namespace Database\Seeders;

use App\Models\ImmunizationSchedule;
use Illuminate\Database\Seeder;

class ImmunizationScheduleSeeder extends Seeder
{
    public function run(): void
    {
        // Philippine NIP schedule (DOH).
        // min_age_days = earliest age to give this dose
        // max_age_days = latest age it still counts toward FIC (usually 365 = 1 year)
        // interval_days = min gap from previous dose of the same antigen
        $rows = [
            // ─── At birth ───
            ['bcg',   'BCG',         1,   0, 365, 'At birth',                 null,  true,  true,  10],
            ['hepb',  'Hepatitis B', 1,   0, 365, 'Within 24 hours of birth', null,  true,  true,  20],

            // ─── 6 weeks ───
            ['penta', 'Pentavalent', 1,  42, 365, '6 weeks',                  null,  true,  true,  30],
            ['opv',   'OPV',         1,  42, 365, '6 weeks',                  null,  true,  true,  40],
            ['pcv',   'PCV',         1,  42, 365, '6 weeks',                  null,  true,  true,  50],
            ['rota',  'Rotavirus',   1,  42, 365, '6 weeks',                  null,  false, false, 60],

            // ─── 10 weeks ───
            ['penta', 'Pentavalent', 2,  70, 365, '10 weeks',                 28,   true,  true,  70],
            ['opv',   'OPV',         2,  70, 365, '10 weeks',                 28,   true,  true,  80],
            ['pcv',   'PCV',         2,  70, 365, '10 weeks',                 28,   true,  true,  90],
            ['rota',  'Rotavirus',   2,  70, 365, '10 weeks',                 28,   false, false, 100],

            // ─── 14 weeks ───
            ['penta', 'Pentavalent', 3,  98, 365, '14 weeks',                 28,   true,  true,  110],
            ['opv',   'OPV',         3,  98, 365, '14 weeks',                 28,   true,  true,  120],
            ['pcv',   'PCV',         3,  98, 365, '14 weeks',                 28,   true,  true,  130],
            ['ipv',   'IPV',         1,  98, 365, '14 weeks',                 null, true,  true,  140],

            // ─── 9 months ───
            ['mmr',   'MMR',         1, 270, 365, '9 months',                 null, true,  true,  150],

            // ─── 12–15 months (CBI, not FIC) ───
            ['mmr',   'MMR',         2, 365, 730, '12–15 months',             90,   false, true,  160],
        ];

        foreach ($rows as $r) {
            ImmunizationSchedule::updateOrCreate(
                ['antigen' => $r[0], 'dose_number' => $r[2]],
                [
                    'antigen_label'        => $r[1],
                    'min_age_days'         => $r[3],
                    'max_age_days'         => $r[4],
                    'recommended_age_label'=> $r[5],
                    'interval_days'        => $r[6],
                    'is_required_for_fic'  => $r[7],
                    'is_required_for_cbi'  => $r[8],
                    'sort_order'           => $r[9],
                    'is_active'            => true,
                ]
            );
        }
    }
}