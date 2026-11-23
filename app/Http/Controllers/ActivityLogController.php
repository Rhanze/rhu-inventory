<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\HistoricalDateRange;
use App\Support\SpreadsheetCsv;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
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

    private function getBaseQuery(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'module' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim($filters['search'] ?? '');
        $module = trim($filters['module'] ?? '');
        $action = trim($filters['action'] ?? '');

        $query = ActivityLog::with(['user', 'healthUnit'])
            ->where('health_unit_id', session('health_unit_id'));

        // Admin sees all RHU logs, staff sees only their own logs
        if (session('role') === 'staff') {
            $query->where('user_id', session('user_id'));
        }

        $query->when($search, function ($q) use ($search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('module', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                           ->orWhere('username', 'like', "%{$search}%");
                    });
            });
        });

        $query->when($module !== '', function ($q) use ($module) {
            $q->where('module', $module);
        });

        $query->when($action !== '', function ($q) use ($action) {
            $q->where('action', $action);
        });

        return $query;
    }

    public function index(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $filteredQuery = $this->getBaseQuery($request);
        $historyRange = HistoricalDateRange::fromRequest($request);
        $historyRange->apply($filteredQuery, 'created_at');
        $search = trim($request->input('search', ''));

        $baseQuery = ActivityLog::with(['user', 'healthUnit'])
            ->where('health_unit_id', session('health_unit_id'));

        if (session('role') === 'staff') {
            $baseQuery->where('user_id', session('user_id'));
        }

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'today' => (clone $baseQuery)->whereDate('created_at', today())->count(),
            'last_7_days' => (clone $baseQuery)->where('created_at', '>=', now()->subDays(7))->count(),
            'this_month' => (clone $baseQuery)->whereMonth('created_at', now()->month)
                                              ->whereYear('created_at', now()->year)
                                              ->count(),
        ];

        $logs = $filteredQuery
            ->orderByDesc('id')
            ->paginate(100)
            ->withQueryString();

        return view('activity_logs.index', compact(
            'logs',
            'search',
            'stats',
            'historyRange'
        ));
    }

    public function exportCsv(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $query = $this->getBaseQuery($request);
        HistoricalDateRange::fromRequest($request)->apply($query, 'created_at');
        $logs = $query
            ->orderByDesc('id')
            ->get();

        $filename = 'activity_logs_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');

            SpreadsheetCsv::writeRow($handle, [
                '#',
                'User',
                'Module',
                'Action',
                'Description',
                'Date & Time',
                'Where',
            ]);

            foreach ($logs as $index => $log) {
                SpreadsheetCsv::writeRow($handle, [
                    $index + 1,
                    $log->user->name ?? 'Unknown User',
                    $log->module,
                    $log->action,
                    $log->description,
                    $log->created_at ? \Carbon\Carbon::parse($log->created_at)->format('M d, Y | h:i A') : 'N/A',
                    $log->healthUnit->name ?? ($log->health_unit_id === null ? 'Provincial Health Office' : 'Facility not recorded'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}