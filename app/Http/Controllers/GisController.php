<?php

namespace App\Http\Controllers;

use App\Helpers\ActivityLogger;
use App\Models\HealthUnit;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class GisController extends Controller
{
    private const ANTIQUE_BOUNDS = [
        'minLat' => 10.38,
        'maxLat' => 11.95,
        'minLng' => 121.40,
        'maxLng' => 122.30,
    ];

    private const PROGRAM_CATEGORIES = [
        'medicine' => 'Medicine',
        'vaccine' => 'Vaccine',
        'family_planning' => 'Family Planning',
        'family_health_nutrition' => 'Family Health & Nutrition',
        'emerging_reemerging' => 'Emerging & Re-emerging',
        'non_communicable' => 'Non-Communicable Disease',
        'dengue_prevention' => 'Dengue Prevention',
        'tuberculosis' => 'Tuberculosis',
    ];

    private function checkAccess()
    {
        if (! session()->has('user_id')) {
            return redirect()->route('login');
        }
        if (session('role') !== 'admin') {
            return redirect()->route('dashboard')
                ->with('error', 'The GIS map is only available to admins.');
        }

        return null;
    }

    public function index()
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $isRhuAdmin = ! is_null(session('health_unit_id'));
        $ownRhuId = $isRhuAdmin ? (int) session('health_unit_id') : null;

        $rhus = HealthUnit::orderBy('municipality')->orderBy('name')->get();

        $withCoords = $rhus->whereNotNull('latitude')->whereNotNull('longitude')->count();
        $withoutCoords = $rhus->count() - $withCoords;

        $ownRhu = $ownRhuId ? $rhus->firstWhere('id', $ownRhuId) : null;
        $ownRhuHasCoords = $ownRhu
            && ! is_null($ownRhu->latitude)
            && ! is_null($ownRhu->longitude);

        $categories = self::PROGRAM_CATEGORIES;

        return view('gis.index', compact(
            'rhus', 'withCoords', 'withoutCoords',
            'isRhuAdmin', 'ownRhuId', 'ownRhu', 'ownRhuHasCoords',
            'categories'
        ));
    }

    public function data()
    {
        $ownRhuId = ! is_null(session('health_unit_id'))
            ? (int) session('health_unit_id')
            : null;

        $rhus = HealthUnit::orderBy('municipality')->get();
        $balancesByRhu = $this->medicineBalances()->groupBy('health_unit_id');

        $data = $rhus->map(function ($rhu) use ($ownRhuId, $balancesByRhu) {
            $balances = $balancesByRhu->get($rhu->id, collect());
            $stocked = $balances->filter(fn ($row) => (int) $row->usable_quantity > 0);
            $status = match (true) {
                $stocked->isEmpty() => 'none',
                $balances->contains(fn ($row) => (int) $row->usable_quantity <=
                    (int) ($row->medicine->min_stock ?? config('inventory.default_min_stock', 50))) => 'low',
                default => 'healthy',
            };

            return [
                'id' => $rhu->id,
                'name' => $rhu->name,
                'municipality' => $rhu->municipality ?? '',
                'address' => $rhu->address ?? '',
                'facility_type' => $rhu->facility_type ?? '',
                'latitude' => $rhu->latitude,
                'longitude' => $rhu->longitude,
                'total_medicines' => $stocked->count(),
                'stock_status' => $status,
                'has_coords' => ! is_null($rhu->latitude) && ! is_null($rhu->longitude),
                'is_own_rhu' => $ownRhuId !== null && (int) $rhu->id === $ownRhuId,
            ];
        });

        return response()->json([
            'rhus' => $data,
            'own_rhu_id' => $ownRhuId,
        ]);
    }

    /**
     * Count available medicine types and report usable quantities by unit.
     */
    public function categoryCount(Request $request)
    {
        if (! session()->has('user_id') || session('role') !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $filters = $request->validate(['rhu_id' => 'nullable|integer|min:1', 'category' => 'nullable|string|max:100']);
        $rhuId = (int) ($filters['rhu_id'] ?? 0);
        $category = trim($filters['category'] ?? '');

        if (! $rhuId || $category === '') {
            return response()->json([
                'medicine_types' => 0,
                'stock_by_unit' => [],
            ]);
        }

        $stocked = $this->medicineBalances($rhuId, $category)->filter(fn ($row) => (int) $row->usable_quantity > 0);
        $byUnit = $stocked->groupBy(fn ($row) => mb_strtolower(trim((string) $row->medicine->unit)) ?: 'unspecified')
            ->map(fn ($rows) => ['unit' => trim((string) $rows->first()->medicine->unit) ?: 'unspecified',
                'quantity' => (int) $rows->sum('usable_quantity')])->values();

        return response()->json([
            'medicine_types' => $stocked->count(),
            'stock_by_unit' => $byUnit,
        ]);
    }

    private function medicineBalances(?int $rhuId = null, ?string $category = null): Collection
    {
        return Inventory::query()->with('medicine:id,unit,min_stock')
            ->whereHas('medicine', fn ($medicine) => $medicine->when($category !== null,
                fn ($query) => $query->where('category', $category)))
            ->when($rhuId !== null, fn ($query) => $query->where('health_unit_id', $rhuId))
            ->select(['health_unit_id', 'medicine_id'])
            ->selectRaw('SUM(CASE WHEN available_stock > 0 AND (expiration_date IS NULL OR expiration_date >= ?) THEN available_stock ELSE 0 END) as usable_quantity',
                [today()->toDateString()])
            ->groupBy('health_unit_id', 'medicine_id')->get();
    }

    public function updateCoordinates(Request $request, $id)
    {
        if (! session()->has('user_id') || session('role') !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $rhu = HealthUnit::findOrFail($id);
        $ownRhuId = session('health_unit_id');

        if (! is_null($ownRhuId) && (int) $rhu->id !== (int) $ownRhuId) {
            return response()->json([
                'error' => 'You can only set coordinates for your own RHU.',
            ], 403);
        }

        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $lat = (float) $request->latitude;
        $lng = (float) $request->longitude;

        $b = self::ANTIQUE_BOUNDS;
        $inAntique = $lat >= $b['minLat'] && $lat <= $b['maxLat']
                  && $lng >= $b['minLng'] && $lng <= $b['maxLng'];

        if (! $inAntique) {
            return response()->json([
                'error' => 'The selected location is outside Antique province. Please pick a location within the province.',
            ], 422);
        }

        $rhu->latitude = $lat;
        $rhu->longitude = $lng;
        $rhu->save();

        ActivityLogger::log(
            'GIS',
            'Update',
            'Updated coordinates for '.$rhu->name.' ('.$lat.', '.$lng.').'
        );

        return response()->json([
            'success' => true,
            'message' => 'Coordinates updated.',
            'latitude' => $rhu->latitude,
            'longitude' => $rhu->longitude,
        ]);
    }
}
