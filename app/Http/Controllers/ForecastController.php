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

        $forecast  = $this->forecastService->getForecast($healthUnitId);
        $planned   = $this->forecastService->getPlannedForecast($healthUnitId, 90);
        $unplanned = $this->forecastService->getUnplannedForecast($healthUnitId, null, 30);
        $trending  = $this->forecastService->getTrendingDiseases($healthUnitId, 30);

        // ─────────────────────────────────────────────────────────
        // Paginate the Stock Analysis items for the table only.
        // The charts still use the full $forecast['items'] data.
        // ─────────────────────────────────────────────────────────
        $allItems = collect($forecast['items'] ?? [])->values();

        $perPage    = 15;
        $currentPg  = max(1, (int) (Paginator::resolveCurrentPage('forecast_page') ?: 1));
        $offset     = ($currentPg - 1) * $perPage;

        $pageItems = $allItems->slice($offset, $perPage)->values();

        $itemsPaginator = new LengthAwarePaginator(
            $pageItems,
            $allItems->count(),
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
            'trending'
        ));
    }
}