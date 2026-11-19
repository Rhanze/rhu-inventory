@php
    $overviewItems = collect($forecast['all_items'] ?? []);
    $criticalCount = $overviewItems->where('status', 'critical')->count();
    $reorderCount = $overviewItems->filter(fn ($item) => $item['reorder_point'] !== null && $item['available_stock'] <= $item['reorder_point'])->count();
    $unknownCount = $overviewItems->filter(fn ($item) => $item['model']['periods'] === 0)->count();
@endphp
<div class="forecast-overview" aria-label="Stock priorities across all results">
    <a id="forecast-kpi-critical" class="forecast-metric forecast-metric-danger" href="{{ route('forecast.index', ['stock_filter' => 'critical']) }}#forecast-stock">
        <span>Critical stock</span>
        <strong class="forecast-critical">{{ number_format($criticalCount) }}</strong>
        <small>As of {{ today()->toDateString() }}. One medicine / facility entry flagged critical by forecast coverage.</small>
    </a>
    <a id="forecast-kpi-reorder" class="forecast-metric forecast-metric-warning" href="{{ route('forecast.index', ['stock_filter' => 'reorder']) }}#forecast-stock">
        <span>At reorder point</span>
        <strong>{{ number_format($reorderCount) }}</strong>
        <small>As of {{ today()->toDateString() }}. One medicine / facility entry with available stock at or below its estimated reorder point.</small>
    </a>
    <a id="forecast-kpi-unknown" class="forecast-metric forecast-metric-neutral" href="{{ route('forecast.index', ['stock_filter' => 'unknown']) }}#forecast-stock">
        <span>Without usage history</span>
        <strong>{{ number_format($unknownCount) }}</strong>
        <small>As of {{ today()->toDateString() }}. One medicine / facility entry with no completed release-history periods.</small>
    </a>
    <a class="forecast-metric forecast-metric-success" href="#forecast-events">
        <span>Upcoming events</span>
        <strong>{{ number_format($planned['summary']['total_events']) }}</strong>
        <small>Scheduled over the next {{ $planned['summary']['horizon_days'] }} days</small>
    </a>
</div>
