<?php

namespace App\Http\Controllers;

use App\Helpers\ActivityLogger;
use App\Models\HealthUnit;
use App\Models\MidwifeBarangay;
use App\Models\PublicUser;
use App\Models\User;
use App\Support\CredentialRules;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private function checkAdminAccess()
    {
        if (! session()->has('user_id')) {
            return redirect()->route('login');
        }
        if (session('role') !== 'admin') {
            return redirect()->route('dashboard')
                ->with('error', 'Only admin can access the User Management module.');
        }

        return null;
    }

    private function allowedRoles(): array
    {
        if (is_null(session('health_unit_id'))) {
            return ['admin', 'staff'];
        }

        return ['staff', 'midwife'];
    }

    private function canCreateMidwives(): bool
    {
        return ! is_null(session('health_unit_id')) && session('role') === 'admin';
    }

    public function index(Request $request)
    {
        if ($redirect = $this->checkAdminAccess()) {
            return $redirect;
        }

        $search = $request->search;
        $rhuFilter = $request->rhu_filter;
        $roleFilter = $request->role;

        $users = User::with('healthUnit')
            ->when(session('health_unit_id'), function ($query) {
                $query->where('health_unit_id', session('health_unit_id'));
            })
            ->when(is_null(session('health_unit_id')) && $rhuFilter, function ($query) use ($rhuFilter) {
                $query->where('health_unit_id', $rhuFilter);
            })
            ->when($roleFilter, function ($query) use ($roleFilter) {
                $query->where('role', $roleFilter);
            })
            ->where('id', '!=', session('user_id'))
            ->where('is_emergency', false)
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%");
                });
            })
            ->orderBy('name', 'asc')
            ->paginate(config('pagination.inventory', 20))
            ->appends([
                'search' => $search,
                'rhu_filter' => $rhuFilter,
                'role' => $roleFilter,
            ]);

        $healthUnits = HealthUnit::rhuOnly()->orderBy('name')->get();
        $canCreateMidwives = $this->canCreateMidwives();

        return view('users.index', compact('users', 'healthUnits', 'canCreateMidwives'));
    }

    public function create()
    {
        if ($redirect = $this->checkAdminAccess()) {
            return $redirect;
        }

        if (is_null(session('health_unit_id'))) {
            $healthUnits = HealthUnit::rhuOnly()->orderBy('name')->get();
        } else {
            $healthUnits = HealthUnit::where('id', session('health_unit_id'))->get();
        }

        $allowedRoles = $this->allowedRoles();
        $canCreateMidwife = $this->canCreateMidwives();

        // ← NEW: barangay options for the midwife assignment checkbox list
        $barangayOptions = [];
        if ($canCreateMidwife) {
            $barangayOptions = $this->barangayOptionsForCurrentRhu();
        }

        return view('users.create', compact('healthUnits', 'allowedRoles', 'canCreateMidwife', 'barangayOptions'));
    }

    public function store(Request $request)
    {
        if ($redirect = $this->checkAdminAccess()) {
            return $redirect;
        }

        $allowedRoles = $this->allowedRoles();

        $request->validate([
            'name' => 'required|string|max:150',
            'username' => 'required|string|max:50|unique:users,username',
            'password' => CredentialRules::password(true),
            'role' => ['required', Rule::in($allowedRoles)],
            'health_unit_id' => 'required|exists:health_units,id',
            'barangays' => 'nullable|array',                      // ← NEW
            'barangays.*' => 'string|max:100',                      // ← NEW
            'primary_barangay' => 'nullable|string|max:100',           // ← NEW
        ]);

        if (session('health_unit_id') && (int) $request->health_unit_id !== (int) session('health_unit_id')) {
            return back()->withInput()->withErrors([
                'health_unit_id' => 'You can only create accounts for your own RHU.',
            ]);
        }

        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'health_unit_id' => $request->health_unit_id,
                'is_active' => true,
            ]);

            // ← NEW: save barangay assignments if the new user is a midwife
            if ($user->role === 'midwife' && $request->has('barangays')) {
                $this->saveBarangayAssignments(
                    $user,
                    $request->input('barangays', []),
                    $request->input('primary_barangay')
                );
            }

            return $user;
        });

        ActivityLogger::log(
            'Users',
            'Create',
            'Created user account for '.$user->name.' ('.$user->role.').'
        );

        return redirect()->route('users.index')->with('success', 'User account created successfully.');
    }

    public function edit($id)
    {
        if ($redirect = $this->checkAdminAccess()) {
            return $redirect;
        }

        $user = User::where('is_emergency', false)
            ->when(session('health_unit_id'), function ($q) {
                $q->where('health_unit_id', session('health_unit_id'));
            })
            ->findOrFail($id);

        if (is_null(session('health_unit_id'))) {
            $healthUnits = HealthUnit::rhuOnly()->orderBy('name')->get();
        } else {
            $healthUnits = HealthUnit::where('id', session('health_unit_id'))->get();
        }

        $allowedRoles = $this->allowedRoles();
        if (! in_array($user->role, $allowedRoles)) {
            $allowedRoles[] = $user->role;
        }

        // ← NEW: existing barangay assignments + all options for the checkbox list
        $barangayOptions = [];
        $assignedBarangays = [];
        $primaryBarangay = null;

        if ($user->role === 'midwife') {
            $barangayOptions = $this->barangayOptionsForCurrentRhu();

            $assignments = MidwifeBarangay::where('user_id', $user->id)->get();
            $assignedBarangays = $assignments->pluck('barangay')->toArray();
            $primaryBarangay = $assignments->firstWhere('is_primary', true)?->barangay;
        }

        return view('users.edit', compact(
            'user', 'healthUnits', 'allowedRoles',
            'barangayOptions', 'assignedBarangays', 'primaryBarangay'
        ));
    }

    public function update(Request $request, $id)
    {
        if ($redirect = $this->checkAdminAccess()) {
            return $redirect;
        }

        $user = User::where('is_emergency', false)
            ->when(session('health_unit_id'), function ($q) {
                $q->where('health_unit_id', session('health_unit_id'));
            })
            ->findOrFail($id);

        $allowedRoles = $this->allowedRoles();
        if (! in_array($user->role, $allowedRoles)) {
            $allowedRoles[] = $user->role;
        }

        $request->validate([
            'name' => 'required|string|max:150',
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'role' => ['required', Rule::in($allowedRoles)],
            'password' => CredentialRules::password(),
            'health_unit_id' => 'required|exists:health_units,id',
            'barangays' => 'nullable|array',                      // ← NEW
            'barangays.*' => 'string|max:100',                      // ← NEW
            'primary_barangay' => 'nullable|string|max:100',           // ← NEW
        ]);

        if (session('health_unit_id') && (int) $request->health_unit_id !== (int) session('health_unit_id')) {
            return back()->withInput()->withErrors([
                'health_unit_id' => 'You can only manage accounts within your own RHU.',
            ]);
        }

        // Prevent demotion of the last admin
        $adminCount = User::where('health_unit_id', $request->health_unit_id)
            ->where('role', 'admin')
            ->where('is_emergency', false)
            ->when($user->role === 'admin', function ($q) use ($user) {
                $q->where('id', '!=', $user->id);
            })
            ->count();

        if ($user->role === 'admin' && $request->role !== 'admin' && $adminCount < 1) {
            return redirect()->route('users.index')
                ->with('error', 'You cannot change the last admin of this RHU into a different role.');
        }

        $oldName = $user->name;
        $oldRole = $user->role;

        DB::transaction(function () use ($user, $request) {
            $data = [
                'name' => $request->name,
                'username' => $request->username,
                'role' => $request->role,
                'health_unit_id' => $request->health_unit_id,
            ];

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);

            // ← NEW: sync barangay assignments
            if ($user->role === 'midwife') {
                $this->saveBarangayAssignments(
                    $user,
                    $request->input('barangays', []),
                    $request->input('primary_barangay')
                );
            } else {
                // If role changed away from midwife, clear the assignments
                MidwifeBarangay::where('user_id', $user->id)->delete();
            }
        });

        ActivityLogger::log(
            'Users',
            'Update',
            'Updated user account for '.$oldName.' from role '.$oldRole.' to '.$user->role.'.'
        );

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy($id)
    {
        if ($redirect = $this->checkAdminAccess()) {
            return $redirect;
        }

        $user = User::where('is_emergency', false)
            ->when(session('health_unit_id'), function ($q) {
                $q->where('health_unit_id', session('health_unit_id'));
            })
            ->findOrFail($id);

        if ((int) $user->id === (int) session('user_id')) {
            return redirect()->route('users.index')
                ->with('error', 'You cannot delete your own account while logged in.');
        }

        $adminCount = User::where('health_unit_id', $user->health_unit_id)
            ->where('role', 'admin')
            ->where('is_emergency', false)
            ->count();

        if ($user->role === 'admin' && $adminCount <= 1) {
            return redirect()->route('users.index')
                ->with('error', 'You cannot delete the last admin of this RHU.');
        }

        try {
            ActivityLogger::log(
                'Users',
                'Delete',
                'Deleted user account for '.$user->name.' ('.$user->role.').'
            );

            // ← NEW: reassign or clear any patients this midwife was handling
            if ($user->role === 'midwife') {
                PublicUser::where('assigned_midwife_id', $user->id)
                    ->update(['assigned_midwife_id' => null]);
            }

            MidwifeBarangay::where('user_id', $user->id)->delete();

            $user->delete();

            return redirect()->route('users.index')->with('success', 'User deleted successfully.');
        } catch (QueryException $e) {
            return redirect()->route('users.index')
                ->with('error', 'This user cannot be deleted because it is already used by other records.');
        }
    }

    public function editOwn()
    {
        $user = User::findOrFail(session('user_id'));

        return view('settings.edit', compact('user'));
    }

    public function updateOwn(Request $request)
    {
        $user = User::findOrFail(session('user_id'));

        $request->validate([
            'name' => 'required|string|max:150',
            'password' => CredentialRules::password(),
        ]);

        $data = ['name' => $request->name];
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);
        session(['user_name' => $user->name]);

        ActivityLogger::log(
            'Settings',
            'Update',
            'User '.$user->name.' updated their own account settings.'
        );

        return redirect()->route('settings')->with('success', 'Your account has been updated.');
    }

    // ═══════════════════════════════════════════════════════════
    //  MIDWIFE BARANGAY ASSIGNMENT HELPERS  ← NEW
    // ═══════════════════════════════════════════════════════════

    /**
     * Distinct barangays from public_users within the current RHU.
     * Used to populate the checkbox list on the user form.
     */
    private function barangayOptionsForCurrentRhu(): array
    {
        $rhuId = session('health_unit_id');
        if (! $rhuId) {
            return [];
        }

        return PublicUser::where('health_unit_id', $rhuId)
            ->whereNotNull('barangay')
            ->where('barangay', '!=', '')
            ->distinct()
            ->orderBy('barangay')
            ->pluck('barangay')
            ->toArray();
    }

    /**
     * Persist a midwife's barangay assignments.
     * If $primary is set, it will be the only row with is_primary = true
     * for that barangay across all midwives.
     */
    private function saveBarangayAssignments(User $user, array $barangays, ?string $primary): void
    {
        // Wipe old assignments
        MidwifeBarangay::where('user_id', $user->id)->delete();

        $barangays = array_values(array_unique(array_filter($barangays)));
        if (empty($barangays)) {
            return;
        }

        foreach ($barangays as $brgy) {
            $isPrimary = ($primary === $brgy);

            // Demote any other midwife marked primary for the same barangay
            if ($isPrimary) {
                MidwifeBarangay::where('barangay', $brgy)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            MidwifeBarangay::create([
                'user_id' => $user->id,
                'barangay' => $brgy,
                'is_primary' => $isPrimary,
            ]);
        }
    }
}
