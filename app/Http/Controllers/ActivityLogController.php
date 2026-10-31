<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
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
        $search = trim($request->get('search', ''));
        $module = trim($request->get('module', ''));
        $action = trim($request->get('action', ''));

        $query = ActivityLog::with('user')
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

        $search = trim($request->get('search', ''));

        $baseQuery = ActivityLog::with('user')
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

        $logs = (clone $baseQuery)
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('module', 'like', "%{$search}%")
                      ->orWhere('action', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($uq) use ($search) {
                          $uq->where('name', 'like', "%{$search}%")
                             ->orWhere('username', 'like', "%{$search}%");
                      });
                });
            })
            ->orderByDesc('id')
            ->paginate(100)  // <-- now 100 per page
            ->withQueryString();

        return view('activity_logs.index', compact(
            'logs',
            'search',
            'stats'
        ));
    }

    public function exportCsv(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $search = trim($request->get('search', ''));

        $query = ActivityLog::with('user')
            ->where('health_unit_id', session('health_unit_id'));

        if (session('role') === 'staff') {
            $query->where('user_id', session('user_id'));
        }

        $logs = $query
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('module', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                               ->orWhere('username', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->get();

        $filename = 'activity_logs_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                '#',
                'User',
                'Module',
                'Action',
                'Description',
                'Date & Time',
            ]);

            foreach ($logs as $index => $log) {
                fputcsv($handle, [
                    $index + 1,
                    $log->user->name ?? 'Unknown User',
                    $log->module,
                    $log->action,
                    $log->description,
                    $log->created_at ? \Carbon\Carbon::parse($log->created_at)->format('M d, Y | h:i A') : 'N/A',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}