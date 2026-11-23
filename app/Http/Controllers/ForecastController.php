<?php

namespace App\Http\Controllers;

use App\Services\ForecastService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class ForecastController extends Controller
{
    protected ForecastService $forecastService;

    public function __construct(ForecastService $forecastService)
    {
        $this->forecastService = $forecastService;
    }

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
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $healthUnitId = session('health_unit_id');
        $filters = $request->validate(['stock_filter' => ['nullable', 'in:all,shortage,low,critical,reorder,unknown']]);
        $stockFilter = $filters['stock_filter'] ?? 'all';

        $forecast  = $this->forecastService->getForecast($healthUnitId);
        $planned   = $this->forecastService->getPlannedForecast($healthUnitId, 90);
        $unplanned = $this->forecastService->getUnplannedForecast($healthUnitId, null, 30);

        // ─────────────────────────────────────────────────────────
        // Paginate the Stock Analysis items for the table only.
        // The charts still use the full $forecast['items'] data.
        // ─────────────────────────────────────────────────────────
        $allItems = collect($forecast['items'] ?? [])->values();
        $filteredItems = $allItems->filter(fn ($item) => match ($stockFilter) {
            'shortage' => $item['shortage'] > 0,
            'low' => in_array($item['status'], ['low', 'critical'], true),
            'critical' => $item['status'] === 'critical',
            'reorder' => $item['reorder_due'],
            'unknown' => $item['model']['periods'] === 0,
            default => true,
        })->values();

        $perPage    = 15;
        $currentPg  = max(1, (int) (Paginator::resolveCurrentPage('forecast_page') ?: 1));
        $offset     = ($currentPg - 1) * $perPage;

        $pageItems = $filteredItems->slice($offset, $perPage)->values();

        $itemsPaginator = new LengthAwarePaginator(
            $pageItems,
            $filteredItems->count(),
            $perPage,
            $currentPg,
            [
                'path'     => Paginator::resolveCurrentPath(),
                'pageName' => 'forecast_page',
                'query'    => $request->query(),
            ]
        );

        // Replace `items` on the forecast array with the paginator.
        // The original full list is preserved in `all_items` for the charts.
        $forecast['all_items'] = $allItems->all();
        $forecast['items']     = $itemsPaginator;

        return view('forecast.index', compact(
            'forecast',
            'planned',
            'unplanned',
            'stockFilter'
        ));
    }
}
