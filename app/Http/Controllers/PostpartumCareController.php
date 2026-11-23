<?php

namespace App\Http\Controllers;

use App\Models\PostpartumVisit;
use App\Models\PublicUser;
use App\Support\ClinicalAudit;
use Illuminate\Http\Request;

class PostpartumCareController extends Controller
{
    private function guard()
    {
        if (! session('user_id')) {
            return redirect()->route('login');
        }
        if (! session('health_unit_id')) {
            return redirect()->route('dashboard')
                ->with('error', 'Postpartum care is only available at the RHU level.');
        }

        return null;
    }

    public function index(Request $request)
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');
        $search = $request->input('search');
        $visitNumber = $request->input('visit_number');

        $archived = $request->boolean('archived');
        abort_unless(! $archived || ClinicalAudit::archiveVisible(), 403);
        $visits = ClinicalAudit::scopedVisits(PostpartumVisit::class)->with('patient')
            ->when($archived, fn ($query) => $query->onlyTrashed())
            ->when($search, function ($q) use ($search) {
                $q->whereHas('patient', function ($p) use ($search) {
                    $p->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->when($visitNumber, fn ($q) => $q->where('visit_number', $visitNumber))
            ->orderByDesc('visit_date')
            ->paginate(20)
            ->appends(['search' => $search, 'visit_number' => $visitNumber, 'archived' => $archived ? 1 : null]);

        $patients = PublicUser::forViewer()->where('health_unit_id', $healthUnitId)
            ->orderBy('last_name')
            ->get();

        $stats = [
            'total' => PostpartumVisit::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', $healthUnitId)->count(),
            'this_month' => PostpartumVisit::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', $healthUnitId)
                ->whereBetween('visit_date', [today()->startOfMonth()->toDateString(), today()->endOfMonth()->toDateString()])
                ->count(),
            'mothers' => PostpartumVisit::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', $healthUnitId)
                ->distinct('patient_id')
                ->count('patient_id'),
        ];

        return view('postpartum.index', compact(
            'visits', 'patients', 'stats', 'search', 'visitNumber', 'archived'
        ));
    }

    public function store(Request $request)
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $validated = $request->validate([
            'patient_id' => 'required|exists:public_users,id',
            'delivery_date' => 'required|date|before_or_equal:today',
            'place_of_delivery' => 'nullable|string|max:100',
            'attended_by' => 'nullable|string|max:100',
            'visit_date' => 'required|date|before_or_equal:today|after_or_equal:delivery_date',
            'next_visit_date' => 'nullable|date|after:visit_date',
            'visit_number' => 'nullable|integer|min:1|max:10',
            'remarks' => 'nullable|string|max:2000',
        ]);

        $validated['health_unit_id'] = session('health_unit_id');
        $validated['visit_number'] = $validated['visit_number'] ?? 1;

        $patient = ClinicalAudit::createVisit(PostpartumVisit::class, $validated);

        return back()->with('success', 'Postpartum visit recorded for '.$patient->full_name.'.');
    }

    public function destroy($id)
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        ClinicalAudit::setVoided(PostpartumVisit::class, (int) $id, true);

        return back()->with('success', 'Record voided and retained in the archive.');
    }

    public function restore($id)
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        ClinicalAudit::setVoided(PostpartumVisit::class, (int) $id, false);

        return back()->with('success', 'Record restored.');
    }
}
