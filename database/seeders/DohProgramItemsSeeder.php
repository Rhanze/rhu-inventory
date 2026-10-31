<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DohProgramItemsSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * DOH program items from the Antique PHO warehouse exports.
         *
         * Each item now carries `is_dispensable`:
         *   true  = given to citizens by the RHU (visible in public search)
         *   false = internal supply (PPE, equipment, manuals, lab kits)
         */
        $items = [
            // ═══════════════════════════════════════════════════════════
            // DENGUE PREVENTION AND CONTROL PROGRAM
            // ═══════════════════════════════════════════════════════════
            [
                'medicine_name'         => 'Dengue Rapid Diagnostic Test Kit (NS1)',
                'dosage'                => null,
                'dosage_form'           => 'Test Kit',
                'category'              => 'dengue_prevention',
                'unit'                  => 'Tests',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Spray Can',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'dengue_prevention',
                'unit'                  => 'Units',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Hand Compression Sprayer',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'dengue_prevention',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],

            // ═══════════════════════════════════════════════════════════
            // EMERGING AND RE-EMERGING INFECTIOUS DISEASE PROGRAM
            // ═══════════════════════════════════════════════════════════
            [
                'medicine_name'         => 'Rubber Boots',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'emerging_reemerging',
                'unit'                  => 'Pairs',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Cover All PPE Set',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'emerging_reemerging',
                'unit'                  => 'Sets',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Doxycycline Hyclate',
                'dosage'                => '100mg',
                'dosage_form'           => 'Capsule',
                'category'              => 'emerging_reemerging',
                'unit'                  => 'Capsule',
                'requires_prescription' => true,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'Surgical Cap',
                'dosage'                => null,
                'dosage_form'           => 'Pack',
                'category'              => 'emerging_reemerging',
                'unit'                  => 'Pack',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Manual of Procedure (MOP) for Primary Health on Publication of Antimicrobial Stewardship',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'emerging_reemerging',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Manual on Environmental and Occupational Health Service Package',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'emerging_reemerging',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Rain Boots',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'emerging_reemerging',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Apron',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'emerging_reemerging',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Cadaver Bag (Adult)',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'emerging_reemerging',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Cover All (Large)',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'emerging_reemerging',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Goggles',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'emerging_reemerging',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Infrared Thermometer',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'emerging_reemerging',
                'unit'                  => 'Units',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],

            // ═══════════════════════════════════════════════════════════
            // FAMILY HEALTH & NUTRITION CLUSTER
            // ═══════════════════════════════════════════════════════════
            [
                'medicine_name'         => 'Thermohygrometer',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'family_health_nutrition',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Calcium Carbonate',
                'dosage'                => '500mg',
                'dosage_form'           => 'Tablet',
                'category'              => 'family_health_nutrition',
                'unit'                  => 'Tablet',
                'requires_prescription' => false,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'Health Kit',
                'dosage'                => null,
                'dosage_form'           => 'Kit',
                'category'              => 'family_health_nutrition',
                'unit'                  => 'Kit',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Zinc Sulfate (equivalent to 10mg elemental Zinc/mL) drops',
                'dosage'                => '15 mL',
                'dosage_form'           => 'Drops',
                'category'              => 'family_health_nutrition',
                'unit'                  => 'Bottles',
                'requires_prescription' => false,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'Diphenhydramine Hydrochloride',
                'dosage'                => '12.5g/5mL',
                'dosage_form'           => 'Syrup 60mL',
                'category'              => 'family_health_nutrition',
                'unit'                  => 'Bottles',
                'requires_prescription' => false,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'Foldable Walker',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'family_health_nutrition',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Vaccine Carrier',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'family_health_nutrition',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],

            // ═══════════════════════════════════════════════════════════
            // NATIONAL FAMILY PLANNING
            // ═══════════════════════════════════════════════════════════
            [
                'medicine_name'         => 'Male Condom - Plain and Flavored',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'family_planning',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'COC Pill - Levonorgestrel 150mcg + Ethinylestradiol 30mcg (+ Lactose 40mg) Tablet',
                'dosage'                => '28 tabs per cycle',
                'dosage_form'           => 'Tablet',
                'category'              => 'family_planning',
                'unit'                  => 'Cycle',
                'requires_prescription' => false,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'Bilateral Tubal Ligation Kit',
                'dosage'                => null,
                'dosage_form'           => 'Kit',
                'category'              => 'family_planning',
                'unit'                  => 'Kit',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Gather Approach Counseling Cue Card for Family Planning',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'family_planning',
                'unit'                  => 'Copies',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Medroxyprogesterone',
                'dosage'                => '150 mg/mL 1 mL',
                'dosage_form'           => 'Suspension for Injection Vial',
                'category'              => 'family_planning',
                'unit'                  => 'Vial',
                'requires_prescription' => true,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'Flyer - StoryAH Ta!',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'family_planning',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Auto Disable Syringe 1mL',
                'dosage'                => null,
                'dosage_form'           => 'Syringe',
                'category'              => 'family_planning',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Flipchart - Family Planning',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'family_planning',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'WRA Tracking Tool',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'family_planning',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Form - Psychosocial History and Assessment Forms (Ver. 1.0)',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'family_planning',
                'unit'                  => 'Copies',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Progestin Subdermal Implant (PSI) Ancillary Kit',
                'dosage'                => null,
                'dosage_form'           => 'Kit',
                'category'              => 'family_planning',
                'unit'                  => 'Kit',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Digital Thermometer',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'family_planning',
                'unit'                  => 'Units',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Etonogestrel 68 mg Progestin Sub-dermal Implant (PSI)',
                'dosage'                => '68 mg',
                'dosage_form'           => 'Implant',
                'category'              => 'family_planning',
                'unit'                  => 'Rod',
                'requires_prescription' => true,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'Intra Uterine Device (IUD)',
                'dosage'                => null,
                'dosage_form'           => 'Device',
                'category'              => 'family_planning',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => true,
            ],

            // ═══════════════════════════════════════════════════════════
            // NATIONAL TUBERCULOSIS CONTROL PROGRAM
            // ═══════════════════════════════════════════════════════════
            [
                'medicine_name'         => 'Rifampicin + Isoniazid',
                'dosage'                => '75mg + 50mg',
                'dosage_form'           => 'Dispersible Tablet',
                'category'              => 'tuberculosis',
                'unit'                  => 'Tablet',
                'requires_prescription' => true,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'Glass Slide',
                'dosage'                => 'boxes',
                'dosage_form'           => null,
                'category'              => 'tuberculosis',
                'unit'                  => 'Box',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Ethambutol',
                'dosage'                => '400 mg',
                'dosage_form'           => 'Tablet',
                'category'              => 'tuberculosis',
                'unit'                  => 'Tablet',
                'requires_prescription' => true,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'Rifapentine + Isoniazid',
                'dosage'                => '300mg + 300mg',
                'dosage_form'           => 'Tablet',
                'category'              => 'tuberculosis',
                'unit'                  => 'Tablet',
                'requires_prescription' => true,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'Isoniazid + Rifampicin + Pyrazinamide + Ethambutol',
                'dosage'                => '75mg + 150mg + 400mg + 275mg',
                'dosage_form'           => 'Tablet',
                'category'              => 'tuberculosis',
                'unit'                  => 'Tablet',
                'requires_prescription' => true,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'Tuberculin Syringe 1mL (box of 100s)',
                'dosage'                => '1mL',
                'dosage_form'           => 'Syringe',
                'category'              => 'tuberculosis',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Isoniazid + Rifampicin',
                'dosage'                => '75 mg + 150 mg',
                'dosage_form'           => 'Tablet',
                'category'              => 'tuberculosis',
                'unit'                  => 'Tablet',
                'requires_prescription' => true,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'Rifampicin + Isoniazid + Pyrazinamide',
                'dosage'                => '75mg + 50mg + 150mg',
                'dosage_form'           => 'Dispersible Tablet',
                'category'              => 'tuberculosis',
                'unit'                  => 'Tablet',
                'requires_prescription' => true,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'Ethambutol Hydrochloride Tablets BP',
                'dosage'                => '400mg',
                'dosage_form'           => 'Tablet',
                'category'              => 'tuberculosis',
                'unit'                  => 'Tablet',
                'requires_prescription' => true,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'Sputum Cup - Disposable Plastic with Screw Cap Over',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'tuberculosis',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Tuberculin Purified Protein Derivative (PPD)',
                'dosage'                => '5 TU/0.1 mL',
                'dosage_form'           => 'Powder for Injection Vial + 2 mL Diluent Ampule',
                'category'              => 'tuberculosis',
                'unit'                  => 'Vial',
                'requires_prescription' => true,
                'is_dispensable'        => true,
            ],

            // ═══════════════════════════════════════════════════════════
            // NON COMMUNICABLE DISEASE PROGRAM
            // ═══════════════════════════════════════════════════════════
            [
                'medicine_name'         => 'Vaginal Speculum Plastic Disposable - Small',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'non_communicable',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Vaginal Speculum Plastic Disposable - Medium',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'non_communicable',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Vaginal Speculum Plastic Disposable - Large',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'non_communicable',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Amlodipine',
                'dosage'                => '5 mg',
                'dosage_form'           => 'Tablet',
                'category'              => 'non_communicable',
                'unit'                  => 'Tablet',
                'requires_prescription' => true,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'Quad Cane',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'non_communicable',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Wheelchair',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'non_communicable',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Urinalysis Reagent Strips 10 Parameters (100s)',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'non_communicable',
                'unit'                  => 'Canister',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Glucometer with Glucose Test Strip',
                'dosage'                => null,
                'dosage_form'           => 'Kit',
                'category'              => 'non_communicable',
                'unit'                  => 'Kit',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Biphasic Isophane Human Insulin 70/30',
                'dosage'                => '100IU/mL 10mL',
                'dosage_form'           => 'Suspension for Injection',
                'category'              => 'non_communicable',
                'unit'                  => 'Vial',
                'requires_prescription' => true,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'Biphasic Isophane Human Insulin 70/30 rDNA',
                'dosage'                => '100IU/mL 10mL',
                'dosage_form'           => 'Solution for Injection',
                'category'              => 'non_communicable',
                'unit'                  => 'Vial',
                'requires_prescription' => true,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'Isophane Insulin Human (recombinant DNA)',
                'dosage'                => '100 IU/mL 10 mL',
                'dosage_form'           => 'Solution for Injection',
                'category'              => 'non_communicable',
                'unit'                  => 'Vial',
                'requires_prescription' => true,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'Regular Insulin Human (recombinant DNA)',
                'dosage'                => '100 IU/mL 10mL',
                'dosage_form'           => 'Solution for Injection',
                'category'              => 'non_communicable',
                'unit'                  => 'Vial',
                'requires_prescription' => true,
                'is_dispensable'        => true,
            ],
            [
                'medicine_name'         => 'Wooden Crutches',
                'dosage'                => null,
                'dosage_form'           => null,
                'category'              => 'non_communicable',
                'unit'                  => 'Pieces',
                'requires_prescription' => false,
                'is_dispensable'        => false,
            ],
            [
                'medicine_name'         => 'Diphenhydramine Hydrochloride',
                'dosage'                => '50mg/mL 1mL',
                'dosage_form'           => 'Ampule',
                'category'              => 'non_communicable',
                'unit'                  => 'Ampule',
                'requires_prescription' => true,
                'is_dispensable'        => true,
            ],
        ];

        $categories = [
            'dengue_prevention',
            'emerging_reemerging',
            'family_health_nutrition',
            'family_planning',
            'tuberculosis',
            'non_communicable',
        ];

        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                'medicine_name'         => $item['medicine_name'],
                'dosage'                => $item['dosage'] ?? '',
                'dosage_form'           => $item['dosage_form'] ?? '',
                'category'              => $item['category'],
                'base_unit'             => $item['unit'],
                'requires_prescription' => $item['requires_prescription'],
                'is_dispensable'        => $item['is_dispensable'],
                'min_stock'             => 10,
                'max_stock'             => 500,
                'description'           => '',
                'pack_type'             => '',
                'pieces_per_pack'       => 1,
            ];
        }

        DB::transaction(function () use ($rows, $categories) {
            DB::table('medicines')->whereIn('category', $categories)->delete();
            foreach (array_chunk($rows, 50) as $chunk) {
                DB::table('medicines')->insert($chunk);
            }
        });

        if ($this->command) {
            $this->command->info('Inserted ' . count($rows) . ' DOH program items.');
        }
    }
}