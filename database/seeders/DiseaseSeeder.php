<?php

namespace Database\Seeders;

use App\Models\Disease;
use Illuminate\Database\Seeder;

class DiseaseSeeder extends Seeder
{
    public function run(): void
    {
        $diseases = [
            // Vector-borne
            ['code' => 'DENGUE',       'name' => 'Dengue Fever',              'category' => 'Vector-borne'],
            ['code' => 'MALARIA',      'name' => 'Malaria',                   'category' => 'Vector-borne'],
            ['code' => 'LEPTO',        'name' => 'Leptospirosis',             'category' => 'Vector-borne'],

            // Respiratory
            ['code' => 'ILI',          'name' => 'Influenza-Like Illness',    'category' => 'Respiratory'],
            ['code' => 'ARI',          'name' => 'Acute Respiratory Infection','category' => 'Respiratory'],
            ['code' => 'PNEUMONIA',    'name' => 'Pneumonia',                 'category' => 'Respiratory'],
            ['code' => 'TB',           'name' => 'Tuberculosis',              'category' => 'Respiratory'],

            // Water/food-borne
            ['code' => 'AGE',          'name' => 'Acute Gastroenteritis',     'category' => 'Water/Food-borne'],
            ['code' => 'TYPHOID',      'name' => 'Typhoid Fever',             'category' => 'Water/Food-borne'],
            ['code' => 'CHOLERA',      'name' => 'Cholera',                   'category' => 'Water/Food-borne'],

            // Non-communicable
            ['code' => 'HYPERTENSION', 'name' => 'Hypertension',              'category' => 'Non-communicable', 'is_reportable' => false],
            ['code' => 'DIABETES',     'name' => 'Diabetes Mellitus',         'category' => 'Non-communicable', 'is_reportable' => false],

            // Maternal/Child
            ['code' => 'PRENATAL',     'name' => 'Prenatal Consultations',    'category' => 'Maternal Care',    'is_reportable' => false],
            ['code' => 'MALNUTRITION', 'name' => 'Malnutrition',              'category' => 'Child Health'],

            // Other
            ['code' => 'MEASLES',      'name' => 'Measles',                   'category' => 'Vaccine-Preventable'],
            ['code' => 'HFMD',         'name' => 'Hand, Foot and Mouth Disease','category' => 'Vaccine-Preventable'],
        ];

        foreach ($diseases as $d) {
            Disease::updateOrCreate(['code' => $d['code']], $d);
        }
    }
}