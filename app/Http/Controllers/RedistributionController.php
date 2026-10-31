<?php

namespace App\Http\Controllers;

use App\Services\RedistributionService;
use Illuminate\Http\Request;

class RedistributionController extends Controller
{
    protected RedistributionService $redistributionService;

    public function __construct(RedistributionService $redistributionService)
    {
        $this->redistributionService = $redistributionService;
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

        // Only PHO admin can see all redistributions
        if (!is_null(session('health_unit_id'))) {
            return redirect()->route('dashboard')
                ->with('error', 'Redistribution suggestions are only available to PHO administrators.');
        }

        $data = $this->redistributionService->getSuggestions();

        return view('redistribution.index', $data);
    }

    /**
     * Export redistribution suggestions as CSV
     */
    public function exportCsv(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        if (!is_null(session('health_unit_id'))) {
            return redirect()->route('dashboard')
                ->with('error', 'Redistribution suggestions are only available to PHO administrators.');
        }

        $data = $this->redistributionService->getSuggestions();
        $suggestions = $data['suggestions'];

        $filename = 'redistribution_suggestions_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($suggestions) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Medicine',
                'From RHU',
                'To RHU',
                'Suggested Quantity',
                'Reason'
            ]);

            foreach ($suggestions as $suggestion) {
                fputcsv($handle, [
                    $suggestion['medicine_name'],
                    $suggestion['from_rhu'],
                    $suggestion['to_rhu'],
                    $suggestion['suggested_quantity'],
                    $suggestion['reason'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}