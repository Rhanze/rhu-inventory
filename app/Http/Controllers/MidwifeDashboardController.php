<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\Disease;
use App\Models\DiseaseReport;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Notification;
use App\Models\PublicUser;
use App\Models\TransferRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MidwifeDashboardController extends Controller
{
    /**
     * DOH EPI Schedule — age in weeks or months from birth.  // ← NEW
     * Source: DOH National Immunization Program.
     */
    private const EPI_SCHEDULE = [
        ['vaccine' => 'BCG',    'age_weeks' => 0,  'age_months' => 0,  'dose' => 1],
        ['vaccine' => 'HepB1',  'age_weeks' => 0,  'age_months' => 0,  'dose' => 1],
        ['vaccine' => 'HepB2',  'age_weeks' => 6,  'age_months' => 0,  'dose' => 2],
        ['vaccine' => 'HepB3',  'age_weeks' => 14, 'age_months' => 0,  'dose' => 3],
        ['vaccine' => 'Penta1', 'age_weeks' => 6,  'age_months' => 0,  'dose' => 1],
        ['vaccine' => 'Penta2', 'age_weeks' => 10, 'age_months' => 0,  'dose' => 2],
        ['vaccine' => 'Penta3', 'age_weeks' => 14, 'age_months' => 0,  'dose' => 3],
        ['vaccine' => 'OPV1',   'age_weeks' => 6,  'age_months' => 0,  'dose' => 1],
        ['vaccine' => 'OPV2',   'age_weeks' => 10, 'age_months' => 0,  'dose' => 2],
        ['vaccine' => 'OPV3',   'age_weeks' => 14, 'age_months' => 0,  'dose' => 3],
        ['vaccine' => 'IPV',    'age_weeks' => 14, 'age_months' => 0,  'dose' => 1],
        ['vaccine' => 'PCV1',   'age_weeks' => 6,  'age_months' => 0,  'dose' => 1],
        ['vaccine' => 'PCV2',   'age_weeks' => 10, 'age_months' => 0,  'dose' => 2],
        ['vaccine' => 'PCV3',   'age_weeks' => 14, 'age_months' => 0,  'dose' => 3],
        ['vaccine' => 'MMR1',   'age_weeks' => 0,  'age_months' => 9,  'dose' => 1],
        ['vaccine' => 'MMR2',   'age_weeks' => 0,  'age_months' => 12, 'dose' => 2],
    ];

    public function index()
    {
        if (! session()->has('user_id')) {
            return redirect()->route('login');
        }

        if (session('role') !== 'midwife') {
            return redirect()->route('dashboard');
        }

        $healthUnitId = session('health_unit_id');

        if (! $healthUnitId) {
            return redirect()->route('login')
                ->with('error', 'Midwife accounts must be assigned to an RHU.');
        }

        $user = User::find(session('user_id'));
        $healthUnit = HealthUnit::find($healthUnitId);

        // ═══════════ Snapshot ═══════════
        $dispensedToday = DispensingRecord::where('dispensed_by', session('user_id'))->where('health_unit_id', $healthUnitId)
            ->whereDate('date_dispensed', today())
            ->count();

        $dispensedThisMonth = DispensingRecord::where('dispensed_by', session('user_id'))->where('health_unit_id', $healthUnitId)
            ->whereMonth('date_dispensed', now()->month)
            ->whereYear('date_dispensed', now()->year)
            ->count();

        $activePatients = PublicUser::forViewer()->where('health_unit_id', $healthUnitId)->count();

        $pendingRequests = Allocation::where('allocated_by', session('user_id'))->where('health_unit_id', $healthUnitId)
            ->where('status', 'pending')
            ->count();

        // ═══════════ Recent dispensing ═══════════
        $recentDispensing = DispensingRecord::where('dispensed_by', session('user_id'))->with(['medicine'])
            ->where('health_unit_id', $healthUnitId)
            ->orderByDesc('date_dispensed')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        // ═══════════ Incoming transfers ═══════════
        $incomingTransfers = TransferRequest::with(['medicine', 'fromHealthUnit'])
            ->where('to_health_unit_id', $healthUnitId)
            ->where('status', 'accepted')
            ->orderByDesc('approved_at')
            ->limit(5)
            ->get();

        // ═══════════ Recent supply requests ═══════════
        $recentRequests = Allocation::where('allocated_by', session('user_id'))->with(['medicine'])
            ->where('health_unit_id', $healthUnitId)
            ->orderByDesc('date_allocated')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        // ═══════════ Unread notifications ═══════════
        $unreadNotifications = Notification::where('health_unit_id', $healthUnitId)
            ->where('is_read', false)
            ->whereNull('archived_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // ═══════════ Disease trend data ═══════════
        $diseases = Disease::active()
            ->whereHas('reports', function ($q) use ($healthUnitId) {
                $q->where('health_unit_id', $healthUnitId)
                    ->where('report_date', '>=', now()->subDays(90));
            })
            ->orderBy('name')
            ->get();

        $weekStart = now()->startOfWeek();
        $weekCases = [];
        foreach ($diseases as $d) {
            $weekCases[$d->id] = (int) DiseaseReport::where('health_unit_id', $healthUnitId)
                ->where('disease_id', $d->id)
                ->where('report_date', '>=', $weekStart)
                ->sum('case_count');
        }

        $outbreakAlerts = DiseaseReport::with('disease')
            ->where('health_unit_id', $healthUnitId)
            ->where('is_outbreak', true)
            ->where('report_date', '>=', now()->subDays(30))
            ->orderByDesc('report_date')
            ->get();

        $totalCasesThisMonth = (int) DiseaseReport::where('health_unit_id', $healthUnitId)
            ->where('report_date', '>=', now()->startOfMonth())
            ->sum('case_count');

        $allDiseases = Disease::active()->orderBy('name')->get();

        // ═══════════ IMMUNIZATION CALENDAR — NEXT 14 DAYS ═══════════  // ← NEW
        $calendarPreview = $this->buildDueListByDay(
            (int) $healthUnitId,
            now()->startOfDay(),
            now()->addDays(13)->endOfDay()
        );

        // ═══════════ BARANGAY IMMUNIZATION COVERAGE ═══════════  // ← NEW
        $barangayCoverage = $this->buildBarangayCoverage((int) $healthUnitId);

        return view('dashboard.midwife', compact(
            'user',
            'healthUnit',
            'dispensedToday',
            'dispensedThisMonth',
            'activePatients',
            'pendingRequests',
            'recentDispensing',
            'incomingTransfers',
            'recentRequests',
            'unreadNotifications',
            'diseases',
            'weekCases',
            'outbreakAlerts',
            'totalCasesThisMonth',
            'allDiseases',
            'calendarPreview',      // ← NEW
            'barangayCoverage'      // ← NEW
        ));
    }

    /**
     * Full vaccination calendar page.  // ← NEW
     */
    public function vaccinationCalendar(Request $request)
    {
        if (! session()->has('user_id') || session('role') !== 'midwife') {
            return redirect()->route('login');
        }

        $healthUnitId = session('health_unit_id');
        if (! $healthUnitId) {
            return redirect()->route('dashboard');
        }

        $month = $request->get('month', now()->format('Y-m'));
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Grid range: whole weeks covering the month
        $gridStart = $start->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $end->copy()->endOfWeek(Carbon::SATURDAY);

        // Compute due list for entire grid
        $dueByDay = $this->buildDueListByDay((int) $healthUnitId, $gridStart, $gridEnd);

        // Build week grid
        $weeks = [];
        $cursor = $gridStart->copy();
        while ($cursor <= $gridEnd) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $key = $cursor->toDateString();
                $items = $dueByDay[$key] ?? [];

                $week[] = [
                    'date' => $cursor->copy(),
                    'in_month' => $cursor->month === $start->month,
                    'is_today' => $cursor->isToday(),
                    'count' => count($items),
                    'overdue' => collect($items)->where('is_overdue', true)->count(),
                    'items' => $items,
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        // Selected day
        $selectedDate = $request->get('day', now()->toDateString());
        $selectedSchedules = $dueByDay[$selectedDate] ?? [];

        // Month stats
        $flat = collect($dueByDay)->flatten(1);
        $monthStats = [
            'total_pending' => $flat->where('is_overdue', false)->count(),
            'total_overdue' => $flat->where('is_overdue', true)->count(),
            'total_given' => 0, // historical given records aren't in the due list
        ];

        return view('vaccination.calendar', compact(
            'weeks', 'start', 'end', 'month',
            'selectedDate', 'selectedSchedules', 'monthStats'
        ));
    }

    // ═══════════════════════════════════════════════════════════════  // ← NEW
    // HELPER METHODS                                                    // ← NEW
    // ═══════════════════════════════════════════════════════════════  // ← NEW

    /**
     * Compute due doses per day in a date range, based on birthdates.
     * Returns array keyed by Y-m-d; each value = array of due items.
     */
    private function buildDueListByDay(int $healthUnitId, Carbon $start, Carbon $end): array
    {
        // Pull all children under 5 with a birthdate in this health unit
        $children = PublicUser::forViewer()->where('health_unit_id', $healthUnitId)
            ->whereNotNull('birth_date')
            ->where('birth_date', '>=', now()->subYears(5))
            ->get();

        if ($children->isEmpty()) {
            return [];
        }

        $childIds = $children->pluck('id')->toArray();

        // Fetch given vaccines per child (single query for performance)
        $givenByChild = [];
        if (Schema::hasTable('immunization_records')) {
            $records = DB::table('immunization_records')
                ->whereIn('public_user_id', $childIds)
                ->get();

            foreach ($records as $r) {
                $vax = strtolower($r->vaccine ?? '');
                $dose = (int) ($r->dose_number ?? 1);
                $code = $vax.':'.$dose;
                $givenByChild[$r->public_user_id][$code] = true;
            }
        }

        $byDay = [];

        foreach ($children as $child) {
            $birth = Carbon::parse($child->birth_date);

            // Build a lookup set of vaccines already given to this child
            $givenCodes = $givenByChild[$child->id] ?? [];

            foreach (self::EPI_SCHEDULE as $rule) {
                // Compute the due date
                if ($rule['age_weeks'] > 0) {
                    $dueDate = $birth->copy()->addWeeks($rule['age_weeks']);
                } elseif ($rule['age_months'] > 0) {
                    $dueDate = $birth->copy()->addMonths($rule['age_months']);
                } else {
                    $dueDate = $birth->copy();
                }

                // Skip if not in our window
                if ($dueDate->lt($start) || $dueDate->gt($end)) {
                    continue;
                }

                // Skip if already given
                $code = strtolower($rule['vaccine']).':'.$rule['dose'];
                if (isset($givenCodes[$code])) {
                    continue;
                }

                $key = $dueDate->toDateString();
                if (! isset($byDay[$key])) {
                    $byDay[$key] = [];
                }

                $byDay[$key][] = [
                    'child_id' => $child->id,
                    'child_name' => $child->full_name ?? trim(($child->first_name ?? '').' '.($child->last_name ?? '')),
                    'barangay' => $child->barangay ?? '—',
                    'vaccine' => $rule['vaccine'],
                    'dose' => $rule['dose'],
                    'due_date' => $dueDate->toDateString(),
                    'is_overdue' => $dueDate->lt(now()->startOfDay()),
                ];
            }
        }

        return $byDay;
    }

    /**
     * Immunization coverage per barangay — for the bar chart.
     */
    private function buildBarangayCoverage(int $healthUnitId): array
    {
        // Total children under 5 per barangay (denominator)
        $totals = PublicUser::forViewer()->where('health_unit_id', $healthUnitId)
            ->whereNotNull('birth_date')
            ->where('birth_date', '>=', now()->subYears(5))
            ->whereNotNull('barangay')
            ->where('barangay', '!=', '')
            ->select('barangay', DB::raw('COUNT(*) as total'))
            ->groupBy('barangay')
            ->orderByDesc('total')
            ->pluck('total', 'barangay');

        if ($totals->isEmpty()) {
            return [
                'labels' => [],
                'immunized' => [],
                'totals' => [],
                'percent' => [],
            ];
        }

        // Immunized children per barangay (at least 1 dose recorded)
        $immunized = collect();
        if (Schema::hasTable('immunization_records')) {
            $immunized = DB::table('immunization_records')
                ->join('public_users', 'public_users.id', '=', 'immunization_records.public_user_id')
                ->when(session('role') === 'midwife', fn ($q) => $q->where('public_users.assigned_midwife_id', session('user_id')))
                ->where('public_users.health_unit_id', $healthUnitId)
                ->whereIn('public_users.barangay', $totals->keys()->toArray())
                ->select(
                    'public_users.barangay',
                    DB::raw('COUNT(DISTINCT immunization_records.public_user_id) as count')
                )
                ->groupBy('public_users.barangay')
                ->pluck('count', 'barangay');
        }

        $labels = $totals->keys()->toArray();
        $immCounts = [];
        $percents = [];

        foreach ($labels as $b) {
            $imm = (int) ($immunized[$b] ?? 0);
            $tot = (int) ($totals[$b] ?? 0);
            $immCounts[] = $imm;
            $percents[] = $tot > 0 ? (int) round(($imm / $tot) * 100) : 0;
        }

        return [
            'labels' => $labels,
            'immunized' => $immCounts,
            'totals' => $totals->values()->toArray(),
            'percent' => $percents,
        ];
    }
}
