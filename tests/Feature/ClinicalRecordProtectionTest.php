<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\PublicUser;
use App\Models\User;
use App\Support\ClinicalAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClinicalRecordProtectionTest extends TestCase
{
    use RefreshDatabase;

    public static function clinicalSections(): array
    {
        return [
            ['prenatal', 'prenatal_visits'],
            ['postpartum', 'postpartum_visits'],
            ['family_planning', 'family_planning'],
        ];
    }

    #[DataProvider('clinicalSections')]
    public function test_void_and_restore_keep_clinical_history_and_audit_entries(string $section, string $table): void
    {
        $facility = HealthUnit::create(['name' => 'Fictional Clinic']);
        $admin = User::factory()->create(['role' => 'admin', 'health_unit_id' => $facility->id]);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $facility->id]);
        $patient = PublicUser::create(['first_name' => 'Fictional', 'last_name' => 'Patient',
            'health_unit_id' => $facility->id, 'birth_date' => '2000-01-01', 'is_pregnant' => true, 'address' => 'Fictional street']);
        $today = today()->toDateString();

        $this->withSession(['user_id' => $staff->id])->post(route($section.'.store'), [
            'patient_id' => $patient->id, 'visit_date' => $today, 'delivery_date' => $today,
            'date_accepted' => $today, 'method' => 'Fixture method',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $id = DB::table($table)->value('id');
        $this->delete(route($section.'.destroy', $id))->assertSessionHas('success');
        $this->assertSoftDeleted($table, ['id' => $id]);
        $this->delete(route('public_users.destroy', $patient->id))->assertSessionHas('error');
        $this->get(route($section.'.index', ['archived' => 1]))->assertForbidden();
        $this->post(route($section.'.restore', $id))->assertForbidden();

        $this->withSession(['user_id' => $admin->id])->get(route($section.'.index', ['archived' => 1]))
            ->assertOk()->assertSee(route($section.'.restore', $id));
        $this->post(route($section.'.restore', $id))->assertSessionHas('success');
        $this->assertDatabaseHas($table, ['id' => $id, 'deleted_at' => null]);
        $this->assertDatabaseHas('activity_logs', ['module' => ClinicalAudit::MODULE,
            'user_id' => $staff->id, 'action' => 'Void', 'description' => $table.' #'.$id]);
        $this->assertDatabaseHas('activity_logs', ['module' => ClinicalAudit::MODULE,
            'user_id' => $admin->id, 'action' => 'Restore', 'description' => $table.' #'.$id]);
    }

    public function test_patient_read_audit_survives_routine_pruning_without_recording_search_terms(): void
    {
        $facility = HealthUnit::create(['name' => 'Fictional Clinic']);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $facility->id]);
        $filter = $this->withSession(['user_id' => $staff->id])
            ->post(route('public_users.filter'), ['search' => 'Private fixture term'])->assertRedirect();
        $location = $filter->headers->get('Location');
        $this->assertStringNotContainsString('Private', $location);
        $this->get($location)->assertOk();
        $this->assertDatabaseHas('activity_logs', ['user_id' => $staff->id, 'module' => ClinicalAudit::MODULE,
            'action' => 'Read', 'description' => 'patient_directory']);
        DB::table('activity_logs')->update(['created_at' => now()->subDays(60)]);
        $this->artisan('logs:prune')->assertSuccessful();
        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertStringNotContainsString('Private fixture term', DB::table('activity_logs')->value('description'));
    }

    public function test_removing_an_audited_account_revokes_access_and_retains_the_actor(): void
    {
        $facility = HealthUnit::create(['name' => 'Fictional Clinic']);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $facility->id]);
        $admin = User::factory()->create(['role' => 'admin', 'health_unit_id' => $facility->id]);
        $this->withSession(['user_id' => $staff->id])->get(route('public_users.index'))->assertOk();
        $this->withSession(['user_id' => $admin->id])->delete(route('users.destroy', $staff->id))
            ->assertSessionHas('success', 'Account deactivated to retain its clinical audit history.');
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'is_active' => false]);
        $this->assertDatabaseHas('activity_logs', ['module' => ClinicalAudit::MODULE, 'user_id' => $staff->id]);
        $this->withSession(['user_id' => $staff->id])->get(route('public_users.index'))->assertRedirect(route('login'));
    }
}
