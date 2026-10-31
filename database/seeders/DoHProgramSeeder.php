<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DoHProgramSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('doh_programs')->truncate();

        $vitA = "Child health package. Vitamin A dosing:\n"
              . "• Infants 6–11 months: 100,000 IU (single dose)\n"
              . "• Children 12–59 months: 200,000 IU every 6 months\n"
              . "• Postpartum/lactating women: 200,000 IU within 4 weeks of delivery";

        $programs = [
            ['name' => 'National Immunization Day',          'recurrence' => 'monthly', 'day' => 15, 'color' => '#42ad8a', 'description' => 'Routine EPI catch-up day.'],
            ['name' => 'Nutrition Month',                    'recurrence' => 'yearly',  'month' => 7,  'day' => 1,  'duration_days' => 31, 'color' => '#eab308', 'description' => 'July — nutrition awareness & feeding programs.'],
            ['name' => 'Family Planning Month',              'recurrence' => 'yearly',  'month' => 8,  'day' => 1,  'duration_days' => 31, 'color' => '#ec4899', 'description' => 'August — FP awareness and counseling.'],
            ['name' => 'National TB Day',                    'recurrence' => 'yearly',  'month' => 3,  'day' => 24, 'color' => '#dc2626', 'description' => 'World TB Day — screening drives.'],
            ['name' => 'World Health Day',                   'recurrence' => 'yearly',  'month' => 4,  'day' => 7,  'color' => '#0ea5e9', 'description' => 'WHO World Health Day.'],
            ['name' => 'Maternal Health Week',               'recurrence' => 'yearly',  'month' => 5,  'day' => 8,  'duration_days' => 7,  'color' => '#8b5cf6', 'description' => 'Safe motherhood awareness week.'],
            ['name' => 'Global Handwashing Day',             'recurrence' => 'yearly',  'month' => 10, 'day' => 15, 'color' => '#06b6d4', 'description' => 'Handwashing campaign.'],
            ['name' => 'Garantisadong Pambata April Round',  'recurrence' => 'yearly',  'month' => 4,  'day' => 1,  'duration_days' => 30, 'color' => '#f97316', 'description' => $vitA],
            ['name' => 'Garantisadong Pambata October Round','recurrence' => 'yearly',  'month' => 10, 'day' => 1,  'duration_days' => 30, 'color' => '#f97316', 'description' => $vitA],
            ['name' => 'Deworming January Round',            'recurrence' => 'yearly',  'month' => 1,  'day' => 15, 'color' => '#22c55e', 'description' => 'Mass deworming for school-age children.'],
            ['name' => 'Deworming July Round',               'recurrence' => 'yearly',  'month' => 7,  'day' => 15, 'color' => '#22c55e', 'description' => 'Mid-year deworming round.'],
            ['name' => 'World AIDS Day',                     'recurrence' => 'yearly',  'month' => 12, 'day' => 1,  'color' => '#dc2626', 'description' => 'HIV awareness and testing campaign.'],
            ['name' => 'Folic Acid Awareness Week',          'recurrence' => 'yearly',  'month' => 1,  'day' => 8,  'duration_days' => 7,  'color' => '#e11d48', 'description' => 'Pre-pregnancy folic acid awareness.'],
            ['name' => 'Diabetes Awareness Week',            'recurrence' => 'yearly',  'month' => 7,  'day' => 15, 'duration_days' => 7,  'color' => '#7c3aed', 'description' => 'Blood sugar screening drives.'],
        ];

        DB::table('doh_programs')->insert(array_map(function ($p) {
            return array_merge([
                'recurrence'    => 'yearly',
                'month'         => null,
                'day'           => null,
                'weekday'       => null,
                'duration_days' => 1,
                'is_active'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ], $p);
        }, $programs));
    }
}