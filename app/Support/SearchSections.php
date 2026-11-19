<?php

namespace App\Support;

final class SearchSections
{
    public static function forViewer(?string $role, bool $hasRhu): array
    {
        if ($role === 'midwife') {
            return ['all' => 'All Sections', 'medicine' => 'Medicines', 'dispensing' => 'Dispensing'];
        }

        $sections = ['all' => 'All Sections', 'medicine' => 'Medicines'];
        if ($role === 'admin') {
            $sections['users'] = 'Users';
            if (! $hasRhu) {
                $sections['rhu'] = 'RHU';
            }
        }

        $sections += [
            'inventory' => 'Inventory',
            'stock_logs' => 'Stock Logs',
            'allocation' => 'Allocation',
            'transfers' => 'Transfers',
            'forecast' => 'Forecast',
        ];

        if ($hasRhu) {
            $sections['dispensing'] = 'Dispensing';
        }

        return $sections;
    }
}
