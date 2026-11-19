@php
    $balanceUrl = route('stock_logs.index', $metricScope + $stockLogScope);
    $allocationUrl = route('stock_logs.index', $metricScope + $periodStockLogScope + ['record_kind' => 'allocation']);
    $dispensingUrl = route('stock_logs.index', $metricScope + $periodStockLogScope + ['record_kind' => 'dispensing']);
    $allocationRecords = number_format($metrics['allocation_records']).' '.\Illuminate\Support\Str::plural('record', $metrics['allocation_records']);
    $dispensingRecords = number_format($metrics['dispensing_records']).' '.\Illuminate\Support\Str::plural('record', $metrics['dispensing_records']);
@endphp
<div class="history-metric-groups">
    <div class="history-metric-group history-balance">
        <p class="history-metric-heading">Closing stock</p>
        @if($metrics['balance_complete'])
            <dl class="history-metric-list">
                <div>
                    <dt>Available</dt>
                    <dd><a href="{{ $balanceUrl }}" aria-label="{{ number_format($metrics['closing_available']) }} available. View stock movements for {{ $metricScopeLabel }}.">{{ number_format($metrics['closing_available']) }}</a></dd>
                </div>
                <div>
                    <dt>Reserved</dt>
                    <dd><a href="{{ $balanceUrl }}" aria-label="{{ number_format($metrics['closing_reserved']) }} reserved. View stock movements for {{ $metricScopeLabel }}.">{{ number_format($metrics['closing_reserved']) }}</a></dd>
                </div>
            </dl>
        @else
            <p class="history-warning"><strong>Balance unavailable.</strong> {{ match ($metrics['reason']) {
                'Before recorded stock history' => 'The closing date is before stock recording began.',
                'No recorded inventory history' => 'There is no saved stock history.',
                'Incomplete or ambiguous batch history' => 'Some movements cannot be matched to a batch.',
                'Incomplete dated movement history', 'Conflicting movement dates' => 'Some stock movements have missing or conflicting dates.',
                default => 'The saved records are insufficient to verify the stock balance.',
            } }}</p>
        @endif
    </div>
    <div class="history-metric-group history-activity">
        <p class="history-metric-heading">Activity during the period</p>
        <dl class="history-metric-list">
            <div>
                <dt>Allocated</dt>
                <dd>
                    <a href="{{ $allocationUrl }}" aria-label="{{ number_format($metrics['allocated_units']) }} allocated. View allocation movements for {{ $metricScopeLabel }}.">{{ number_format($metrics['allocated_units']) }}</a>
                    <span class="history-record-count">{{ $allocationRecords }}</span>
                </dd>
            </div>
            <div>
                <dt>Dispensed</dt>
                <dd>
                    <a href="{{ $dispensingUrl }}" aria-label="{{ number_format($metrics['dispensed_units']) }} dispensed. View dispensing movements for {{ $metricScopeLabel }}.">{{ number_format($metrics['dispensed_units']) }}</a>
                    <span class="history-record-count"><a href="{{ $dispensingUrl }}" aria-label="{{ $dispensingRecords }}. View movement entries for counted dispensing records for {{ $metricScopeLabel }}.">{{ $dispensingRecords }}</a></span>
                </dd>
            </div>
        </dl>
    </div>
</div>
