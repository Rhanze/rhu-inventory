<?php

namespace App\Services;

use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\StockLog;

class ELMISExportService
{
    public function generateInventoryReport(?int $healthUnitId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Inventory::with(['medicine', 'healthUnit'])->where('available_stock', '>', 0);
        if ($healthUnitId) {
            $query->where('health_unit_id', $healthUnitId);
        }
        $inventories = $query->get();

        $rows = [];
        $totalAvailable = 0;
        $totalAllocated = 0;

        foreach ($inventories as $inventory) {
            $rows[] = [
                'facility_name' => $inventory->healthUnit->name ?? 'N/A',
                'facility_type' => $inventory->healthUnit->facility_type ?? 'N/A',
                'medicine_name' => $inventory->medicine->medicine_name ?? 'N/A',
                'dosage' => $inventory->medicine->dosage ?? '',
                'category' => $inventory->medicine->category ?? '',
                'batch_number' => $inventory->batch_no ?? '',
                'expiration_date' => $inventory->expiration_date ? $inventory->expiration_date->format('Y-m-d') : '',
                'available_stock' => $inventory->available_stock,
                'allocated_stock' => $inventory->allocated_stock ?? 0,
                'last_updated' => now()->format('Y-m-d H:i:s'),
            ];
            $totalAvailable += $inventory->available_stock;
            $totalAllocated += $inventory->allocated_stock ?? 0;
        }

        return [
            'rows' => $rows,
            'summary' => [
                'total_facilities' => $healthUnitId ? 1 : HealthUnit::count(),
                'total_medicines' => $inventories->count(),
                'total_available' => $totalAvailable,
                'total_allocated' => $totalAllocated,
                'generated_at' => now()->format('Y-m-d H:i:s'),
            ],
        ];
    }

    public function generateStockMovementReport(?int $healthUnitId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = StockLog::whereNull('voided_at')->with(['medicine', 'healthUnit', 'addedByUser']);
        if ($healthUnitId) {
            $query->where('health_unit_id', $healthUnitId);
        }
        if ($dateFrom) {
            $query->whereDate('date_added', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('date_added', '<=', $dateTo);
        }
        $logs = $query->orderBy('date_added', 'desc')->get();

        $rows = [];
        foreach ($logs as $log) {
            $isAddition = is_null($log->remarks) || str_contains($log->remarks ?? '', 'Added');
            $isRelease = str_contains($log->remarks ?? '', 'Released') || str_contains($log->remarks ?? '', 'Dispensed');
            $rows[] = [
                'facility_name' => $log->healthUnit->name ?? 'N/A',
                'medicine_name' => $log->medicine->medicine_name ?? 'N/A',
                'batch_number' => $log->batch_no ?? '',
                'quantity' => $log->quantity_added,
                'transaction_type' => $isRelease ? 'RELEASE' : ($isAddition ? 'ADDITION' : 'UNKNOWN'),
                'transaction_date' => $log->date_added,
                'performed_by' => $log->addedByUser->name ?? 'N/A',
            ];
        }

        return [
            'rows' => $rows,
            'summary' => [
                'total_transactions' => $logs->count(),
                'generated_at' => now()->format('Y-m-d H:i:s'),
            ],
        ];
    }

    public function exportCsv(array $data, string $filename)
    {
        $headers = ['Facility Name', 'Facility Type', 'Medicine Name', 'Dosage', 'Category', 'Batch Number', 'Expiration Date', 'Available Stock', 'Allocated Stock', 'Last Updated'];

        return response()->streamDownload(function () use ($data, $headers) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            foreach ($data['rows'] as $row) {
                fputcsv($handle, array_values($row));
            }
            if (! empty($data['summary'])) {
                fputcsv($handle, []);
                fputcsv($handle, ['--- SUMMARY ---']);
                foreach ($data['summary'] as $k => $v) {
                    fputcsv($handle, [ucwords(str_replace('_', ' ', $k)), $v]);
                }
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportStockMovementCsv(array $data, string $filename)
    {
        $headers = ['Facility Name', 'Medicine Name', 'Batch Number', 'Quantity', 'Transaction Type', 'Transaction Date', 'Performed By'];

        return response()->streamDownload(function () use ($data, $headers) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            foreach ($data['rows'] as $row) {
                fputcsv($handle, array_values($row));
            }
            if (! empty($data['summary'])) {
                fputcsv($handle, []);
                fputcsv($handle, ['--- SUMMARY ---']);
                foreach ($data['summary'] as $k => $v) {
                    fputcsv($handle, [ucwords(str_replace('_', ' ', $k)), $v]);
                }
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
