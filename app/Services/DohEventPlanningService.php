<?php

namespace App\Services;

use App\Models\DoHProgram;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DohEventPlanningService
{
    public function __construct(private StockTrendService $stockTrends) {}

    public function upcoming(int $healthUnitId, ?Carbon $from = null, int $days = 30): array
    {
        $from = ($from ?? today())->copy()->startOfDay();
        $through = $from->copy()->addDays(max(0, min(365, $days)));
        $occurrences = [];
        foreach (DoHProgram::active()->orderBy('name')->get() as $program) {
            foreach ($program->startsBetween($from, $through) as $date) {
                $occurrences[] = ['program' => $program, 'date' => $date];
            }
        }

        return $this->plans($healthUnitId, $occurrences);
    }

    public function onDate(int $healthUnitId, Carbon $date): array
    {
        $date = $date->copy()->startOfDay();
        $occurrences = [];
        foreach (DoHProgram::active()->orderBy('name')->get() as $program) {
            $starts = $program->startsBetween($date->copy()->subDays(max(1, (int) $program->duration_days) - 1), $date);
            if ($starts !== []) {
                $occurrences[] = ['program' => $program, 'date' => end($starts)];
            }
        }

        return $this->plans($healthUnitId, $occurrences);
    }

    private function plans(int $healthUnitId, array $occurrences): array
    {
        if ($occurrences === []) {
            return [];
        }

        $unit = HealthUnit::findOrFail($healthUnitId);
        $trends = $this->stockTrends->analyze([$healthUnitId])[$healthUnitId] ?? [];
        $catalog = Medicine::orderBy('medicine_name')->get();
        $materials = DB::table('doh_event_materials')->where('health_unit_id', $healthUnitId)
            ->whereIn('event_date', array_map(fn ($occurrence) => $occurrence['date']->toDateString(), $occurrences))->get()
            ->groupBy(fn ($row) => $row->doh_program_id.':'.$row->event_date);
        $batches = Inventory::where('health_unit_id', $healthUnitId)->where('available_stock', '>', 0)
            ->where(fn ($query) => $query->whereNull('expiration_date')->orWhereDate('expiration_date', '>=', today()))
            ->fefo()->get()->groupBy('medicine_id');
        $events = [];

        foreach ($occurrences as $occurrence) {
            $program = $occurrence['program'];
            $date = $occurrence['date'];
            $mapping = $this->materialMapping($program->name);
            $saved = ($materials[$program->id.':'.$date->toDateString()] ?? collect())->keyBy('medicine_id');
            $candidates = $catalog->filter(fn ($medicine) => $saved->has($medicine->id) || $this->matches($medicine, $mapping));
            $items = [];
            $editableItems = [];
            foreach ($candidates as $medicine) {
                $quantity = $saved[$medicine->id]->quantity ?? null;
                $item = $this->projectItem($medicine, $quantity, $date, max(1, (int) $program->duration_days),
                    $trends[$medicine->id] ?? $this->stockTrends->emptyProjection(), $batches[$medicine->id] ?? collect());
                $editableItems[] = $item;
                if ($quantity !== null && (int) $quantity === 0) {
                    continue;
                }
                $items[] = $item;
            }
            $events[] = [
                'program_id' => (int) $program->id,
                'name' => $program->name,
                'description' => $program->description,
                'date' => $date->toDateString(),
                'end_date' => $date->copy()->addDays(max(1, (int) $program->duration_days) - 1)->toDateString(),
                'days_away' => (int) today()->diffInDays($date, false),
                'where' => $unit->name.($unit->municipality ? ', '.$unit->municipality : ''),
                'who' => 'RHU inventory staff and the program team, with assigned midwives coordinating local needs.',
                'items' => $items,
                'editable_items' => $editableItems,
                'source' => $mapping['source'] ?? null,
                'shortage_count' => count(array_filter($items, fn ($item) => ($item['stock_up'] ?? 0) > 0)),
                'unquantified_count' => count(array_filter($items, fn ($item) => $item['target_quantity'] === null)),
            ];
        }
        usort($events, fn ($left, $right) => strcmp($left['date'], $right['date']) ?: strcmp($left['name'], $right['name']));

        return $events;
    }

    private function materialMapping(string $name): array
    {
        foreach (config('doh_planning.materials', []) as $keyword => $mapping) {
            if (str_contains(mb_strtolower($name), $keyword)) {
                return $mapping;
            }
        }

        return [];
    }

    private function matches(Medicine $medicine, array $mapping): bool
    {
        if (in_array(mb_strtolower((string) $medicine->category), $mapping['categories'] ?? [], true)) {
            return true;
        }
        foreach ($mapping['names'] ?? [] as $name) {
            if (str_contains(mb_strtolower($medicine->medicine_name), $name)) {
                return true;
            }
        }

        return false;
    }

    private function projectItem(Medicine $medicine, ?int $planned, Carbon $date, int $duration, array $trend, $inventory): array
    {
        $days = max(0, (int) today()->diffInDays($date, false));
        $daily = $trend['daily'];
        $ready = $trend['fit']['method'] === 'linear_regression' && $days + $duration <= count($daily);
        $eventDemand = $ready ? (int) ceil(array_sum(array_slice($daily, $days, $duration))) : null;
        $target = $planned ?? $eventDemand;
        $usableOnDate = (int) $inventory->filter(fn ($batch) => ! $batch->expiration_date || $batch->expiration_date->gte($date))->sum('available_stock');
        $remaining = $inventory->map(fn ($batch) => ['quantity' => (float) $batch->available_stock,
            'expires' => $batch->expiration_date?->toDateString()])->all();
        $routineUnfilled = 0;
        for ($offset = 0; $offset < $days; $offset++) {
            $day = today()->addDays($offset)->toDateString();
            $demand = $ready ? ($daily[$offset] ?? 0) : 0;
            $routineUnfilled += $this->consumeDay($remaining, $day, $demand);
        }
        $onDate = array_sum(array_column(array_filter($remaining,
            fn ($batch) => $batch['expires'] === null || $batch['expires'] >= $date->toDateString()), 'quantity'));
        $projected = $ready ? max(0, (int) floor($onDate + 1e-9)) : null;
        $end = $date->copy()->addDays($duration - 1)->toDateString();
        $expiresDuring = array_sum(array_column(array_filter($remaining, fn ($batch) => $batch['expires'] !== null
            && $batch['expires'] >= $date->toDateString() && $batch['expires'] < $end), 'quantity'));
        $unfilled = 0;
        if ($target !== null) {
            // A manual target is spread over the program days. Forecast targets retain their daily trend shape.
            $eventDaily = $planned !== null ? array_fill(0, $duration, $target / $duration) : array_slice($daily, $days, $duration);
            foreach ($eventDaily as $offset => $demand) {
                $unfilled += $this->consumeDay($remaining, $date->copy()->addDays($offset)->toDateString(), $demand);
            }
        }
        $routineShortage = $ready ? max(0, (int) ceil($routineUnfilled - 1e-9)) : null;
        $programShortage = $target === null ? null : max(0, (int) ceil($unfilled - 1e-9));

        return ['medicine_id' => (int) $medicine->id, 'name' => $medicine->medicine_name,
            'dosage' => $medicine->dosage, 'unit' => $medicine->unit ?: 'unit',
            'current_stock' => (int) $inventory->sum('available_stock'), 'expiry_eligible_stock' => $usableOnDate,
            'projected_stock' => $projected, 'target_quantity' => $target, 'planned_quantity' => $planned,
            'stock_up' => $target === null ? null : ($routineShortage ?? 0) + $programShortage,
            'routine_shortage_before_program' => $routineShortage,
            'program_shortage' => $programShortage,
            'expires_during_program' => max(0, (int) floor($expiresDuring + 1e-9)),
            'history_weeks' => $trend['fit']['periods'], 'history_ready' => $ready,
            'basis' => $planned !== null ? 'RHU planned quantity' : ($ready ? 'Recorded release trend for the event duration' : 'Quantity needs local planning')];
    }

    private function consumeDay(array &$batches, string $date, float $demand): float
    {
        foreach ($batches as &$batch) {
            if ($batch['expires'] !== null && $batch['expires'] < $date) {
                $batch['quantity'] = 0;
            }
            $used = min($batch['quantity'], $demand);
            $batch['quantity'] -= $used;
            $demand -= $used;
        }
        unset($batch);

        return max(0, $demand);
    }
}
