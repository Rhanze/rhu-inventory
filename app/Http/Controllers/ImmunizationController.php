<?php

namespace App\Http\Controllers;

use App\Models\ImmunizationRecord;
use App\Models\ImmunizationSchedule;
use App\Models\PublicUser;
use App\Services\ImmunizationService;
use App\Services\VaccinationScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImmunizationController extends Controller
{
    public function __construct(
        protected ImmunizationService $immunizationService,
        private VaccinationScheduleService $schedules,
    ) {}

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
        $patientId = $request->validate(['patient' => 'nullable|integer|min:1'])['patient'] ?? null;

        // ── Children under 5 in this RHU (youngest first) ──
        $query = PublicUser::forViewer()->where('health_unit_id', $healthUnitId)
            ->when($patientId, fn ($children) => $children->whereKey($patientId))
            ->whereNotNull('birth_date')
            ->whereDate('birth_date', '>=', now()->subYears(5)->toDateString())
            ->orderByDesc('birth_date')->orderBy('id');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        $schedule = ImmunizationSchedule::active()->ordered()->get();
        $perPage = 50;
        $status = match ($filter) {
            'due' => 'has_due',
            'overdue' => 'has_overdue',
            default => null,
        };
        if ($status === null) {
            $children = $query->paginate($perPage)->withQueryString();
            $bulk = $this->immunizationService->getBulkSchedulesForChildren($children->getCollection(), $schedule);
        } else {
            // Compute candidate status before paging so an older child cannot disappear from follow-up.
            $page = LengthAwarePaginator::resolveCurrentPage();
            $skip = ($page - 1) * $perPage;
            $matches = 0;
            $selected = collect();
            $bulk = [];
            $query->chunk(200, function ($candidates) use ($status, $schedule, $skip, $perPage, $selected, &$matches, &$bulk) {
                $statuses = $this->immunizationService->getBulkSchedulesForChildren($candidates, $schedule);
                foreach ($candidates as $child) {
                    if (($statuses[$child->id]['overall'] ?? null) !== $status) {
                        continue;
                    }
                    if ($matches >= $skip && $selected->count() < $perPage) {
                        $selected->push($child);
                        $bulk[$child->id] = $statuses[$child->id];
                    }
                    $matches++;
                }
            });
            $children = (new LengthAwarePaginator($selected, $matches, $perPage, $page, ['path' => $request->url()]))
                ->withQueryString();
        }

        return view('immunization.tcl', compact(
            'children',
            'bulk',
            'schedule',
            'filter',
            'search',
            'patientId'
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

        [$child, $recorded] = DB::transaction(function () use ($validated, $healthUnitId, $scheduleDose) {
            $child = PublicUser::forViewer()->where('health_unit_id', $healthUnitId)
                ->lockForUpdate()->findOrFail($validated['public_user_id']);
            if ($child->birth_date && Carbon::parse($validated['date_given'])->startOfDay()->lessThan($child->birth_date)) {
                throw ValidationException::withMessages(['date_given' => 'Choose a vaccination date on or after the patient birth date.']);
            }
            if ($child->immunizationRecords()->where('antigen', $validated['vaccine'])->where('dose_number', $validated['dose_number'])->exists()) {
                return [$child, false];
            }
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

            $dueDate = $child->birth_date?->copy()->addDays($scheduleDose->min_age_days)->toDateString()
                ?? $record->date_given->toDateString();
            $this->schedules->fulfillAdministration($child, $scheduleDose, $record, $dueDate);
            return [$child, true];
        }, 3);

        if (! $recorded) {
            return back()->with('error', 'This dose has already been recorded for '.$child->full_name.'.');
        }

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
