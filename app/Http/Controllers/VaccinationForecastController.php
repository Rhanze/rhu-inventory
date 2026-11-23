<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\DoHProgram;
use App\Models\FamilyPlanning;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\PostpartumVisit;
use App\Models\PrenatalVisit;
use App\Models\PublicUser;
use App\Models\RhuImmunizationSchedule;
use App\Models\VaccinationSchedule;
use App\Services\VaccinationScheduleService;
use App\Services\DohEventPlanningService;
use App\Support\BarangayDemandScope;
use Carbon\Carbon;
use App\Support\SpreadsheetCsv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VaccinationForecastController extends Controller
{
    protected VaccinationScheduleService $scheduleService;

    public function __construct(VaccinationScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
    }

    private function guardRhuUser()
    {
        if (! session('user_id')) {
            return redirect()->route('login');
        }
        if (! session('health_unit_id')) {
            return redirect()->route('dashboard')
                ->with('error', 'Vaccination tracking is only available at the RHU level.');
        }

        return null;
    }

    private function guardEpiManager()
    {
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        abort_unless(session('role') === 'midwife', 403);

        return null;
    }

    public function index(Request $request)
    {
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');

        $today = today();
        $through = $today->copy()->addDays(30);
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $appointments = VaccinationSchedule::current()->whereHas('patient', fn ($q) => $q->forViewer())->with('patient')
            ->where('health_unit_id', $healthUnitId)->whereDate('due_date', '<=', $through)
            ->where(fn ($query) => $query->where('status', 'pending')
                ->orWhere(fn ($missed) => $missed->where('status', 'missed')->whereDoesntHave('replacement')))
            ->get()->each(function ($appointment) {
                $appointment->session_date = $this->scheduleService->appointmentSessionDate($appointment, $appointment->patient?->barangay);
            })->sortBy('session_date')->values();
        $pending = $appointments->where('status', 'pending');
        $upcoming = $pending->filter(fn ($appointment) => $appointment->session_date->betweenIncluded($today, $through));
        $pastSessions = $appointments->filter(fn ($appointment) => $appointment->session_date->lt($today));
        $overdue = $pastSessions->take(50);

        $monthStats = [
            'due_this_month' => $pending->filter(fn ($appointment) => $appointment->session_date->betweenIncluded($monthStart, $monthEnd))->count(),
            'overdue' => $pastSessions->count(),
            'given_month' => $this->scheduleService->countAdministrations($healthUnitId, $monthStart, $today),
        ];

        return view('vaccination.forecast', compact('upcoming', 'overdue', 'monthStats'));
    }

    public function calendar(Request $request)
    {
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        $filters = $request->validate(['month' => 'nullable|date_format:Y-m', 'day' => 'nullable|date_format:Y-m-d',
            'area_label' => 'nullable|string|max:120']);
        $healthUnitId = session('health_unit_id');
        $calendarAreaFilter = session('role') === 'midwife'
            ? BarangayDemandScope::validateForViewer((int) $healthUnitId, $filters['area_label'] ?? null, 'area_label')
            : null;

        $rhu = HealthUnit::find($healthUnitId);

        $month = $request->input('month') ?: now()->format('Y-m');
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $gridStart = $start->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $end->copy()->endOfWeek(Carbon::SATURDAY);

        // ── Vaccination doses ──
        $rawSchedules = VaccinationSchedule::current()->whereHas('patient', fn ($q) => $q->forViewer())->with('patient')
            ->where('health_unit_id', $healthUnitId)
            ->when($calendarAreaFilter, fn ($query) => $query->whereHas('patient', fn ($patient) => $patient
                ->whereRaw('LOWER(TRIM(barangay)) = ?', [mb_strtolower($calendarAreaFilter)])))
            ->whereBetween('due_date', [
                $gridStart->copy()->subDays(35)->toDateString(),
                $gridEnd->toDateString(),
            ])
            ->orderBy('due_date')
            ->get();

        $schedules = $rawSchedules
            ->map(function ($s) {
                $s->session_date = $this->scheduleService->appointmentSessionDate($s, $s->patient?->barangay);

                return $s;
            })
            ->filter(fn ($s) => $s->session_date->between($gridStart, $gridEnd))
            ->groupBy(fn ($s) => $s->session_date->toDateString());

        // ── Events ──
        $events = CalendarEvent::where('health_unit_id', $healthUnitId)
            ->when($calendarAreaFilter, fn ($query) => $query->where(fn ($area) => $area
                ->whereRaw('LOWER(TRIM(area_label)) = ?', [mb_strtolower($calendarAreaFilter)])->orWhereNull('area_label')))
            ->whereBetween('event_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->orderBy('event_date')
            ->orderByRaw("CASE WHEN priority = 'emergency' THEN 0 WHEN priority = 'high' THEN 1 ELSE 2 END")
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn ($e) => Carbon::parse($e->event_date)->toDateString());

        // ── Family Planning supplies due ──
        $fpSupplies = FamilyPlanning::whereHas('patient', fn ($q) => $q->forViewer())->with('patient')
            ->where('health_unit_id', $healthUnitId)
            ->when($calendarAreaFilter, fn ($query) => $query->whereHas('patient', fn ($patient) => $patient
                ->whereRaw('LOWER(TRIM(barangay)) = ?', [mb_strtolower($calendarAreaFilter)])))
            ->whereNotNull('next_supply_date')
            ->whereBetween('next_supply_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->orderBy('next_supply_date')
            ->get()
            ->groupBy(fn ($f) => $f->next_supply_date->toDateString());

        // ── Prenatal visits scheduled ──
        $prenatalVisits = PrenatalVisit::whereHas('patient', fn ($q) => $q->forViewer())->with('patient')
            ->where('health_unit_id', $healthUnitId)
            ->when($calendarAreaFilter, fn ($query) => $query->whereHas('patient', fn ($patient) => $patient
                ->whereRaw('LOWER(TRIM(barangay)) = ?', [mb_strtolower($calendarAreaFilter)])))
            ->whereNotNull('next_visit_date')
            ->whereBetween('next_visit_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->orderBy('next_visit_date')
            ->get()
            ->groupBy(fn ($p) => $p->next_visit_date->toDateString());

        // ── Postpartum visits scheduled ──
        $postpartumVisits = PostpartumVisit::whereHas('patient', fn ($q) => $q->forViewer())->with('patient')
            ->where('health_unit_id', $healthUnitId)
            ->when($calendarAreaFilter, fn ($query) => $query->whereHas('patient', fn ($patient) => $patient
                ->whereRaw('LOWER(TRIM(barangay)) = ?', [mb_strtolower($calendarAreaFilter)])))
            ->whereNotNull('next_visit_date')
            ->whereBetween('next_visit_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->orderBy('next_visit_date')
            ->get()
            ->groupBy(fn ($p) => $p->next_visit_date->toDateString());

        // ── DoH national programs (per-day lookup) ──
        $dohByDate = [];
        if (class_exists(DoHProgram::class)) {
            $activePrograms = DoHProgram::active()->get();

            $cursorDoh = $gridStart->copy();
            while ($cursorDoh <= $gridEnd) {
                $matched = [];
                foreach ($activePrograms as $prog) {
                    if ($prog->occursOn($cursorDoh)) {
                        $matched[] = [
                            'id' => $prog->id,
                            'name' => $prog->name,
                            'description' => $prog->description,
                            'color' => $prog->color,
                        ];
                    }
                }
                if (! empty($matched)) {
                    $dohByDate[$cursorDoh->toDateString()] = $matched;
                }
                $cursorDoh->addDay();
            }
        }

        // ── Build weeks ──
        $weeks = [];
        $cursor = $gridStart->copy();
        while ($cursor <= $gridEnd) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $key = $cursor->toDateString();
                $daySchedules = $schedules[$key] ?? collect();
                $dayEvents = $events[$key] ?? collect();
                $dayFP = $fpSupplies[$key] ?? collect();
                $dayPrenatal = $prenatalVisits[$key] ?? collect();
                $dayPostpartum = $postpartumVisits[$key] ?? collect();
                $dayDoh = $dohByDate[$key] ?? [];

                $week[] = [
                    'date' => $cursor->copy(),
                    'in_month' => $cursor->month === $start->month,
                    'is_today' => $cursor->isToday(),
                    'count' => $daySchedules->count(),
                    'child_count' => $daySchedules->pluck('public_user_id')->unique()->count(),
                    'event_count' => $dayEvents->count(),
                    'fp_count' => $dayFP->count(),
                    'prenatal_count' => $dayPrenatal->count(),
                    'postpartum_count' => $dayPostpartum->count(),
                    'overdue' => $daySchedules->where('status', 'missed')->count(),
                    'doh_count' => count($dayDoh),
                    'doh' => $dayDoh,
                    'items' => $daySchedules->map(fn ($s) => [
                        'id' => $s->id,
                        'vaccine' => $s->vaccine_name,
                        'dose' => $s->dose_number,
                        'child_name' => $s->patient?->full_name ?? 'Unknown',
                        'barangay' => $s->patient?->barangay ?? '—',
                        'status' => $s->status,
                        'is_overdue' => $s->is_overdue,
                    ])->values()->all(),
                    'events' => $dayEvents->map(fn ($e) => [
                        'id' => $e->id,
                        'title' => $e->title,
                        'type' => $e->type,
                        'type_label' => $e->type_label,
                        'priority' => $e->priority,
                        'start_time' => $e->start_time,
                        'end_time' => $e->end_time,
                        'description' => $e->description,
                        'area_label' => $e->area_label ?? null,
                    ])->values()->all(),
                    'fp' => $dayFP->map(fn ($f) => [
                        'id' => $f->id,
                        'patient_name' => $f->patient?->full_name ?? 'Unknown',
                        'barangay' => $f->patient?->barangay ?? '—',
                        'method' => $f->method,
                        'date' => $f->next_supply_date->format('M d, Y'),
                    ])->values()->all(),
                    'prenatal' => $dayPrenatal->map(fn ($p) => [
                        'id' => $p->id,
                        'patient_name' => $p->patient?->full_name ?? 'Unknown',
                        'barangay' => $p->patient?->barangay ?? '—',
                        'trimester' => $p->trimester,
                        'date' => $p->next_visit_date->format('M d, Y'),
                    ])->values()->all(),
                    'postpartum' => $dayPostpartum->map(fn ($p) => [
                        'id' => $p->id,
                        'patient_name' => $p->patient?->full_name ?? 'Unknown',
                        'barangay' => $p->patient?->barangay ?? '—',
                        'visit_number' => $p->visit_number,
                        'date' => $p->next_visit_date->format('M d, Y'),
                    ])->values()->all(),
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        // ── Selected day ──
        $selectedDate = $request->get('day', now()->toDateString());

        $sd = $schedules[$selectedDate] ?? collect();
        $se = $events[$selectedDate] ?? collect();
        $sf = $fpSupplies[$selectedDate] ?? collect();
        $sp = $prenatalVisits[$selectedDate] ?? collect();
        $sq = $postpartumVisits[$selectedDate] ?? collect();
        $sDoh = $dohByDate[$selectedDate] ?? [];

        $selectedChildCount = $sd->pluck('public_user_id')->unique()->count();

        $selectedSchedules = $sd->map(fn ($s) => [
            'id' => $s->id,
            'patient_id' => $s->public_user_id,
            'vaccine' => $s->vaccine_name,
            'dose' => $s->dose_number,
            'child_name' => $s->patient?->full_name ?? 'Unknown',
            'barangay' => $s->patient?->barangay ?? '—',
            'status' => $s->status,
            'is_overdue' => $s->is_overdue,
        ])->values()->all();

        $selectedEvents = $se->map(fn ($e) => [
            'id' => $e->id,
            'created_by' => $e->created_by,
            'title' => $e->title,
            'type' => $e->type,
            'type_label' => $e->type_label,
            'priority' => $e->priority,
            'priority_label' => $e->priority_label,
            'start_time' => $e->start_time,
            'end_time' => $e->end_time,
            'description' => $e->description,
            'event_date' => $e->event_date->format('Y-m-d'),
            'area_label' => $e->area_label ?? null,
        ])->values()->all();

        $selectedFP = $sf->map(fn ($f) => [
            'id' => $f->id,
            'patient_name' => $f->patient?->full_name ?? 'Unknown',
            'barangay' => $f->patient?->barangay ?? '—',
            'method' => $f->method,
        ])->values()->all();

        $selectedPrenatal = $sp->map(fn ($p) => [
            'id' => $p->id,
            'patient_name' => $p->patient?->full_name ?? 'Unknown',
            'barangay' => $p->patient?->barangay ?? '—',
            'trimester' => $p->trimester,
        ])->values()->all();

        $selectedPostpartum = $sq->map(fn ($p) => [
            'id' => $p->id,
            'patient_name' => $p->patient?->full_name ?? 'Unknown',
            'barangay' => $p->patient?->barangay ?? '—',
            'visit_number' => $p->visit_number,
        ])->values()->all();

        $flat = collect($schedules)->flatten();
        $appointments = $this->scheduleService->summarizeAppointments($flat);
        $monthStats = ['total_pending' => $appointments['pending'], 'total_overdue' => $appointments['missed'],
            'total_given' => $appointments['given']];

        // ═══ Vitamin A eligibility (April / October only) ═══
        $vitaminAEligibility = null;
        $selectedMonthNum = (int) Carbon::parse($selectedDate)->month;

        if ($start->month === 4 || $start->month === 10 || in_array($selectedMonthNum, [4, 10], true)) {
            $vitaminAEligibility = $this->computeVitaminAEligibility($healthUnitId);
        }

        // ═══ Per-area EPI schedules + barangays for the panel ═══
        $isMidwife = session('role') === 'midwife';
        $assignedAreas = $isMidwife ? BarangayDemandScope::options((int) $healthUnitId, (int) session('user_id')) : collect();
        $epiSchedules = RhuImmunizationSchedule::active()
            ->forHealthUnit($healthUnitId)
            ->when($isMidwife, fn ($query) => $query->whereIn(DB::raw('LOWER(TRIM(area_label))'),
                $assignedAreas->map(fn ($area) => mb_strtolower($area))))
            ->orderBy('area_label')
            ->get();

        $barangays = $isMidwife ? $assignedAreas : PublicUser::forViewer()
            ->where('health_unit_id', $healthUnitId)
            ->whereNotNull('barangay')
            ->where('barangay', '!=', '')
            ->distinct()
            ->orderBy('barangay')
            ->pluck('barangay');

        $selectedProgramPlans = collect(app(DohEventPlanningService::class)->onDate((int) $healthUnitId,
            Carbon::parse($selectedDate)))->keyBy('program_id');
        $planningMedicines = $selectedProgramPlans->isEmpty() ? collect()
            : Medicine::orderBy('medicine_name')->get(['id', 'medicine_name', 'dosage', 'unit']);

        return view('vaccination.calendar', compact(
            'weeks', 'start', 'end', 'month',
            'selectedDate', 'selectedSchedules', 'selectedEvents',
            'selectedFP', 'selectedPrenatal', 'selectedPostpartum',
            'selectedChildCount', 'monthStats', 'rhu',
            'sDoh', 'vitaminAEligibility',
            'epiSchedules', 'barangays', 'selectedProgramPlans', 'planningMedicines', 'calendarAreaFilter'
        ));
    }

    private function computeVitaminAEligibility(int $healthUnitId): array
    {
        $today = now();

        $sixMonthsAgo = $today->copy()->subMonths(6)->toDateString();
        $twelveMonthsAgo = $today->copy()->subMonths(12)->toDateString();
        $sixtyMonthsAgo = $today->copy()->subMonths(60)->toDateString();

        $infants = PublicUser::forViewer()
            ->where('health_unit_id', $healthUnitId)
            ->whereNotNull('birth_date')
            ->where('birth_date', '<=', $sixMonthsAgo)
            ->where('birth_date', '>', $twelveMonthsAgo)
            ->count();

        $children = PublicUser::forViewer()
            ->where('health_unit_id', $healthUnitId)
            ->whereNotNull('birth_date')
            ->where('birth_date', '<=', $twelveMonthsAgo)
            ->where('birth_date', '>', $sixtyMonthsAgo)
            ->count();

        $postpartum = 0;
        if (Schema::hasTable('postpartum_visits')) {
            $cols = Schema::getColumnListing('postpartum_visits');

            $dateCol = null;
            foreach (['delivery_date', 'date_of_delivery', 'delivered_at', 'birth_date', 'date_of_birth'] as $c) {
                if (in_array($c, $cols, true)) {
                    $dateCol = $c;
                    break;
                }
            }

            if ($dateCol) {
                $fourWeeksAgo = $today->copy()->subWeeks(4)->toDateString();

                $deliveries = PostpartumVisit::whereHas('patient', fn ($q) => $q->forViewer())
                    ->where('health_unit_id', $healthUnitId)
                    ->whereNotNull($dateCol)
                    ->whereDate($dateCol, '>=', $fourWeeksAgo)
                    ->whereDate($dateCol, '<=', $today->toDateString())
                    ->select('patient_id')->selectRaw('DATE('.$dateCol.') as delivery_day')->distinct();
                $postpartum = DB::query()->fromSub($deliveries, 'eligible_deliveries')->count();
            }
        }

        return [
            'infants_6_11m' => $infants,
            'children_12_59m' => $children,
            'postpartum_4w' => $postpartum,
            'capsules_100k' => $infants,
            'capsules_200k' => $children + $postpartum,
            'total_capsules' => $infants + $children + $postpartum,
        ];
    }

    public function monthly(Request $request)
    {
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        $request->validate(['month' => 'nullable|date_format:Y-m']);
        $healthUnitId = session('health_unit_id');

        $month = $request->input('month') ?: now()->format('Y-m');
        $summary = $this->scheduleService->monthlySummary($healthUnitId, $month, includeAppointments: false);
        $nextMonth = Carbon::parse($month.'-01')->addMonth()->format('Y-m');
        $nextSummary = $this->scheduleService->monthlySummary($healthUnitId, $nextMonth, includeAppointments: false);
        $appointments = $this->scheduleService->monthlyAppointments($healthUnitId, $month)
            ->paginate(50)->appends(['month' => $month]);

        return view('vaccination.monthly', compact('summary', 'nextSummary', 'month', 'appointments'));
    }

    public function monthlyExport(Request $request)
    {
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        $request->validate(['month' => 'nullable|date_format:Y-m']);
        $healthUnitId = session('health_unit_id');
        $month = $request->input('month') ?: now()->format('Y-m');
        $summary = $this->scheduleService->monthlySummary($healthUnitId, $month, includeAppointments: false);
        $appointments = $this->scheduleService->monthlyAppointments($healthUnitId, $month);
        $filename = 'vaccination_forecast_'.$month.'.csv';

        return response()->streamDownload(function () use ($summary, $appointments) {
            $handle = fopen('php://output', 'w');
            SpreadsheetCsv::writeRow($handle, ['Month', $summary['month']]);
            SpreadsheetCsv::writeRow($handle, []);
            SpreadsheetCsv::writeRow($handle, ['Vaccine', 'Children', 'Doses']);
            foreach ($summary['by_vaccine'] as $row) {
                SpreadsheetCsv::writeRow($handle, [$row['vaccine'], $row['children'], $row['doses']]);
            }
            SpreadsheetCsv::writeRow($handle, []);
            SpreadsheetCsv::writeRow($handle, ['TOTAL', $summary['total_children'], $summary['total_doses']]);
            SpreadsheetCsv::writeRow($handle, []);
            SpreadsheetCsv::writeRow($handle, ['Child Name', 'Barangay', 'Vaccine', 'Dose', 'Due Date', 'Status']);
            foreach ($appointments->lazy(200) as $s) {
                SpreadsheetCsv::writeRow($handle, [
                    $s->patient?->full_name ?? 'Unknown',
                    $s->patient?->barangay ?? '—',
                    $s->vaccine_name,
                    $s->dose_number,
                    $s->due_date->format('Y-m-d'),
                    $s->status,
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function childSchedule($patient)
    {
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');
        $child = PublicUser::forViewer()->where('id', $patient)
            ->where('health_unit_id', $healthUnitId)
            ->firstOrFail();

        $schedules = VaccinationSchedule::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', $healthUnitId)
            ->where('public_user_id', $child->id)
            ->orderBy('due_date')
            ->get();

        foreach ($schedules->where('status', 'pending')->whereNull('superseded_at') as $appointment) {
            $appointment->session_date = $this->scheduleService->appointmentSessionDate($appointment, $child->barangay);
        }
        $stats = $this->scheduleService->summarizeAppointments($schedules);

        return view('vaccination.child', compact('child', 'schedules', 'stats'));
    }

    public function regenerate(Request $request)
    {
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');
        $patientId = $request->input('patient_id');

        $query = PublicUser::forViewer()->where('health_unit_id', $healthUnitId)->whereNotNull('birth_date');
        if ($patientId) {
            $query->where('id', $patientId);
        }

        $children = $query->get();
        $total = 0;
        foreach ($children as $child) {
            $total += $this->scheduleService->generateFor($child);
        }

        return redirect()->route('vaccination.calendar')
            ->with('success', "Generated {$total} new schedule entries for ".$children->count().' patient(s).');
    }

    public function updateSessionPattern(Request $request)
    {
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        $rhu = HealthUnit::find(session('health_unit_id'));
        if (! $rhu) {
            return back()->with('error', 'RHU not found.');
        }

        $validated = $request->validate([
            'immunization_pattern' => 'required|in:exact,weekly,monthly_first,monthly_last,monthly_nth',
            'immunization_weekday' => 'required_unless:immunization_pattern,exact|nullable|integer|min:0|max:6',
            'immunization_week_number' => 'required_if:immunization_pattern,monthly_nth|nullable|integer|min:1|max:4',
        ]);

        if ($validated['immunization_pattern'] === 'exact') {
            $validated['immunization_weekday'] = null;
            $validated['immunization_week_number'] = null;
        }

        if ($validated['immunization_pattern'] !== 'exact'
            && ! isset($validated['immunization_weekday'])) {
            return back()->with('error', 'Please select a day of the week for this pattern.');
        }

        $rhu->update($validated);

        return back()->with('success', 'Default session pattern updated.');
    }

    /**
     * Add a per-area EPI schedule.
     */
    public function storeEpiSchedule(Request $request)
    {
        if ($redirect = $this->guardEpiManager()) {
            return $redirect;
        }

        $validated = $request->validate([
            'area_label' => 'required|string|max:120',
            'pattern' => 'required|in:weekly,monthly_first,monthly_last,monthly_nth',
            'weekday' => 'required|integer|min:0|max:6',
            'week_number' => 'nullable|integer|min:1|max:4',
        ]);

        if ($validated['pattern'] === 'monthly_nth' && empty($validated['week_number'])) {
            return back()->with('error', 'Please select which week of the month (1st–4th).');
        }

        $healthUnitId = session('health_unit_id');
        $area = BarangayDemandScope::validateForViewer((int) $healthUnitId, $validated['area_label'], 'area_label', true);

        $existing = RhuImmunizationSchedule::forHealthUnit($healthUnitId)
            ->whereRaw('LOWER(TRIM(area_label)) = ?', [mb_strtolower($area)])
            ->exists();

        if ($existing) {
            return back()->with('error',
                'A schedule for "'.$area.'" already exists. Delete it first if you want to change the pattern.');
        }

        RhuImmunizationSchedule::create([
            'health_unit_id' => $healthUnitId,
            'area_label' => $area,
            'pattern' => $validated['pattern'],
            'weekday' => $validated['weekday'],
            'week_number' => $validated['pattern'] === 'monthly_nth' ? $validated['week_number'] : null,
            'is_active' => true,
        ]);

        return back()->with('success', 'EPI schedule added for '.$area.'.');
    }

    /**
     * Remove a per-area schedule. Existing calendar events are NOT removed.
     */
    public function destroyEpiSchedule($id)
    {
        if ($redirect = $this->guardEpiManager()) {
            return $redirect;
        }

        $schedule = RhuImmunizationSchedule::forHealthUnit(session('health_unit_id'))->findOrFail($id);
        $area = $schedule->area_label;
        BarangayDemandScope::validateForViewer((int) session('health_unit_id'), $area, 'area_label', true);
        $schedule->delete();

        return back()->with('success',
            'EPI schedule for "'.$area.'" removed. (Existing calendar events were kept.)');
    }

    /**
     * Generate recurring EPI day events for the next N months,
     * one event series per active area schedule.
     */
    public function generateEpiDays(Request $request)
    {
        if ($redirect = $this->guardEpiManager()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');
        $rhu = HealthUnit::find($healthUnitId);
        if (! $rhu) {
            return back()->with('error', 'RHU not found.');
        }

        $months = (int) $request->input('months', 12);
        $months = max(1, min(24, $months));

        $schedules = RhuImmunizationSchedule::active()
            ->forHealthUnit($healthUnitId)
            ->whereIn(DB::raw('LOWER(TRIM(area_label))'),
                BarangayDemandScope::options((int) $healthUnitId, (int) session('user_id'))->map(fn ($area) => mb_strtolower($area)))
            ->get();

        if ($schedules->isEmpty()) {
            return back()->with('error',
                'No area schedules yet. Add at least one (area + day) below before generating EPI days.');
        }

        $totalCreated = 0;
        $byArea = [];

        foreach ($schedules as $sched) {
            // Temporarily map the schedule's fields onto the RHU's attribute
            // names so the existing upcomingSessionDates() helper works.
            $clone = clone $rhu;
            $clone->immunization_pattern = $sched->pattern;
            $clone->immunization_weekday = $sched->weekday;
            $clone->immunization_week_number = $sched->week_number;

            $dates = $this->scheduleService->upcomingSessionDates($clone, $months);
            $created = 0;

            foreach ($dates as $date) {
                $exists = CalendarEvent::where('health_unit_id', $healthUnitId)
                    ->where('event_date', $date->toDateString())
                    ->where('type', CalendarEvent::TYPE_EPI_DAY)
                    ->where('area_label', $sched->area_label)
                    ->exists();

                if ($exists) {
                    continue;
                }

                CalendarEvent::create([
                    'health_unit_id' => $healthUnitId,
                    'title' => 'EPI Day — '.$sched->area_label,
                    'description' => 'Regular immunization session · '.$sched->pattern_label,
                    'event_date' => $date->toDateString(),
                    'type' => CalendarEvent::TYPE_EPI_DAY,
                    'priority' => 'normal',
                    'area_label' => $sched->area_label,
                    'created_by' => session('user_id'),
                ]);

                $created++;
            }

            $totalCreated += $created;
            $byArea[] = $sched->area_label.' ('.$created.')';
        }

        $msg = "Generated {$totalCreated} EPI day event(s) across ".$schedules->count()
             .' area(s): '.implode(', ', $byArea).'.';

        return back()->with('success', $msg);
    }

    /**
     * Remove all UPCOMING auto-generated EPI day events for this RHU.
     * Optionally scoped to one area via ?area_label=.
     */
    public function clearEpiDays(Request $request)
    {
        if ($redirect = $this->guardEpiManager()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');
        $validated = $request->validate(['area_label' => 'nullable|string|max:120']);
        $areaLabel = BarangayDemandScope::validateForViewer((int) $healthUnitId, $validated['area_label'] ?? null, 'area_label');

        $query = CalendarEvent::where('health_unit_id', $healthUnitId)
            ->where('type', CalendarEvent::TYPE_EPI_DAY)
            ->whereDate('event_date', '>=', now()->toDateString())
            ->whereIn(DB::raw('LOWER(TRIM(area_label))'), BarangayDemandScope::options((int) $healthUnitId, (int) session('user_id'))
                ->map(fn ($area) => mb_strtolower($area)))
            ->when($areaLabel, fn ($query) => $query->whereRaw('LOWER(TRIM(area_label)) = ?', [mb_strtolower($areaLabel)]));

        $deleted = $query->delete();

        if ($deleted === 0) {
            return back()->with('error', 'No upcoming EPI day events to clear.');
        }

        return back()->with('success', "Removed {$deleted} upcoming EPI day event(s).");
    }

    public function coverageByBarangay()
    {
        if (! session('user_id')) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $healthUnitId = session('health_unit_id');
        if (! $healthUnitId) {
            return response()->json(['labels' => [], 'data' => []]);
        }

        $coverage = app(\App\Services\BarangayImmunizationCoverageService::class)->forHealthUnit((int) $healthUnitId);

        return response()->json(['labels' => $coverage['labels'], 'data' => $coverage['immunized'],
            'totals' => $coverage['totals'], 'percent' => $coverage['percent']]);
    }
}
