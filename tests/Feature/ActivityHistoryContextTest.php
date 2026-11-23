<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityHistoryContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_history_and_export_share_dates_and_facility_scope(): void
    {
        $unit = HealthUnit::create(['name' => 'History context RHU', 'facility_type' => 'Rural Health Unit']);
        $other = HealthUnit::create(['name' => 'Other history RHU', 'facility_type' => 'Rural Health Unit']);
        $admin = User::factory()->create(['role' => 'admin', 'health_unit_id' => $unit->id]);
        foreach ([$unit->id => 'Included receipt', $other->id => 'Private other receipt'] as $unitId => $description) {
            ActivityLog::create(['health_unit_id' => $unitId, 'user_id' => $admin->id,
                'module' => 'Inventory', 'action' => 'Create', 'description' => $description,
                'created_at' => '2024-10-02 09:00:00']);
        }
        ActivityLog::create(['health_unit_id' => $unit->id, 'user_id' => $admin->id,
            'module' => 'Inventory', 'action' => 'Create', 'description' => 'Outside selected year',
            'created_at' => '2025-10-02 09:00:00']);
        $this->withSession(['user_id' => $admin->id]);

        $this->get(route('activity_logs.index', ['year' => 2024]))->assertOk()
            ->assertSee('Included receipt')->assertSee('History context RHU')
            ->assertDontSee('Private other receipt')->assertDontSee('Outside selected year');
        $csv = $this->get(route('activity_logs.export', ['year' => 2024]))->assertOk()->streamedContent();
        $this->assertStringContainsString('Included receipt', $csv);
        $this->assertStringContainsString('History context RHU', $csv);
        $this->assertStringNotContainsString('Private other receipt', $csv);
        $this->assertStringNotContainsString('Outside selected year', $csv);
    }

    public function test_activity_history_preserves_staff_ownership_and_validates_filter_types(): void
    {
        $unit = HealthUnit::create(['name' => 'Staff history RHU']);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $other = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        foreach ([$staff->id => 'Own action', $other->id => 'Other staff action'] as $userId => $description) {
            ActivityLog::create(['health_unit_id' => $unit->id, 'user_id' => $userId,
                'module' => 'Inventory', 'action' => 'Create', 'description' => $description, 'created_at' => now()]);
        }
        $this->withSession(['user_id' => $staff->id])->get(route('activity_logs.index'))->assertOk()
            ->assertSee('Own action')->assertDontSee('Other staff action');
        $this->getJson(route('activity_logs.index', ['search' => ['invalid']]))
            ->assertUnprocessable()->assertJsonValidationErrors('search');
        $this->getJson(route('activity_logs.export', ['date_from' => '2025-11-02', 'date_to' => '2025-11-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('date_to');
    }

    public function test_stock_receipt_records_its_reason_and_keeps_addition_direction(): void
    {
        $unit = HealthUnit::create(['name' => 'Receipt reason RHU']);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Receipt reason medicine']);
        $this->withSession(['user_id' => $staff->id]);

        $this->post(route('stock_logs.store'), ['medicine_id' => $medicine->id,
            'quantity_added' => 12, 'date_added' => today()->toDateString(),
            'batch_no' => 'REASON', 'remarks' => 'Prepare for the scheduled outreach'])
            ->assertRedirect()->assertSessionHas('success');
        $log = StockLog::where('medicine_id', $medicine->id)->firstOrFail();
        $this->assertSame('addition', $log->movement_direction);
        $this->assertSame('Added — Prepare for the scheduled outreach', $log->remarks);
        $this->assertSame($staff->id, $log->added_by);
        $this->assertDatabaseHas('inventory', ['medicine_id' => $medicine->id,
            'health_unit_id' => $unit->id, 'available_stock' => 12]);
        $this->assertStringContainsString('Prepare for the scheduled outreach', ActivityLog::firstOrFail()->description);

        $this->postJson(route('stock_logs.store'), ['medicine_id' => $medicine->id,
            'quantity_added' => 1, 'date_added' => today()->toDateString(), 'remarks' => ['invalid']])
            ->assertUnprocessable()->assertJsonValidationErrors('remarks');
        $this->assertDatabaseCount('stock_logs', 1);
    }
}
