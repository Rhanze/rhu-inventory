<?php

namespace App\Http\Controllers;

use App\Models\PrenatalVisit;
use App\Models\PublicUser;
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

        $visits = PrenatalVisit::whereHas('patient', fn ($q) => $q->forViewer())->with('patient')
            ->where('health_unit_id', $healthUnitId)
            ->when($search, function ($q) use ($search) {
                $q->whereHas('patient', function ($p) use ($search) {
                    $p->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->when($trimester, fn ($q) => $q->where('trimester', $trimester))
            ->orderByDesc('visit_date')
            ->paginate(20)
            ->appends(['search' => $search, 'trimester' => $trimester]);

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
                ->whereBetween('visit_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'pregnant' => PublicUser::forViewer()->where('health_unit_id', $healthUnitId)
                ->where('is_pregnant', true)
                ->count(),
        ];

        return view('prenatal.index', compact(
            'visits', 'patients', 'stats', 'search', 'trimester'
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

        $patient = PublicUser::forViewer()->where('id', $validated['patient_id'])
            ->where('health_unit_id', session('health_unit_id'))
            ->firstOrFail();

        PrenatalVisit::create([
            'patient_id' => $validated['patient_id'],
            'health_unit_id' => $validated['health_unit_id'],
            'visit_date' => $validated['visit_date'],
            'next_visit_date' => $validated['next_visit_date'] ?? null,
            'trimester' => $validated['trimester'] ?? null,
            'blood_pressure' => $validated['blood_pressure'] ?? null,
            'weight' => $validated['weight'] ?? null,
            'fundal_height' => $validated['fundal_height'] ?? null,
            'fetal_heart_tone' => $validated['fetal_heart_tone'] ?? null,
            'tt_dose' => $validated['tt_dose'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        return back()->with('success', 'Prenatal visit recorded for '.$patient->full_name.'.');
    }

    public function destroy($id)
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $visit = PrenatalVisit::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', session('health_unit_id'))->findOrFail($id);
        $visit->delete();

        return back()->with('success', 'Visit deleted.');
    }
}
