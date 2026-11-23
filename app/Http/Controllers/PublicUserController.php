<?php

namespace App\Http\Controllers;

use App\Models\DispensingRecord;
use App\Models\MidwifeBarangay;
use App\Models\PublicUser;
use App\Models\User;
use App\Services\VaccinationScheduleService;
use App\Support\BarangayDemandScope;
use App\Support\ClinicalAudit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicUserController extends Controller
{
    protected VaccinationScheduleService $scheduleService;

    public function __construct(VaccinationScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
    }

    private function checkRhuAccess()
    {
        if (! session('user_id')
            || ! in_array(session('role'), ['admin', 'staff', 'midwife'])) {
            return redirect()->route('login');
        }
        if (is_null(session('health_unit_id'))) {
            return redirect()->route('dashboard')
                ->with('error', 'Public users are managed at the RHU level only.');
        }

        return null;
    }

    /**
     * Figure out which midwife a patient belongs to, based on barangay.
     * Priority:
     *   1. The current midwife, after assigned-barangay validation
     *   2. The primary midwife for that barangay
     *   3. Any midwife assigned to that barangay
     *   4. null
     */
    private function resolveMidwifeForBarangay(?string $barangay): ?int
    {
        // Midwife inputs have already passed assigned-area validation. Keep the
        // patient with that account even when colleagues cover the same area.
        if (session('role') === 'midwife') {
            return (int) session('user_id');
        }

        if ($barangay) {
            $assignments = MidwifeBarangay::where('barangay', $barangay)
                ->whereHas('user', fn ($q) => $q->where('health_unit_id', session('health_unit_id'))
                    ->where('role', 'midwife')->where('is_active', true));
            $primary = (clone $assignments)
                ->where('is_primary', true)
                ->orderBy('user_id')
                ->value('user_id');
            if ($primary) {
                return (int) $primary;
            }

            $any = $assignments->orderBy('user_id')->value('user_id');
            if ($any) {
                return (int) $any;
            }
        }

        return null;
    }

    private function barangayOptions()
    {
        if (session('role') === 'midwife') {
            return BarangayDemandScope::options((int) session('health_unit_id'), (int) session('user_id'));
        }

        return PublicUser::where('health_unit_id', session('health_unit_id'))->whereNotNull('barangay')
            ->where('barangay', '!=', '')->distinct()->orderBy('barangay')->pluck('barangay');
    }

    public function index(Request $request)
    {
        if ($redirect = $this->checkRhuAccess()) {
            return $redirect;
        }

        if ($request->query->has('search')) {
            return redirect()->route('public_users.index', $request->only('barangay', 'age_group', 'assigned_midwife_id'));
        }

        $request->validate(['barangay' => 'nullable|string|max:100', 'criteria' => 'nullable|uuid']);
        $criteria = $request->input('criteria');
        $stored = $criteria ? $request->session()->get('patient_directory_searches', [])[$criteria] ?? null : null;
        $search = $stored && $stored['user'] === session('user_id') && $stored['facility'] === session('health_unit_id')
            ? $stored['term'] : null;
        $barangay = BarangayDemandScope::validateForViewer((int) session('health_unit_id'), $request->input('barangay'));
        if (session('role') === 'midwife' && $barangay !== null) {
            $request->merge(['barangay' => $barangay]);
        }
        $rawAgeGroup = $request->age_group;
        $assignedFilter = $request->assigned_midwife_id;   // ← NEW
        $healthUnitId = session('health_unit_id');
        $isMidwife = session('role') === 'midwife';

        $ageGroup = [
            'under_1' => 'infant',
            'under_5' => 'child',
        ][$rawAgeGroup] ?? $rawAgeGroup;

        $today = Carbon::today();

        $query = PublicUser::where('health_unit_id', $healthUnitId)
            // ← CHANGED: midwives see their ASSIGNED patients, not just the ones they registered
            ->when($isMidwife, fn ($q) => $q->where('assigned_midwife_id', session('user_id')))
            ->when($search, function ($q, $s) {
                $q->where(function ($sub) use ($s) {
                    $sub->where('first_name', 'like', "%{$s}%")
                        ->orWhere('last_name', 'like', "%{$s}%")
                        ->orWhere('philhealth_id', 'like', "%{$s}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$s}%"]);
                });
            })
            ->when($barangay, fn ($q) => $q->where('barangay', $barangay))
            // ← NEW: admin/staff can filter by assigned midwife
            ->when(! $isMidwife && $assignedFilter, fn ($q) => $q->where('assigned_midwife_id', $assignedFilter))
            ->when($ageGroup === 'infant', function ($q) use ($today) {
                $q->whereNotNull('birth_date')
                    ->where('birth_date', '>', $today->copy()->subYear()->toDateString());
            })
            ->when($ageGroup === 'child', function ($q) use ($today) {
                $q->whereNotNull('birth_date')
                    ->where('birth_date', '<=', $today->copy()->subYear()->toDateString())
                    ->where('birth_date', '>', $today->copy()->subYears(5)->toDateString());
            })
            ->when($ageGroup === 'school_age', function ($q) use ($today) {
                $q->whereNotNull('birth_date')
                    ->where('birth_date', '<=', $today->copy()->subYears(5)->toDateString())
                    ->where('birth_date', '>', $today->copy()->subYears(18)->toDateString());
            })
            ->when($ageGroup === 'adult', function ($q) use ($today) {
                $q->whereNotNull('birth_date')
                    ->where('birth_date', '<=', $today->copy()->subYears(18)->toDateString())
                    ->where('birth_date', '>', $today->copy()->subYears(60)->toDateString());
            })
            ->when($ageGroup === 'pregnant', fn ($q) => $q->where('is_pregnant', true))
            ->when($ageGroup === 'senior', function ($q) use ($today) {
                $q->whereNotNull('birth_date')
                    ->where('birth_date', '<=', $today->copy()->subYears(60)->toDateString());
            });

        $members = $query
            ->with('assignedMidwife:id,name')   // ← NEW
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->appends([
                'criteria' => $search ? $criteria : null,
                'barangay' => $barangay,
                'age_group' => $rawAgeGroup,
                'assigned_midwife_id' => $assignedFilter,
            ]);

        $barangays = $this->barangayOptions();

        // ← NEW: dropdown of midwives for admin/staff filter
        $midwifeList = $isMidwife
            ? collect()
            : User::where('health_unit_id', $healthUnitId)
                ->where('role', 'midwife')
                ->orderBy('name')
                ->get(['id', 'name']);

        return view('public_users.index', compact(
            'members', 'barangays', 'midwifeList', 'search'
        ));
    }

    public function filter(Request $request)
    {
        if ($redirect = $this->checkRhuAccess()) {
            return $redirect;
        }

        $filters = $request->validate([
            'search' => 'nullable|string|max:120',
            'barangay' => 'nullable|string|max:100',
            'age_group' => 'nullable|string|max:32',
            'assigned_midwife_id' => 'nullable|integer|min:1',
        ]);
        $term = trim($filters['search'] ?? '');
        unset($filters['search']);
        if ($term !== '') {
            $token = (string) Str::uuid();
            $previous = $request->session()->get('patient_directory_searches', []);
            $request->session()->put('patient_directory_searches', [
                $token => ['term' => $term, 'user' => session('user_id'), 'facility' => session('health_unit_id')],
            ] + array_slice($previous, 0, 9, true));
            $filters['criteria'] = $token;
        }

        return redirect()->route('public_users.index', $filters);
    }

    public function create()
    {
        if ($redirect = $this->checkRhuAccess()) {
            return $redirect;
        }

        $isMidwife = session('role') === 'midwife';

        $barangays = $this->barangayOptions();

        return view('public_users.create', compact('barangays'));
    }

    public function store(Request $request)
    {
        if ($redirect = $this->checkRhuAccess()) {
            return $redirect;
        }

        $validated = $request->validate([
            'philhealth_id' => 'nullable|string|max:50|unique:public_users,philhealth_id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'birth_date' => 'required|date|before_or_equal:today',
            'sex' => 'nullable|in:male,female',
            'barangay' => 'required|string|max:100',
            'address' => 'required|string|max:255',
            'contact_number' => 'nullable|string|max:20',
            'household_id' => 'nullable|string|max:50',
            'family_serial_no' => 'nullable|string|max:50',
            'is_pregnant' => 'nullable|boolean',
            'lmp' => 'nullable|date|before_or_equal:today',
            'gravida' => 'nullable|integer|min:0|max:30',
            'para' => 'nullable|integer|min:0|max:30',
            'risk_codes' => 'nullable|string|max:500',
        ]);

        if (! $request->has('is_pregnant')) {
            $validated['is_pregnant'] = false;
        }
        $validated['barangay'] = BarangayDemandScope::validateForViewer((int) session('health_unit_id'), $validated['barangay'], 'barangay', true);

        // ← NEW: auto-assign the midwife for this barangay
        $assignedMidwifeId = $this->resolveMidwifeForBarangay($validated['barangay']);

        $attributes = [
            'health_unit_id' => session('health_unit_id'),
            'registered_by' => session('user_id'),
            'assigned_midwife_id' => $assignedMidwifeId,   // ← NEW
            'philhealth_id' => $validated['philhealth_id'] ?? null,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'birth_date' => $validated['birth_date'],
            'barangay' => $validated['barangay'],
            'address' => $validated['address'],
            'contact_number' => $validated['contact_number'] ?? null,
            'household_id' => $validated['household_id'] ?? null,
            'family_serial_no' => $validated['family_serial_no'] ?? null,
            'is_pregnant' => (bool) ($validated['is_pregnant'] ?? false),
            'lmp' => $validated['lmp'] ?? null,
            'gravida' => $validated['gravida'] ?? null,
            'para' => $validated['para'] ?? null,
            'risk_codes' => $validated['risk_codes'] ?? null,
        ];
        $created = DB::transaction(function () use ($attributes) {
            return $this->scheduleService->generateFor(PublicUser::create($attributes));
        }, 3);
        $scheduleMsg = $created > 0 ? " {$created} vaccination dose(s) scheduled." : '';

        return redirect()->route('public_users.index')
            ->with('success', 'Patient registered successfully.'.$scheduleMsg);
    }

    public function edit($id)
    {
        if ($redirect = $this->checkRhuAccess()) {
            return $redirect;
        }

        $member = PublicUser::where('health_unit_id', session('health_unit_id'))
            ->when(session('role') === 'midwife',
                fn ($q) => $q->where('assigned_midwife_id', session('user_id')))
            ->findOrFail($id);

        $isMidwife = session('role') === 'midwife';

        $barangays = $this->barangayOptions();

        // ← NEW: list of midwives for the reassign dropdown (admin/staff only)
        $midwifeList = $isMidwife
            ? collect()
            : User::where('health_unit_id', session('health_unit_id'))
                ->where('role', 'midwife')
                ->orderBy('name')
                ->get(['id', 'name']);

        return view('public_users.edit', compact('member', 'barangays', 'midwifeList'));
    }

    public function update(Request $request, $id)
    {
        if ($redirect = $this->checkRhuAccess()) {
            return $redirect;
        }

        DB::transaction(fn () => $this->updateLockedPatient($request, $id), 3);

        return redirect()->route('public_users.index')
            ->with('success', 'Patient record updated.');
    }

    private function updateLockedPatient(Request $request, $id): void
    {
        $member = PublicUser::where('health_unit_id', session('health_unit_id'))
            ->when(session('role') === 'midwife',
                fn ($q) => $q->where('assigned_midwife_id', session('user_id')))
            ->lockForUpdate()->findOrFail($id);

        $oldBarangay = $member->barangay;
        $keepHistoricalBarangay = session('role') === 'midwife' && ! $request->filled('barangay')
            && ! $this->barangayOptions()->containsStrict($oldBarangay);

        $validated = $request->validate([
            'philhealth_id' => [
                'nullable', 'string', 'max:50',
                Rule::unique('public_users', 'philhealth_id')->ignore($member->id),
            ],
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'birth_date' => 'required|date|before_or_equal:today',
            'barangay' => $keepHistoricalBarangay ? 'nullable|string|max:100' : 'required|string|max:100',
            'address' => 'required|string|max:255',
            'contact_number' => 'nullable|string|max:20',
            'household_id' => 'nullable|string|max:50',
            'family_serial_no' => 'nullable|string|max:50',
            'is_pregnant' => 'nullable|boolean',
            'lmp' => 'nullable|date|before_or_equal:today',
            'gravida' => 'nullable|integer|min:0|max:30',
            'para' => 'nullable|integer|min:0|max:30',
            'risk_codes' => 'nullable|string|max:500',
            'assigned_midwife_id' => ['nullable', Rule::exists('users', 'id')->where('health_unit_id', session('health_unit_id'))->where('role', 'midwife')->where('is_active', true)],   // ← NEW
        ]);

        if (! $request->has('is_pregnant')) {
            $validated['is_pregnant'] = false;
        }
        $validated['barangay'] = $keepHistoricalBarangay ? $oldBarangay
            : BarangayDemandScope::validateForViewer((int) session('health_unit_id'), $validated['barangay'], 'barangay', true);

        $newBirthDate = Carbon::parse($validated['birth_date'])->toDateString();
        $birthDateChanged = $member->birth_date?->toDateString() !== $newBirthDate;
        if ($birthDateChanged && ($member->immunizationRecords()->whereDate('date_given', '<', $newBirthDate)->exists()
            || $member->vaccinationSchedules()->given()->whereDate('given_date', '<', $newBirthDate)->exists())) {
            throw ValidationException::withMessages(['birth_date' => 'The birth date cannot be later than a recorded vaccination date.']);
        }
        if ($birthDateChanged && ($member->prenatalVisits()->whereDate('visit_date', '<', $newBirthDate)->exists()
            || $member->postpartumVisits()->whereDate('delivery_date', '<', $newBirthDate)->exists()
            || $member->familyPlanning()->whereDate('date_accepted', '<', $newBirthDate)->exists())) {
            throw ValidationException::withMessages(['birth_date' => 'The birth date cannot be later than a recorded clinical visit.']);
        }

        // ← NEW: figure out the assigned midwife
        if (! $member->assigned_midwife_id && session('role') !== 'midwife' && ! $request->filled('assigned_midwife_id')) {
            // If it's never been set, auto-assign
            $assignedMidwifeId = $this->resolveMidwifeForBarangay($validated['barangay']);
        } elseif (session('role') === 'midwife') {
            // Midwives cannot reassign; keep current or auto-assign
            $assignedMidwifeId = $member->assigned_midwife_id
                ?? $this->resolveMidwifeForBarangay($validated['barangay']);
        } else {
            // Admin/staff: use form value if provided, otherwise re-resolve on barangay change
            $assignedMidwifeId = $request->filled('assigned_midwife_id')
                ? $request->input('assigned_midwife_id')
                : ($validated['barangay'] !== $oldBarangay
                    ? $this->resolveMidwifeForBarangay($validated['barangay'])
                    : $member->assigned_midwife_id);
        }

        $member->update([
            'philhealth_id' => $validated['philhealth_id'] ?? null,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'birth_date' => $newBirthDate,
            'barangay' => $validated['barangay'],
            'address' => $validated['address'],
            'contact_number' => $validated['contact_number'] ?? null,
            'household_id' => $validated['household_id'] ?? null,
            'family_serial_no' => $validated['family_serial_no'] ?? null,
            'is_pregnant' => (bool) ($validated['is_pregnant'] ?? false),
            'lmp' => $validated['lmp'] ?? null,
            'gravida' => $validated['gravida'] ?? null,
            'para' => $validated['para'] ?? null,
            'risk_codes' => $validated['risk_codes'] ?? null,
            'assigned_midwife_id' => $assignedMidwifeId,   // ← NEW
        ]);

        if ($birthDateChanged) {
            $this->scheduleService->regenerateForCorrectedBirthDate($member);
            ClinicalAudit::record('Correct birth date', 'patient_directory', (int) $id);
        }
    }

    public function destroy($id)
    {
        if ($redirect = $this->checkRhuAccess()) {
            return $redirect;
        }

        if (session('role') === 'midwife') {
            return back()->with('error', 'Midwives cannot delete patient records. Ask an RHU admin or staff.');
        }

        $deleted = DB::transaction(function () use ($id) {
            $member = PublicUser::where('health_unit_id', session('health_unit_id'))->lockForUpdate()->findOrFail($id);
            if ($member->immunizationRecords()->exists() || $member->prenatalVisits()->withTrashed()->exists()
                || $member->postpartumVisits()->withTrashed()->exists() || $member->familyPlanning()->withTrashed()->exists()
                || $member->vaccinationSchedules()->whereIn('status', ['given', 'missed'])->exists()
                || DispensingRecord::withTrashed()->where('public_user_id', $member->id)->exists()) {
                return false;
            }

            $member->vaccinationSchedules()->delete();
            $member->delete();
            ClinicalAudit::record('Delete', 'patient_directory', (int) $id);

            return true;
        }, 3);
        if (! $deleted) {
            return back()->with('error', 'Cannot delete — this patient has clinical records. Preserve the history instead.');
        }

        return redirect()->route('public_users.index')
            ->with('success', 'Patient deleted.');
    }

    public function searchSuggestions(Request $request)
    {
        $validated = $request->validate(['q' => 'required|string|min:2|max:120']);
        $search = trim($validated['q']);
        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $healthUnitId = session('health_unit_id');
        if (is_null($healthUnitId)) {
            return response()->json([]);
        }

        $isMidwife = session('role') === 'midwife';

        $members = PublicUser::where('health_unit_id', $healthUnitId)
            // ← CHANGED: use assigned_midwife_id
            ->when($isMidwife, fn ($q) => $q->where('assigned_midwife_id', session('user_id')))
            ->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('philhealth_id', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            })
            ->select('id', 'first_name', 'last_name', 'birth_date', 'barangay')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(8)
            ->get();

        return response()->json($members->map(function ($m) {
            $cleanName = trim($m->first_name.' '.$m->last_name);
            $age = $m->birth_date ? Carbon::parse($m->birth_date)->age : '?';

            return [
                'id' => $m->id,
                'name' => $cleanName,
                'text' => $cleanName
                          .' ('.$age.'y'
                          .($m->barangay ? ', '.$m->barangay : '')
                          .')',
            ];
        }));
    }
}
