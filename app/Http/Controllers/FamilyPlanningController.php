<?php

namespace App\Http\Controllers;

use App\Models\FamilyPlanning;
use App\Models\PublicUser;
use App\Support\ClinicalAudit;
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

        $archived = $request->boolean('archived');
        abort_unless(! $archived || ClinicalAudit::archiveVisible(), 403);
        $records = ClinicalAudit::scopedVisits(FamilyPlanning::class)->with('patient')
            ->when($archived, fn ($query) => $query->onlyTrashed())
            ->when($search, function ($q) use ($search) {
                $q->whereHas('patient', function ($p) use ($search) {
                    $p->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->when($method, fn ($q) => $q->where('method', $method))
            ->when($supplyFilter === 'due', function ($q) {
                $q->whereNotNull('next_supply_date')
                    ->whereBetween('next_supply_date', [today()->toDateString(), today()->addDays(30)->toDateString()]);
            })
            ->orderByDesc('date_accepted')
            ->paginate(20)
            ->appends(['search' => $search, 'method' => $method, 'supply' => $supplyFilter, 'archived' => $archived ? 1 : null]);

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
                ->whereBetween('date_accepted', [today()->startOfMonth()->toDateString(), today()->endOfMonth()->toDateString()])
                ->count(),
            'due_for_supply' => FamilyPlanning::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', $healthUnitId)
                ->whereNotNull('next_supply_date')
                ->whereBetween('next_supply_date', [today()->toDateString(), today()->addDays(30)->toDateString()])
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
            'stats', 'search', 'method', 'supplyFilter', 'archived'
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

        $patient = ClinicalAudit::createVisit(FamilyPlanning::class, $validated);

        return back()->with('success', 'Family planning record added for '.$patient->full_name.'.');
    }

    public function destroy($id)
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        ClinicalAudit::setVoided(FamilyPlanning::class, (int) $id, true);

        return back()->with('success', 'Record voided and retained in the archive.');
    }

    public function restore($id)
    {
        if ($redirect = $this->guard()) {
            return $redirect;
        }

        ClinicalAudit::setVoided(FamilyPlanning::class, (int) $id, false);

        return back()->with('success', 'Record restored.');
    }
}
