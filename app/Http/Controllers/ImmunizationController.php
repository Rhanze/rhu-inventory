<?php

namespace App\Http\Controllers;

use App\Models\ImmunizationRecord;
use App\Models\ImmunizationSchedule;
use App\Models\PublicUser;
use App\Models\VaccinationSchedule;
use App\Services\ImmunizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImmunizationController extends Controller
{
    protected ImmunizationService $immunizationService;

    public function __construct(ImmunizationService $immunizationService)
    {
        $this->immunizationService = $immunizationService;
    }

    private function guardRhuUser()
    {
        if (! session('user_id')) {
            return redirect()->route('login');
        }
        if (! session('health_unit_id')) {
            return redirect()->route('dashboard')
                ->with('error', 'Immunization tracking is only available at the RHU level.');
        }

        return null;
    }

    /**
     * Target Client List — the child × dose grid.
     * Only shows children under 5 (DOH EPI coverage window).
     */
    public function tcl(Request $request)
    {
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');
        $search = $request->input('search');
        $filter = $request->input('filter', 'all');

        // ── Children under 5 in this RHU (youngest first) ──
        $query = PublicUser::forViewer()->where('health_unit_id', $healthUnitId)
            ->whereNotNull('birth_date')
            ->whereDate('birth_date', '>=', now()->subYears(5)->toDateString())
            ->orderByDesc('birth_date')
            ->limit(300);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        $children = $query->get();

        // ── Bulk-compute each child's dose statuses ──
        $bulk = $this->immunizationService->getBulkSchedulesForChildren($children);

        // ── Apply filter AFTER computing statuses ──
        if ($filter === 'due') {
            $children = $children->filter(
                fn ($c) => ($bulk[$c->id]['overall'] ?? '') === 'has_due'
            )->values();
        } elseif ($filter === 'overdue') {
            $children = $children->filter(
                fn ($c) => ($bulk[$c->id]['overall'] ?? '') === 'has_overdue'
            )->values();
        }

        // ── Master schedule template (groups + column headers) ──
        $schedule = ImmunizationSchedule::active()->ordered()->get();

        return view('immunization.tcl', compact(
            'children',
            'bulk',
            'schedule',
            'filter',
            'search'
        ));
    }

    /**
     * Record a dose from the TCL modal.
     */
    public function store(Request $request)
    {
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');

        $validated = $request->validate([
            'public_user_id' => 'required|exists:public_users,id',
            'vaccine' => 'required|string|max:255',
            'dose_number' => 'required|integer|min:1',
            'date_given' => 'required|date|before_or_equal:today',
            'batch_no' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:2000',
        ]);

        $scheduleDose = ImmunizationSchedule::active()->where('dose_number', $validated['dose_number'])
            ->where(fn ($q) => $q->where('antigen', $validated['vaccine'])->orWhere('antigen_label', $validated['vaccine']))->first();
        if (! $scheduleDose) {
            return back()->withInput()->withErrors(['vaccine' => 'Select an active vaccine and dose from the schedule.']);
        }
        $validated['vaccine'] = $scheduleDose->antigen;

        // Verify child belongs to this RHU
        $child = PublicUser::forViewer()->where('id', $validated['public_user_id'])
            ->where('health_unit_id', $healthUnitId)
            ->firstOrFail();

        // Prevent duplicate dose
        $exists = ImmunizationRecord::whereHas('patient', fn ($q) => $q->forViewer())->where('public_user_id', $child->id)
            ->where('antigen', $validated['vaccine'])
            ->where('dose_number', $validated['dose_number'])
            ->exists();

        if ($exists) {
            return back()->with('error',
                'This dose has already been recorded for '.$child->full_name.'.');
        }

        DB::transaction(function () use ($validated, $child, $healthUnitId, $scheduleDose) {
            $record = ImmunizationRecord::create([
                'public_user_id' => $child->id,
                'health_unit_id' => $healthUnitId,
                'antigen' => $validated['vaccine'],
                'dose_number' => $validated['dose_number'],
                'date_given' => $validated['date_given'],
                'batch_no' => $validated['batch_no'] ?? null,
                'administered_by' => session('user_id'),
                'remarks' => $validated['remarks'] ?? null,
            ]);

            // Keep the calendar in sync if a schedule row exists
            VaccinationSchedule::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', $healthUnitId)
                ->where('public_user_id', $child->id)
                ->where('vaccine_name', $scheduleDose->antigen_label)
                ->where('dose_number', $validated['dose_number'])
                ->update([
                    'status' => 'given',
                    'given_date' => $validated['date_given'],
                    'immunization_record_id' => $record->id,
                ]);
        });

        return back()->with('success',
            'Recorded: '.$validated['vaccine'].
            ' (Dose '.$validated['dose_number'].') for '.$child->full_name.'.');
    }

    /**
     * Full immunization history for one child.
     */
    public function history($patient)
    {
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');

        $child = PublicUser::forViewer()->where('id', $patient)
            ->where('health_unit_id', $healthUnitId)
            ->firstOrFail();

        $records = ImmunizationRecord::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', $healthUnitId)
            ->where('public_user_id', $child->id)
            ->orderBy('date_given', 'desc')
            ->get();

        return view('immunization.history', compact('child', 'records'));
    }
}
