@php
    $overviewItems = collect($forecast['all_items'] ?? []);
    $criticalCount = $overviewItems->where('status', 'critical')->count();
    $reorderCount = $overviewItems->filter(fn ($item) => $item['reorder_point'] !== null && $item['available_stock'] <= $item['reorder_point'])->count();
    $unknownCount = $overviewItems->filter(fn ($item) => $item['model']['periods'] === 0)->count();
@endphp
<div class="forecast-overview" aria-label="Stock priorities across all results">
    <a class="forecast-metric" href="#forecast-stock">
        <span>Critical stock</span>
        <strong class="forecast-critical">{{ number_format($criticalCount) }}</strong>
        <small>Medicine / facility entries needing review</small>
    </a>
    <a class="forecast-metric" href="#forecast-stock">
        <span>At reorder point</span>
        <strong>{{ number_format($reorderCount) }}</strong>
        <small>Available stock at or below the reorder point</small>
    </a>
    <a class="forecast-metric" href="#stock-trend-heading">
        <span>Without usage history</span>
        <strong>{{ number_format($unknownCount) }}</strong>
        <small>Medicine / facility entries needing usage data</small>
    </a>
    <a class="forecast-metric" href="#forecast-events">
        <span>Upcoming events</span>
        <strong>{{ number_format($planned['summary']['total_events']) }}</strong>
        <small>Scheduled over the next {{ $planned['summary']['horizon_days'] }} days</small>
    </a>
</div>
<nav class="forecast-section-nav" aria-label="Forecast sections">
    <a href="#forecast-stock">Stock analysis</a>
    <a href="#stock-trend-heading">Release trend</a>
    <a href="#forecast-events">Scheduled demand</a>
    <a href="#forecast-diseases">Disease reports</a>
    <a href="#forecast-charts">Stock charts</a>
</nav>
