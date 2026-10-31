<?php

namespace App\Http\Controllers;

use App\Models\MidwifeBarangay;
use App\Models\PublicUser;
use App\Models\User;
use App\Models\VaccinationSchedule;
use App\Services\VaccinationScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
     *   1. The primary midwife for that barangay
     *   2. Any midwife assigned to that barangay
     *   3. The current user (if they're a midwife)
     *   4. null
     */
    private function resolveMidwifeForBarangay(?string $barangay): ?int
    {
        if ($barangay) {
            $primary = MidwifeBarangay::where('barangay', $barangay)
                ->where('is_primary', true)
                ->value('user_id');
            if ($primary) {
                return (int) $primary;
            }

            $any = MidwifeBarangay::where('barangay', $barangay)->value('user_id');
            if ($any) {
                return (int) $any;
            }
        }

        if (session('role') === 'midwife') {
            return (int) session('user_id');
        }

        return null;
    }

    public function index(Request $request)
    {
        if ($redirect = $this->checkRhuAccess()) {
            return $redirect;
        }

        $search = $request->search;
        $barangay = $request->barangay;
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
                'search' => $search,
                'barangay' => $barangay,
                'age_group' => $rawAgeGroup,
                'assigned_midwife_id' => $assignedFilter,
            ]);

        $barangays = PublicUser::where('health_unit_id', $healthUnitId)
            ->when($isMidwife, fn ($q) => $q->where('assigned_midwife_id', session('user_id')))
            ->whereNotNull('barangay')
            ->where('barangay', '!=', '')
            ->distinct()
            ->orderBy('barangay')
            ->pluck('barangay');

        // ← NEW: dropdown of midwives for admin/staff filter
        $midwifeList = $isMidwife
            ? collect()
            : User::where('health_unit_id', $healthUnitId)
                ->where('role', 'midwife')
                ->orderBy('name')
                ->get(['id', 'name']);

        return view('public_users.index', compact(
            'members', 'barangays', 'midwifeList'
        ));
    }

    public function create()
    {
        if ($redirect = $this->checkRhuAccess()) {
            return $redirect;
        }

        $isMidwife = session('role') === 'midwife';

        $barangays = PublicUser::where('health_unit_id', session('health_unit_id'))
            ->when($isMidwife, fn ($q) => $q->where('assigned_midwife_id', session('user_id')))
            ->whereNotNull('barangay')
            ->where('barangay', '!=', '')
            ->distinct()
            ->orderBy('barangay')
            ->pluck('barangay');

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

        // ← NEW: auto-assign the midwife for this barangay
        $assignedMidwifeId = $this->resolveMidwifeForBarangay($validated['barangay']);

        $member = PublicUser::create([
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
        ]);

        $scheduleMsg = '';
        if ($member->birth_date && $member->is_under_five) {
            $created = $this->scheduleService->generateFor($member);
            $scheduleMsg = $created > 0
                ? " {$created} vaccination dose(s) scheduled."
                : '';
        }

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

        $barangays = PublicUser::where('health_unit_id', session('health_unit_id'))
            ->when($isMidwife, fn ($q) => $q->where('assigned_midwife_id', session('user_id')))
            ->whereNotNull('barangay')
            ->where('barangay', '!=', '')
            ->distinct()
            ->orderBy('barangay')
            ->pluck('barangay');

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

        $member = PublicUser::where('health_unit_id', session('health_unit_id'))
            ->when(session('role') === 'midwife',
                fn ($q) => $q->where('assigned_midwife_id', session('user_id')))
            ->findOrFail($id);

        $oldBirthDate = $member->birth_date ? $member->birth_date->toDateString() : null;
        $oldBarangay = $member->barangay;

        $validated = $request->validate([
            'philhealth_id' => [
                'nullable', 'string', 'max:50',
                Rule::unique('public_users', 'philhealth_id')->ignore($member->id),
            ],
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'birth_date' => 'required|date|before_or_equal:today',
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
            'assigned_midwife_id' => ['nullable', Rule::exists('users', 'id')->where('health_unit_id', session('health_unit_id'))->where('role', 'midwife')->where('is_active', true)],   // ← NEW
        ]);

        if (! $request->has('is_pregnant')) {
            $validated['is_pregnant'] = false;
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
            'assigned_midwife_id' => $assignedMidwifeId,   // ← NEW
        ]);

        $newBirthDate = $member->fresh()->birth_date?->toDateString();
        if ($oldBirthDate !== $newBirthDate && $member->is_under_five) {
            VaccinationSchedule::where('public_user_id', $member->id)
                ->where('status', 'pending')
                ->delete();

            $this->scheduleService->generateFor($member);
        }

        return redirect()->route('public_users.index')
            ->with('success', 'Patient record updated.');
    }

    public function destroy($id)
    {
        if ($redirect = $this->checkRhuAccess()) {
            return $redirect;
        }

        if (session('role') === 'midwife') {
            return back()->with('error', 'Midwives cannot delete patient records. Ask an RHU admin or staff.');
        }

        $member = PublicUser::where('health_unit_id', session('health_unit_id'))
            ->findOrFail($id);

        if ($member->immunizationRecords()->exists()) {
            return back()->with('error',
                'Cannot delete — this patient has immunization records. Preserve the history instead.');
        }

        VaccinationSchedule::where('public_user_id', $member->id)->delete();

        $member->delete();

        return redirect()->route('public_users.index')
            ->with('success', 'Patient deleted.');
    }

    public function searchSuggestions(Request $request)
    {
        $search = trim($request->get('q', ''));
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
            ->select('id', 'philhealth_id', 'first_name', 'last_name', 'birth_date', 'barangay')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(8)
            ->get();

        return response()->json($members->map(function ($m) {
            $cleanName = trim($m->first_name.' '.$m->last_name);
            $idPart = $m->philhealth_id ? $m->philhealth_id.' — ' : '';
            $age = $m->birth_date ? Carbon::parse($m->birth_date)->age : '?';

            return [
                'id' => $m->id,
                'name' => $cleanName,
                'text' => $idPart.$cleanName
                          .' ('.$age.'y'
                          .($m->barangay ? ', '.$m->barangay : '')
                          .')',
            ];
        }));
    }
}
