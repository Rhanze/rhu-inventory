<?php

namespace App\Http\Controllers;

use App\Models\FamilyPlanning;
use App\Models\PostpartumVisit;
use App\Models\PrenatalVisit;
use App\Models\PublicUser;
use App\Models\VaccinationSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Patient360Controller extends Controller
{
    public function show($id)
    {
        if (! session('user_id')) {
            return redirect()->route('login');
        }

        $healthUnitId = session('health_unit_id');

        $query = PublicUser::forViewer();
        if ($healthUnitId) {
            $query->where('health_unit_id', $healthUnitId);
        }
        $patient = $query->findOrFail($id);

        // ── Immunization schedule (confirmed: public_user_id) ───────────
        $vaccinations = VaccinationSchedule::whereHas('patient', fn ($q) => $q->forViewer())->where('public_user_id', $patient->id)
            ->orderBy('due_date')
            ->get();

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

        // ── Dispensing (best-effort by name — table has no patient_id) ──
        $dispensingRecords = DB::table('dispensing_records')
            ->where('patient_name', $patient->full_name)
            ->where('health_unit_id', $patient->health_unit_id)
            ->orderByDesc('date_dispensed')
            ->limit(30)
            ->get();

        // NOTE: disease_reports is a per-RHU aggregate table — it has no
        // patient FK, so it cannot be scoped to an individual patient.

        // ── Computed age ────────────────────────────────────────────────
        $age = $patient->birth_date ? Carbon::parse($patient->birth_date)->age : null;
        $ageMonths = $patient->birth_date ? (int) Carbon::parse($patient->birth_date)->diffInMonths(now()) : null;

        $stats = [
            'total_doses' => $vaccinations->count(),
            'given_doses' => $vaccinations->where('status', 'given')->count(),
            'pending_doses' => $vaccinations->where('status', 'pending')->count(),
            'missed_doses' => $vaccinations->where('status', 'missed')->count(),
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
