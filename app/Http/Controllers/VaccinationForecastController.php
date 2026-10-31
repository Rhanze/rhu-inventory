<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\DoHProgram;
use App\Models\FamilyPlanning;
use App\Models\HealthUnit;
use App\Models\PostpartumVisit;
use App\Models\PrenatalVisit;
use App\Models\PublicUser;
use App\Models\RhuImmunizationSchedule;
use App\Models\VaccinationSchedule;
use App\Services\VaccinationScheduleService;
use Carbon\Carbon;
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

    public function index(Request $request)
    {
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');
        $this->scheduleService->markOverdueAsMissed(30);

        $upcoming = VaccinationSchedule::whereHas('patient', fn ($q) => $q->forViewer())->with('patient')
            ->where('health_unit_id', $healthUnitId)
            ->where('status', 'pending')
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->orderBy('due_date')
            ->get();

        $overdue = VaccinationSchedule::whereHas('patient', fn ($q) => $q->forViewer())->with('patient')
            ->where('health_unit_id', $healthUnitId)
            ->where(function ($q) {
                $q->where('status', 'missed')
                    ->orWhere(function ($s) {
                        $s->where('status', 'pending')->whereDate('due_date', '<', now());
                    });
            })
            ->orderBy('due_date')
            ->limit(50)
            ->get();

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $monthStats = [
            'due_this_month' => VaccinationSchedule::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', $healthUnitId)
                ->whereBetween('due_date', [$monthStart, $monthEnd])
                ->where('status', 'pending')->count(),
            'overdue' => VaccinationSchedule::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', $healthUnitId)
                ->where('status', 'missed')->count(),
            'given_month' => VaccinationSchedule::whereHas('patient', fn ($q) => $q->forViewer())->where('health_unit_id', $healthUnitId)
                ->where('status', 'given')
                ->whereBetween('given_date', [$monthStart, $monthEnd])->count(),
        ];

        return view('vaccination.forecast', compact('upcoming', 'overdue', 'monthStats'));
    }

    public function calendar(Request $request)
    {
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');
        $this->scheduleService->markOverdueAsMissed(30);

        $rhu = HealthUnit::find($healthUnitId);

        $month = $request->get('month', now()->format('Y-m'));
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $gridStart = $start->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $end->copy()->endOfWeek(Carbon::SATURDAY);

        // ── Vaccination doses ──
        $rawSchedules = VaccinationSchedule::whereHas('patient', fn ($q) => $q->forViewer())->with('patient')
            ->where('health_unit_id', $healthUnitId)
            ->whereBetween('due_date', [
                $gridStart->copy()->subDays(10)->toDateString(),
                $gridEnd->copy()->addDays(10)->toDateString(),
            ])
            ->orderBy('due_date')
            ->get();

        $schedules = $rawSchedules
            ->map(function ($s) use ($rhu) {
                $s->session_date = $this->scheduleService->sessionDateFor(
                    Carbon::parse($s->due_date),
                    $rhu?->id
                );

                return $s;
            })
            ->filter(fn ($s) => $s->session_date->between($gridStart, $gridEnd))
            ->groupBy(fn ($s) => $s->session_date->toDateString());

        // ── Events ──
        $events = CalendarEvent::where('health_unit_id', $healthUnitId)
            ->whereBetween('event_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->orderBy('event_date')
            ->orderByRaw("CASE WHEN priority = 'emergency' THEN 0 WHEN priority = 'high' THEN 1 ELSE 2 END")
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn ($e) => Carbon::parse($e->event_date)->toDateString());

        // ── Family Planning supplies due ──
        $fpSupplies = FamilyPlanning::whereHas('patient', fn ($q) => $q->forViewer())->with('patient')
            ->where('health_unit_id', $healthUnitId)
            ->whereNotNull('next_supply_date')
            ->whereBetween('next_supply_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->orderBy('next_supply_date')
            ->get()
            ->groupBy(fn ($f) => $f->next_supply_date->toDateString());

        // ── Prenatal visits scheduled ──
        $prenatalVisits = PrenatalVisit::whereHas('patient', fn ($q) => $q->forViewer())->with('patient')
            ->where('health_unit_id', $healthUnitId)
            ->whereNotNull('next_visit_date')
            ->whereBetween('next_visit_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->orderBy('next_visit_date')
            ->get()
            ->groupBy(fn ($p) => $p->next_visit_date->toDateString());

        // ── Postpartum visits scheduled ──
        $postpartumVisits = PostpartumVisit::whereHas('patient', fn ($q) => $q->forViewer())->with('patient')
            ->where('health_unit_id', $healthUnitId)
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
                        'is_overdue' => $s->status === 'pending' && $s->due_date->isPast(),
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
            'vaccine' => $s->vaccine_name,
            'dose' => $s->dose_number,
            'child_name' => $s->patient?->full_name ?? 'Unknown',
            'barangay' => $s->patient?->barangay ?? '—',
            'status' => $s->status,
            'is_overdue' => $s->status === 'pending' && $s->due_date->isPast(),
        ])->values()->all();

        $selectedEvents = $se->map(fn ($e) => [
            'id' => $e->id,
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
        $monthStats = [
            'total_pending' => $flat->where('status', 'pending')->count(),
            'total_overdue' => $flat->where('status', 'missed')->count(),
            'total_given' => $flat->where('status', 'given')->count(),
        ];

        // ═══ Vitamin A eligibility (April / October only) ═══
        $vitaminAEligibility = null;
        $selectedMonthNum = (int) Carbon::parse($selectedDate)->month;

        if ($start->month === 4 || $start->month === 10 || in_array($selectedMonthNum, [4, 10], true)) {
            $vitaminAEligibility = $this->computeVitaminAEligibility($healthUnitId);
        }

        // ═══ Per-area EPI schedules + barangays for the panel ═══
        $epiSchedules = RhuImmunizationSchedule::active()
            ->forHealthUnit($healthUnitId)
            ->orderBy('area_label')
            ->get();

        $barangays = PublicUser::forViewer()
            ->where('health_unit_id', $healthUnitId)
            ->whereNotNull('barangay')
            ->where('barangay', '!=', '')
            ->distinct()
            ->orderBy('barangay')
            ->pluck('barangay');

        return view('vaccination.calendar', compact(
            'weeks', 'start', 'end', 'month',
            'selectedDate', 'selectedSchedules', 'selectedEvents',
            'selectedFP', 'selectedPrenatal', 'selectedPostpartum',
            'selectedChildCount', 'monthStats', 'rhu',
            'sDoh', 'vitaminAEligibility',
            'epiSchedules', 'barangays'
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

                $postpartum = PostpartumVisit::whereHas('patient', fn ($q) => $q->forViewer())
                    ->where('health_unit_id', $healthUnitId)
                    ->whereNotNull($dateCol)
                    ->where($dateCol, '>=', $fourWeeksAgo)
                    ->where($dateCol, '<=', $today->toDateString())
                    ->count();
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

        $healthUnitId = session('health_unit_id');
        $this->scheduleService->markOverdueAsMissed(30);
        $this->scheduleService->rescheduleMissedDoses(30);

        $month = $request->input('month', now()->format('Y-m'));
        $summary = $this->scheduleService->monthlySummary($healthUnitId, $month);
        $nextMonth = Carbon::parse($month.'-01')->addMonth()->format('Y-m');
        $nextSummary = $this->scheduleService->monthlySummary($healthUnitId, $nextMonth);

        return view('vaccination.monthly', compact('summary', 'nextSummary', 'month'));
    }

    public function monthlyExport(Request $request)
    {
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');
        $month = $request->input('month', now()->format('Y-m'));
        $summary = $this->scheduleService->monthlySummary($healthUnitId, $month);
        $filename = 'vaccination_forecast_'.$month.'.csv';

        return response()->streamDownload(function () use ($summary) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Month', $summary['month']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Vaccine', 'Children', 'Doses']);
            foreach ($summary['by_vaccine'] as $row) {
                fputcsv($handle, [$row['vaccine'], $row['children'], $row['doses']]);
            }
            fputcsv($handle, []);
            fputcsv($handle, ['TOTAL', $summary['total_children'], $summary['total_doses']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Child Name', 'Barangay', 'Vaccine', 'Dose', 'Due Date', 'Status']);
            foreach ($summary['children'] as $s) {
                fputcsv($handle, [
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

        $stats = $this->scheduleService->summaryFor($child);

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
            'immunization_weekday' => 'nullable|integer|min:0|max:6',
            'immunization_week_number' => 'nullable|integer|min:1|max:4',
        ]);

        if ($validated['immunization_pattern'] === 'exact') {
            $validated['immunization_weekday'] = null;
            $validated['immunization_week_number'] = null;
        }

        if ($validated['immunization_pattern'] !== 'exact'
            && ($validated['immunization_weekday'] === null || $validated['immunization_weekday'] === '')) {
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
        if ($redirect = $this->guardRhuUser()) {
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
        $area = trim($validated['area_label']);

        $existing = RhuImmunizationSchedule::forHealthUnit($healthUnitId)
            ->where('area_label', $area)
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
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        $schedule = RhuImmunizationSchedule::forHealthUnit(session('health_unit_id'))->findOrFail($id);
        $area = $schedule->area_label;
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
        if ($redirect = $this->guardRhuUser()) {
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
                    ->where('type', 'immunization_day')
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
                    'type' => 'immunization_day',
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
        if ($redirect = $this->guardRhuUser()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');
        $areaLabel = $request->input('area_label');

        $query = CalendarEvent::where('health_unit_id', $healthUnitId)
            ->where('type', 'immunization_day')
            ->whereDate('event_date', '>=', now()->toDateString());

        if ($areaLabel) {
            $query->where('area_label', $areaLabel);
        }

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

        $rows = DB::table('immunization_records')
            ->join('public_users', 'public_users.id', '=', 'immunization_records.public_user_id')
            ->when(session('role') === 'midwife', fn ($q) => $q->where('public_users.assigned_midwife_id', session('user_id')))
            ->where('public_users.health_unit_id', $healthUnitId)
            ->whereNotNull('public_users.barangay')
            ->where('public_users.barangay', '!=', '')
            ->select('public_users.barangay',
                DB::raw('COUNT(DISTINCT immunization_records.public_user_id) as immunized'))
            ->groupBy('public_users.barangay')
            ->orderByDesc('immunized')
            ->get();

        $totals = PublicUser::forViewer()
            ->where('health_unit_id', $healthUnitId)
            ->whereNotNull('barangay')
            ->where('barangay', '!=', '')
            ->whereNotNull('birth_date')
            ->where('birth_date', '>=', now()->subYears(5))
            ->select('barangay', DB::raw('COUNT(*) as total'))
            ->groupBy('barangay')
            ->pluck('total', 'barangay');

        $labels = $rows->pluck('barangay');
        $data = $rows->pluck('immunized')->map(fn ($v) => (int) $v);
        $percent = collect();

        foreach ($labels as $idx => $barangay) {
            $total = (int) ($totals[$barangay] ?? 0);
            $imms = (int) ($data[$idx] ?? 0);
            $percent->push($total === 0 ? 0 : (int) round(($imms / $total) * 100));
        }

        return response()->json([
            'labels' => $labels,
            'data' => $data,
            'totals' => $labels->map(fn ($b) => (int) ($totals[$b] ?? 0)),
            'percent' => $percent,
        ]);
    }
}
