<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Kept for compatibility with old setup commands; disease cases are no longer seeded. */
class SampleDiseaseReportsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn('Disease trends now use medicine consumption. No disease case reports were created or changed.');
        $this->command?->info('For local consumption examples, run SampleStockHistorySeeder and ensure the medicines have disease associations.');
    }
}
