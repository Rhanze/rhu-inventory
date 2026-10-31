<?php

namespace App\Http\Controllers;

use App\Models\FamilyPlanning;
use App\Models\PublicUser;
use Illuminate\Http\Request;

class FamilyPlanningController extends Controller
{
    private function guard()
    {
        if (! session('user_id')) {
            return redirect()->route('login');
        }
        if (! session('health_unit_id')) {
            return redirect()->route('dashboard')
                ->with('error', 'Family planning is only available at the RHU level.');
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
        $method = $request->input('method');
        $supplyFilter = $request->input('supply');  // 'due' = next supply in next 30 days' = next supply in next 30 days

        $records = FamilyPlanning::whereHas('patient', fn ($q) => $q->forViewer())->with('patient')
            ->where('health_unit_id', $healthUnitId)
            ->when($search, function ($q) use ($search) {
                $q->whereHas('patient', function ($p) use ($search) {
                    $p->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->when($method, fn ($q) => $q->where('method', $method))
            ->when($supplyFilter === 'due', function ($q) {
                $q->whereNotNull('next_supply_date')
                    ->whereBetween('next_supply_date', [now(), now()->addDays(30)]);
            })
            ->orderByDesc('date_accepted')
            ->paginate(20)
            ->appends(['search' => $search, 'method' => $method, 'supply' => $supplyFilter]);

        $patients = PublicUser::forViewer()->where('health_unit_id', $healthUnitId)
            ->orderBy('last_name')
            ->get();

        $methods = FamilyPlanning::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', $healthUnitId)
            ->whereNotNull('method')
            ->distinct()
            ->orderBy('method')
            ->pluck('method');

        $stats = [
            'total' => FamilyPlanning::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', $healthUnitId)->count(),
            'this_month' => FamilyPlanning::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', $healthUnitId)
                ->whereBetween('date_accepted', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'due_for_supply' => FamilyPlanning::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', $healthUnitId)
                ->whereNotNull('next_supply_date')
                ->whereBetween('next_supply_date', [now(), now()->addDays(30)])
                ->count(),
        ];

        // Standard DOH FP methods
        $commonMethods = [
            'Pills',
            'Condoms',
            'Injectable (DMPA)',
            'Implant',
            'IUD',
            'BTL',
            'Vasectomy',
            'LAM',
            'SDM',
            'Natural FP',
        ];

        return view('family_planning.index', compact(
            'records', 'patients', 'methods', 'commonMethods',
            'stats', 'search', 'method', 'supplyFilter'
        ));
    }

    public function store(Request $request)
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $validated = $request->validate([
            'patient_id' => 'required|exists:public_users,id',
            'method' => 'required|string|max:50',
            'date_accepted' => 'required|date|before_or_equal:today',
            'next_supply_date' => 'nullable|date|after:date_accepted',
            'remarks' => 'nullable|string|max:2000',
        ]);

        $validated['health_unit_id'] = session('health_unit_id');

        $patient = PublicUser::forViewer()->where('id', $validated['patient_id'])
            ->where('health_unit_id', session('health_unit_id'))
            ->firstOrFail();

        FamilyPlanning::create($validated);

        return back()->with('success', 'Family planning record added for '.$patient->full_name.'.');
    }

    public function destroy($id)
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        $record = FamilyPlanning::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', session('health_unit_id'))->findOrFail($id);
        $record->delete();

        return back()->with('success', 'Record deleted.');
    }
}
