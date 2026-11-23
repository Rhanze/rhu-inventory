<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\MidwifeBarangay;
use App\Models\PublicUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidwifeSnapshotLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_links_open_the_exact_personal_records_and_current_assigned_patients_and_requests(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 12:00:00', 'Asia/Manila'));
        $unit = HealthUnit::create(['name' => 'Snapshot RHU']);
        $foreign = HealthUnit::create(['name' => 'Foreign snapshot RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $colleague = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Current area']);
        $medicine = Medicine::create(['medicine_name' => 'Snapshot medicine']);
        foreach ([[$unit->id, $midwife->id, '2026-11-18'], [$unit->id, $midwife->id, '2026-11-18'],
            [$unit->id, $midwife->id, '2026-11-01'], [$unit->id, $midwife->id, '2026-10-31'],
            [$unit->id, $midwife->id, '2026-11-19'], [$unit->id, $colleague->id, '2026-11-18'],
            [$foreign->id, $midwife->id, '2026-11-18']] as [$unitId, $actor, $date]) {
            DispensingRecord::create(['health_unit_id' => $unitId, 'dispensed_by' => $actor,
                'medicine_id' => $medicine->id, 'barangay' => 'Previously served area', 'date_dispensed' => $date, 'quantity_dispensed' => 10]);
        }
        $deleted = DispensingRecord::create(['health_unit_id' => $unit->id, 'dispensed_by' => $midwife->id,
            'medicine_id' => $medicine->id, 'date_dispensed' => today(), 'quantity_dispensed' => 100]);
        $deleted->delete();
        foreach ([[$unit->id, $midwife->id], [$unit->id, $midwife->id], [$unit->id, $colleague->id],
            [$foreign->id, $midwife->id]] as $index => [$unitId, $assigned]) {
            PublicUser::create(['health_unit_id' => $unitId, 'assigned_midwife_id' => $assigned,
                'registered_by' => $midwife->id, 'first_name' => 'Snapshot '.$index, 'last_name' => 'Patient',
                'birth_date' => '2000-01-01', 'address' => 'Local']);
        }
        $requestBase = ['health_unit_id' => $unit->id, 'allocated_by' => $midwife->id,
            'medicine_id' => $medicine->id, 'date_allocated' => today(), 'quantity_allocated' => 1,
            'status' => 'pending', 'destination' => 'Own supply request'];
        Allocation::create($requestBase);
        Allocation::create($requestBase);
        Allocation::create(array_replace($requestBase, ['status' => 'approved']));
        Allocation::create(array_replace($requestBase, ['allocated_by' => $colleague->id]));
        Allocation::create(array_replace($requestBase, ['health_unit_id' => $foreign->id]));
        Allocation::create($requestBase + ['destination_health_unit_id' => $foreign->id]);
        $removed = Allocation::create($requestBase);
        $removed->delete();

        $dashboard = $this->withSession(['user_id' => $midwife->id])->get(route('dashboard.midwife'))->assertOk()
            ->assertViewHas('dispensedToday', 2)->assertViewHas('dispensedThisMonth', 3)
            ->assertViewHas('activePatients', 2)->assertViewHas('pendingRequests', 2)
            ->assertSee('As of 2026-11-18')->assertSee('currently assigned to you at this RHU')
            ->assertSee('your pending supply requests at this RHU.')
            ->assertDontSee('Transfers to other facilities are excluded.');
        $document = new \DOMDocument;
        @$document->loadHTML($dashboard->getContent());
        $xpath = new \DOMXPath($document);
        $destinations = [
            'midwife-kpi-dispensed-today' => ['records', 2],
            'midwife-kpi-dispensed-month' => ['records', 3],
            'midwife-kpi-registered' => ['members', 2],
            'midwife-kpi-pending-requests' => ['requests', 2],
        ];
        foreach ($destinations as $id => [$variable, $count]) {
            $href = $xpath->evaluate('string(//a[@id="'.$id.'"]/@href)');
            $this->assertNotSame('', $href);
            $this->get($href)->assertOk()->assertViewHas($variable, fn ($rows) => $rows->total() === $count);
            if (str_starts_with($id, 'midwife-kpi-dispensed')) {
                parse_str(parse_url($href, PHP_URL_QUERY), $filters);
                $this->assertSame('personal', $filters['dispensing_scope']);
                $this->assertSame('2026-11-18', $filters['date_to']);
            }
        }
    }
}
