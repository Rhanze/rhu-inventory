<?php

namespace Database\Seeders;

use App\Models\Disease;
use App\Models\Medicine;
use App\Models\DiseaseMedicineMap;
use Illuminate\Database\Seeder;

class DiseaseMedicineMapSeeder extends Seeder
{
    /**
     * Maps common PH diseases to the medicines used to treat them.
     * Matches by partial name — safe to run even if some don't exist yet.
     */
    public function run(): void
    {
        $map = [
            // disease name substring => [ [medicine name substring, priority, doses/case] ]
            'dengue'      => [
                ['paracetamol', 1, 6],
                ['ors',         1, 3],
            ],
            'typhoid'     => [
                ['ciprofloxacin', 1, 14],
                ['azithromycin',  2, 7],
            ],
            'measles'     => [
                ['vitamin a', 1, 2],
            ],
            'pneumonia'   => [
                ['amoxicillin', 1, 21],
            ],
            'diarrhea'    => [
                ['ors',   1, 6],
                ['zinc',  1, 10],
            ],
            'diarrhoea'   => [
                ['ors',   1, 6],
                ['zinc',  1, 10],
            ],
            'tuberculosis'=> [
                ['rifampicin',   1, 180],
                ['isoniazid',    1, 180],
            ],
            'tb'          => [
                ['rifampicin',   1, 180],
                ['isoniazid',    1, 180],
            ],
            'hypertension'=> [
                ['amlodipine',  1, 30],
                ['losartan',    2, 30],
            ],
            'diabetes'    => [
                ['metformin',   1, 30],
            ],
            'malaria'     => [
                ['chloroquine',    1, 3],
                ['primaquine',     2, 14],
            ],
        ];

        $inserted = 0;

        foreach ($map as $diseaseNeedle => $medicineRows) {
            $diseases = Disease::where('name', 'like', "%{$diseaseNeedle}%")->get();
            if ($diseases->isEmpty()) continue;

            foreach ($diseases as $disease) {
                foreach ($medicineRows as [$medicineNeedle, $priority, $doses]) {
                    $medicine = Medicine::where('medicine_name', 'like', "%{$medicineNeedle}%")->first();
                    if (!$medicine) continue;

                    $exists = DiseaseMedicineMap::where('disease_id', $disease->id)
                        ->where('medicine_id', $medicine->id)
                        ->exists();
                    if ($exists) continue;

                    DiseaseMedicineMap::create([
                        'disease_id'     => $disease->id,
                        'medicine_id'    => $medicine->id,
                        'priority'       => $priority,
                        'doses_per_case' => $doses,
                    ]);
                    $inserted++;
                }
            }
        }

        $this->command->info("Inserted {$inserted} disease→medicine mappings.");
    }
}