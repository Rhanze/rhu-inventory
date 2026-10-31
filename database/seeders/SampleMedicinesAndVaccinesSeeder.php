<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SampleMedicinesAndVaccinesSeeder extends Seeder
{
    public function run(): void
    {
        // [name, dose, form, category, unit, Rx, dispensable]
        $medicines = [
            ['Paracetamol',                  '500 mg',         'Tablet',                      'medicine', 'Tablet',   false, true],
            ['Ibuprofen',                    '200 mg',         'Tablet',                      'medicine', 'Tablet',   false, true],
            ['Mefenamic Acid',               '500 mg',         'Capsule',                     'medicine', 'Capsule',  false, true],
            ['Aspirin',                      '80 mg',          'Tablet',                      'medicine', 'Tablet',   false, true],
            ['Amoxicillin',                  '500 mg',         'Capsule',                     'medicine', 'Capsule',  true,  true],
            ['Azithromycin',                 '500 mg',         'Tablet',                      'medicine', 'Tablet',   true,  true],
            ['Cefixime',                     '200 mg',         'Capsule',                     'medicine', 'Capsule',  true,  true],
            ['Cotrimoxazole',                '800 mg / 160 mg','Tablet',                      'medicine', 'Tablet',   true,  true],
            ['Metronidazole',                '500 mg',         'Tablet',                      'medicine', 'Tablet',   true,  true],
            ['Ciprofloxacin',                '500 mg',         'Tablet',                      'medicine', 'Tablet',   true,  true],
            ['Cetirizine',                   '10 mg',          'Tablet',                      'medicine', 'Tablet',   false, true],
            ['Loratadine',                   '10 mg',          'Tablet',                      'medicine', 'Tablet',   false, true],
            ['Diphenhydramine',              '25 mg',          'Capsule',                     'medicine', 'Capsule',  false, true],
            ['Amlodipine',                   '10 mg',          'Tablet',                      'medicine', 'Tablet',   true,  true],
            ['Losartan',                     '50 mg',          'Tablet',                      'medicine', 'Tablet',   true,  true],
            ['Metoprolol',                   '50 mg',          'Tablet',                      'medicine', 'Tablet',   true,  true],
            ['Metformin',                    '500 mg',         'Tablet',                      'medicine', 'Tablet',   true,  true],
            ['Simvastatin',                  '20 mg',          'Tablet',                      'medicine', 'Tablet',   true,  true],
            ['Salbutamol',                   '100 mcg/dose',   'Metered Dose Inhaler',        'medicine', 'Inhaler',  true,  true],
            ['Oral Rehydration Salts (ORS)', '20.5 g',         'Powder Sachet',               'medicine', 'Sachet',   false, true],
        ];

        $vaccines = [
            ['BCG Vaccine',                                 '0.05 mL',  'Lyophilized Powder for Injection', 'vaccine', 'Vial',  true,  true],
            ['Hepatitis B Vaccine',                         '0.5 mL',   'Suspension for Injection',         'vaccine', 'Vial',  true,  true],
            ['Pentavalent Vaccine (DPT-HepB-Hib)',          '0.5 mL',   'Suspension for Injection',         'vaccine', 'Vial',  true,  true],
            ['Oral Polio Vaccine (OPV)',                    '2 drops',  'Oral Drops',                       'vaccine', 'Vial',  false, true],
            ['Inactivated Polio Vaccine (IPV)',             '0.5 mL',   'Suspension for Injection',         'vaccine', 'Vial',  true,  true],
            ['Measles-Rubella (MR) Vaccine',                '0.5 mL',   'Lyophilized Powder for Injection', 'vaccine', 'Vial',  true,  true],
            ['Measles-Mumps-Rubella (MMR) Vaccine',         '0.5 mL',   'Lyophilized Powder for Injection', 'vaccine', 'Vial',  true,  true],
            ['Rotavirus Vaccine',                           '1.5 mL',   'Oral Suspension',                  'vaccine', 'Tube',  false, true],
            ['Pneumococcal Conjugate Vaccine (PCV)',        '0.5 mL',   'Suspension for Injection',         'vaccine', 'Vial',  true,  true],
            ['Tetanus-Diphtheria (Td) Vaccine',             '0.5 mL',   'Suspension for Injection',         'vaccine', 'Vial',  true,  true],
            ['Tetanus Toxoid (TT) Vaccine',                 '0.5 mL',   'Suspension for Injection',         'vaccine', 'Vial',  true,  true],
            ['Human Papillomavirus (HPV) Vaccine',          '0.5 mL',   'Suspension for Injection',         'vaccine', 'Vial',  true,  true],
            ['Influenza Vaccine (Quadrivalent)',            '0.5 mL',   'Suspension for Injection',         'vaccine', 'Vial',  true,  true],
            ['Pneumococcal Polysaccharide Vaccine (PPSV23)','0.5 mL',   'Solution for Injection',           'vaccine', 'Vial',  true,  true],
            ['Hepatitis A Vaccine',                         '0.5 mL',   'Suspension for Injection',         'vaccine', 'Vial',  true,  true],
            ['Varicella Vaccine',                           '0.5 mL',   'Lyophilized Powder for Injection', 'vaccine', 'Vial',  true,  true],
            ['Rabies Vaccine (Intradermal)',                '0.1 mL',   'Lyophilized Powder for Injection', 'vaccine', 'Vial',  true,  true],
            ['Rabies Vaccine (Intramuscular)',              '0.5 mL',   'Lyophilized Powder for Injection', 'vaccine', 'Vial',  true,  true],
            ['Rabies Immune Globulin (RIG)',                '150 IU/mL','Solution for Injection',           'vaccine', 'Vial',  true,  true],
            ['Japanese Encephalitis Vaccine',               '0.5 mL',   'Suspension for Injection',         'vaccine', 'Vial',  true,  true],
        ];

        $build = function (array $items): array {
            $rows = [];
            foreach ($items as $item) {
                $rows[] = [
                    'medicine_name'         => $item[0],
                    'dosage'                => $item[1],
                    'dosage_form'           => $item[2],
                    'category'              => $item[3],
                    'base_unit'             => $item[4],
                    'requires_prescription' => $item[5],
                    'is_dispensable'        => $item[6],
                    'min_stock'             => 10,
                    'max_stock'             => 500,
                    'description'           => '',
                    'pack_type'             => '',
                    'pieces_per_pack'       => 1,
                ];
            }
            return $rows;
        };

        $rows = array_merge($build($medicines), $build($vaccines));

        DB::transaction(function () use ($rows) {
            // 1. Wipe this seeder's owned categories so re-runs are clean
            DB::table('medicines')->whereIn('category', ['medicine', 'vaccine'])->delete();

            // 2. Wipe legacy clinical-class categories from previous versions
            DB::table('medicines')->whereIn('category', [
                'analgesic', 'antibiotic', 'antihistamine', 'rehydration',
                'antihypertensive', 'antidiabetic', 'lipid_lowering', 'bronchodilator',
            ])->delete();

            // 3. Migrate the 6 sample medicines previously misfiled under
            //    non_communicable (Amlodipine 10mg, Losartan, Metoprolol,
            //    Metformin, Simvastatin, Salbutamol) — remove them so they
            //    don't duplicate with the new medicine-category rows above.
            DB::table('medicines')
                ->where('category', 'non_communicable')
                ->whereIn('medicine_name', [
                    'Losartan', 'Metoprolol', 'Metformin', 'Simvastatin', 'Salbutamol',
                ])
                ->delete();

            DB::table('medicines')
                ->where('category', 'non_communicable')
                ->where('medicine_name', 'Amlodipine')
                ->where('dosage', '10 mg')
                ->delete();

            // 4. Insert fresh rows
            foreach (array_chunk($rows, 50) as $chunk) {
                DB::table('medicines')->insert($chunk);
            }
        });

        if ($this->command) {
            $this->command->info(
                'Inserted ' . count($medicines) . ' medicines and '
                . count($vaccines) . ' vaccines.'
            );
        }
    }
}