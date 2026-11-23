<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoNotificationScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_pho_lists_and_badges_exclude_every_supply_request_type_and_keep_stock_oversight(): void
    {
        $unit = HealthUnit::create(['name' => 'Notification scope RHU']);
        $other = HealthUnit::create(['name' => 'Other notification RHU']);
        $pho = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $service = app(NotificationService::class);
        $hidden = [];
        foreach (['supply_request_pending', 'supply_request_approved', 'supply_request_rejected', 'supply_request_follow_up'] as $type) {
            $hidden[] = $service->notifyRhu($unit->id, $type, $type, 'RHU supply request');
        }
        $hidden[] = $service->notifyRhu(null, 'supply_request_pending', 'Legacy PHO request', 'Legacy shared row');
        $hidden[] = $service->notifyUser($pho->id, null, 'supply_request_approved', 'Legacy personal request', 'Legacy personal row');
        $hiddenArchived = $service->notifyRhu($unit->id, 'supply_request_rejected', 'Archived supply request', 'Archived request');
        $hiddenArchived->update(['archived_at' => now()]);
        $visible = [
            $service->notifyRhu($unit->id, 'low_stock', 'Stock at first RHU', 'Stock oversight'),
            $service->notifyRhu($other->id, 'near_expiry', 'Stock at second RHU', 'Stock oversight'),
            $service->notifyRhu(null, 'expired', 'Provincial stock', 'Stock oversight'),
        ];
        $visibleArchived = $service->notifyRhu($unit->id, 'near_expiry', 'Archived stock alert', 'Stock oversight');
        $visibleArchived->update(['archived_at' => now()]);
        $this->withSession(['user_id' => $pho->id]);

        $expected = collect($visible)->pluck('id')->sort()->values()->all();
        $this->get(route('notifications.index'))->assertOk()
            ->assertViewHas('notifications', fn ($rows) => $rows->pluck('id')->sort()->values()->all() === $expected)
            ->assertViewHas('unreadCount', 3)->assertViewHas('archivedCount', 1);
        $this->get(route('notifications.index', ['view' => 'archived']))->assertOk()
            ->assertViewHas('notifications', fn ($rows) => $rows->pluck('id')->all() === [$visibleArchived->id]);
        $this->get(route('notifications.index', ['view' => 'all']))->assertOk()
            ->assertViewHas('notifications', fn ($rows) => $rows->total() === 4);
        $this->getJson(route('notifications.unread'))->assertOk()->assertJsonPath('count', 3);
        $recent = $this->getJson(route('notifications.recent'))->assertOk()->assertJsonPath('unread_count', 3);
        $this->assertSame($expected, collect($recent->json('notifications'))->pluck('id')->sort()->values()->all());

        foreach ($hidden as $notice) {
            $this->postJson(route('notifications.mark-read', $notice->id))->assertOk()->assertJsonPath('success', false);
            $this->postJson(route('notifications.archive', $notice->id))->assertOk()->assertJsonPath('success', false);
            $this->deleteJson(route('notifications.destroy', $notice->id))->assertOk()->assertJsonPath('success', false);
            $this->assertFalse($notice->fresh()->is_read);
            $this->assertNull($notice->fresh()->archived_at);
        }
        $this->postJson(route('notifications.unarchive', $hiddenArchived->id))->assertOk()->assertJsonPath('success', false);
        $this->assertNotNull($hiddenArchived->fresh()->archived_at);
        $this->postJson(route('notifications.mark-all-read'))->assertOk()->assertJsonPath('count', 3);
        $this->assertSame(0, Notification::whereIn('id', collect($hidden)->pluck('id'))->where('is_read', true)->count());
        $this->deleteJson(route('notifications.destroy-archived'))->assertOk()->assertJsonPath('count', 1);
        $this->assertDatabaseHas('notifications', ['id' => $hiddenArchived->id]);
        $this->deleteJson(route('notifications.destroy-all'))->assertOk()->assertJsonPath('count', 3);
        $this->assertDatabaseCount('notifications', count($hidden) + count($visible) + 2);
        $this->assertDatabaseCount('notification_viewer_states', 4);
        $this->get(route('notifications.index', ['view' => 'all']))->assertOk()
            ->assertViewHas('notifications', fn ($rows) => $rows->total() === 0);
    }

    public function test_rhu_and_midwife_supply_request_visibility_and_actions_keep_their_own_scope(): void
    {
        $unit = HealthUnit::create(['name' => 'Local supply RHU']);
        $other = HealthUnit::create(['name' => 'Other supply RHU']);
        $admin = User::factory()->create(['role' => 'admin', 'health_unit_id' => $unit->id]);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $otherMidwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $service = app(NotificationService::class);
        $shared = $service->notifyRhu($unit->id, 'supply_request_pending', 'Local shared request', 'Local request');
        $foreign = $service->notifyRhu($other->id, 'supply_request_pending', 'Foreign shared request', 'Foreign request');
        $personal = $service->notifyUser($midwife->id, $unit->id, 'supply_request_approved', 'Own decision', 'Own request approved');
        $private = $service->notifyUser($otherMidwife->id, $unit->id, 'supply_request_approved', 'Other decision', 'Other request approved');

        $this->withSession(['user_id' => $admin->id])->get(route('notifications.index'))->assertOk()
            ->assertViewHas('notifications', fn ($rows) => $rows->pluck('id')->all() === [$shared->id]);
        $this->getJson(route('notifications.unread'))->assertJsonPath('count', 1);
        $this->postJson(route('notifications.mark-read', $shared->id))->assertJsonPath('success', true);
        $this->postJson(route('notifications.mark-read', $foreign->id))->assertJsonPath('success', false);
        $this->postJson(route('notifications.mark-read', $personal->id))->assertJsonPath('success', false);

        $this->withSession(['user_id' => $midwife->id])->get(route('notifications.index'))->assertOk()
            ->assertViewHas('notifications', fn ($rows) => $rows->pluck('id')->all() === [$personal->id]);
        $this->getJson(route('notifications.unread'))->assertJsonPath('count', 1);
        $this->postJson(route('notifications.mark-read', $personal->id))->assertJsonPath('success', true);
        $this->postJson(route('notifications.archive', $private->id))->assertJsonPath('success', false);
        $this->assertFalse($private->fresh()->is_read);
        $this->assertNull($private->fresh()->archived_at);
    }

    public function test_shared_request_decisions_use_neutral_copy_and_personal_decisions_address_the_requester(): void
    {
        $unit = HealthUnit::create(['name' => 'Decision RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Decision medicine']);
        $service = app(NotificationService::class);

        foreach (['approved', 'rejected'] as $status) {
            $allocation = Allocation::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
                'allocated_by' => $midwife->id, 'recipient_user_id' => $midwife->id,
                'quantity_allocated' => 5, 'date_allocated' => today(), 'status' => $status]);
            $this->assertSame(2, $service->notifySupplyRequestDecision($allocation));
            $shared = Notification::where('allocation_id', $allocation->id)->whereNull('user_id')->sole();
            $personal = Notification::where('allocation_id', $allocation->id)->where('user_id', $midwife->id)->sole();
            $this->assertStringStartsWith('The supply request', $shared->message);
            $this->assertStringContainsString('Decision RHU', $shared->message);
            $this->assertStringNotContainsString('Your request', $shared->message);
            $this->assertStringStartsWith('Your request', $personal->message);
            $this->assertStringContainsString($status, $personal->message);
            $this->assertSame(route('allocation.index'), $shared->link);
            $this->assertSame(route('supply_requests.index'), $personal->link);
            $this->assertSame(0, $service->notifySupplyRequestDecision($allocation));
        }
    }
}
