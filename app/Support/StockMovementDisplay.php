<?php

namespace App\Support;

use App\Models\StockLog;
use Illuminate\Support\Str;

class StockMovementDisplay
{
    public static function describe(object $movement): array
    {
        $log = $movement instanceof StockLog;
        $direction = $log ? $movement->movement_direction : $movement->direction;
        $unit = $log ? $movement->medicine?->unit : $movement->unit;
        $type = $movement->movement_type;
        $available = $log ? null : $movement->available_delta;
        $reserved = $log ? null : $movement->allocated_delta;
        $kind = self::kind($movement, $log, $direction);

        $label = match ($type) {
            'transfer_in' => 'Received from another facility',
            'transfer_out' => 'Sent to another facility',
            default => match ($kind) {
                'addition' => 'Stock received',
                'dispensing' => 'Dispensed',
                'allocation' => $direction === 'addition' ? 'Allocation received' : 'Allocated',
                'transfer' => match ($direction) {
                    'addition' => 'Received from another facility',
                    'release' => 'Sent to another facility',
                    default => 'Facility transfer',
                },
                'reservation' => 'Reserved for transfer',
                'reversal' => 'Movement reversed',
                'adjustment' => in_array($type, ['adjustment_in', 'adjustment_out'], true) ? 'Stock adjustment' : 'Stock movement',
                default => 'Stock movement',
            },
        };

        $quantity = self::quantity((int) $movement->quantity_added, $unit);
        $stockChanges = [];
        if ($available !== null && $reserved !== null) {
            $available = (int) $available;
            $reserved = (int) $reserved;
            [$effect, $tone] = self::balanceEffect($available, $reserved);
            $stockChanges = [
                'Available stock' => self::signedChange($available, $unit),
                'Reserved stock' => self::signedChange($reserved, $unit),
            ];
            if ($available !== 0 && $reserved !== 0 && $available !== -$reserved) {
                $quantity = null;
            }
        } else {
            [$effect, $tone] = match ($direction) {
                'addition' => ['Stock in', 'in'],
                'release' => $kind === 'allocation' && $type !== 'transfer_out' ? [null, 'neutral'] : ['Stock out', 'out'],
                default => [null, 'neutral'],
            };
        }

        $status = $log ? null : $movement->status_label;

        return [
            'label' => $label,
            'quantity' => $quantity,
            'effect' => $effect,
            'tone' => $tone,
            'status' => $status === 'Active' ? null : $status,
            'stock_changes' => $stockChanges,
        ];
    }

    private static function kind(object $movement, bool $log, ?string $direction): string
    {
        if (! $log) {
            return $movement->event_type;
        }

        $remarks = strtolower($movement->remarks ?? '');

        return match (true) {
            in_array($movement->movement_type, ['adjustment_in', 'adjustment_out'], true) => 'adjustment',
            $movement->isDispensingMovement() => 'dispensing',
            $movement->movement_type === 'allocation', $movement->allocation_id !== null => 'allocation',
            $movement->transfer_id !== null => 'transfer',
            $movement->movement_type === null && preg_match('/^(allocated|released.*allocated)/', $remarks) === 1 => 'allocation',
            $direction === 'addition' => 'addition',
            default => 'adjustment',
        };
    }

    private static function balanceEffect(int $available, int $reserved): array
    {
        return match (true) {
            $available < 0 && $available === -$reserved => ['Moved from available to reserved stock', 'reserved'],
            $available > 0 && $available === -$reserved => ['Returned from reserved to available stock', 'in'],
            $available !== 0 && $reserved !== 0 => ['Available and reserved stock changed', 'neutral'],
            $available > 0 => ['Added to available stock', 'in'],
            $available < 0 => ['Removed from available stock', 'out'],
            $reserved > 0 => ['Added to reserved stock', 'reserved'],
            $reserved < 0 => ['Removed from reserved stock', 'out'],
            default => ['No change to stock balances', 'neutral'],
        };
    }

    private static function signedChange(int $delta, ?string $unit): string
    {
        if ($delta === 0) {
            return 'No change';
        }

        return ($delta > 0 ? '+' : '−').self::quantity(abs($delta), $unit);
    }

    private static function quantity(int $quantity, ?string $unit): string
    {
        $unit = trim($unit ?? '');
        $name = strtolower($unit ?: 'unit');
        $unitLabel = match ($name) {
            'g', 'kg', 'mg', 'ml', 'l', 'mcg', 'iu' => $unit,
            'units' => Str::plural('unit', $quantity),
            default => Str::plural($name, $quantity),
        };

        return number_format($quantity).' '.$unitLabel;
    }
}
