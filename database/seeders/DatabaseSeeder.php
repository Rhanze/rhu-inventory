<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Only run the sample user in local/dev environments
        if (app()->environment('local')) {
            User::factory()->create([
                'name'  => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        // ─── Philippine NIP schedule (reference data, safe for all envs) ───
        $this->call(ImmunizationScheduleSeeder::class);

        // ─── DoH national health programs (Nutrition Month, TB Day, etc.) ───
        $this->call(DoHProgramSeeder::class);

        // ─── Disease → Medicine mapping (for forecasting recommendations) ───
        $this->call(DiseaseMedicineMapSeeder::class);
    }
}