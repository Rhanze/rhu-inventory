<?php

namespace App\Http\Controllers;

use App\Models\HealthUnit;
use App\Models\Inventory;
use Illuminate\Http\Request;

class GisController extends Controller
{
    private const ANTIQUE_BOUNDS = [
        'minLat' => 10.38,
        'maxLat' => 11.95,
        'minLng' => 121.40,
        'maxLng' => 122.30,
    ];

    private const PROGRAM_CATEGORIES = [
        'medicine'                     => 'Medicine',
        'vaccine'                      => 'Vaccine',
        'family planning'              => 'Family Planning',
        'maternal and child health'    => 'Maternal & Child Health',
        'disease prevention & control' => 'Disease Prevention & Control',
        'ncd & lifestyle'              => 'NCD & Lifestyle',
    ];

    private function checkAccess()
    {
        if (!session()->has('user_id')) {
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

        $isRhuAdmin = !is_null(session('health_unit_id'));
        $ownRhuId   = $isRhuAdmin ? (int) session('health_unit_id') : null;

        $rhus = HealthUnit::orderBy('municipality')->orderBy('name')->get();

        $withCoords    = $rhus->whereNotNull('latitude')->whereNotNull('longitude')->count();
        $withoutCoords = $rhus->count() - $withCoords;

        $ownRhu = $ownRhuId ? $rhus->firstWhere('id', $ownRhuId) : null;
        $ownRhuHasCoords = $ownRhu
            && !is_null($ownRhu->latitude)
            && !is_null($ownRhu->longitude);

        $categories = self::PROGRAM_CATEGORIES;

        return view('gis.index', compact(
            'rhus', 'withCoords', 'withoutCoords',
            'isRhuAdmin', 'ownRhuId', 'ownRhu', 'ownRhuHasCoords',
            'categories'
        ));
    }

    public function data()
    {
        $ownRhuId = !is_null(session('health_unit_id'))
            ? (int) session('health_unit_id')
            : null;

        $rhus = HealthUnit::orderBy('municipality')->get();

        $data = $rhus->map(function ($rhu) use ($ownRhuId) {
            $inventories = Inventory::where('health_unit_id', $rhu->id)
                ->where('available_stock', '>', 0)
                ->get();

            return [
                'id'              => $rhu->id,
                'name'            => $rhu->name,
                'municipality'    => $rhu->municipality ?? '',
                'address'         => $rhu->address ?? '',
                'facility_type'   => $rhu->facility_type ?? '',
                'latitude'        => $rhu->latitude,
                'longitude'       => $rhu->longitude,
                'total_medicines' => $inventories->count(),
                'total_stock'     => (int) $inventories->sum('available_stock'),
                'has_coords'      => !is_null($rhu->latitude) && !is_null($rhu->longitude),
                'is_own_rhu'      => $ownRhuId !== null && (int) $rhu->id === $ownRhuId,
            ];
        });

        return response()->json([
            'rhus'       => $data,
            'own_rhu_id' => $ownRhuId,
        ]);
    }

    /**
     * Category count — returns how many medicine types (and total stock)
     * a given RHU has in a given program category.
     */
    public function categoryCount(Request $request)
    {
        if (!session()->has('user_id') || session('role') !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $rhuId    = (int) $request->input('rhu_id');
        $category = trim((string) $request->input('category'));

        if (!$rhuId || $category === '') {
            return response()->json([
                'medicine_types' => 0,
                'total_stock'    => 0,
            ]);
        }

        $base = Inventory::query()
            ->where('health_unit_id', $rhuId)
            ->where('available_stock', '>', 0)
            ->whereHas('medicine', function ($q) use ($category) {
                $q->where('category', 'like', '%' . $category . '%');
            });

        $types = (clone $base)->distinct('medicine_id')->count('medicine_id');
        $stock = (int) (clone $base)->sum('available_stock');

        return response()->json([
            'medicine_types' => $types,
            'total_stock'    => $stock,
        ]);
    }

    public function updateCoordinates(Request $request, $id)
    {
        if (!session()->has('user_id') || session('role') !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $rhu      = HealthUnit::findOrFail($id);
        $ownRhuId = session('health_unit_id');

        if (!is_null($ownRhuId) && (int) $rhu->id !== (int) $ownRhuId) {
            return response()->json([
                'error' => 'You can only set coordinates for your own RHU.',
            ], 403);
        }

        $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $lat = (float) $request->latitude;
        $lng = (float) $request->longitude;

        $b = self::ANTIQUE_BOUNDS;
        $inAntique = $lat >= $b['minLat'] && $lat <= $b['maxLat']
                  && $lng >= $b['minLng'] && $lng <= $b['maxLng'];

        if (!$inAntique) {
            return response()->json([
                'error' => 'The selected location is outside Antique province. Please pick a location within the province.',
            ], 422);
        }

        $rhu->latitude  = $lat;
        $rhu->longitude = $lng;
        $rhu->save();

        \App\Helpers\ActivityLogger::log(
            'GIS',
            'Update',
            'Updated coordinates for ' . $rhu->name . ' (' . $lat . ', ' . $lng . ').'
        );

        return response()->json([
            'success'   => true,
            'message'   => 'Coordinates updated.',
            'latitude'  => $rhu->latitude,
            'longitude' => $rhu->longitude,
        ]);
    }
}