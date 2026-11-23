<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\PublicUser;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationAdministrationAuditTest extends TestCase
{
    use RefreshDatabase;

    private function signInPho(): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $this->withSession(['user_id' => $admin->id]);

        return $admin;
    }

    public function test_account_form_retains_safe_fields_on_facility_rejection(): void
    {
        $local = HealthUnit::create(['name' => 'Account facility']);
        $other = HealthUnit::create(['name' => 'Other account facility']);
        $admin = User::factory()->create(['role' => 'admin', 'health_unit_id' => $local->id]);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $local->id]);
        $this->withSession(['user_id' => $admin->id]);
        $fields = ['name' => 'Account form fixture', 'role' => 'staff', 'health_unit_id' => $other->id,
            'password' => 'FixturePass123!', 'password_confirmation' => 'FixturePass123!'];

        $this->post(route('users.store'), $fields + ['username' => 'fixture-account'])
            ->assertSessionHasErrors('health_unit_id')->assertSessionHasInput('name', $fields['name'])
            ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        $this->put(route('users.update', $staff->id), $fields + ['username' => $staff->username])
            ->assertSessionHasErrors('health_unit_id')->assertSessionHasInput('name', $fields['name'])
            ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
    }

    public function test_stock_alerts_combine_usable_batches_and_include_stockouts(): void
    {
        $unit = HealthUnit::create(['name' => 'Stock notification RHU']);
        $medicine = Medicine::create(['medicine_name' => 'Well stocked medicine']);
        foreach ([2, 500] as $stock) {
            Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
                'available_stock' => $stock, 'expiration_date' => today()->addYear()]);
        }
        $stockout = Medicine::create(['medicine_name' => 'Stockout medicine']);
        Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $stockout->id,
            'available_stock' => 0, 'expiration_date' => today()->addYear()]);
        $expired = Medicine::create(['medicine_name' => 'Expired medicine']);
        Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $expired->id,
            'available_stock' => 200, 'expiration_date' => today()->subDay()]);

        $service = app(NotificationService::class);
        $service->scanAndGenerate();
        $this->assertSame(2, Notification::where('type', 'low_stock')->count());
        $this->assertDatabaseHas('notifications', ['type' => 'low_stock', 'severity' => 'danger',
            'message' => 'Stockout medicine has 0 usable units remaining.']);
        $this->assertDatabaseHas('notifications', ['type' => 'low_stock', 'severity' => 'danger',
            'message' => 'Expired medicine has 0 usable units remaining.']);
        $this->assertSame(0, $service->scanAndGenerate());
    }

    public function test_distinct_supply_decisions_each_notify_the_rhu_and_requester_once(): void
    {
        $unit = HealthUnit::create(['name' => 'Notification RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Notification medicine']);
        $service = app(NotificationService::class);
        foreach ([5, 9] as $quantity) {
            $allocation = Allocation::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
                'quantity_allocated' => $quantity, 'allocated_by' => $midwife->id, 'date_allocated' => now(), 'status' => 'approved']);
            $this->assertSame(2, $service->notifySupplyRequestDecision($allocation));
            $this->assertSame(0, $service->notifySupplyRequestDecision($allocation));
            $this->assertSame(2, Notification::where('allocation_id', $allocation->id)->count());
        }
        $this->assertSame(4, Notification::count());
    }

    public function test_notification_pages_reach_older_rows_and_preserve_the_selected_archive_filter(): void
    {
        $unit = HealthUnit::create(['name' => 'Paged RHU']);
        $actor = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        for ($index = 1; $index <= 51; $index++) {
            Notification::create(['health_unit_id' => $unit->id, 'type' => 'audit', 'title' => 'Archived '.$index,
                'message' => 'Page fixture', 'severity' => 'info', 'is_read' => true,
                'created_at' => now(), 'archived_at' => now()]);
        }
        $this->withSession(['user_id' => $actor->id])->get(route('notifications.index', ['view' => 'archived']))
            ->assertOk()->assertViewHas('notifications', fn ($rows) => $rows->total() === 51
                && $rows->count() === 50 && str_contains($rows->nextPageUrl(), 'view=archived'))
            ->assertSee('page=2', false);
        $this->get(route('notifications.index', ['view' => 'archived', 'page' => 2]))->assertOk()
            ->assertViewHas('notifications', fn ($rows) => $rows->count() === 1 && $rows->first()->title === 'Archived 1');
        $this->getJson(route('notifications.index', ['view' => ['archived']]))->assertUnprocessable();
    }

    public function test_health_unit_scope_cannot_bypass_an_unread_filter_and_personal_rows_stay_private(): void
    {
        $unit = HealthUnit::create(['name' => 'Scoped RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $other = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $service = app(NotificationService::class);
        $global = $service->notifyRhu(null, 'audit', 'Read global', 'Global fixture');
        $global->update(['is_read' => true]);
        $private = $service->notifyUser($other->id, $unit->id, 'audit', 'Private', 'Private fixture');
        $this->assertSame(1, Notification::unread()->forHealthUnit($unit->id)->count());
        $this->withSession(['user_id' => $midwife->id])->postJson(route('notifications.mark-read', $private->id))
            ->assertOk()->assertJsonPath('success', false);
        $this->assertFalse($private->fresh()->is_read);
    }

    public function test_last_administrator_cannot_be_moved_even_if_the_destination_has_an_administrator(): void
    {
        $this->signInPho();
        $source = HealthUnit::create(['name' => 'Source RHU']);
        $destination = HealthUnit::create(['name' => 'Destination RHU']);
        $target = User::factory()->create(['role' => 'admin', 'health_unit_id' => $source->id]);
        User::factory()->create(['role' => 'admin', 'health_unit_id' => $destination->id]);
        $this->put(route('users.update', $target->id), ['name' => $target->name, 'username' => $target->username,
            'role' => 'admin', 'health_unit_id' => $destination->id])->assertRedirect(route('users.index'))
            ->assertSessionHas('error');
        $this->assertSame($source->id, $target->fresh()->health_unit_id);
    }

    public function test_an_administrator_can_be_reassigned_when_the_source_retains_an_active_administrator(): void
    {
        $this->signInPho();
        $source = HealthUnit::create(['name' => 'Retained admin RHU']);
        $destination = HealthUnit::create(['name' => 'Staff destination RHU']);
        $target = User::factory()->create(['role' => 'admin', 'health_unit_id' => $source->id]);
        User::factory()->create(['role' => 'admin', 'health_unit_id' => $source->id]);
        $this->put(route('users.update', $target->id), ['name' => $target->name, 'username' => $target->username,
            'role' => 'staff', 'health_unit_id' => $destination->id])->assertRedirect(route('users.index'))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'staff', 'health_unit_id' => $destination->id]);
    }

    public function test_an_inactive_administrator_does_not_allow_removal_of_the_only_active_administrator(): void
    {
        $this->signInPho();
        $unit = HealthUnit::create(['name' => 'Active admin RHU']);
        $target = User::factory()->create(['role' => 'admin', 'health_unit_id' => $unit->id]);
        User::factory()->create(['role' => 'admin', 'health_unit_id' => $unit->id, 'is_active' => false]);
        $this->delete(route('users.destroy', $target->id))->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'admin']);
        $this->put(route('users.update', $target->id), ['name' => $target->name, 'username' => $target->username,
            'role' => 'staff', 'health_unit_id' => $unit->id])->assertSessionHas('error');
        $this->assertSame('admin', $target->fresh()->role);
    }

    public function test_pho_profile_edits_can_preserve_the_existing_provincial_assignment(): void
    {
        $this->signInPho();
        $target = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $this->get(route('users.edit', $target->id))->assertOk()->assertSee('Provincial Health Office (no RHU)');
        $this->put(route('users.update', $target->id), ['name' => 'Edited PHO admin', 'username' => $target->username,
            'role' => 'admin', 'health_unit_id' => null])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'health_unit_id' => null, 'name' => 'Edited PHO admin']);
    }

    public function test_patient_assignments_are_cleared_when_a_midwife_changes_role_or_facility(): void
    {
        $this->signInPho();
        $source = HealthUnit::create(['name' => 'Patient source RHU']);
        $destination = HealthUnit::create(['name' => 'Patient destination RHU']);
        foreach (['staff', 'midwife'] as $newRole) {
            $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $source->id]);
            $patient = PublicUser::create(['health_unit_id' => $source->id, 'assigned_midwife_id' => $midwife->id,
                'first_name' => 'Assigned', 'last_name' => 'Patient', 'address' => 'Fixture', 'birth_date' => today()->subYear()]);
            $this->put(route('users.update', $midwife->id), ['name' => $midwife->name, 'username' => $midwife->username,
                'role' => $newRole, 'health_unit_id' => $newRole === 'midwife' ? $destination->id : $source->id])
                ->assertSessionHasNoErrors()->assertSessionHas('success');
            $this->assertNull($patient->fresh()->assigned_midwife_id);
        }
    }
}
