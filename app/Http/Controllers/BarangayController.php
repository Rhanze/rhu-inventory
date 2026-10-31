<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\HealthUnit;
use App\Helpers\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class BarangayController extends Controller
{
    private function checkAccess()
    {
        if (!session()->has('user_id')) {
            return redirect()->route('login');
        }

        if (!in_array(session('role'), ['admin', 'staff'])) {
            return redirect()->route('login');
        }

        return null;
    }

    public function index(Request $request)
{
    // Access check
    if (!session()->has('user_id')) {
        return redirect()->route('login');
    }
    if (!in_array(session('role'), ['admin', 'staff'])) {
        return redirect()->route('login');
    }

    $search = $request->search;
    $healthUnitId = session('health_unit_id');

    $barangays = Barangay::with('healthUnit')
        ->where('health_unit_id', $healthUnitId)
        ->when($search, function ($query, $search) {
            $query->where('barangay_name', 'like', "%{$search}%");
        })
        ->orderBy('barangay_name')
        ->paginate(config('pagination.inventory', 20))
        ->appends(['search' => $search]);

    return view('barangay.index', compact('barangays'));
}

    public function create()
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        if (is_null(session('health_unit_id'))) {
        return redirect()->route('stock_logs.index')
            ->with('error', 'Stock addition is only available at the RHU level.');
        }

        $healthUnit = HealthUnit::find(session('health_unit_id'));

        return view('barangay.create', compact('healthUnit'));
    }

    public function store(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        if (is_null(session('health_unit_id'))) {
        return redirect()->route('stock_logs.index')
            ->with('error', 'Stock addition is only available at the RHU level.');
        }

        $request->validate([
            'barangay_name' => 'required|string|max:150',
        ]);

        $barangay = Barangay::create([
            'health_unit_id' => session('health_unit_id'),
            'barangay_name' => $request->barangay_name,
        ]);

        ActivityLogger::log(
            'Barangays',
            'Create',
            'Added barangay: ' . $barangay->barangay_name . '.'
        );

        return redirect()->route('barangay.index')->with('success', 'Barangay added successfully.');
    }

    public function edit($id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $barangay = Barangay::where('health_unit_id', session('health_unit_id'))->findOrFail($id);
        $healthUnit = HealthUnit::find(session('health_unit_id'));

        return view('barangay.edit', compact('barangay', 'healthUnit'));
    }

    public function update(Request $request, $id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $request->validate([
            'barangay_name' => 'required|string|max:150',
        ]);

        $barangay = Barangay::where('health_unit_id', session('health_unit_id'))->findOrFail($id);

        $oldName = $barangay->barangay_name;

        $barangay->update([
            'barangay_name' => $request->barangay_name,
        ]);

        ActivityLogger::log(
            'Barangays',
            'Update',
            'Updated barangay: ' . $oldName . ' to ' . $barangay->barangay_name . '.'
        );

        return redirect()->route('barangay.index')->with('success', 'Barangay updated successfully.');
    }

    public function destroy($id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $barangay = Barangay::where('health_unit_id', session('health_unit_id'))->findOrFail($id);

        try {
            ActivityLogger::log(
                'Barangays',
                'Delete',
                'Deleted barangay: ' . $barangay->barangay_name . '.'
            );

            $barangay->delete();

            return redirect()->route('barangay.index')->with('success', 'Barangay deleted successfully.');
        } catch (QueryException $e) {
            return redirect()->route('barangay.index')->with('error', 'This barangay cannot be deleted because it is already used by other records.');
        }
    }
}