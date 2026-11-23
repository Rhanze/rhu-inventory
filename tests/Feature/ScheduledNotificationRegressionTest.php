<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\HealthUnit;
use App\Models\ImmunizationRecord;
use App\Models\Notification;
use App\Models\PublicUser;
use App\Models\User;
use App\Models\VaccinationSchedule;
use App\Services\NotificationService;
use Database\Seeders\ImmunizationScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduledNotificationRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function child(HealthUnit $unit, User $midwife): PublicUser
    {
        return PublicUser::create(['health_unit_id' => $unit->id, 'assigned_midwife_id' => $midwife->id,
            'first_name' => 'Scheduled', 'last_name' => 'Child', 'birth_date' => today()->subYear(), 'address' => 'Local']);
    }

    private function dose(PublicUser $child, string $vaccine, array $attributes = []): VaccinationSchedule
    {
        return VaccinationSchedule::create($attributes + ['health_unit_id' => $child->health_unit_id,
            'public_user_id' => $child->id, 'vaccine_name' => $vaccine, 'dose_number' => 1,
            'due_date' => today()->subDay(), 'status' => 'pending']);
    }

    public function test_overdue_digest_counts_assigned_children_once_and_excludes_completed_or_out_of_window_doses(): void
    {
        $this->seed(ImmunizationScheduleSeeder::class);
        $unit = HealthUnit::create(['name' => 'Reminder RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $other = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $inactive = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id, 'is_active' => false]);
        $child = $this->child($unit, $midwife);
        $this->dose($child, 'BCG');
        $this->dose($child, 'Hepatitis B', ['status' => 'missed']);
        $complete = $this->child($unit, $midwife);
        $this->dose($complete, 'BCG');
        ImmunizationRecord::create(['public_user_id' => $complete->id, 'health_unit_id' => $unit->id,
            'antigen' => 'bcg', 'dose_number' => 1, 'date_given' => today()]);
        $outside = $this->child($unit, $midwife);
        $this->dose($outside, 'BCG', ['due_date' => today()->subDays(15)]);
        $this->dose($outside, 'Hepatitis B', ['due_date' => today()]);
        $this->dose($this->child($unit, $inactive), 'BCG');
        $rescheduled = $this->child($unit, $midwife);
        $this->dose($rescheduled, 'BCG', ['status' => 'missed']);
        $this->dose($rescheduled, 'BCG', ['status' => 'given', 'given_date' => today()]);
        $foreignUnit = HealthUnit::create(['name' => 'Another RHU']);
        $this->dose($this->child($foreignUnit, $midwife), 'BCG');

        $service = app(NotificationService::class);
        $this->assertSame(1, $service->scanOverdueDoses());
        $notice = Notification::where('type', 'overdue_doses')->sole();
        $this->assertSame($midwife->id, $notice->user_id);
        $this->assertStringStartsWith('1 assigned child has overdue vaccine doses.', $notice->message);
        $this->assertSame('/vaccination/calendar', $notice->link);
        $this->assertSame(0, Notification::where('user_id', $other->id)->count());
        $this->assertSame(0, $service->scanOverdueDoses());
    }

    public function test_epi_reminders_recognize_calendar_types_and_ignore_deleted_events(): void
    {
        $service = app(NotificationService::class);
        foreach (['immunization_day', 'immunization_drive', 'catch_up_campaign'] as $type) {
            $unit = HealthUnit::create(['name' => $type.' RHU']);
            $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
            User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id, 'is_active' => false]);
            CalendarEvent::create(['health_unit_id' => $unit->id, 'title' => 'Community vaccination',
                'event_date' => today()->addDay(), 'type' => $type, 'priority' => 'normal', 'created_by' => $midwife->id]);
        }
        $deletedUnit = HealthUnit::create(['name' => 'Deleted event RHU']);
        $actor = User::factory()->create(['role' => 'staff', 'health_unit_id' => $deletedUnit->id]);
        $deleted = CalendarEvent::create(['health_unit_id' => $deletedUnit->id, 'title' => 'EPI removed',
            'event_date' => today()->addDay(), 'type' => 'immunization_day', 'priority' => 'normal', 'created_by' => $actor->id]);
        DB::table('calendar_events')->where('id', $deleted->id)->update(['deleted_at' => now()]);

        $this->assertSame(6, $service->scanEpiReminders());
        $this->assertSame(['/vaccination/calendar'], Notification::where('type', 'epi_reminder')->distinct()->pluck('link')->all());
        $this->assertSame(0, Notification::where('health_unit_id', $deletedUnit->id)->count());
        $this->assertSame(0, $service->scanEpiReminders());
    }

    public function test_nightly_notification_command_completes_with_the_current_schema(): void
    {
        $this->artisan('notifications:scan')->assertSuccessful();
    }
}
