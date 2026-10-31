<?php

namespace App\Http\Controllers;

use App\Models\PostpartumVisit;
use App\Models\PublicUser;
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

        $visits = PostpartumVisit::whereHas('patient', fn ($q) => $q->forViewer())->with('patient')
            ->where('health_unit_id', $healthUnitId)
            ->when($search, function ($q) use ($search) {
                $q->whereHas('patient', function ($p) use ($search) {
                    $p->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->when($visitNumber, fn ($q) => $q->where('visit_number', $visitNumber))
            ->orderByDesc('visit_date')
            ->paginate(20)
            ->appends(['search' => $search, 'visit_number' => $visitNumber]);

        $patients = PublicUser::forViewer()->where('health_unit_id', $healthUnitId)
            ->orderBy('last_name')
            ->get();

        $stats = [
            'total' => PostpartumVisit::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', $healthUnitId)->count(),
            'this_month' => PostpartumVisit::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', $healthUnitId)
                ->whereBetween('visit_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'mothers' => PostpartumVisit::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', $healthUnitId)
                ->distinct('patient_id')
                ->count('patient_id'),
        ];

        return view('postpartum.index', compact(
            'visits', 'patients', 'stats', 'search', 'visitNumber'
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

        $patient = PublicUser::forViewer()->where('id', $validated['patient_id'])
            ->where('health_unit_id', session('health_unit_id'))
            ->firstOrFail();

        PostpartumVisit::create([
            'patient_id' => $validated['patient_id'],
            'health_unit_id' => $validated['health_unit_id'],
            'delivery_date' => $validated['delivery_date'],
            'place_of_delivery' => $validated['place_of_delivery'] ?? null,
            'attended_by' => $validated['attended_by'] ?? null,
            'visit_date' => $validated['visit_date'],
            'next_visit_date' => $validated['next_visit_date'] ?? null,
            'visit_number' => $validated['visit_number'],
            'remarks' => $validated['remarks'] ?? null,
        ]);

        return back()->with('success', 'Postpartum visit recorded for '.$patient->full_name.'.');
    }

    public function destroy($id)
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $visit = PostpartumVisit::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', session('health_unit_id'))->findOrFail($id);
        $visit->delete();

        return back()->with('success', 'Visit deleted.');
    }
}
