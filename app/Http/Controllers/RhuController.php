<?php

namespace App\Http\Controllers;

use App\Helpers\ActivityLogger;
use App\Models\ActivityLog;
use App\Models\HealthUnit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RhuController extends Controller
{
    private function checkAdminAccess()
    {
        if (! session()->has('user_id')) {
            return redirect()->route('login');
        }

        abort_unless(session('role') === 'admin' && is_null(session('health_unit_id')), 403,
            'Only PHO administrators can manage RHU reference records.');

        return null;
    }

    public function index(Request $request)
    {
        if ($redirect = $this->checkAdminAccess()) {
            return $redirect;
        }

        $search = $request->search;

        $healthUnits = HealthUnit::when($search, function ($query, $search) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('municipality', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%");
        })
            ->orderBy('name', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(config('pagination.inventory', 20))
            ->appends(['search' => $search]);

        return view('rhu.index', compact('healthUnits'));
    }

    public function create()
    {
        if ($redirect = $this->checkAdminAccess()) {
            return $redirect;
        }

        return view('rhu.create');
    }

    public function store(Request $request)
    {
        if ($redirect = $this->checkAdminAccess()) {
            return $redirect;
        }

        $request->validate([
            'name' => 'required|string|max:150',
            'municipality' => 'required|string|max:100',
            'address' => 'required|string|max:255',
        ]);

        $healthUnit = HealthUnit::create([
            'name' => $request->name,
            'municipality' => $request->municipality,
            'address' => $request->address,
            'facility_type' => 'Rural Health Unit',
        ]);

        ActivityLogger::log(
            'RHU',
            'Create',
            'Added RHU: '.$healthUnit->name.'.',
            $healthUnit->id
        );

        return redirect()->route('rhu.index')->with('success', 'RHU added successfully.');
    }

    public function edit($id)
    {
        if ($redirect = $this->checkAdminAccess()) {
            return $redirect;
        }

        $healthUnit = HealthUnit::findOrFail($id);

        return view('rhu.edit', compact('healthUnit'));
    }

    public function update(Request $request, $id)
    {
        if ($redirect = $this->checkAdminAccess()) {
            return $redirect;
        }

        $request->validate([
            'name' => 'required|string|max:150',
            'municipality' => 'required|string|max:100',
            'address' => 'required|string|max:255',
        ]);

        $healthUnit = HealthUnit::findOrFail($id);
        $oldName = $healthUnit->name;

        $healthUnit->update([
            'name' => $request->name,
            'municipality' => $request->municipality,
            'address' => $request->address,
        ]);

        ActivityLogger::log(
            'RHU',
            'Update',
            'Updated RHU: '.$oldName.' to '.$healthUnit->name.'.',
            $healthUnit->id
        );

        return redirect()->route('rhu.index')->with('success', 'RHU updated successfully.');
    }

    public function destroy($id)
    {
        if ($redirect = $this->checkAdminAccess()) {
            return $redirect;
        }

        $healthUnit = HealthUnit::findOrFail($id);

        if (HealthUnit::count() <= 1) {
            return redirect()->route('rhu.index')
                ->with('error', 'You cannot delete the last remaining RHU.');
        }

        if ((int) session('health_unit_id') === (int) $healthUnit->id) {
            return redirect()->route('rhu.index')
                ->with('error', 'You cannot delete the RHU assigned to your current account.');
        }

        $assignedUsers = User::where('health_unit_id', $healthUnit->id)->count();
        if ($assignedUsers > 0) {
            return redirect()->route('rhu.index')
                ->with('error', "This RHU cannot be deleted because it is still assigned to {$assignedUsers} user account(s).");
        }

        if ($healthUnit->children()->exists() || $this->hasOperationalRecords($healthUnit->id)) {
            return redirect()->route('rhu.index')->with('error', 'This RHU has operational or clinical history and cannot be deleted.');
        }

        try {
            DB::transaction(function () use ($healthUnit) {
                ActivityLog::where('health_unit_id', $healthUnit->id)->update(['health_unit_id' => null]);
                $healthUnit->delete();
                ActivityLogger::log('RHU', 'Delete', 'Deleted RHU: '.$healthUnit->name.'.');
            });
        } catch (QueryException $e) {
            return redirect()->route('rhu.index')->with('error', 'This RHU is referenced by existing records and cannot be deleted.');
        }

        return redirect()->route('rhu.index')->with('success', 'RHU deleted successfully.');
    }

    private function hasOperationalRecords(int $healthUnitId): bool
    {
        foreach (['inventory', 'stock_logs', 'dispensing_records', 'public_users', 'allocations', 'disease_reports',
            'vaccination_schedules', 'immunization_records', 'prenatal_visits', 'postpartum_visits',
            'family_planning', 'calendar_events', 'rhu_immunization_schedules', 'cold_chain_logs', 'aefi_reports'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'health_unit_id')
                && DB::table($table)->where('health_unit_id', $healthUnitId)->exists()) {
                return true;
            }
        }

        return DB::table('transfer_requests')->where('from_health_unit_id', $healthUnitId)
            ->orWhere('to_health_unit_id', $healthUnitId)->exists()
            || DB::table('allocations')->where('destination_health_unit_id', $healthUnitId)->exists();
    }
}
