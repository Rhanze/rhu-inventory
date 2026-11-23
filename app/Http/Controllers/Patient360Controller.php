<?php

namespace App\Http\Controllers;

use App\Models\DispensingRecord;
use App\Models\FamilyPlanning;
use App\Models\PostpartumVisit;
use App\Models\PrenatalVisit;
use App\Models\PublicUser;
use App\Models\VaccinationSchedule;
use App\Services\VaccinationScheduleService;
use Carbon\Carbon;

class Patient360Controller extends Controller
{
    public function show($id, VaccinationScheduleService $schedules)
    {
        if (! session('user_id')) {
            return redirect()->route('login');
        }

        $healthUnitId = session('health_unit_id');
        abort_unless($healthUnitId, 403, 'Patient records require an assigned RHU.');

        $query = PublicUser::forViewer();
        if ($healthUnitId) {
            $query->where('health_unit_id', $healthUnitId);
        }
        $patient = $query->findOrFail($id);

        // ── Immunization schedule (confirmed: public_user_id) ───────────
        $vaccinations = VaccinationSchedule::whereHas('patient', fn ($q) => $q->forViewer())->where('public_user_id', $patient->id)
            ->orderBy('due_date')
            ->get();
        $doseCounts = $schedules->summarizeAppointments($vaccinations);

        // ── Maternal (confirmed: patient_id) ────────────────────────────
        $prenatalVisits = PrenatalVisit::whereHas('patient', fn ($q) => $q->forViewer())->where('patient_id', $patient->id)
            ->orderByDesc('visit_date')
            ->get();

        $postpartumVisits = PostpartumVisit::whereHas('patient', fn ($q) => $q->forViewer())->where('patient_id', $patient->id)
            ->orderByDesc('visit_date')
            ->get();

        // ── Family Planning (confirmed: patient_id, date_accepted) ──────
        $fpRecords = FamilyPlanning::whereHas('patient', fn ($q) => $q->forViewer())->where('patient_id', $patient->id)
            ->orderByDesc('date_accepted')
            ->get();

        $dispensingRecords = DispensingRecord::qualifying()->with(['medicine', 'issuedLots'])
            ->where('public_user_id', $patient->id)
            ->where('health_unit_id', $patient->health_unit_id)
            ->when(session('role') === 'midwife', fn ($q) => $q->where('dispensed_by', session('user_id')))
            ->orderByDesc('date_dispensed')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        // NOTE: disease_reports is a per-RHU aggregate table — it has no
        // patient FK, so it cannot be scoped to an individual patient.

        // ── Computed age ────────────────────────────────────────────────
        $age = $patient->birth_date ? Carbon::parse($patient->birth_date)->age : null;
        $ageMonths = $patient->birth_date ? (int) Carbon::parse($patient->birth_date)->diffInMonths(now()) : null;

        $stats = [
            'total_doses' => $doseCounts['total'],
            'given_doses' => $doseCounts['given'],
            'pending_doses' => $doseCounts['pending'],
            'missed_doses' => $doseCounts['missed'],
            'prenatal_count' => $prenatalVisits->count(),
            'postpartum_count' => $postpartumVisits->count(),
            'fp_count' => $fpRecords->count(),
            'dispensing_count' => $dispensingRecords->count(),
        ];

        return view('patients.show', compact(
            'patient', 'age', 'ageMonths', 'stats',
            'vaccinations',
            'prenatalVisits', 'postpartumVisits',
            'fpRecords', 'dispensingRecords'
        ));
    }
}
