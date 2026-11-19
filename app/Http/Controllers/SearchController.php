<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use App\Models\TransferRequest;
use App\Services\ForecastService;
use App\Support\SearchSections;
use Illuminate\Validation\Rule;
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

    private function searchableUsers()
    {
        return User::query()->where('is_emergency', false)->where('id', '!=', session('user_id'))
            ->when(session('health_unit_id'), fn ($q) => $q->where('health_unit_id', session('health_unit_id')));
    }

    private function inventoryForViewer()
    {
        return Inventory::with(['medicine', 'healthUnit'])
            ->when(session('health_unit_id') !== null, fn ($query) => $query->where('health_unit_id', session('health_unit_id')));
    }

    private function stockLogsForViewer()
    {
        return StockLog::activeForReporting()->with(['medicine', 'addedByUser', 'healthUnit'])
            ->when(session('health_unit_id') !== null, fn ($query) => $query->where('health_unit_id', session('health_unit_id')));
    }

    private function allocationsForViewer()
    {
        return Allocation::with(['medicine', 'allocatedBy', 'inventory', 'healthUnit', 'destinationHealthUnit'])
            ->when(session('health_unit_id') !== null, fn ($query) => $query->where(fn ($units) => $units
                ->where('health_unit_id', session('health_unit_id'))->orWhere('destination_health_unit_id', session('health_unit_id'))));
    }

    private function stockListingParameters($record, int $page): array
    {
        return array_filter(['page' => $page, 'highlight' => $record->id,
            'rhu_filter' => session('health_unit_id') === null ? $record->health_unit_id : null]);
    }

    private function dispensingForViewer(int $healthUnitId)
    {
        return DispensingRecord::qualifying()->where('health_unit_id', $healthUnitId)
            ->when(session('role') === 'midwife', fn ($q) => $q->where('dispensed_by', session('user_id')));
    }

    private function dispensingSearchUrl(int $id, int $page): string
    {
        $filters = ['page' => $page, 'highlight' => $id];
        if (session('role') === 'midwife') {
            $filters['dispensing_scope'] = 'personal';
        }

        return route('dispensing.index', $filters).'#dispensing-'.$id;
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
        } elseif ($recordOrderValue === null) {
            $position = (clone $baseQuery)->where(function ($q) use ($orderColumn, $orderDirection, $recordId) {
                if ($orderDirection === 'desc') {
                    $q->whereNotNull($orderColumn)->orWhere(fn ($ties) => $ties->whereNull($orderColumn)->where('id', '>', $recordId));
                } else {
                    $q->whereNull($orderColumn)->where('id', '<', $recordId);
                }
            })->count();
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

        $role = session('role');
        $healthUnitId = session('health_unit_id');
        $values = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'scope' => ['nullable', 'string', Rule::in(array_keys(SearchSections::forViewer($role, $healthUnitId !== null)))],
        ]);
        $query = trim($values['q'] ?? '');
        $scope = $values['scope'] ?? 'all';


        $medicineResults = collect();
        $userResults = collect();
        $rhuResults = collect();
        $inventoryResults = collect();
        $stockLogResults = collect();
        $allocationResults = collect();
        $dispensingResults = collect();
        $transferResults = collect();
        $forecastResults = collect();

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
                    ->orderBy('medicine_name', 'asc')->orderBy('id', 'asc');

                $medicineResults = (clone $searchQuery)->get();

                $baseNoSearch = Medicine::query()->orderBy('medicine_name', 'asc')->orderBy('id', 'asc');

                foreach ($medicineResults as $med) {
                    $med->search_page = $this->getPageForRecord(
                        $baseNoSearch, 'medicine_name', 'asc',
                        $med->id, $med->medicine_name,
                        (int) config('pagination.medicines', 20)
                    );
                }
            }

            // ---------- Users ----------
            if ($role === 'admin' && ($scope === 'all' || $scope === 'users')) {
                $searchQuery = $this->searchableUsers()->with('healthUnit')
                    ->where(function ($q) use ($query) {
                        $q->where('name', 'like', "%{$query}%")
                            ->orWhere('username', 'like', "%{$query}%")
                            ->orWhere('role', 'like', "%{$query}%");
                    })
                    ->orderBy('name', 'asc')->orderBy('id', 'asc');

                $userResults = (clone $searchQuery)->get();

                $baseNoSearch = $this->searchableUsers()->with('healthUnit')->orderBy('name', 'asc')->orderBy('id', 'asc');

                foreach ($userResults as $user) {
                    $user->search_page = $this->getPageForRecord(
                        $baseNoSearch, 'name', 'asc',
                        $user->id, $user->name,
                        (int) config('pagination.inventory', 20)
                    );
                }
            }

            // ---------- RHU ----------
            if ($role === 'admin' && $healthUnitId === null && ($scope === 'all' || $scope === 'rhu')) {
                $searchQuery = HealthUnit::query()
                    ->where(function ($q) use ($query) {
                        $q->where('name', 'like', "%{$query}%")
                            ->orWhere('municipality', 'like', "%{$query}%")
                            ->orWhere('address', 'like', "%{$query}%");
                    })
                    ->orderBy('name', 'asc')->orderBy('id', 'asc');

                $rhuResults = (clone $searchQuery)->get();

                $baseNoSearch = HealthUnit::query()->orderBy('name', 'asc')->orderBy('id', 'asc');

                foreach ($rhuResults as $rhu) {
                    $rhu->search_page = $this->getPageForRecord(
                        $baseNoSearch, 'name', 'asc',
                        $rhu->id, $rhu->name,
                        (int) config('pagination.inventory', 20)
                    );
                }
            }

            // ---------- Inventory ----------  ← NOW SEARCHES BATCH
            if ($role !== 'midwife' && ($scope === 'all' || $scope === 'inventory')) {
                $searchQuery = $this->inventoryForViewer()
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

                $baseNoSearch = $this->inventoryForViewer()
                    ->orderByDesc('id');

                foreach ($inventoryResults as $inv) {
                    $inv->search_page = $this->getPageForRecord(
                        (clone $baseNoSearch)->when($healthUnitId === null, fn ($facility) => $facility->where('health_unit_id', $inv->health_unit_id)), 'id', 'desc',
                        $inv->id, $inv->id,
                        (int) config('pagination.inventory', 20)
                    );
                    $inv->search_url = route('inventory.index', $this->stockListingParameters($inv, $inv->search_page)).'#inventory-'.$inv->id;
                }
            }

            // ---------- Stock Logs ----------  ← NOW SEARCHES BATCH
            if ($role !== 'midwife' && ($scope === 'all' || $scope === 'stock_logs')) {
                $searchQuery = $this->stockLogsForViewer()
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
                    ->orderByDesc('date_added')
                    ->orderByDesc('id');

                $stockLogResults = (clone $searchQuery)->get();

                $baseNoSearch = $this->stockLogsForViewer()
                    ->orderByDesc('date_added')
                    ->orderByDesc('id');

                foreach ($stockLogResults as $log) {
                    $log->search_page = $this->getPageForRecord(
                        (clone $baseNoSearch)->when($healthUnitId === null, fn ($facility) => $facility->where('health_unit_id', $log->health_unit_id)), 'date_added', 'desc',
                        $log->id, $log->getRawOriginal('date_added'),
                        20
                    );
                    $log->search_url = route('stock_logs.index', $this->stockListingParameters($log, $log->search_page)).'#stock-log-'.$log->id;
                }
            }

            // ---------- Allocation ----------  ← NOW SEARCHES BATCH (via inventory relation)
            if ($role !== 'midwife' && ($scope === 'all' || $scope === 'allocation')) {
                $searchQuery = $this->allocationsForViewer()
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
                    ->orderByDesc('date_allocated')
                    ->orderByDesc('id');

                $allocationResults = (clone $searchQuery)->get();

                $baseNoSearch = $this->allocationsForViewer()
                    ->orderByDesc('date_allocated')
                    ->orderByDesc('id');

                foreach ($allocationResults as $alloc) {
                    $alloc->search_page = $this->getPageForRecord(
                        $baseNoSearch, 'date_allocated', 'desc',
                        $alloc->id, $alloc->getRawOriginal('date_allocated'),
                        (int) config('pagination.inventory', 20)
                    );
                }
            }

            // ---------- Dispensing ----------  ← NOW SEARCHES BATCH
            if ($healthUnitId !== null && ($scope === 'all' || $scope === 'dispensing')) {
                $perPage = (int) config('pagination.dispensing', 15);
                $baseNoSearch = $this->dispensingForViewer($healthUnitId);
                $searchQuery = (clone $baseNoSearch)->with(['medicine', 'dispensedByUser'])
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
                    ->orderByDesc('date_dispensed')
                    ->orderByDesc('id');

                $dispensingResults = (clone $searchQuery)->get();

                foreach ($dispensingResults as $rec) {
                    $rec->search_page = $this->getPageForRecord(
                        $baseNoSearch, 'date_dispensed', 'desc',
                        $rec->id, $rec->date_dispensed?->toDateString(),
                        $perPage
                    );
                    $rec->search_url = $this->dispensingSearchUrl($rec->id, $rec->search_page);
                }
            }
        }

        if ($query !== '' && $role !== 'midwife' && in_array($scope, ['all', 'transfers'], true)) {
            $baseTransfers = $this->transfersForViewer();
            $transferResults = (clone $baseTransfers)->where(function ($matches) use ($query) {
                $matches->where('notes', 'like', '%'.$query.'%')
                    ->orWhereHas('medicine', fn ($medicine) => $medicine->where('medicine_name', 'like', '%'.$query.'%'))
                    ->orWhereHas('fromHealthUnit', fn ($unit) => $unit->where('name', 'like', '%'.$query.'%'))
                    ->orWhereHas('toHealthUnit', fn ($unit) => $unit->where('name', 'like', '%'.$query.'%'));
            })->get();
            foreach ($transferResults as $transfer) {
                $transfer->search_page = $this->getPageForRecord($baseTransfers, 'created_at', 'desc',
                    $transfer->id, $transfer->getRawOriginal('created_at'), 20);
            }
        }
        if ($query !== '' && $role !== 'midwife' && in_array($scope, ['all', 'forecast'], true)) {
            $forecastResults = $this->forecastMatches($query, $healthUnitId);
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
            'transferResults' => $transferResults,
            'forecastResults' => $forecastResults,
        ]);
    }

    private function transfersForViewer()
    {
        return TransferRequest::with(['medicine', 'fromHealthUnit', 'toHealthUnit'])
            ->visibleToViewer(session('health_unit_id'), session('role'))
            ->orderByDesc('created_at')->orderByDesc('id');
    }

    private function forecastMatches(string $query, ?int $healthUnitId)
    {
        return collect(app(ForecastService::class)->getForecast($healthUnitId)['items'])->values()
            ->map(function ($item, $index) {
                $page = intdiv($index, 15) + 1;
                $item['search_url'] = route('forecast.index', ['forecast_page' => $page]).'#forecast-item-'
                    .$item['inventory']->health_unit_id.'-'.$item['inventory']->medicine_id;

                return $item;
            })->filter(fn ($item) => mb_stripos($item['medicine_name'].' '.$item['health_unit_name'], $query) !== false)->values();
    }

    /**
     * Autocomplete for the live header dropdown.
     */
    public function autocomplete(Request $request)
    {
        $role = session('role');
        $healthUnitId = session('health_unit_id');
        $values = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'scope' => ['nullable', 'string', Rule::in(array_keys(SearchSections::forViewer($role, $healthUnitId !== null)))],
        ]);
        $query = trim($values['q'] ?? '');
        $scope = $values['scope'] ?? 'all';

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $suggestions = [];
        $perPage = 20;   // must match the per-page of each module


        // Medicines
        if ($scope === 'all' || $scope === 'medicine') {
            $medicines = Medicine::where('medicine_name', 'like', "%{$query}%")
                ->orWhere('dosage', 'like', "%{$query}%")
                ->orderBy('medicine_name')->orderBy('id')
                ->limit(5)
                ->get(['id', 'medicine_name', 'dosage']);

            $baseNoSearch = Medicine::orderBy('medicine_name', 'asc');

            foreach ($medicines as $m) {
                $page = $this->getPageForRecord($baseNoSearch, 'medicine_name', 'asc', $m->id, $m->medicine_name, (int) config('pagination.medicines', 20));
                $suggestions[] = [
                    'text' => $m->medicine_name.($m->dosage ? ' - '.$m->dosage : ''),
                    'type' => 'Medicine',
                    'url' => route('medicine.index', ['page' => $page, 'highlight' => $m->id]).'#medicine-'.$m->id,
                ];
            }
        }

        // Users (admin only)
        if ($role === 'admin' && ($scope === 'all' || $scope === 'users')) {
            $users = $this->searchableUsers()->where(fn ($q) => $q->where('name', 'like', "%{$query}%")
                ->orWhere('username', 'like', "%{$query}%"))
                ->orderBy('name')->orderBy('id')
                ->limit(5)
                ->get(['id', 'name', 'username']);

            $baseNoSearch = $this->searchableUsers()->orderBy('name', 'asc')->orderBy('id', 'asc');

            foreach ($users as $u) {
                $page = $this->getPageForRecord($baseNoSearch, 'name', 'asc', $u->id, $u->name, (int) config('pagination.inventory', 20));
                $suggestions[] = [
                    'text' => $u->name.' ('.$u->username.')',
                    'type' => 'User',
                    'url' => route('users.index', ['page' => $page, 'highlight' => $u->id]).'#user-'.$u->id,
                ];
            }
        }

        // RHU
        if ($role === 'admin' && $healthUnitId === null && ($scope === 'all' || $scope === 'rhu')) {
            $rhus = HealthUnit::where('name', 'like', "%{$query}%")
                ->orWhere('municipality', 'like', "%{$query}%")
                ->orderBy('name')->orderBy('id')
                ->limit(5)
                ->get(['id', 'name', 'municipality']);

            $baseNoSearch = HealthUnit::orderBy('name', 'asc');

            foreach ($rhus as $r) {
                $page = $this->getPageForRecord($baseNoSearch, 'name', 'asc', $r->id, $r->name, (int) config('pagination.inventory', 20));
                $suggestions[] = [
                    'text' => $r->name.' ('.$r->municipality.')',
                    'type' => 'RHU',
                    'url' => route('rhu.index', ['page' => $page, 'highlight' => $r->id]).'#rhu-'.$r->id,
                ];
            }
        }

        // Inventory — now includes batch
        if ($role !== 'midwife' && ($scope === 'all' || $scope === 'inventory')) {
            $inventories = $this->inventoryForViewer()
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

            $baseNoSearch = $this->inventoryForViewer()
                ->orderByDesc('id');

            foreach ($inventories as $inv) {
                $page = $this->getPageForRecord((clone $baseNoSearch)->when($healthUnitId === null, fn ($facility) => $facility->where('health_unit_id', $inv->health_unit_id)), 'id', 'desc', $inv->id, $inv->id, (int) config('pagination.inventory', 20));
                $batchPart = $inv->batch_no ? ' · Batch '.$inv->batch_no : '';
                $suggestions[] = [
                    'text' => ($inv->medicine->medicine_name ?? 'N/A').$batchPart.' (Stock: '.$inv->available_stock.')',
                    'type' => 'Inventory',
                    'url' => route('inventory.index', $this->stockListingParameters($inv, $page)).'#inventory-'.$inv->id,
                ];
            }
        }

        // Stock Logs — now includes batch
        if ($role !== 'midwife' && ($scope === 'all' || $scope === 'stock_logs')) {
            $stockLogs = $this->stockLogsForViewer()
                ->where(function ($q) use ($query) {
                    $q->where('batch_no', 'like', "%{$query}%")
                        ->orWhereHas('medicine', function ($mq) use ($query) {
                            $mq->where('medicine_name', 'like', "%{$query}%");
                        })
                        ->orWhere('date_added', 'like', "%{$query}%");
                })
                ->orderByDesc('date_added')
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            $baseNoSearch = $this->stockLogsForViewer()
                ->orderByDesc('date_added')
                ->orderByDesc('id');

            foreach ($stockLogs as $log) {
                $page = $this->getPageForRecord((clone $baseNoSearch)->when($healthUnitId === null, fn ($facility) => $facility->where('health_unit_id', $log->health_unit_id)), 'date_added', 'desc', $log->id, $log->getRawOriginal('date_added'), 20);
                $batchPart = $log->batch_no ? ' · Batch '.$log->batch_no : '';
                $suggestions[] = [
                    'text' => ($log->medicine->medicine_name ?? 'N/A').$batchPart.' (+'.$log->quantity_added.' on '.$log->date_added.')',
                    'type' => 'Stock Log',
                    'url' => route('stock_logs.index', $this->stockListingParameters($log, $page)).'#stock-log-'.$log->id,
                ];
            }
        }

        // Allocation — now includes batch
        if ($role !== 'midwife' && ($scope === 'all' || $scope === 'allocation')) {
            $allocations = $this->allocationsForViewer()
                ->where(function ($q) use ($query) {
                    $q->where('destination', 'like', "%{$query}%")
                        ->orWhereHas('medicine', function ($mq) use ($query) {
                            $mq->where('medicine_name', 'like', "%{$query}%");
                        })
                        ->orWhereHas('inventory', function ($iq) use ($query) {
                            $iq->where('batch_no', 'like', "%{$query}%");
                        });
                })
                ->orderByDesc('date_allocated')
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            $baseNoSearch = $this->allocationsForViewer()
                ->orderByDesc('date_allocated')
                ->orderByDesc('id');

            foreach ($allocations as $a) {
                $page = $this->getPageForRecord($baseNoSearch, 'date_allocated', 'desc', $a->id, $a->getRawOriginal('date_allocated'), (int) config('pagination.inventory', 20));
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
        if ($healthUnitId !== null && ($scope === 'all' || $scope === 'dispensing')) {
            $perPage = (int) config('pagination.dispensing', 15);
            $baseNoSearch = $this->dispensingForViewer($healthUnitId);
            $records = (clone $baseNoSearch)->with('medicine')
                ->where(function ($q) use ($query) {
                    $q->where('batch_no', 'like', "%{$query}%")
                        ->orWhere('doctor_name', 'like', "%{$query}%")
                        ->orWhereHas('medicine', function ($mq) use ($query) {
                            $mq->where('medicine_name', 'like', "%{$query}%");
                        });
                })
                ->orderByDesc('date_dispensed')
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            foreach ($records as $rec) {
                $page = $this->getPageForRecord($baseNoSearch, 'date_dispensed', 'desc', $rec->id, $rec->date_dispensed?->toDateString(), $perPage);
                $batchPart = $rec->batch_no ? ' · Batch '.$rec->batch_no : '';
                $suggestions[] = [
                    'text' => ($rec->medicine->medicine_name ?? 'N/A').$batchPart.' (Qty: '.$rec->quantity_dispensed.' on '.$rec->date_dispensed.')',
                    'type' => 'Dispensing',
                    'url' => $this->dispensingSearchUrl($rec->id, $page),
                ];
            }
        }

        if ($role !== 'midwife' && in_array($scope, ['all', 'transfers'], true) && count($suggestions) < 10) {
            $baseTransfers = $this->transfersForViewer();
            $transfers = (clone $baseTransfers)->where(function ($matches) use ($query) {
                $matches->where('notes', 'like', '%'.$query.'%')
                    ->orWhereHas('medicine', fn ($medicine) => $medicine->where('medicine_name', 'like', '%'.$query.'%'))
                    ->orWhereHas('fromHealthUnit', fn ($unit) => $unit->where('name', 'like', '%'.$query.'%'))
                    ->orWhereHas('toHealthUnit', fn ($unit) => $unit->where('name', 'like', '%'.$query.'%'));
            })->limit(5)->get();
            foreach ($transfers as $transfer) {
                $page = $this->getPageForRecord($baseTransfers, 'created_at', 'desc',
                    $transfer->id, $transfer->getRawOriginal('created_at'), 20);
                $suggestions[] = ['text' => ($transfer->medicine->medicine_name ?? 'Transfer').' - '.$transfer->status,
                    'type' => 'Transfer', 'url' => route('transfers.index', ['page' => $page]).'#transfer-'.$transfer->id];
            }
        }
        if ($role !== 'midwife' && in_array($scope, ['all', 'forecast'], true) && count($suggestions) < 10) {
            foreach ($this->forecastMatches($query, $healthUnitId)->take(5) as $item) {
                $suggestions[] = ['text' => $item['medicine_name'].' - '.$item['health_unit_name'],
                    'type' => 'Forecast', 'url' => $item['search_url']];
            }
        }

        return response()->json(array_slice($suggestions, 0, 10));
    }
}
