<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private function checkAccess()
    {
        if (! session()->has('user_id')) {
            return redirect()->route('login');
        }

        return null;
    }

    /**
     * Calculate on which page a record appears in the given ordered query.
     */
    private function getPageForRecord($baseQuery, $orderColumn, $orderDirection, $recordId, $recordOrderValue, $perPage)
    {
        if ($orderColumn === 'id' && $orderDirection === 'desc') {
            $position = (clone $baseQuery)->where('id', '>', $recordId)->count();
        } elseif ($orderColumn === 'id' && $orderDirection === 'asc') {
            $position = (clone $baseQuery)->where('id', '<', $recordId)->count();
        } else {
            if ($orderDirection === 'asc') {
                $position = (clone $baseQuery)
                    ->where(function ($q) use ($orderColumn, $recordOrderValue, $recordId) {
                        $q->where($orderColumn, '<', $recordOrderValue)
                            ->orWhere(function ($sub) use ($orderColumn, $recordOrderValue, $recordId) {
                                $sub->where($orderColumn, $recordOrderValue)
                                    ->where('id', '<', $recordId);
                            });
                    })->count();
            } else { // desc
                $position = (clone $baseQuery)
                    ->where(function ($q) use ($orderColumn, $recordOrderValue, $recordId) {
                        $q->where($orderColumn, '>', $recordOrderValue)
                            ->orWhere(function ($sub) use ($orderColumn, $recordOrderValue, $recordId) {
                                $sub->where($orderColumn, $recordOrderValue)
                                    ->where('id', '>', $recordId);
                            });
                    })->count();
            }
        }

        return (int) ceil(($position + 1) / $perPage);
    }

    public function index(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $query = trim($request->get('q', ''));
        $scope = $request->get('scope', 'all');
        $role = session('role');
        $healthUnitId = session('health_unit_id');

        $allowedScopes = $role === 'admin'
            ? ['all', 'medicine', 'users', 'rhu', 'inventory', 'stock_logs', 'allocation', 'dispensing']
            : ['all', 'medicine', 'inventory', 'stock_logs', 'allocation', 'dispensing'];

        if (! in_array($scope, $allowedScopes)) {
            $scope = 'all';
        }

        $medicineResults = collect();
        $userResults = collect();
        $rhuResults = collect();
        $inventoryResults = collect();
        $stockLogResults = collect();
        $allocationResults = collect();
        $dispensingResults = collect();

        // Per-page value used everywhere (must match pagination config)
        $perPage = 20;

        if ($query !== '') {

            // ---------- Medicines ----------
            if ($scope === 'all' || $scope === 'medicine') {
                $searchQuery = Medicine::query()
                    ->where(function ($q) use ($query) {
                        $q->where('medicine_name', 'like', "%{$query}%")
                            ->orWhere('dosage', 'like', "%{$query}%")
                            ->orWhere('category', 'like', "%{$query}%")
                            ->orWhere('description', 'like', "%{$query}%");
                    })
                    ->orderBy('medicine_name', 'asc');

                $medicineResults = (clone $searchQuery)->get();

                $baseNoSearch = Medicine::query()->orderBy('medicine_name', 'asc');

                foreach ($medicineResults as $med) {
                    $med->search_page = $this->getPageForRecord(
                        $baseNoSearch, 'medicine_name', 'asc',
                        $med->id, $med->medicine_name,
                        $perPage
                    );
                }
            }

            // ---------- Users ----------
            if ($role === 'admin' && ($scope === 'all' || $scope === 'users')) {
                $searchQuery = User::with('healthUnit')
                    ->where(function ($q) use ($query) {
                        $q->where('name', 'like', "%{$query}%")
                            ->orWhere('username', 'like', "%{$query}%")
                            ->orWhere('role', 'like', "%{$query}%");
                    })
                    ->orderBy('name', 'asc');

                $userResults = (clone $searchQuery)->get();

                $baseNoSearch = User::with('healthUnit')->orderBy('name', 'asc');

                foreach ($userResults as $user) {
                    $user->search_page = $this->getPageForRecord(
                        $baseNoSearch, 'name', 'asc',
                        $user->id, $user->name,
                        $perPage
                    );
                }
            }

            // ---------- RHU ----------
            if ($role === 'admin' && ($scope === 'all' || $scope === 'rhu')) {
                $searchQuery = HealthUnit::query()
                    ->where(function ($q) use ($query) {
                        $q->where('name', 'like', "%{$query}%")
                            ->orWhere('municipality', 'like', "%{$query}%")
                            ->orWhere('address', 'like', "%{$query}%");
                    })
                    ->orderBy('name', 'asc');

                $rhuResults = (clone $searchQuery)->get();

                $baseNoSearch = HealthUnit::query()->orderBy('name', 'asc');

                foreach ($rhuResults as $rhu) {
                    $rhu->search_page = $this->getPageForRecord(
                        $baseNoSearch, 'name', 'asc',
                        $rhu->id, $rhu->name,
                        $perPage
                    );
                }
            }

            // ---------- Inventory ----------  ← NOW SEARCHES BATCH
            if ($scope === 'all' || $scope === 'inventory') {
                $searchQuery = Inventory::with(['medicine', 'healthUnit'])
                    ->where('health_unit_id', $healthUnitId)
                    ->where(function ($q) use ($query) {
                        $q->where('batch_no', 'like', "%{$query}%")
                            ->orWhere('expiration_date', 'like', "%{$query}%")
                            ->orWhereHas('medicine', function ($mq) use ($query) {
                                $mq->where('medicine_name', 'like', "%{$query}%")
                                    ->orWhere('dosage', 'like', "%{$query}%")
                                    ->orWhere('category', 'like', "%{$query}%");
                            });
                    })
                    ->orderByDesc('id');

                $inventoryResults = (clone $searchQuery)->get();

                $baseNoSearch = Inventory::with(['medicine', 'healthUnit'])
                    ->where('health_unit_id', $healthUnitId)
                    ->orderByDesc('id');

                foreach ($inventoryResults as $inv) {
                    $inv->search_page = $this->getPageForRecord(
                        $baseNoSearch, 'id', 'desc',
                        $inv->id, $inv->id,
                        $perPage
                    );
                }
            }

            // ---------- Stock Logs ----------  ← NOW SEARCHES BATCH
            if ($scope === 'all' || $scope === 'stock_logs') {
                $searchQuery = StockLog::with(['medicine', 'addedByUser'])
                    ->where('health_unit_id', $healthUnitId)
                    ->where(function ($q) use ($query) {
                        $q->where('batch_no', 'like', "%{$query}%")
                            ->orWhere('date_added', 'like', "%{$query}%")
                            ->orWhereHas('medicine', function ($mq) use ($query) {
                                $mq->where('medicine_name', 'like', "%{$query}%");
                            })
                            ->orWhereHas('addedByUser', function ($uq) use ($query) {
                                $uq->where('name', 'like', "%{$query}%")
                                    ->orWhere('username', 'like', "%{$query}%");
                            });
                    })
                    ->orderByDesc('id');

                $stockLogResults = (clone $searchQuery)->get();

                $baseNoSearch = StockLog::with(['medicine', 'addedByUser'])
                    ->where('health_unit_id', $healthUnitId)
                    ->orderByDesc('id');

                foreach ($stockLogResults as $log) {
                    $log->search_page = $this->getPageForRecord(
                        $baseNoSearch, 'id', 'desc',
                        $log->id, $log->id,
                        $perPage
                    );
                }
            }

            // ---------- Allocation ----------  ← NOW SEARCHES BATCH (via inventory relation)
            if ($scope === 'all' || $scope === 'allocation') {
                $searchQuery = Allocation::with(['medicine', 'allocatedBy', 'inventory'])
                    ->where('health_unit_id', $healthUnitId)
                    ->where(function ($q) use ($query) {
                        $q->where('date_allocated', 'like', "%{$query}%")
                            ->orWhere('destination', 'like', "%{$query}%")
                            ->orWhereHas('medicine', function ($mq) use ($query) {
                                $mq->where('medicine_name', 'like', "%{$query}%");
                            })
                            ->orWhereHas('inventory', function ($iq) use ($query) {
                                $iq->where('batch_no', 'like', "%{$query}%");
                            })
                            ->orWhereHas('allocatedBy', function ($uq) use ($query) {
                                $uq->where('name', 'like', "%{$query}%")
                                    ->orWhere('username', 'like', "%{$query}%");
                            });
                    })
                    ->orderByDesc('id');

                $allocationResults = (clone $searchQuery)->get();

                $baseNoSearch = Allocation::with(['medicine', 'allocatedBy'])
                    ->where('health_unit_id', $healthUnitId)
                    ->orderByDesc('id');

                foreach ($allocationResults as $alloc) {
                    $alloc->search_page = $this->getPageForRecord(
                        $baseNoSearch, 'id', 'desc',
                        $alloc->id, $alloc->id,
                        $perPage
                    );
                }
            }

            // ---------- Dispensing ----------  ← NOW SEARCHES BATCH
            if ($scope === 'all' || $scope === 'dispensing') {
                $searchQuery = DispensingRecord::when(session('role') === 'midwife', fn ($q) => $q->where('dispensed_by', session('user_id')))->with(['medicine', 'dispensedByUser'])
                    ->where('health_unit_id', $healthUnitId)
                    ->where(function ($q) use ($query) {
                        $q->where('batch_no', 'like', "%{$query}%")
                            ->orWhere('doctor_name', 'like', "%{$query}%")
                            ->orWhere('date_dispensed', 'like', "%{$query}%")
                            ->orWhereHas('medicine', function ($mq) use ($query) {
                                $mq->where('medicine_name', 'like', "%{$query}%");
                            })
                            ->orWhereHas('dispensedByUser', function ($uq) use ($query) {
                                $uq->where('name', 'like', "%{$query}%")
                                    ->orWhere('username', 'like', "%{$query}%");
                            });
                    })
                    ->orderByDesc('id');

                $dispensingResults = (clone $searchQuery)->get();

                $baseNoSearch = DispensingRecord::when(session('role') === 'midwife', fn ($q) => $q->where('dispensed_by', session('user_id')))->with(['medicine', 'dispensedByUser'])
                    ->where('health_unit_id', $healthUnitId)
                    ->orderByDesc('id');

                foreach ($dispensingResults as $rec) {
                    $rec->search_page = $this->getPageForRecord(
                        $baseNoSearch, 'id', 'desc',
                        $rec->id, $rec->id,
                        $perPage
                    );
                }
            }
        }

        return view('search.results', [
            'query' => $query,
            'scope' => $scope,
            'medicineResults' => $medicineResults,
            'userResults' => $userResults,
            'rhuResults' => $rhuResults,
            'inventoryResults' => $inventoryResults,
            'stockLogResults' => $stockLogResults,
            'allocationResults' => $allocationResults,
            'dispensingResults' => $dispensingResults,
        ]);
    }

    /**
     * Autocomplete for the live header dropdown.
     */
    public function autocomplete(Request $request)
    {
        $query = trim($request->get('q', ''));
        $scope = $request->get('scope', 'all');
        $role = session('role');
        $healthUnitId = session('health_unit_id');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $suggestions = [];
        $perPage = 20;   // must match the per-page of each module

        $allowedScopes = $role === 'admin'
            ? ['all', 'medicine', 'users', 'rhu', 'inventory', 'stock_logs', 'allocation', 'dispensing']
            : ['all', 'medicine', 'inventory', 'stock_logs', 'allocation', 'dispensing'];

        if (! in_array($scope, $allowedScopes)) {
            $scope = 'all';
        }

        // Medicines
        if ($scope === 'all' || $scope === 'medicine') {
            $medicines = Medicine::where('medicine_name', 'like', "%{$query}%")
                ->orWhere('dosage', 'like', "%{$query}%")
                ->orderBy('medicine_name')
                ->limit(5)
                ->get(['id', 'medicine_name', 'dosage']);

            $baseNoSearch = Medicine::orderBy('medicine_name', 'asc');

            foreach ($medicines as $m) {
                $page = $this->getPageForRecord($baseNoSearch, 'medicine_name', 'asc', $m->id, $m->medicine_name, $perPage);
                $suggestions[] = [
                    'text' => $m->medicine_name.($m->dosage ? ' - '.$m->dosage : ''),
                    'type' => 'Medicine',
                    'url' => route('medicine.index', ['page' => $page, 'highlight' => $m->id]).'#medicine-'.$m->id,
                ];
            }
        }

        // Users (admin only)
        if ($role === 'admin' && ($scope === 'all' || $scope === 'users')) {
            $users = User::where('name', 'like', "%{$query}%")
                ->orWhere('username', 'like', "%{$query}%")
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'name', 'username']);

            $baseNoSearch = User::orderBy('name', 'asc');

            foreach ($users as $u) {
                $page = $this->getPageForRecord($baseNoSearch, 'name', 'asc', $u->id, $u->name, $perPage);
                $suggestions[] = [
                    'text' => $u->name.' ('.$u->username.')',
                    'type' => 'User',
                    'url' => route('users.index', ['page' => $page, 'highlight' => $u->id]).'#user-'.$u->id,
                ];
            }
        }

        // RHU
        if ($role === 'admin' && ($scope === 'all' || $scope === 'rhu')) {
            $rhus = HealthUnit::where('name', 'like', "%{$query}%")
                ->orWhere('municipality', 'like', "%{$query}%")
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'name', 'municipality']);

            $baseNoSearch = HealthUnit::orderBy('name', 'asc');

            foreach ($rhus as $r) {
                $page = $this->getPageForRecord($baseNoSearch, 'name', 'asc', $r->id, $r->name, $perPage);
                $suggestions[] = [
                    'text' => $r->name.' ('.$r->municipality.')',
                    'type' => 'RHU',
                    'url' => route('rhu.index', ['page' => $page, 'highlight' => $r->id]).'#rhu-'.$r->id,
                ];
            }
        }

        // Inventory — now includes batch
        if ($scope === 'all' || $scope === 'inventory') {
            $inventories = Inventory::with('medicine')
                ->where('health_unit_id', $healthUnitId)
                ->where(function ($q) use ($query) {
                    $q->where('batch_no', 'like', "%{$query}%")
                        ->orWhereHas('medicine', function ($mq) use ($query) {
                            $mq->where('medicine_name', 'like', "%{$query}%")
                                ->orWhere('dosage', 'like', "%{$query}%");
                        })
                        ->orWhere('expiration_date', 'like', "%{$query}%");
                })
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            $baseNoSearch = Inventory::with('medicine')
                ->where('health_unit_id', $healthUnitId)
                ->orderByDesc('id');

            foreach ($inventories as $inv) {
                $page = $this->getPageForRecord($baseNoSearch, 'id', 'desc', $inv->id, $inv->id, $perPage);
                $batchPart = $inv->batch_no ? ' · Batch '.$inv->batch_no : '';
                $suggestions[] = [
                    'text' => ($inv->medicine->medicine_name ?? 'N/A').$batchPart.' (Stock: '.$inv->available_stock.')',
                    'type' => 'Inventory',
                    'url' => route('inventory.index', ['page' => $page, 'highlight' => $inv->id]).'#inventory-'.$inv->id,
                ];
            }
        }

        // Stock Logs — now includes batch
        if ($scope === 'all' || $scope === 'stock_logs') {
            $stockLogs = StockLog::with('medicine')
                ->where('health_unit_id', $healthUnitId)
                ->where(function ($q) use ($query) {
                    $q->where('batch_no', 'like', "%{$query}%")
                        ->orWhereHas('medicine', function ($mq) use ($query) {
                            $mq->where('medicine_name', 'like', "%{$query}%");
                        })
                        ->orWhere('date_added', 'like', "%{$query}%");
                })
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            $baseNoSearch = StockLog::with('medicine')
                ->where('health_unit_id', $healthUnitId)
                ->orderByDesc('id');

            foreach ($stockLogs as $log) {
                $page = $this->getPageForRecord($baseNoSearch, 'id', 'desc', $log->id, $log->id, $perPage);
                $batchPart = $log->batch_no ? ' · Batch '.$log->batch_no : '';
                $suggestions[] = [
                    'text' => ($log->medicine->medicine_name ?? 'N/A').$batchPart.' (+'.$log->quantity_added.' on '.$log->date_added.')',
                    'type' => 'Stock Log',
                    'url' => route('stock_logs.index', ['page' => $page, 'highlight' => $log->id]).'#stock-log-'.$log->id,
                ];
            }
        }

        // Allocation — now includes batch
        if ($scope === 'all' || $scope === 'allocation') {
            $allocations = Allocation::with(['medicine', 'allocatedBy', 'inventory'])
                ->where('health_unit_id', $healthUnitId)
                ->where(function ($q) use ($query) {
                    $q->where('destination', 'like', "%{$query}%")
                        ->orWhereHas('medicine', function ($mq) use ($query) {
                            $mq->where('medicine_name', 'like', "%{$query}%");
                        })
                        ->orWhereHas('inventory', function ($iq) use ($query) {
                            $iq->where('batch_no', 'like', "%{$query}%");
                        });
                })
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            $baseNoSearch = Allocation::with(['medicine', 'allocatedBy'])
                ->where('health_unit_id', $healthUnitId)
                ->orderByDesc('id');

            foreach ($allocations as $a) {
                $page = $this->getPageForRecord($baseNoSearch, 'id', 'desc', $a->id, $a->id, $perPage);
                $destination = $a->destination ?: 'N/A';
                $batchPart = ($a->inventory && $a->inventory->batch_no) ? ' · Batch '.$a->inventory->batch_no : '';
                $suggestions[] = [
                    'text' => ($a->medicine->medicine_name ?? 'N/A').$batchPart.' → '.$destination.' ('.$a->quantity_allocated.')',
                    'type' => 'Allocation',
                    'url' => route('allocation.index', ['page' => $page, 'highlight' => $a->id]).'#allocation-'.$a->id,
                ];
            }
        }

        // Dispensing — now includes batch
        if ($scope === 'all' || $scope === 'dispensing') {
            $records = DispensingRecord::when(session('role') === 'midwife', fn ($q) => $q->where('dispensed_by', session('user_id')))->with('medicine')
                ->where('health_unit_id', $healthUnitId)
                ->where(function ($q) use ($query) {
                    $q->where('batch_no', 'like', "%{$query}%")
                        ->orWhere('doctor_name', 'like', "%{$query}%")
                        ->orWhereHas('medicine', function ($mq) use ($query) {
                            $mq->where('medicine_name', 'like', "%{$query}%");
                        });
                })
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            $baseNoSearch = DispensingRecord::when(session('role') === 'midwife', fn ($q) => $q->where('dispensed_by', session('user_id')))->with('medicine')
                ->where('health_unit_id', $healthUnitId)
                ->orderByDesc('id');

            foreach ($records as $rec) {
                $page = $this->getPageForRecord($baseNoSearch, 'id', 'desc', $rec->id, $rec->id, $perPage);
                $batchPart = $rec->batch_no ? ' · Batch '.$rec->batch_no : '';
                $suggestions[] = [
                    'text' => ($rec->medicine->medicine_name ?? 'N/A').$batchPart.' (Qty: '.$rec->quantity_dispensed.' on '.$rec->date_dispensed.')',
                    'type' => 'Dispensing',
                    'url' => route('dispensing.index', ['page' => $page, 'highlight' => $rec->id]).'#dispensing-'.$rec->id,
                ];
            }
        }

        return response()->json(array_slice($suggestions, 0, 10));
    }
}
