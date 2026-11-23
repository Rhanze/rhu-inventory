<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $role = 'staff'): array
    {
        $unit = HealthUnit::create(['name' => 'Notification navigation RHU']);
        $user = User::factory()->create(['role' => $role, 'health_unit_id' => $unit->id]);
        $this->withSession(['user_id' => $user->id]);

        return [$unit, $user];
    }

    private function notification(HealthUnit $unit, array $extra = []): Notification
    {
        return Notification::create(array_replace(['health_unit_id' => $unit->id, 'type' => 'audit',
            'title' => 'Navigation notice', 'message' => 'Notice details', 'severity' => 'info',
            'is_read' => true, 'created_at' => now()], $extra));
    }

    public function test_empty_notification_destinations_are_not_clickable_and_recent_api_returns_null(): void
    {
        [$unit] = $this->fixture();
        $unavailable = [];
        foreach ([null, '', '   ', '#', '#missing-target', 'javascript:void(0)'] as $link) {
            $unavailable[] = $this->notification($unit, ['link' => $link])->id;
        }
        $working = $this->notification($unit, ['link' => route('settings')]);
        $this->get(route('notifications.index'))->assertOk()
            ->assertDontSee('href="#"', false)->assertDontSee('href=""', false)
            ->assertDontSee('href="#missing-target"', false)->assertDontSee('javascript:void(0)', false)
            ->assertSee('href="'.route('settings').'"', false);
        $urls = collect($this->getJson(route('notifications.recent'))->assertOk()->json('notifications'))->pluck('url', 'id');
        foreach ($unavailable as $id) {
            $this->assertNull($urls[$id]);
        }
        $this->assertSame(route('settings'), $urls[$working->id]);
    }

    public function test_inventory_alerts_with_placeholder_links_open_the_actual_inventory_record(): void
    {
        [$unit] = $this->fixture();
        $medicine = Medicine::create(['medicine_name' => 'Navigation inventory medicine']);
        $batch = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'batch_no' => 'NAVIGATION-ALERT', 'available_stock' => 3]);
        $notice = $this->notification($unit, ['inventory_id' => $batch->id, 'link' => '#']);
        $url = route('inventory.index', ['highlight' => $batch->id]).'#inventory-'.$batch->id;
        $this->assertSame($url, $notice->destinationUrl('staff'));
        $this->get(route('notifications.index'))->assertOk()->assertSee('href="'.$url.'"', false);
        $this->getJson(route('notifications.recent'))->assertOk()->assertJsonPath('notifications.0.url', $url);
        $this->get($url)->assertOk()->assertSee('NAVIGATION-ALERT');
    }

    public function test_supply_decision_links_take_rhu_staff_and_midwives_to_the_correct_nonempty_lists(): void
    {
        [$unit, $staff] = $this->fixture();
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Navigation supply medicine']);
        $allocation = Allocation::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'allocated_by' => $midwife->id, 'recipient_user_id' => $midwife->id, 'date_allocated' => today(),
            'quantity_allocated' => 2, 'destination' => 'Navigation clinic', 'status' => 'approved']);
        app(NotificationService::class)->notifySupplyRequestDecision($allocation);
        $shared = Notification::where('allocation_id', $allocation->id)->whereNull('user_id')->firstOrFail();
        $personal = Notification::where('allocation_id', $allocation->id)->where('user_id', $midwife->id)->firstOrFail();
        $this->assertSame(route('allocation.index'), $shared->link);
        $this->assertSame(route('supply_requests.index'), $personal->link);

        // Existing shared notices may still contain the old requester-only destination.
        $shared->update(['link' => route('supply_requests.index')]);
        $this->getJson(route('notifications.recent'))->assertOk()
            ->assertJsonPath('notifications.0.url', route('allocation.index'));
        $this->get(route('allocation.index'))->assertOk()->assertSee('Navigation supply medicine');
        $this->withSession(['user_id' => $midwife->id]);
        $this->getJson(route('notifications.recent'))->assertOk()
            ->assertJsonPath('notifications.0.url', route('supply_requests.index'));
        $this->get(route('supply_requests.index'))->assertOk()->assertSee('Navigation supply medicine');
    }

    public function test_allocation_receipts_keep_their_action_without_an_open_link_back_to_the_same_notice_page(): void
    {
        [$unit, $midwife] = $this->fixture('midwife');
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Receipt navigation medicine']);
        $batch = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'available_stock' => 0, 'allocated_stock' => 1]);
        $allocation = Allocation::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'inventory_id' => $batch->id, 'allocated_by' => $staff->id, 'recipient_user_id' => $midwife->id, 'date_allocated' => today(),
            'quantity_allocated' => 1, 'destination' => 'Clinic stock', 'status' => 'awaiting_receipt']);
        $this->notification($unit, ['user_id' => $midwife->id, 'allocation_id' => $allocation->id,
            'type' => 'allocation_to_user', 'link' => route('notifications.index')]);
        $this->get(route('notifications.index'))->assertOk()
            ->assertSee('action="'.route('allocation.mark_received', $allocation->id).'"', false)
            ->assertDontSee('Open →');
        $this->getJson(route('notifications.recent'))->assertOk()->assertJsonPath('notifications.0.url', null);
    }
}
