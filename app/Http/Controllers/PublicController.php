<?php

namespace App\Http\Controllers;

use App\Models\HealthUnit;
use App\Models\Medicine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicController extends Controller
{
    // ============================================================
    // Helper — builds the multi-word search condition
    // Each word must match at least one of the searchable columns.
    // ============================================================
    private function applyMultiWordSearch($query, string $q, string $tablePrefix = 'medicines')
    {
        $terms = preg_split('/\s+/', trim($q), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($terms as $term) {
            $query->where(function ($sub) use ($term, $tablePrefix) {
                $sub->where("{$tablePrefix}.medicine_name", 'like', "%{$term}%")
                    ->orWhere("{$tablePrefix}.dosage", 'like', "%{$term}%")
                    ->orWhere("{$tablePrefix}.category", 'like', "%{$term}%")
                    ->orWhere("{$tablePrefix}.description", 'like', "%{$term}%");
            });
        }

        return $query;
    }

    // ============================================================
    // Helper — adds the "countable stock" filter to a query that
    // joins the `inventory` table.
    //   • ignores soft-deleted inventory rows
    //   • ignores expired batches
    // ============================================================
    private function applyCountableInventory($query)
    {
        $query->whereNull('inventory.deleted_at')
            ->where(function ($q) {
                $q->whereNull('inventory.expiration_date')
                    ->orWhereDate('inventory.expiration_date', '>=', now()->toDateString());
            });

        return $query;
    }

    // ============================================================
    // Helper — only show items the RHU actually dispenses to
    // citizens. Internal supplies (PPE, equipment, manuals, lab
    // kits) are excluded from every public query.
    // ============================================================
    private function applyDispensableOnly($query)
    {
        $query->where('medicines.is_dispensable', true);

        return $query;
    }

    // ============================================================
    // ============== AUTOCOMPLETE (medicines) ====================
    // ============================================================
    public function autocomplete(Request $request)
    {
        $search = trim($request->get('q', ''));
        $healthUnitId = $request->get('health_unit_id');

        if (strlen($search) < 2) {
            return response()->json([]);
        }

        try {
            $query = DB::table('medicines')
                ->whereNull('medicines.deleted_at');

            $this->applyDispensableOnly($query);
            $this->applyMultiWordSearch($query, $search, 'medicines');

            if ($healthUnitId) {
                $query->whereExists(function ($sub) use ($healthUnitId) {
                    $sub->select(DB::raw(1))
                        ->from('inventory')
                        ->whereColumn('inventory.medicine_id', 'medicines.id')
                        ->where('inventory.health_unit_id', $healthUnitId)
                        ->where('inventory.available_stock', '>', 0)
                        ->whereNull('inventory.deleted_at')
                        ->where(function ($q) {
                            $q->whereNull('inventory.expiration_date')
                                ->orWhereDate('inventory.expiration_date', '>=', now()->toDateString());
                        });
                });
            }

            $medicines = $query->orderBy('medicines.medicine_name')
                ->limit(10)
                ->get(['medicines.id', 'medicines.medicine_name', 'medicines.dosage']);

            return response()->json($medicines->map(function ($m) {
                $parts = array_filter([$m->medicine_name, $m->dosage]);

                return [
                    'id' => $m->id,
                    'text' => implode(' ', $parts),
                ];
            }));

        } catch (\Throwable $e) {
            \Log::error('Autocomplete failed: '.$e->getMessage());

            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    // ============================================================
    // ============== MAIN PUBLIC SEARCH PAGE =====================
    // ============================================================
    public function home(Request $request)
    {
        $q = trim($request->get('q', ''));
        $selectedRhuId = $request->get('health_unit_id');
        $selectedRhuId = ($selectedRhuId !== null && $selectedRhuId !== '')
                            ? (int) $selectedRhuId
                            : null;

        $rhuList = HealthUnit::whereIn('facility_type', ['Rural Health Unit', 'RHU'])
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'municipality']);

        $selectedRhu = $selectedRhuId
            ? $rhuList->firstWhere('id', $selectedRhuId)
            : null;

        $medicines = collect();
        $medicineExists = false;

        if ($q !== '' && $selectedRhu) {
            $medQuery = DB::table('medicines')
                ->join('inventory', 'medicines.id', '=', 'inventory.medicine_id')
                ->join('health_units', 'inventory.health_unit_id', '=', 'health_units.id')
                ->whereNull('medicines.deleted_at')
                ->where('inventory.health_unit_id', $selectedRhuId);

            $this->applyDispensableOnly($medQuery);
            $this->applyMultiWordSearch($medQuery, $q, 'medicines');
            $this->applyCountableInventory($medQuery);

            $medicines = $medQuery
                ->select(
                    'medicines.id',
                    'medicines.medicine_name',
                    'medicines.dosage',
                    'medicines.category',
                    'medicines.description',
                    'medicines.requires_prescription',
                    DB::raw('COALESCE(SUM(inventory.available_stock), 0) as total_available')
                )
                ->groupBy(
                    'medicines.id',
                    'medicines.medicine_name',
                    'medicines.dosage',
                    'medicines.category',
                    'medicines.description',
                    'medicines.requires_prescription'
                )
                ->orderBy('medicines.medicine_name', 'asc')
                ->get();

            if ($medicines->isEmpty()) {
                $existsQuery = DB::table('medicines')->whereNull('medicines.deleted_at');
                $this->applyDispensableOnly($existsQuery);
                $this->applyMultiWordSearch($existsQuery, $q, 'medicines');
                $medicineExists = $existsQuery->count() > 0;
            }
        }

        return view('public.home', compact(
            'q',
            'rhuList',
            'selectedRhu',
            'selectedRhuId',
            'medicines',
            'medicineExists'
        ));
    }

    // ============================================================
    // ============== MEDICINE DETAIL PAGE ========================
    // ============================================================
    public function show(Request $request, $id)
    {
        // Block deep-links to internal-only items.
        $medicine = Medicine::where('is_dispensable', true)->findOrFail($id);
        $healthUnitId = $request->get('health_unit_id');
        $q = $request->get('q', '');

        $query = DB::table('inventory')
            ->join('health_units', 'inventory.health_unit_id', '=', 'health_units.id')
            ->where('inventory.medicine_id', $id)
            ->whereIn('health_units.facility_type', ['Rural Health Unit', 'RHU'])
            ->whereNull('inventory.deleted_at')
            ->where(function ($q) {
                $q->whereNull('inventory.expiration_date')
                    ->orWhereDate('inventory.expiration_date', '>=', now()->toDateString());
            });

        if ($healthUnitId) {
            $query->where('health_units.id', $healthUnitId);
        }

        $availability = $query
            ->select(
                'health_units.id',
                'health_units.name',
                'health_units.municipality',
                'health_units.address',
                DB::raw('SUM(inventory.available_stock) as total_available'),
                DB::raw('SUM(inventory.allocated_stock) as allocated_stock'),
                DB::raw('MIN(inventory.expiration_date) as nearest_expiration')
            )
            ->groupBy(
                'health_units.id',
                'health_units.name',
                'health_units.municipality',
                'health_units.address'
            )
            ->orderBy('health_units.name', 'asc')
            ->get();

        return view('public.show', compact('medicine', 'availability', 'healthUnitId', 'q'));
    }
}
