<?php

namespace App\Http\Controllers;

use App\Models\HealthUnit;
use App\Services\ELMISExportService;
use Illuminate\Http\Request;

class ELMISExportController extends Controller
{
    protected ELMISExportService $elmisService;

    public function __construct(ELMISExportService $elmisService)
    {
        $this->elmisService = $elmisService;
    }

    private function checkAccess()
    {
        if (! session()->has('user_id')) {
            return redirect()->route('login');
        }
        if (! in_array(session('role'), ['admin', 'staff'])) {
            return redirect()->route('login');
        }

        return null;
    }

    public function index()
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $healthUnits = [];
        if (is_null(session('health_unit_id'))) {
            $healthUnits = HealthUnit::orderBy('name')->get();
        }

        return view('elmis.export', compact('healthUnits'));
    }

    public function exportInventory(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $request->validate([
            'health_unit_id' => 'nullable|exists:health_units,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $healthUnitId = $this->exportHealthUnit($request);
        $data = $this->elmisService->generateInventoryReport($healthUnitId, $request->date_from, $request->date_to);
        $filename = 'elmis_inventory_'.now()->format('Ymd_His').'.csv';

        return $this->elmisService->exportCsv($data, $filename);
    }

    public function exportStockMovement(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $request->validate([
            'health_unit_id' => 'nullable|exists:health_units,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $healthUnitId = $this->exportHealthUnit($request);
        $data = $this->elmisService->generateStockMovementReport($healthUnitId, $request->date_from, $request->date_to);
        $filename = 'elmis_stock_movement_'.now()->format('Ymd_His').'.csv';

        return $this->elmisService->exportStockMovementCsv($data, $filename);
    }

    private function exportHealthUnit(Request $request): ?int
    {
        if (session('health_unit_id')) {
            abort_if($request->filled('health_unit_id') && (int) $request->health_unit_id !== (int) session('health_unit_id'), 403);

            return (int) session('health_unit_id');
        }
        abort_unless(session('role') === 'admin', 403);

        return $request->filled('health_unit_id') ? (int) $request->health_unit_id : null;
    }
}
