<?php

namespace App\Http\Controllers;

use App\Models\HealthUnit;
use App\Helpers\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class RhuController extends Controller
{
    private function checkAdminAccess()
    {
        if (!session()->has('user_id')) {
            return redirect()->route('login');
        }

        if (session('role') !== 'admin') {
            return redirect()->route('dashboard')->with('error', 'Only admin can access the RHU module.');
        }

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
        ]);

        ActivityLogger::log(
            'RHU',
            'Create',
            'Added RHU: ' . $healthUnit->name . '.',
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
            'Updated RHU: ' . $oldName . ' to ' . $healthUnit->name . '.',
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

    $assignedUsers = \App\Models\User::where('health_unit_id', $healthUnit->id)->count();
    if ($assignedUsers > 0) {
        return redirect()->route('rhu.index')
            ->with('error', "This RHU cannot be deleted because it is still assigned to {$assignedUsers} user account(s).");
    }

    // ✅ Manually set health_unit_id to NULL for all related activity logs
    \App\Models\ActivityLog::where('health_unit_id', $healthUnit->id)
        ->update(['health_unit_id' => null]);

    // If you have other related tables (inventory, stock_logs, etc.), do the same here
    // \App\Models\Inventory::where('health_unit_id', $healthUnit->id)->update(['health_unit_id' => null]);

    ActivityLogger::log(
        'RHU',
        'Delete',
        'Deleted RHU: ' . $healthUnit->name . '.',
        $healthUnit->id
    );

    $healthUnit->delete();

    return redirect()->route('rhu.index')->with('success', 'RHU deleted successfully.');
}
}