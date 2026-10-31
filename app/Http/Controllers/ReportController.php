<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\StockLog;
use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
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

    public function index()
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        return view('reports.index');
    }

    /**
     * Parse batch input into a list of matching batch numbers.
     */
    private function parseBatchInput(Request $request, string $modelClass): array
    {
        $raw = (string) $request->input('batch_input', '');
        if (trim($raw) === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $raw));
        $parts = array_values(array_filter($parts, fn ($v) => $v !== ''));

        $candidates = [];

        foreach ($parts as $part) {
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $part, $m)) {
                $start = (int) $m[1];
                $end   = (int) $m[2];

                if ($start > $end) {
                    [$start, $end] = [$end, $start];
                }

                if ($end - $start > 1000) {
                    $end = $start + 1000;
                }

                for ($i = $start; $i <= $end; $i++) {
                    $candidates[] = (string) $i;
                }
            } else {
                $candidates[] = $part;
            }
        }

        $candidates = array_values(array_unique($candidates));

        if (empty($candidates)) {
            return [];
        }

        $query = $modelClass::query()->where(function ($q) use ($candidates) {
            foreach ($candidates as $v) {
                $q->orWhere(function ($sub) use ($v) {
                    $sub->where('batch_no', $v)
                        ->orWhere('batch_no', 'like', '%-'  . $v)
                        ->orWhere('batch_no', 'like', '%-0' . $v)
                        ->orWhere('batch_no', 'like', '%-00' . $v);
                });
            }
        });

        return $query->distinct()->pluck('batch_no')->toArray();
    }

    // ========== INVENTORY REPORT ==========
    public function inventoryReport(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $inventories = $this->buildInventoryQuery($request)->get();

        $healthUnits = HealthUnit::orderBy('name')->get();

        return view('reports.inventory', compact('inventories', 'healthUnits'));
    }

    public function inventoryCsv(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $inventories = $this->buildInventoryQuery($request)->get();

        $headers = ['ID', 'Batch No.', 'Medicine', 'Dosage', 'Dosage Form', 'Packaging',
                    'Available', 'Allocated', 'Dispensed', 'Storage Location', 'Expiration Date'];
        if (is_null(session('health_unit_id'))) {
            $headers[] = 'RHU';
        }

        $rows = $inventories->map(function ($inv) {
            $med = $inv->medicine;

            $packParts = [];
            if ($med && $med->pieces_per_pack && $med->unit) {
                $packParts[] = $med->pieces_per_pack . ' ' . $med->unit;
            } elseif ($med && $med->unit) {
                $packParts[] = $med->unit;
            }
            if ($med && $med->pack_type) {
                $packParts[] = $med->pack_type;
            }
            $packText = $packParts ? implode(' / ', $packParts) : '';

            $row = [
                $inv->id,
                $inv->batch_no ?? '',
                $med->medicine_name ?? '',
                $med->dosage ?? '',
                $med->dosage_form ?? '',
                $packText,
                $inv->available_stock,
                $inv->allocated_stock,
                $inv->total_dispensed ?? 0,
                $inv->storage_location ?? '',
                $inv->expiration_date,
            ];
            if (is_null(session('health_unit_id'))) {
                $row[] = $inv->healthUnit->name ?? '';
            }
            return $row;
        });

        return $this->streamCsv(
            'inventory_report_' . now()->format('Ymd_His') . '.csv',
            $headers,
            $rows
        );
    }

    private function buildInventoryQuery(Request $request)
    {
        $rhuFilter    = $request->rhu_filter;
        $healthUnitId = session('health_unit_id');
        $expiryFilter = $request->expiry_filter;
        $category     = $request->category;

        $batchFilters = $this->parseBatchInput($request, Inventory::class);

        return Inventory::with(['medicine', 'healthUnit'])
            ->select('inventory.*')
            ->selectSub(
                DispensingRecord::selectRaw('COALESCE(SUM(quantity_dispensed), 0)')
                    ->whereColumn('inventory.medicine_id', 'dispensing_records.medicine_id')
                    ->whereColumn('inventory.health_unit_id', 'dispensing_records.health_unit_id'),
                'total_dispensed'
            )
            ->when($healthUnitId, fn ($q) => $q->where('inventory.health_unit_id', $healthUnitId))
            ->when(is_null($healthUnitId) && $rhuFilter, fn ($q) => $q->where('inventory.health_unit_id', $rhuFilter))
            ->when(!empty($batchFilters), fn ($q) => $q->whereIn('inventory.batch_no', $batchFilters))
            ->when($category, function ($q) use ($category) {
                $q->whereHas('medicine', fn ($mq) => $mq->where('category', $category));
            })
            ->when($expiryFilter === 'near_expiry', fn ($q) =>
                $q->whereNotNull('inventory.expiration_date')
                  ->whereDate('inventory.expiration_date', '<=', now()->addDays(180))
                  ->whereDate('inventory.expiration_date', '>=', now())
            )
            ->when($expiryFilter === 'expired', fn ($q) =>
                $q->whereNotNull('inventory.expiration_date')
                  ->whereDate('inventory.expiration_date', '<', now())
            )
            ->when($expiryFilter === 'not_expired', fn ($q) =>
                $q->where(function ($sub) {
                    $sub->whereNull('inventory.expiration_date')
                        ->orWhereDate('inventory.expiration_date', '>', now()->addDays(180));
                })
            )
            ->orderByDesc('id');
    }

    // ========== STOCK LOGS REPORT ==========
    public function stockLogsReport(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $stockLogs = $this->buildStockLogsQuery($request)->get();

        $healthUnits = HealthUnit::orderBy('name')->get();

        return view('reports.stock', compact('stockLogs', 'healthUnits'));
    }

    public function stockLogsCsv(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $stockLogs = $this->buildStockLogsQuery($request)->get();

        $headers = ['ID', 'Batch No.', 'Medicine', 'Type', 'Dosage', 'Dosage Form',
                    'Packaging', 'Quantity', 'Storage Location', 'Date Added', 'Added By'];
        if (is_null(session('health_unit_id'))) {
            $headers[] = 'RHU';
        }

        $rows = $stockLogs->map(function ($log) {
            $med = $log->medicine;

            $isRelease = stripos($log->remarks ?? '', 'Released') !== false;
            $typeLabel = $isRelease ? 'Stock Out' : 'Stock In';

            $packParts = [];
            if ($med && $med->pieces_per_pack && $med->unit) {
                $packParts[] = $med->pieces_per_pack . ' ' . $med->unit;
            } elseif ($med && $med->unit) {
                $packParts[] = $med->unit;
            }
            if ($med && $med->pack_type) {
                $packParts[] = $med->pack_type;
            }
            $packText = $packParts ? implode(' / ', $packParts) : '';

            $unit = $med->unit ?? null;
            $qtyText = $log->quantity_added . ($unit ? ' ' . $unit : '');

            $row = [
                $log->id,
                $log->batch_no ?? '',
                $med->medicine_name ?? '',
                $typeLabel,
                $med->dosage ?? '',
                $med->dosage_form ?? '',
                $packText,
                $qtyText,
                $log->storage_location ?? '',
                $log->date_added,
                $log->addedByUser->name ?? '',
            ];
            if (is_null(session('health_unit_id'))) {
                $row[] = $log->healthUnit->name ?? '';
            }
            return $row;
        });

        return $this->streamCsv(
            'stock_logs_report_' . now()->format('Ymd_His') . '.csv',
            $headers,
            $rows
        );
    }

    private function buildStockLogsQuery(Request $request)
    {
        $rhuFilter    = $request->rhu_filter;
        $healthUnitId = session('health_unit_id');
        $category     = $request->category;
        $type         = $request->type;   // ← ADDED

        $batchFilters = $this->parseBatchInput($request, StockLog::class);

        return StockLog::with(['medicine', 'addedByUser', 'healthUnit'])
            ->when($healthUnitId, fn ($q) => $q->where('health_unit_id', $healthUnitId))
            ->when(is_null($healthUnitId) && $rhuFilter, fn ($q) => $q->where('health_unit_id', $rhuFilter))
            ->when(!empty($batchFilters), fn ($q) => $q->whereIn('batch_no', $batchFilters))
            ->when($category, function ($q) use ($category) {
                $q->whereHas('medicine', fn ($mq) => $mq->where('category', $category));
            })
            // Stock In — remarks is NULL or does NOT contain "released"
            ->when($type === 'stock_in', function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNull('remarks')
                        ->orWhereRaw('LOWER(remarks) NOT LIKE ?', ['%released%']);
                });
            })
            // Stock Out — remarks contains "released"
            ->when($type === 'stock_out', function ($q) {
                $q->whereRaw('LOWER(remarks) LIKE ?', ['%released%']);
            })
            ->orderByDesc('id');
    }

    // ========== ALLOCATION REPORT ==========
    public function allocationReport(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $allocations = $this->buildAllocationQuery($request)->get();

        $healthUnits = HealthUnit::orderBy('name')->get();

        return view('reports.allocation', compact('allocations', 'healthUnits'));
    }

    public function allocationCsv(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $allocations = $this->buildAllocationQuery($request)->get();

        $headers = ['ID', 'Medicine', 'Destination', 'Quantity', 'Date Allocated', 'Allocated By'];
        if (is_null(session('health_unit_id'))) {
            $headers[] = 'RHU';
        }

        $rows = $allocations->map(function ($alloc) {
            $row = [
                $alloc->id,
                $alloc->medicine->medicine_name ?? '',
                $alloc->destination_display,
                $alloc->quantity_allocated,
                $alloc->date_allocated,
                $alloc->allocatedBy->name ?? '',
            ];
            if (is_null(session('health_unit_id'))) {
                $row[] = $alloc->healthUnit->name ?? '';
            }
            return $row;
        });

        return $this->streamCsv(
            'allocation_report_' . now()->format('Ymd_His') . '.csv',
            $headers,
            $rows
        );
    }

    private function buildAllocationQuery(Request $request)
    {
        $rhuFilter    = $request->rhu_filter;
        $healthUnitId = session('health_unit_id');

        return Allocation::with(['medicine', 'allocatedBy', 'healthUnit', 'destinationHealthUnit'])
            ->when($healthUnitId, fn ($q) => $q->where('health_unit_id', $healthUnitId))
            ->when(is_null($healthUnitId) && $rhuFilter, fn ($q) => $q->where('health_unit_id', $rhuFilter))
            ->orderByDesc('id');
    }

    // ========== DISPENSING REPORT (RHU-only) ==========
    public function dispensingReport(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        if (is_null(session('health_unit_id'))) {
            return redirect()->route('reports.index')
                ->with('error', 'Dispensing reports are only available at the RHU level.');
        }

        $records = $this->buildDispensingQuery($request)->get();

        $healthUnits = HealthUnit::orderBy('name')->get();

        return view('reports.dispensing', compact('records', 'healthUnits'));
    }

    public function dispensingCsv(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        if (is_null(session('health_unit_id'))) {
            return redirect()->route('reports.index')
                ->with('error', 'Dispensing reports are only available at the RHU level.');
        }

        $records = $this->buildDispensingQuery($request)->get();

        $headers = ['ID', 'Medicine', 'Quantity', 'Doctor', 'Date Dispensed', 'Dispensed By'];
        if (is_null(session('health_unit_id'))) {
            $headers[] = 'RHU';
        }

        $rows = $records->map(function ($rec) {
            $row = [
                $rec->id,
                $rec->medicine->medicine_name ?? '',
                $rec->quantity_dispensed,
                $rec->doctor_name,
                $rec->date_dispensed,
                $rec->dispensedByUser->name ?? '',
            ];
            if (is_null(session('health_unit_id'))) {
                $row[] = $rec->healthUnit->name ?? '';
            }
            return $row;
        });

        return $this->streamCsv(
            'dispensing_report_' . now()->format('Ymd_His') . '.csv',
            $headers,
            $rows
        );
    }

    private function buildDispensingQuery(Request $request)
    {
        $rhuFilter    = $request->rhu_filter;
        $healthUnitId = session('health_unit_id');

        return DispensingRecord::with(['medicine', 'dispensedByUser', 'healthUnit'])
            ->when($healthUnitId, fn ($q) => $q->where('health_unit_id', $healthUnitId))
            ->when(is_null($healthUnitId) && $rhuFilter, fn ($q) => $q->where('health_unit_id', $rhuFilter))
            ->orderByDesc('id');
    }

    // ========== NEAR EXPIRY REPORT (180 days) ==========
    public function nearExpiryReport(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $inventories = $this->buildNearExpiryQuery($request)->get();

        $healthUnits = HealthUnit::orderBy('name')->get();

        return view('reports.near_expiry', compact('inventories', 'healthUnits'));
    }

    public function nearExpiryCsv(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $inventories = $this->buildNearExpiryQuery($request)->get();

        $headers = ['ID', 'Batch No.', 'Medicine', 'Available Stock', 'Expiration Date', 'Status'];
        if (is_null(session('health_unit_id'))) {
            $headers[] = 'RHU';
        }

        $rows = $inventories->map(function ($inv) {
            $status = Carbon::parse($inv->expiration_date)->lt(Carbon::today()) ? 'Expired' : 'Near Expiry';
            $row = [
                $inv->id,
                $inv->batch_no ?? '',
                $inv->medicine->medicine_name ?? '',
                $inv->available_stock,
                $inv->expiration_date,
                $status,
            ];
            if (is_null(session('health_unit_id'))) {
                $row[] = $inv->healthUnit->name ?? '';
            }
            return $row;
        });

        return $this->streamCsv(
            'near_expiry_report_' . now()->format('Ymd_His') . '.csv',
            $headers,
            $rows
        );
    }

    private function buildNearExpiryQuery(Request $request)
    {
        $rhuFilter    = $request->rhu_filter;
        $healthUnitId = session('health_unit_id');
        $sixMonths    = Carbon::today()->addDays(180);

        $batchFilters = $this->parseBatchInput($request, Inventory::class);

        return Inventory::with(['medicine', 'healthUnit'])
            ->when($healthUnitId, fn ($q) => $q->where('health_unit_id', $healthUnitId))
            ->when(is_null($healthUnitId) && $rhuFilter, fn ($q) => $q->where('health_unit_id', $rhuFilter))
            ->when(!empty($batchFilters), fn ($q) => $q->whereIn('batch_no', $batchFilters))
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<=', $sixMonths)
            ->orderBy('expiration_date', 'asc');
    }

    // ========== CSV STREAM HELPER ==========
    private function streamCsv($filename, array $headers, $rows)
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}