<?php

namespace Tests\Feature;

use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\PublicUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispensingPrescriptionDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Prescription display RHU']);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $patient = PublicUser::create(['health_unit_id' => $unit->id, 'first_name' => 'Prescription', 'last_name' => 'Display',
            'assigned_midwife_id' => $staff->id, 'birth_date' => '2000-01-01', 'address' => 'Display address']);
        $records = [];
        foreach ([false, true] as $requiresPrescription) {
            $medicine = Medicine::create(['medicine_name' => $requiresPrescription ? 'Prescription medicine' : 'OTC medicine',
                'category' => 'medicine', 'requires_prescription' => $requiresPrescription]);
            $records[] = DispensingRecord::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
                'public_user_id' => $patient->id, 'patient_name' => $patient->full_name,
                'dispensed_by' => $staff->id, 'date_dispensed' => today(), 'quantity_dispensed' => 1,
                'doctor_name' => $requiresPrescription ? '[DEMO] Clinician RX' : '[DEMO] Clinician OTC']);
        }

        $this->withSession(['user_id' => $staff->id]);

        return [$staff, $patient, $records];
    }

    public function test_non_prescription_rows_and_details_omit_existing_demo_clinicians_and_exports_match(): void
    {
        [$staff, $patient, $records] = $this->fixture();
        $this->get(route('dispensing.index'))->assertOk()
            ->assertSee('OTC medicine')->assertSee('Prescription medicine')
            ->assertDontSee('[DEMO] Clinician OTC')->assertSee('[DEMO] Clinician RX')
            ->assertSee('data-doctor=""', false)->assertSee('data-doctor="[DEMO] Clinician RX"', false);

        $this->get(route('reports.dispensing'))->assertOk()
            ->assertDontSee('[DEMO] Clinician OTC')->assertSee('[DEMO] Clinician RX');
        foreach (['dispensing.export', 'reports.dispensing.csv'] as $route) {
            $csv = $this->get(route($route))->assertOk()->streamedContent();
            $this->assertStringNotContainsString('[DEMO] Clinician OTC', $csv);
            $this->assertStringContainsString('[DEMO] Clinician RX', $csv);
        }
        $this->get(route('dispensing.edit', $records[0]->id))->assertOk()->assertDontSee('[DEMO] Clinician OTC');
        $this->get(route('dispensing.edit', $records[1]->id))->assertOk()->assertSee('[DEMO] Clinician RX');
        $this->assertDatabaseHas('dispensing_records', ['doctor_name' => '[DEMO] Clinician OTC']);
    }

    public function test_dashboards_search_and_patient_history_use_prescription_aware_clinician_names(): void
    {
        [$staff, $patient] = $this->fixture();
        foreach ([route('dashboard'), route('search.index', ['scope' => 'dispensing', 'q' => 'medicine']),
            route('patients.show', $patient->id)] as $url) {
            $this->get($url)->assertOk()->assertDontSee('[DEMO] Clinician OTC')->assertSee('[DEMO] Clinician RX');
        }
        $staff->update(['role' => 'midwife']);
        $this->get(route('dashboard.midwife'))->assertOk()
            ->assertDontSee('[DEMO] Clinician OTC')->assertSee('[DEMO] Clinician RX');
    }

    public function test_missing_historical_prescribers_are_explicit_without_inventing_names_in_exports(): void
    {
        [$staff, $patient, $records] = $this->fixture();
        $records[1]->update(['doctor_name' => null]);
        foreach ([route('dispensing.index'), route('reports.dispensing'), route('dashboard'),
            route('search.index', ['scope' => 'dispensing', 'q' => 'Prescription medicine']),
            route('patients.show', $patient->id)] as $url) {
            $this->get($url)->assertOk()->assertSee('Not recorded')->assertDontSee('[DEMO] Clinician RX');
        }
        $this->get(route('dispensing.index'))->assertSee('data-doctor-display="Not recorded"', false);
        foreach (['dispensing.export', 'reports.dispensing.csv'] as $route) {
            $csv = $this->get(route($route))->assertOk()->streamedContent();
            $rows = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($csv)));
            $doctorColumn = array_search($route === 'dispensing.export' ? 'Doctor Name' : 'Doctor', $rows[0], true);
            $this->assertNotFalse($doctorColumn);
            foreach (array_slice($rows, 1) as $row) {
                $this->assertSame('', $row[$doctorColumn]);
            }
        }
        $this->assertNull($records[1]->fresh()->doctor_name);
        $staff->update(['role' => 'midwife']);
        $this->get(route('dashboard.midwife'))->assertOk()->assertSee('Not recorded');
    }
}
