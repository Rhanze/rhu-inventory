@php
    $historyRange = $historyRange ?? \App\Support\HistoricalDateRange::fromRequest(request());
    $historyAction = $action ?? url()->current();
    $historyOtherFilters = collect(array_replace(request()->except('year', 'date_from', 'date_to', 'page'), $preservedFilters ?? []))
        ->except(['year', 'date_from', 'date_to', 'page'])
        ->filter(fn ($value, $name) => is_scalar($value) && ((string) $value !== '' || array_key_exists($name, $preservedFilters ?? [])))->all();
    $historyResetUrl = $resetUrl ?? ($historyAction.(count($historyOtherFilters) ? '?'.http_build_query($historyOtherFilters) : ''));
@endphp
<section class="history-filter-panel no-print" aria-label="Historical record dates">
    <style>
        .history-filter-panel { background:#fff; border:1px solid #d6e5df; border-radius:16px; padding:18px 20px; min-width:0; }
        .history-filter-panel h3 { margin:0 0 8px; font-size:1.25rem; color:#0f172a;line-height:1.35; }
        .history-filter-panel p { margin:0 0 12px; color:#475569; font-size:1rem; line-height:1.55;overflow-wrap:break-word; }
        .history-filter-panel form { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; }
        .history-filter-panel label { display:flex; flex-direction:column; gap:6px; flex:1 1 12rem; min-width:0; font-size:1rem; font-weight:600; color:#334155; }
        .history-filter-panel input { box-sizing:border-box; width:100%; min-width:0; min-height:48px; padding:10px 12px; background:#fff; border:1px solid #a6b7af; border-radius:8px; color:#0f172a; font:inherit; }
        .history-filter-panel button, .history-filter-panel .history-clear { box-sizing:border-box; display:inline-flex; justify-content:center; align-items:center; min-height:48px;max-width:100%; padding:10px 16px; border-radius:8px; font-size:1rem; font-weight:600;line-height:1.4;text-align:center; text-decoration:none; cursor:pointer; }
        .history-filter-panel button { color:#fff; background:#258663; border:1px solid #258663; }
        .history-filter-panel .history-clear { color:#334155; border:1px solid #a6b7af; background:#fff; }
        .history-filter-panel .history-period { margin:12px 0 0; font-weight:600; }
        .history-filter-panel .history-error { color:#b91c1c; margin:8px 0 0; }
        @media(max-width:480px) { .history-filter-panel { padding:16px; } .history-filter-panel label { flex-basis:100%; } }
        @media print { .history-filter-panel { display:none; } }
    </style>
    <h3>Historical records</h3>
    <p>Choose a year or date range. For a single day, enter the same start and end date. Only records saved in this system are available.</p>
    <form method="GET" action="{{ $historyAction }}" data-empty-action="history">
        @foreach($historyOtherFilters as $historyName => $historyValue)
            <input type="hidden" name="{{ $historyName }}" value="{{ $historyValue }}">
        @endforeach
        <label>Year (optional)
            <input type="number" name="year" min="1900" max="9999" step="1" placeholder="All years" value="{{ request('year') }}" inputmode="numeric">
        </label>
        <label>Start date
            <input type="date" name="date_from" min="1900-01-01" value="{{ request('date_from') }}">
        </label>
        <label>End date
            <input type="date" name="date_to" min="1900-01-01" value="{{ request('date_to') }}">
        </label>
        <button type="submit">View history</button>
        <a class="history-clear" href="{{ $historyResetUrl }}">Clear dates</a>
    </form>
    <p class="history-period">Showing: {{ $historyRange->label() }}</p>
    @foreach(['year', 'date_from', 'date_to'] as $historyField)
        @error($historyField)<p class="history-error" role="alert">{{ $message }}</p>@enderror
    @endforeach
</section>
