<?php

namespace App\Services;

use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\StockLog;
use Illuminate\Support\Collection;

class RedistributionService
{
    /**
     * Get redistribution suggestions for all RHUs
     *
     * @return array [
     *               'overstocked' => Collection of overstocked inventory items,
     *               'understocked' => Collection of understocked inventory items,
     *               'suggestions' => Collection of transfer recommendations
     *               ]
     */
    public function getSuggestions(): array
    {
        // 1. Get all RHUs (health units that are RHUs)
        // FIXED: use facility_type only
        $rhus = HealthUnit::where('facility_type', 'Rural Health Unit')
            ->orWhere('facility_type', 'RHU')
            ->get();

        if ($rhus->isEmpty()) {
            return [
                'overstocked' => collect(),
                'understocked' => collect(),
                'suggestions' => collect(),
            ];
        }

        $rhuIds = $rhus->pluck('id')->toArray();

        // 2. Get all inventory for RHUs with medicine details
        $inventories = app(StockSummaryService::class)->inventories($rhuIds);

        if ($inventories->isEmpty()) {
            return [
                'overstocked' => collect(),
                'understocked' => collect(),
                'suggestions' => collect(),
            ];
        }

        // 3. Calculate average monthly consumption per medicine per RHU (last 90 days)
        $consumptionData = $this->calculateConsumption($rhuIds);

        // 4. Identify overstocked and understocked items
        $overstocked = collect();
        $understocked = collect();

        foreach ($inventories as $inventory) {
            $medicineId = $inventory->medicine_id;
            $healthUnitId = $inventory->health_unit_id;
            $availableStock = $inventory->available_stock;

            // Get monthly consumption for this medicine at this RHU
            $monthlyConsumption = $consumptionData[$healthUnitId][$medicineId]['monthly_avg'] ?? 0;

            // Calculate months of stock remaining
            $monthsRemaining = $monthlyConsumption > 0
                ? round($availableStock / $monthlyConsumption, 1)
                : INF;

            // Classification logic
            $classification = $this->classifyStock($availableStock, $monthlyConsumption, $monthsRemaining);

            $inventoryData = [
                'inventory' => $inventory,
                'medicine_name' => $inventory->medicine->medicine_name ?? 'Unknown',
                'health_unit_name' => $inventory->healthUnit->name ?? 'Unknown',
                'available_stock' => $availableStock,
                'monthly_consumption' => round($monthlyConsumption, 2),
                'months_remaining' => $monthsRemaining === INF ? '∞' : $monthsRemaining,
                'classification' => $classification,
                'threshold' => $this->getThreshold($classification),
            ];

            if ($classification === 'overstocked') {
                $overstocked->push($inventoryData);
            } elseif (in_array($classification, ['understocked', 'critical'])) {
                $understocked->push($inventoryData);
            }
        }

        // 5. Generate transfer suggestions
        $suggestions = $this->generateSuggestions($overstocked, $understocked);

        return [
            'overstocked' => $overstocked,
            'understocked' => $understocked,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Calculate average monthly consumption from stock_logs (releases/removals)
     */
    private function calculateConsumption(array $rhuIds): array
    {
        $result = [];

        // Get all stock log releases (deductions) in the last 90 days
        $logs = StockLog::whereNull('voided_at')->whereIn('health_unit_id', $rhuIds)
            ->where(function ($q) {
                // Exclude additions – only releases/removals
                $q->whereNotNull('remarks')
                    ->where('remarks', 'like', 'Released%')
                    ->orWhere('remarks', 'like', 'Dispensed%')
                    ->orWhere('remarks', 'like', 'Allocated%')
                    ->orWhere('remarks', 'like', 'Deducted%');
            })
            ->where('date_added', '>=', now()->subDays(90))
            ->get();

        // Group by health_unit_id and medicine_id
        foreach ($logs as $log) {
            $huId = $log->health_unit_id;
            $medId = $log->medicine_id;

            if (! isset($result[$huId])) {
                $result[$huId] = [];
            }
            if (! isset($result[$huId][$medId])) {
                $result[$huId][$medId] = [
                    'total_consumed' => 0,
                    'months' => 3, // 90 days = 3 months
                    'monthly_avg' => 0,
                ];
            }
            $result[$huId][$medId]['total_consumed'] += $log->quantity_added;
        }

        // Calculate monthly averages
        foreach ($result as $huId => $medicines) {
            foreach ($medicines as $medId => $data) {
                $result[$huId][$medId]['monthly_avg'] = $data['total_consumed'] / $data['months'];
            }
        }

        return $result;
    }

    /**
     * Classify stock based on available stock and consumption
     */
    private function classifyStock(int $availableStock, float $monthlyConsumption, $monthsRemaining): string
    {
        // If no consumption data (0), cannot classify
        if ($monthlyConsumption == 0) {
            return 'unknown';
        }

        // Overstocked: more than 6 months of supply
        if ($monthsRemaining > 6) {
            return 'overstocked';
        }

        // Understocked: less than 1 month of supply
        if ($monthsRemaining < 0.5) {
            return 'critical';
        }
        if ($monthsRemaining < 1) {
            return 'understocked';
        }

        // Adequate stock: 1-6 months
        return 'adequate';
    }

    /**
     * Get threshold description
     */
    private function getThreshold(string $classification): string
    {
        return match ($classification) {
            'overstocked' => '> 6 months of supply',
            'understocked' => '< 1 month of supply',
            'critical' => '< 2 weeks of supply',
            'adequate' => '1-6 months of supply',
            default => 'No consumption data',
        };
    }

    /**
     * Generate transfer suggestions from overstocked to understocked RHUs
     */
    private function generateSuggestions(Collection $overstocked, Collection $understocked): Collection
    {
        $suggestions = collect();

        if ($overstocked->isEmpty() || $understocked->isEmpty()) {
            return $suggestions;
        }

        // For each overstocked item, find matching understocked items (same medicine)
        $remainingNeed = [];
        foreach ($overstocked as $over) {
            $medicineId = $over['inventory']->medicine_id;
            $medicineName = $over['medicine_name'];

            // Find matching understocked items for the same medicine
            $matches = $understocked->filter(function ($under) use ($medicineId) {
                return $under['inventory']->medicine_id == $medicineId;
            });

            if ($matches->isEmpty()) {
                continue;
            }

            // Calculate transferable quantity (30% of surplus, rounded to nearest unit)
            $surplus = $over['available_stock'] - ($over['monthly_consumption'] * 6);
            $transferQty = max(1, (int) round($surplus * 0.3));

            // For each matching understocked RHU, suggest a transfer
            $remainingDonor = $transferQty;
            foreach ($matches as $under) {
                if ($remainingDonor <= 0) {
                    break;
                }
                $needKey = $under['inventory']->health_unit_id.':'.$medicineId;
                $remainingNeed[$needKey] ??= max(0, (int) ceil(($under['monthly_consumption'] * 3) - $under['available_stock']));
                $need = max(1, (int) round(($under['monthly_consumption'] * 3) - $under['available_stock']));
                $qty = min($remainingDonor, $remainingNeed[$needKey], $over['available_stock']);

                if ($qty <= 0) {
                    continue;
                }

                $remainingDonor -= $qty;
                $remainingNeed[$needKey] -= $qty;
                $suggestions->push([
                    'medicine_name' => $medicineName,
                    'medicine_id' => $medicineId,
                    'from_rhu' => $over['health_unit_name'],
                    'from_rhu_id' => $over['inventory']->health_unit_id,
                    'to_rhu' => $under['health_unit_name'],
                    'to_rhu_id' => $under['inventory']->health_unit_id,
                    'suggested_quantity' => $qty,
                    'reason' => sprintf(
                        'Transfer %d units from %s (overstocked, %s supply) to %s (understocked, %s supply)',
                        $qty,
                        $over['health_unit_name'],
                        $over['months_remaining'].' months',
                        $under['health_unit_name'],
                        $under['months_remaining'].' months'
                    ),
                ]);
            }
        }

        return $suggestions;
    }
}
