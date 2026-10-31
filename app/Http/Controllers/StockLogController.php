<?php

namespace App\Http\Controllers;

use App\Helpers\ActivityLogger;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockLogController extends Controller
{
    private function checkAccess()
    {
        if (! session()->has('user_id')) {
            return redirect()->route('login');
        }
        if (! in_array(session('role'), ['admin', 'staff'])) {
            return redirect()->route('login');
        }

        return null;
    }

    private function parseBatchInput(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        $parts = array_filter(array_map('trim', explode(',', $raw)));
        $expanded = [];

        foreach ($parts as $part) {
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $part, $m)) {
                $s = (int) $m[1];
                $e = (int) $m[2];
                if ($s > $e) {
                    [$s, $e] = [$e, $s];
                }
                if ($e - $s > 1000) {
                    $e = $s + 1000;
                }
                for ($i = $s; $i <= $e; $i++) {
                    $expanded[] = (string) $i;
                }
            } else {
                $expanded[] = $part;
            }
        }

        return array_values(array_unique($expanded));
    }

    private function applyBatchFilter($query, Request $request)
    {
        return $query->when($request->filled('batch_input'), function ($q) use ($request) {
            $batches = $this->parseBatchInput($request->batch_input);
            if (empty($batches)) {
                return;
            }

            $q->where(function ($sub) use ($batches) {
                foreach ($batches as $b) {
                    $sub->orWhere('batch_no', $b)
                        ->orWhere('batch_no', 'like', '%-'.$b)
                        ->orWhere('batch_no', 'like', '%-0'.$b)
                        ->orWhere('batch_no', 'like', '%-00'.$b);
                }
            });
        });
    }

    public function index(Request $request)
    {
        if (! session()->has('user_id')) {
            return redirect()->route('login');
        }

        $search = $request->search;
        $rhuFilter = $request->rhu_filter;
        $typeFilter = $request->type;
        $batchInput = $request->batch_input;
        $category = $request->category;
        $healthUnitId = session('health_unit_id');
        $isPhoAdmin = is_null($healthUnitId);
        $phoId = $isPhoAdmin ? HealthUnit::phoId() : null;

        $query = StockLog::with(['medicine', 'addedByUser', 'healthUnit'])
            ->when($healthUnitId, function ($q) use ($healthUnitId) {
                $q->where('health_unit_id', $healthUnitId);
            })
            ->when($isPhoAdmin && $rhuFilter === 'pho' && $phoId, function ($q) use ($phoId) {
                $q->where('health_unit_id', $phoId);
            })
            ->when($isPhoAdmin && $rhuFilter && $rhuFilter !== 'pho', function ($q) use ($rhuFilter) {
                $q->where('health_unit_id', $rhuFilter);
            })
            ->when($isPhoAdmin && ! $rhuFilter && $phoId, function ($q) use ($phoId) {
                $q->where('health_unit_id', '!=', $phoId);
            })
            ->when($search, function ($query, $search) {
                $query->whereHas('medicine', function ($sub) use ($search) {
                    $sub->where('medicine_name', 'like', "%{$search}%");
                });
            })
            ->when($typeFilter === 'addition', function ($q) {
                $q->where(fn ($sub) => $sub->whereNull('remarks')->orWhere('remarks', 'like', 'Added%'));
            })
            ->when($typeFilter === 'release', function ($q) {
                $q->where('remarks', 'like', 'Released%');
            })
            ->when($category, function ($q) use ($category) {
                $q->whereHas('medicine', fn ($mq) => $mq->where('category', $category));
            });

        $query = $this->applyBatchFilter($query, $request);

        $stockLogs = $query
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->appends([
                'search' => $search,
                'rhu_filter' => $rhuFilter,
                'type' => $typeFilter,
                'batch_input' => $batchInput,
                'category' => $category,
            ]);

        $healthUnits = HealthUnit::rhuOnly()->orderBy('name')->get();

        return view('stock_logs.index', compact('stockLogs', 'healthUnits'));
    }

    public function create()
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        if (is_null(session('health_unit_id'))) {
            return redirect()->route('stock_logs.index')
                ->with('error', 'Stock addition is only available at the RHU level.');
        }

        $healthUnitId = session('health_unit_id');

        $medicines = Medicine::orderBy('medicine_name')->get();

        $inventoryData = Inventory::where('health_unit_id', $healthUnitId)
            ->selectRaw('medicine_id, COALESCE(SUM(available_stock), 0) as total_stock')
            ->groupBy('medicine_id')
            ->pluck('total_stock', 'medicine_id')
            ->toArray();

        return view('stock_logs.create', compact('medicines', 'inventoryData'));
    }

    public function store(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        if (is_null(session('health_unit_id'))) {
            return redirect()->route('stock_logs.index')
                ->with('error', 'Stock addition is only available at the RHU level.');
        }

        $request->validate([
            'medicine_id' => 'required|exists:medicines,id',
            'quantity_added' => 'required|integer|min:1',
            'date_added' => 'required|date',
            'expiration_date' => 'nullable|date',
            'batch_no' => 'nullable|string|max:100',
            'storage_location' => 'nullable|string|max:255',
            'pack_type' => 'nullable|string|max:50',
            'pieces_per_pack' => 'nullable|integer|min:1',
        ]);

        $medicine = Medicine::findOrFail($request->medicine_id);
        $healthUnitId = session('health_unit_id');

        [$stockLog, $inventory] = DB::transaction(function () use ($request, $medicine, $healthUnitId) {
            HealthUnit::whereKey($healthUnitId)->lockForUpdate()->firstOrFail();
            $inventory = Inventory::firstOrCreate([
                'medicine_id' => $medicine->id, 'health_unit_id' => $healthUnitId,
                'batch_no' => $request->batch_no ?: null, 'expiration_date' => $request->expiration_date ?: null,
            ], ['available_stock' => 0, 'allocated_stock' => 0, 'storage_location' => $request->storage_location,
                'pack_type' => $request->pack_type, 'pieces_per_pack' => $request->pieces_per_pack]);

            $stockLog = StockLog::create([
                'medicine_id' => $medicine->id,
                'health_unit_id' => $healthUnitId,
                'quantity_added' => $request->quantity_added,
                'inventory_id' => $inventory->id,
                'movement_type' => 'addition',
                'batch_no' => $request->batch_no,
                'storage_location' => $request->storage_location,
                'date_added' => $request->date_added,
                'added_by' => session('user_id'),
                'remarks' => 'Added — Stock entry',
            ]);

            $inventory->increment('available_stock', $request->quantity_added);

            return [$stockLog, $inventory->fresh()];
        });

        $logParts = [];
        $logParts[] = 'Added stock for '.$medicine->medicine_name;
        $logParts[] = 'with quantity '.$stockLog->quantity_added;
        if ($stockLog->storage_location) {
            $logParts[] = '| Storage: '.$stockLog->storage_location;
        }
        if ($inventory->pack_type && $inventory->pieces_per_pack) {
            $logParts[] = '| Packaging: '.$inventory->pieces_per_pack.' per '.$inventory->pack_type;
        }
        $logParts[] = '. New available stock: '.$inventory->available_stock.'.';

        ActivityLogger::log(
            'Stock Logs',
            'Create',
            implode(' ', $logParts)
        );

        return redirect()->route('stock_logs.index')->with('success', 'Stock added successfully.');
    }

    public function exportCsv(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $search = $request->search;
        $rhuFilter = $request->rhu_filter;
        $typeFilter = $request->type;
        $category = $request->category;
        $healthUnitId = session('health_unit_id');
        $isPhoAdmin = is_null($healthUnitId);
        $phoId = $isPhoAdmin ? HealthUnit::phoId() : null;

        $query = StockLog::with(['medicine', 'healthUnit', 'addedByUser'])
            ->when($healthUnitId, function ($q) use ($healthUnitId) {
                $q->where('health_unit_id', $healthUnitId);
            })
            ->when($isPhoAdmin && $rhuFilter === 'pho' && $phoId, function ($q) use ($phoId) {
                $q->where('health_unit_id', $phoId);
            })
            ->when($isPhoAdmin && $rhuFilter && $rhuFilter !== 'pho', function ($q) use ($rhuFilter) {
                $q->where('health_unit_id', $rhuFilter);
            })
            ->when($isPhoAdmin && ! $rhuFilter && $phoId, function ($q) use ($phoId) {
                $q->where('health_unit_id', '!=', $phoId);
            })
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('medicine', function ($mq) use ($search) {
                        $mq->where('medicine_name', 'like', "%{$search}%");
                    })->orWhereHas('addedByUser', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
                });
            })
            ->when($typeFilter === 'addition', function ($q) {
                $q->where(fn ($sub) => $sub->whereNull('remarks')->orWhere('remarks', 'like', 'Added%'));
            })
            ->when($typeFilter === 'release', function ($q) {
                $q->where('remarks', 'like', 'Released%');
            })
            ->when($category, function ($q) use ($category) {
                $q->whereHas('medicine', fn ($mq) => $mq->where('category', $category));
            });

        $query = $this->applyBatchFilter($query, $request);

        $stockLogs = $query->orderByDesc('id')->get();

        $filename = 'stock_logs_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($stockLogs) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID',
                'Medicine',
                'Batch No.',
                'Storage Location',
                'Quantity Added',
                'Date Added',
                'Added By',
                'RHU',
                'Remarks',
            ]);

            foreach ($stockLogs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->medicine->medicine_name ?? '',
                    $log->batch_no ?? '',
                    $log->storage_location ?? '',
                    $log->quantity_added,
                    $log->date_added,
                    $log->addedByUser->name ?? '',
                    $log->healthUnit->name ?? '',
                    $log->remarks ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function autocompleteMedicines(Request $request)
    {
        $search = trim($request->get('q', ''));
        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $medicines = Medicine::where(function ($q) use ($search) {
            $q->where('medicine_name', 'like', "%{$search}%")
                ->orWhere('dosage', 'like', "%{$search}%");
        })
            ->orderBy('medicine_name')
            ->limit(15)
            ->get(['id', 'medicine_name', 'dosage']);

        $results = $medicines->map(function ($m) {
            $text = $m->medicine_name;
            if ($m->dosage) {
                $text .= ' - '.$m->dosage;
            }

            return [
                'id' => $m->id,
                'text' => $text,
            ];
        });

        return response()->json($results);
    }
}
