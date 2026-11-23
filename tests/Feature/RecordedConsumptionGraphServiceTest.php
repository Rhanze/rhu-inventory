<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\DiseaseMedicineMap;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\User;
use App\Services\DiseaseConsumptionTrendService;
use App\Services\RecordedConsumptionGraphService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordedConsumptionGraphServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-17 12:00:00', 'Asia/Manila'));
    }

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Recorded graph RHU']);
        $user = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Amlodipine', 'dosage' => '5 mg', 'dosage_form' => 'Tablet', 'unit' => 'Tablet']);
        $disease = Disease::create(['code' => 'RECORDED-GRAPH', 'name' => 'Hypertension']);
        DiseaseMedicineMap::create(['disease_id' => $disease->id, 'medicine_id' => $medicine->id]);

        return [$unit, $user, $medicine, $disease];
    }

    private function record(HealthUnit $unit, User $user, Medicine $medicine, string $date, int $quantity): DispensingRecord
    {
        return DispensingRecord::create(['health_unit_id' => $unit->id, 'dispensed_by' => $user->id,
            'medicine_id' => $medicine->id, 'date_dispensed' => $date, 'quantity_dispensed' => $quantity,
            'patient_name' => 'PRIVATE-GRAPH-PATIENT']);
    }

    private function build(HealthUnit $unit, User $user, ?string $from = null, ?string $to = null, ?int $diseaseId = null): array
    {
        $comparison = app(DiseaseConsumptionTrendService::class)->analyze($unit->id, null, 'weekly', $user->id, $from, $to, $diseaseId);

        return app(RecordedConsumptionGraphService::class)->build($unit->id, null, $user->id, $comparison, $from, $to, $diseaseId);
    }

    public function test_same_date_records_are_distinct_and_all_saved_medicines_are_graphable_without_false_associations(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        $first = $this->record($unit, $user, $medicine, '2026-10-10', 9);
        $second = $this->record($unit, $user, $medicine, '2026-10-10', 14);
        $unlinked = Medicine::create(['medicine_name' => 'Unlinked medicine', 'unit' => 'mL']);
        $this->record($unit, $user, $unlinked, '2026-09-23', 7);
        $colleague = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $this->record($unit, $colleague, $medicine, '2026-10-10', 500);
        $otherUnit = HealthUnit::create(['name' => 'Other recorded graph RHU']);
        $this->record($otherUnit, $user, $medicine, '2026-10-10', 1000);

        $result = $this->build($unit, $user);
        $analysis = $result['analysis'];
        $this->assertTrue($result['uses_saved_history']);
        $this->assertSame(['from' => '2026-09-23', 'to' => '2026-10-10'], $analysis['window']);
        $this->assertSame('transactions', $analysis['default_mode']);
        $this->assertCount(2, $analysis['medicine_signals']);
        $mapped = collect($analysis['medicine_signals'])->firstWhere('id', $medicine->id);
        $this->assertSame([['date' => '2026-10-10', 'quantity' => 9, 'record_id' => $first->id],
            ['date' => '2026-10-10', 'quantity' => 14, 'record_id' => $second->id]], $mapped['transactions_data']);
        $this->assertSame(23, $mapped['total_quantity']);
        $this->assertSame(23, array_sum(array_column($mapped['history'], 'quantity')));
        $this->assertSame(['Hypertension'], $mapped['diseases']);
        $this->assertSame([], collect($analysis['medicine_signals'])->firstWhere('id', $unlinked->id)['diseases']);
        $this->assertStringNotContainsString('PRIVATE-GRAPH-PATIENT', json_encode($analysis));
        $this->assertSame(3, $analysis['total_transactions']);
    }

    public function test_explicit_empty_dates_stay_empty_and_disease_filter_excludes_unlinked_records(): void
    {
        [$unit, $user, $medicine, $disease] = $this->fixture();
        $this->record($unit, $user, $medicine, '2026-10-10', 23);
        $unlinked = Medicine::create(['medicine_name' => 'Unlinked record', 'unit' => 'Tablet']);
        $this->record($unit, $user, $unlinked, '2026-10-10', 99);
        $empty = $this->build($unit, $user, '2026-10-11', '2026-10-11');
        $this->assertFalse($empty['uses_saved_history']);
        $this->assertSame([], $empty['analysis']['medicine_signals']);
        $selected = $this->build($unit, $user, '2026-10-10', '2026-10-10', $disease->id);
        $this->assertCount(1, $selected['analysis']['medicine_signals']);
        $this->assertSame($medicine->id, $selected['analysis']['medicine_signals'][0]['id']);
        $this->assertSame(23, $selected['analysis']['medicine_signals'][0]['total_quantity']);
    }

    public function test_current_grouped_quantities_and_comparison_values_remain_current(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        foreach (['2026-10-13', '2026-10-20', '2026-10-27', '2026-11-03'] as $date) {
            $this->record($unit, $user, $medicine, $date, 10);
        }
        $this->record($unit, $user, $medicine, '2026-11-10', 20);
        $unlinked = Medicine::create(['medicine_name' => 'Unlinked current', 'unit' => 'Tablet']);
        $this->record($unit, $user, $unlinked, '2026-10-20', 100);
        $this->record($unit, $user, $unlinked, '2026-11-11', 3);
        $result = $this->build($unit, $user);
        $this->assertFalse($result['uses_saved_history']);
        $this->assertFalse($result['analysis']['historical']);
        $mapped = collect($result['analysis']['medicine_signals'])->firstWhere('id', $medicine->id);
        $this->assertSame([10, 10, 10, 10, 20], array_column($mapped['history'], 'quantity'));
        $this->assertEquals(10, $mapped['baseline_average']);
        $this->assertEquals(100, $mapped['change_percent']);
        $this->assertSame(3, collect($result['analysis']['medicine_signals'])->firstWhere('id', $unlinked->id)['current_quantity']);
    }
}
