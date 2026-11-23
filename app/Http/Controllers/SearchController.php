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
use App\Services\StockSummaryService;
use App\Support\SearchSections;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SearchController extends Controller
{
    private const RESULTS_PER_PAGE = 20;

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

    /** Rank the matched IDs in their destination listing in one query. */
    private function setListingPages(Builder $listing, EloquentCollection $records, string $column, string $direction,
        int $perPage, bool $byFacility = false): void
    {
        if ($records->isEmpty()) {
            return;
        }

        $ordering = $column === 'id' ? "id {$direction}" : "{$column} {$direction}, id {$direction}";
        $partition = $byFacility ? 'PARTITION BY health_unit_id ' : '';
        $ranked = (clone $listing)->reorder()->select('id')
            ->when($byFacility, fn ($query) => $query->whereIn('health_unit_id', $records->pluck('health_unit_id')->unique()))
            ->selectRaw("ROW_NUMBER() OVER ({$partition}ORDER BY {$ordering}) AS listing_position");
        $positions = DB::query()->fromSub($ranked->toBase(), 'ranked_listing')
            ->whereIn('id', $records->modelKeys())->pluck('listing_position', 'id');

        foreach ($records as $record) {
            $record->search_page = (int) ceil(($positions[$record->id] ?? 1) / $perPage);
        }
    }

    private function resultPage(Builder $query, string $section): Paginator
    {
        return $query->simplePaginate(self::RESULTS_PER_PAGE, ['*'], $section.'_results_page')
            ->withQueryString()->fragment('search-'.$section);
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


        $empty = new Paginator([], self::RESULTS_PER_PAGE);
        $medicineResults = $userResults = $rhuResults = $inventoryResults = $stockLogResults = $empty;
        $allocationResults = $dispensingResults = $transferResults = $forecastResults = $empty;

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

                $medicineResults = $this->resultPage($searchQuery, 'medicine');
                $this->setListingPages(Medicine::query(), $medicineResults->getCollection(), 'medicine_name', 'asc',
                    (int) config('pagination.medicines', 20));
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

                $userResults = $this->resultPage($searchQuery, 'users');
                $this->setListingPages($this->searchableUsers(), $userResults->getCollection(), 'name', 'asc',
                    (int) config('pagination.inventory', 20));
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

                $rhuResults = $this->resultPage($searchQuery, 'rhu');
                $this->setListingPages(HealthUnit::query(), $rhuResults->getCollection(), 'name', 'asc',
                    (int) config('pagination.inventory', 20));
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

                $inventoryResults = $this->resultPage($searchQuery, 'inventory');
                $this->setListingPages($this->inventoryForViewer(), $inventoryResults->getCollection(), 'id', 'desc',
                    (int) config('pagination.inventory', 20), $healthUnitId === null);

                foreach ($inventoryResults as $inv) {
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

                $stockLogResults = $this->resultPage($searchQuery, 'stock_logs');
                $this->setListingPages($this->stockLogsForViewer(), $stockLogResults->getCollection(), 'date_added', 'desc',
                    20, $healthUnitId === null);

                foreach ($stockLogResults as $log) {
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

                $allocationResults = $this->resultPage($searchQuery, 'allocation');
                $this->setListingPages($this->allocationsForViewer(), $allocationResults->getCollection(), 'date_allocated', 'desc',
                    (int) config('pagination.inventory', 20));
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

                $dispensingResults = $this->resultPage($searchQuery, 'dispensing');
                $this->setListingPages($baseNoSearch, $dispensingResults->getCollection(), 'date_dispensed', 'desc', $perPage);

                foreach ($dispensingResults as $rec) {
                    $rec->search_url = $this->dispensingSearchUrl($rec->id, $rec->search_page);
                }
            }
        }

        if ($query !== '' && $role !== 'midwife' && in_array($scope, ['all', 'transfers'], true)) {
            $baseTransfers = $this->transfersForViewer();
            $searchQuery = (clone $baseTransfers)->where(function ($matches) use ($query) {
                $matches->where('notes', 'like', '%'.$query.'%')
                    ->orWhereHas('medicine', fn ($medicine) => $medicine->where('medicine_name', 'like', '%'.$query.'%'))
                    ->orWhereHas('fromHealthUnit', fn ($unit) => $unit->where('name', 'like', '%'.$query.'%'))
                    ->orWhereHas('toHealthUnit', fn ($unit) => $unit->where('name', 'like', '%'.$query.'%'));
            });
            $transferResults = $this->resultPage($searchQuery, 'transfers');
            $this->setListingPages($baseTransfers, $transferResults->getCollection(), 'created_at', 'desc', 20);
        }
        if ($query !== '' && $role !== 'midwife' && in_array($scope, ['all', 'forecast'], true)) {
            $matches = $this->forecastMatches($query, $healthUnitId);
            $pageName = 'forecast_results_page';
            $page = Paginator::resolveCurrentPage($pageName);
            $forecastResults = new Paginator($matches->slice(($page - 1) * self::RESULTS_PER_PAGE, self::RESULTS_PER_PAGE + 1),
                self::RESULTS_PER_PAGE, $page, ['path' => $request->url(), 'pageName' => $pageName]);
            $forecastResults->withQueryString()->fragment('search-forecast');
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

    private function forecastMatches(string $query, ?int $healthUnitId, bool $includeProjections = true)
    {
        // The inventory summary has the same ordered facility/medicine rows as the forecast.
        $items = $includeProjections
            ? app(ForecastService::class)->getForecast($healthUnitId)['items']
            : app(StockSummaryService::class)->inventories($healthUnitId === null ? null : [$healthUnitId])
                ->map(fn (Inventory $inventory) => ['inventory' => $inventory,
                    'medicine_name' => $inventory->medicine->medicine_name, 'health_unit_name' => $inventory->healthUnit->name]);

        return $items->values()
            ->filter(fn ($item) => mb_stripos($item['medicine_name'].' '.$item['health_unit_name'], $query) !== false)
            ->map(function ($item, $index) {
                $page = intdiv($index, 15) + 1;
                $item['search_url'] = route('forecast.index', ['forecast_page' => $page]).'#forecast-item-'
                    .$item['inventory']->health_unit_id.'-'.$item['inventory']->medicine_id;

                return $item;
            })->values();
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
        // Medicines
        if ($scope === 'all' || $scope === 'medicine') {
            $medicines = Medicine::where('medicine_name', 'like', "%{$query}%")
                ->orWhere('dosage', 'like', "%{$query}%")
                ->orderBy('medicine_name')->orderBy('id')
                ->limit(5)
                ->get(['id', 'medicine_name', 'dosage']);

            $this->setListingPages(Medicine::query(), $medicines, 'medicine_name', 'asc', (int) config('pagination.medicines', 20));

            foreach ($medicines as $m) {
                $suggestions[] = [
                    'text' => $m->medicine_name.($m->dosage ? ' - '.$m->dosage : ''),
                    'type' => 'Medicine',
                    'url' => route('medicine.index', ['page' => $m->search_page, 'highlight' => $m->id]).'#medicine-'.$m->id,
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

            $this->setListingPages($this->searchableUsers(), $users, 'name', 'asc', (int) config('pagination.inventory', 20));

            foreach ($users as $u) {
                $suggestions[] = [
                    'text' => $u->name.' ('.$u->username.')',
                    'type' => 'User',
                    'url' => route('users.index', ['page' => $u->search_page, 'highlight' => $u->id]).'#user-'.$u->id,
                ];
            }
        }

        // RHU
        if ($role === 'admin' && $healthUnitId === null && ($scope === 'all' || $scope === 'rhu') && count($suggestions) < 10) {
            $rhus = HealthUnit::where('name', 'like', "%{$query}%")
                ->orWhere('municipality', 'like', "%{$query}%")
                ->orderBy('name')->orderBy('id')
                ->limit(5)
                ->get(['id', 'name', 'municipality']);

            $this->setListingPages(HealthUnit::query(), $rhus, 'name', 'asc', (int) config('pagination.inventory', 20));

            foreach ($rhus as $r) {
                $suggestions[] = [
                    'text' => $r->name.' ('.$r->municipality.')',
                    'type' => 'RHU',
                    'url' => route('rhu.index', ['page' => $r->search_page, 'highlight' => $r->id]).'#rhu-'.$r->id,
                ];
            }
        }

        // Inventory — now includes batch
        if ($role !== 'midwife' && ($scope === 'all' || $scope === 'inventory') && count($suggestions) < 10) {
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

            $this->setListingPages($this->inventoryForViewer(), $inventories, 'id', 'desc',
                (int) config('pagination.inventory', 20), $healthUnitId === null);

            foreach ($inventories as $inv) {
                $batchPart = $inv->batch_no ? ' · Batch '.$inv->batch_no : '';
                $suggestions[] = [
                    'text' => ($inv->medicine->medicine_name ?? 'N/A').$batchPart.' (Stock: '.$inv->available_stock.')',
                    'type' => 'Inventory',
                    'url' => route('inventory.index', $this->stockListingParameters($inv, $inv->search_page)).'#inventory-'.$inv->id,
                ];
            }
        }

        // Stock Logs — now includes batch
        if ($role !== 'midwife' && ($scope === 'all' || $scope === 'stock_logs') && count($suggestions) < 10) {
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

            $this->setListingPages($this->stockLogsForViewer(), $stockLogs, 'date_added', 'desc', 20, $healthUnitId === null);

            foreach ($stockLogs as $log) {
                $batchPart = $log->batch_no ? ' · Batch '.$log->batch_no : '';
                $suggestions[] = [
                    'text' => ($log->medicine->medicine_name ?? 'N/A').$batchPart.' (+'.$log->quantity_added.' on '.$log->date_added.')',
                    'type' => 'Stock Log',
                    'url' => route('stock_logs.index', $this->stockListingParameters($log, $log->search_page)).'#stock-log-'.$log->id,
                ];
            }
        }

        // Allocation — now includes batch
        if ($role !== 'midwife' && ($scope === 'all' || $scope === 'allocation') && count($suggestions) < 10) {
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

            $this->setListingPages($this->allocationsForViewer(), $allocations, 'date_allocated', 'desc',
                (int) config('pagination.inventory', 20));

            foreach ($allocations as $a) {
                $destination = $a->destination ?: 'N/A';
                $batchPart = ($a->inventory && $a->inventory->batch_no) ? ' · Batch '.$a->inventory->batch_no : '';
                $suggestions[] = [
                    'text' => ($a->medicine->medicine_name ?? 'N/A').$batchPart.' → '.$destination.' ('.$a->quantity_allocated.')',
                    'type' => 'Allocation',
                    'url' => route('allocation.index', ['page' => $a->search_page, 'highlight' => $a->id]).'#allocation-'.$a->id,
                ];
            }
        }

        // Dispensing — now includes batch
        if ($healthUnitId !== null && ($scope === 'all' || $scope === 'dispensing') && count($suggestions) < 10) {
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

            $this->setListingPages($baseNoSearch, $records, 'date_dispensed', 'desc', $perPage);
            foreach ($records as $rec) {
                $batchPart = $rec->batch_no ? ' · Batch '.$rec->batch_no : '';
                $suggestions[] = [
                    'text' => ($rec->medicine->medicine_name ?? 'N/A').$batchPart.' (Qty: '.$rec->quantity_dispensed.' on '.$rec->date_dispensed.')',
                    'type' => 'Dispensing',
                    'url' => $this->dispensingSearchUrl($rec->id, $rec->search_page),
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
            $this->setListingPages($baseTransfers, $transfers, 'created_at', 'desc', 20);
            foreach ($transfers as $transfer) {
                $suggestions[] = ['text' => ($transfer->medicine->medicine_name ?? 'Transfer').' - '.$transfer->status,
                    'type' => 'Transfer', 'url' => route('transfers.index', ['page' => $transfer->search_page]).'#transfer-'.$transfer->id];
            }
        }
        if ($role !== 'midwife' && in_array($scope, ['all', 'forecast'], true) && count($suggestions) < 10) {
            foreach ($this->forecastMatches($query, $healthUnitId, includeProjections: false)->take(5) as $item) {
                $suggestions[] = ['text' => $item['medicine_name'].' - '.$item['health_unit_name'],
                    'type' => 'Forecast', 'url' => $item['search_url']];
            }
        }

        return response()->json(array_slice($suggestions, 0, 10));
    }
}
