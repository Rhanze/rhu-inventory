<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\DiseaseReport;
use App\Models\HealthUnit;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumptionOnlyDiseaseAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_roles_are_unable_to_create_edit_delete_or_flag_manual_disease_cases(): void
    {
        $unit = HealthUnit::create(['name' => 'Consumption analysis RHU']);
        $disease = Disease::create(['name' => 'Legacy condition', 'code' => 'LEGACY-BOUNDARY']);
        $legacy = DiseaseReport::create(['health_unit_id' => $unit->id, 'disease_id' => $disease->id,
            'report_date' => '2026-01-05', 'period_type' => 'weekly', 'period_start' => '2026-01-05',
            'period_end' => '2026-01-11', 'case_count' => 9, 'is_complete' => true, 'is_outbreak' => true]);
        $before = $legacy->fresh()->getAttributes();

        foreach (['admin', 'staff', 'midwife'] as $role) {
            $user = User::factory()->create(['role' => $role, 'health_unit_id' => $unit->id]);
            $this->withSession(['user_id' => $user->id]);
            $this->post('/disease-reports', ['disease_id' => $disease->id, 'case_count' => 100,
                'report_date' => '2026-01-12', 'period_type' => 'weekly', 'is_complete' => true])->assertStatus(405);
            $this->put('/disease-reports/'.$legacy->id, ['case_count' => 100])->assertNotFound();
            $this->delete('/disease-reports/'.$legacy->id)->assertNotFound();
            $this->post('/disease-reports/close-outbreak/'.$disease->id, ['remarks' => 'Manual flag'])->assertNotFound();
        }

        $this->assertDatabaseCount('disease_reports', 1);
        $this->assertSame($before, $legacy->fresh()->getAttributes());
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('activity_logs', 0);
        foreach (['store', 'update', 'destroy', 'close_outbreak'] as $action) {
            $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('disease_reports.'.$action));
        }
        foreach (['index', 'chart_data', 'export'] as $action) {
            $this->assertSame(['GET', 'HEAD'], app('router')->getRoutes()->getByName('disease_reports.'.$action)->methods());
        }
    }

    public function test_legacy_manual_case_notifications_are_excluded_from_lists_and_badges(): void
    {
        $unit = HealthUnit::create(['name' => 'Consumption notification RHU']);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $base = ['health_unit_id' => $unit->id, 'is_read' => false, 'created_at' => now(),
            'severity' => 'info', 'message' => 'Navigation test', 'link' => route('disease_reports.index')];
        $legacyShared = Notification::create($base + ['type' => 'disease_outbreak', 'title' => 'Legacy manual case alert']);
        $legacyPersonal = Notification::create($base + ['type' => 'disease_outbreak',
            'title' => 'Legacy personal case alert', 'user_id' => $midwife->id]);
        $sharedSupply = Notification::create($base + ['type' => 'low_stock', 'title' => 'Supply alert']);
        $personalSupply = Notification::create($base + ['type' => 'supply_request_approved',
            'title' => 'Own supply alert', 'user_id' => $midwife->id]);

        foreach ([[$staff, $sharedSupply], [$midwife, $personalSupply]] as [$user, $visible]) {
            $this->withSession(['user_id' => $user->id]);
            $this->get(route('notifications.index'))->assertOk()->assertSee($visible->title)
                ->assertDontSee($legacyShared->title)->assertDontSee($legacyPersonal->title);
            $this->getJson(route('notifications.unread'))->assertOk()->assertJsonPath('count', 1);
            $this->getJson(route('notifications.recent'))->assertOk()
                ->assertJsonCount(1, 'notifications')->assertJsonPath('notifications.0.id', $visible->id);
        }

        $this->assertNull($legacyShared->destinationUrl('staff'));
        $this->assertNull($legacyPersonal->destinationUrl('midwife'));
        $this->assertDatabaseCount('notifications', 4);
        $this->assertFalse(method_exists(NotificationService::class, 'notifyDiseaseOutbreak'));
    }
}
