<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\MidwifeBarangay;
use App\Models\User;
use App\Support\BarangayDemandScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MidwifeBarangaySchemaUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_15_000000_complete_midwife_barangay_columns.php');
    }

    public function test_skeletal_legacy_rows_are_preserved_without_guessing_assignments_and_dashboard_can_render(): void
    {
        Schema::drop('midwife_barangay');
        Schema::create('midwife_barangay', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
        $legacyId = DB::table('midwife_barangay')->insertGetId([
            'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-02 08:00:00',
        ]);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertDatabaseHas('midwife_barangay', [
            'id' => $legacyId, 'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-02 08:00:00',
            'user_id' => null, 'barangay' => null, 'is_primary' => false,
        ]);
        $unit = HealthUnit::create(['name' => 'Legacy assignment RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $this->assertSame([], BarangayDemandScope::options($unit->id, $midwife->id)->all());
        $this->withSession(['user_id' => $midwife->id])->get(route('dashboard.midwife'))
            ->assertOk()->assertSee('No barangay is assigned');

        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Assigned after upgrade', 'is_primary' => true]);
        $this->assertSame(['Assigned after upgrade'], BarangayDemandScope::options($unit->id, $midwife->id)->all());
        $this->get(route('dashboard.midwife'))->assertOk()->assertViewHas('demandBarangay', 'Assigned after upgrade');
        $this->assertDatabaseCount('midwife_barangay', 2);
    }

    public function test_partial_legacy_assignments_and_existing_primary_values_survive_repeated_upgrade_and_rollback(): void
    {
        Schema::drop('midwife_barangay');
        Schema::create('midwife_barangay', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('barangay', 100);
            $table->timestamps();
        });
        $unit = HealthUnit::create(['name' => 'Partial legacy assignment RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $assignment = DB::table('midwife_barangay')->insertGetId([
            'user_id' => $midwife->id, 'barangay' => 'Preserved assignment',
            'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-02 08:00:00',
        ]);

        $this->migration()->up();
        $this->assertDatabaseHas('midwife_barangay', ['id' => $assignment, 'is_primary' => false]);
        DB::table('midwife_barangay')->where('id', $assignment)->update(['is_primary' => true]);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Alphabetically earlier']);
        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame(['Preserved assignment', 'Alphabetically earlier'],
            BarangayDemandScope::options($unit->id, $midwife->id)->all());
        $this->assertDatabaseHas('midwife_barangay', [
            'id' => $assignment, 'user_id' => $midwife->id, 'barangay' => 'Preserved assignment', 'is_primary' => true,
            'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-02 08:00:00',
        ]);
        $this->assertDatabaseCount('midwife_barangay', 2);
    }
}
