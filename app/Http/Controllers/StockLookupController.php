<?php

namespace App\Http\Controllers;

use App\Models\HealthUnit;
use App\Models\Medicine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockLookupController extends Controller
{
    /**
     * The standard DOH program categories.
     * Key   = value used in the query filter (case-insensitive match on medicines.category)
     * Label = what the midwife sees
     */
    private const CATEGORIES = [
        'medicine' => 'Medicine',
        'vaccine' => 'Vaccine',
        'family planning' => 'Family Planning',
        'maternal and child health' => 'Maternal & Child Health',
        'disease prevention & control' => 'Disease Prevention & Control',
        'ncd & lifestyle' => 'NCD & Lifestyle',
    ];

    private function isAuthorized(): bool
    {
        return session()->has('user_id')
            && in_array(session('role'), ['admin', 'staff']);
    }

    public function index()
    {
        if (! $this->isAuthorized()) {
            return redirect()->route('login');
        }

        $medicines = Medicine::orderBy('medicine_name')
            ->get(['id', 'medicine_name', 'dosage', 'dosage_form', 'category']);

        return view('stock_lookup.index', [
            'medicines' => $medicines,
            'categories' => self::CATEGORIES,
        ]);
    }

    public function search(Request $request)
    {
        if (! $this->isAuthorized()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $medicineId = $request->get('medicine_id');
        if (! $medicineId) {
            return response()->json(['results' => []]);
        }

        $medicine = Medicine::find($medicineId);
        if (! $medicine) {
            return response()->json(['results' => []]);
        }

        $fromRhuId = session('health_unit_id');

        $rows = DB::table('inventory')
            ->join('health_units', 'inventory.health_unit_id', '=', 'health_units.id')
            ->where('inventory.medicine_id', $medicineId)
            ->where('inventory.available_stock', '>', 0)
            ->whereNull('inventory.deleted_at')
            ->where(fn ($q) => $q->whereNull('inventory.expiration_date')->orWhereDate('inventory.expiration_date', '>=', today()))
            ->when($fromRhuId, function ($q) use ($fromRhuId) {
                $q->where('inventory.health_unit_id', '!=', $fromRhuId);
            })
            ->select(
                'health_units.id          as rhu_id',
                'health_units.name        as rhu_name',
                'health_units.municipality',
                'health_units.latitude',
                'health_units.longitude',
                DB::raw('SUM(inventory.available_stock) as available_stock')
            )
            ->groupBy(
                'health_units.id',
                'health_units.name',
                'health_units.municipality',
                'health_units.latitude',
                'health_units.longitude'
            )
            ->orderByDesc('available_stock')
            ->get();

        $currentRhu = $fromRhuId ? HealthUnit::find($fromRhuId) : null;

        $results = $rows->map(function ($r) use ($currentRhu) {
            $distance = null;

            if ($currentRhu
                && $currentRhu->latitude && $currentRhu->longitude
                && $r->latitude && $r->longitude) {
                $distance = round($this->haversineKm(
                    (float) $currentRhu->latitude, (float) $currentRhu->longitude,
                    (float) $r->latitude, (float) $r->longitude
                ), 1);
            }

            return [
                'rhu_id' => $r->rhu_id,
                'rhu_name' => $r->rhu_name,
                'municipality' => $r->municipality,
                'available_stock' => (int) $r->available_stock,
                'distance_km' => $distance,
                'latitude' => $r->latitude ? (float) $r->latitude : null,
                'longitude' => $r->longitude ? (float) $r->longitude : null,
            ];
        });

        return response()->json([
            'medicine' => [
                'id' => $medicine->id,
                'medicine_name' => $medicine->medicine_name,
                'dosage' => $medicine->dosage,
                'dosage_form' => $medicine->dosage_form,
                'category' => $medicine->category,
                'pack_type' => $medicine->pack_type,
                'pieces_per_pack' => (int) $medicine->pieces_per_pack,
            ],
            'results' => $results,
        ]);
    }

    public function requestTransfer(Request $request)
    {
        if (! $this->isAuthorized()) {
            return redirect()->route('login');
        }

        return redirect()->route('transfers.create');
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * asin(min(1.0, sqrt($a)));
    }
}
