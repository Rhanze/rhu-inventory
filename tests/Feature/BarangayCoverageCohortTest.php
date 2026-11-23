<?php

namespace Tests\Feature;

use App\Http\Controllers\VaccinationForecastController;
use App\Models\HealthUnit;
use App\Models\ImmunizationRecord;
use App\Models\PublicUser;
use App\Models\User;
use App\Services\BarangayImmunizationCoverageService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarangayCoverageCohortTest extends TestCase
{
    use RefreshDatabase;

    public function test_coverage_uses_identical_under_five_facility_and_assignment_cohorts(): void
    {
        $this->travelTo(Carbon::parse('2026-11-17 12:00:00', 'Asia/Manila'));
        $unit = HealthUnit::create(['name' => 'Coverage RHU']);
        $other = HealthUnit::create(['name' => 'Other coverage RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $colleague = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $this->withSession(['user_id' => $midwife->id, 'role' => 'midwife', 'health_unit_id' => $unit->id]);
        $child = function (string $birthDate, array $extra = []) use ($unit, $midwife) {
            return PublicUser::create(array_replace(['health_unit_id' => $unit->id, 'assigned_midwife_id' => $midwife->id,
                'first_name' => 'Coverage', 'last_name' => 'Fixture', 'birth_date' => $birthDate,
                'address' => 'Fixture', 'barangay' => 'Area A'], $extra));
        };
        $dose = function (PublicUser $child, array $extra = []) use ($unit) {
            return ImmunizationRecord::create(array_replace(['public_user_id' => $child->id, 'health_unit_id' => $unit->id,
                'antigen' => 'bcg', 'dose_number' => 1, 'date_given' => today()], $extra));
        };

        $eligible = $child('2021-11-18');
        $dose($eligible);
        $dose($eligible, ['antigen' => 'hepb']); // Several doses still count as one child.
        $child('2026-11-17'); // Newborn included in the denominator.
        $dose($child('2021-11-17')); // Exactly five excluded.
        $dose($child('2020-11-17'));
        $dose($child('2026-11-18')); // Future birth excluded.
        $dose($child('2023-01-01', ['assigned_midwife_id' => $colleague->id]));
        $dose($child('2023-01-01', ['health_unit_id' => $other->id]));
        $dose($child('2023-01-01'), ['date_given' => today()->addDay()]);
        $dose($child('2023-01-01'), ['health_unit_id' => $other->id]);
        $child('2023-01-01', ['barangay' => 'Area B']); // Zero-dose areas remain visible.

        $coverage = app(BarangayImmunizationCoverageService::class)->forHealthUnit($unit->id);
        $this->assertSame(['Area A', 'Area B'], $coverage['labels']);
        $this->assertSame([4, 1], $coverage['totals']);
        $this->assertSame([1, 0], $coverage['immunized']);
        $this->assertSame([25, 0], $coverage['percent']);
        $response = app(VaccinationForecastController::class)->coverageByBarangay()->getData(true);
        $this->assertSame($coverage['immunized'], $response['data']);
        $this->assertSame($coverage['percent'], $response['percent']);
        $this->get(route('dashboard.midwife'))->assertOk()->assertViewHas('barangayCoverage', $coverage);

        session(['role' => 'admin']);
        $adminCoverage = app(BarangayImmunizationCoverageService::class)->forHealthUnit($unit->id);
        $this->assertSame([5, 1], $adminCoverage['totals']);
        $this->assertSame([2, 0], $adminCoverage['immunized']);
        $this->assertSame([40, 0], $adminCoverage['percent']);
    }

    public function test_empty_coverage_returns_aligned_empty_arrays(): void
    {
        $unit = HealthUnit::create(['name' => 'Empty coverage RHU']);
        $this->assertSame(['labels' => [], 'immunized' => [], 'totals' => [], 'percent' => []],
            app(BarangayImmunizationCoverageService::class)->forHealthUnit($unit->id));
    }
}
