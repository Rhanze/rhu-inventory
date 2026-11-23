<?php

namespace App\Http\Controllers;

use App\Models\PrenatalVisit;
use App\Models\PublicUser;
use App\Support\ClinicalAudit;
use Illuminate\Http\Request;

class PrenatalController extends Controller
{
    private function guard()
    {
        if (! session('user_id')) {
            return redirect()->route('login');
        }
        if (! session('health_unit_id')) {
            return redirect()->route('dashboard')
                ->with('error', 'Maternal care is only available at the RHU level.');
        }

        return null;
    }

    public function index(Request $request)
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');
        $search = $request->search;
        $trimester = $request->trimester;

        $archived = $request->boolean('archived');
        abort_unless(! $archived || ClinicalAudit::archiveVisible(), 403);
        $visits = ClinicalAudit::scopedVisits(PrenatalVisit::class)->with('patient')
            ->when($archived, fn ($query) => $query->onlyTrashed())
            ->when($search, function ($q) use ($search) {
                $q->whereHas('patient', function ($p) use ($search) {
                    $p->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->when($trimester, fn ($q) => $q->where('trimester', $trimester))
            ->orderByDesc('visit_date')
            ->paginate(20)
            ->appends(['search' => $search, 'trimester' => $trimester, 'archived' => $archived ? 1 : null]);

        $patients = PublicUser::forViewer()->where('health_unit_id', $healthUnitId)
            ->where(function ($q) {
                $q->where('is_pregnant', true)
                    ->orWhereHas('prenatalVisits');
            })
            ->orderBy('last_name')
            ->get();

        $stats = [
            'total' => PrenatalVisit::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', $healthUnitId)->count(),
            'this_month' => PrenatalVisit::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', $healthUnitId)
                ->whereBetween('visit_date', [today()->startOfMonth()->toDateString(), today()->endOfMonth()->toDateString()])
                ->count(),
            'pregnant' => PublicUser::forViewer()->where('health_unit_id', $healthUnitId)
                ->where('is_pregnant', true)
                ->count(),
        ];

        return view('prenatal.index', compact(
            'visits', 'patients', 'stats', 'search', 'trimester', 'archived'
        ));
    }

    public function store(Request $request)
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $validated = $request->validate([
            'patient_id' => 'required|exists:public_users,id',
            'visit_date' => 'required|date|before_or_equal:today',
            'next_visit_date' => 'nullable|date|after:visit_date',
            'trimester' => 'nullable|in:1st,2nd,3rd',
            'blood_pressure' => 'nullable|string|max:20',
            'weight' => 'nullable|numeric|min:0|max:300',
            'fundal_height' => 'nullable|numeric|min:0|max:60',
            'fetal_heart_tone' => 'nullable|string|max:20',
            'tt_dose' => 'nullable|in:TT1,TT2,TT3,TT4,TT5,TTL',
            'remarks' => 'nullable|string|max:2000',
        ]);

        $validated['health_unit_id'] = session('health_unit_id');

        $patient = ClinicalAudit::createVisit(PrenatalVisit::class, $validated);

        return back()->with('success', 'Prenatal visit recorded for '.$patient->full_name.'.');
    }

    public function destroy($id)
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        ClinicalAudit::setVoided(PrenatalVisit::class, (int) $id, true);

        return back()->with('success', 'Record voided and retained in the archive.');
    }

    public function restore($id)
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        ClinicalAudit::setVoided(PrenatalVisit::class, (int) $id, false);

        return back()->with('success', 'Record restored.');
    }
}
