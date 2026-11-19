@php
    $inventoryNavigationFilters = collect(request()->only('rhu_filter', 'year', 'date_from', 'date_to'))
        ->filter(fn ($value) => is_scalar($value) && (string) $value !== '')->all();
    $inventoryNavigationTab = $activeInventoryTab ?? 'inventory';
@endphp
@once
    <style>
        .inventory-section-nav{display:inline-flex;flex-wrap:wrap;gap:4px;align-items:stretch;max-width:100%;min-width:0;padding:4px;border-radius:12px;background:#f1f5f9;box-sizing:border-box}
        .inventory-section-nav a{display:inline-flex;align-items:center;justify-content:center;gap:8px;flex:0 1 auto;min-width:0;min-height:44px;box-sizing:border-box;padding:8px 16px;border-radius:9px;color:#475569;font-size:1.0625rem;font-weight:600;line-height:1.4;text-decoration:none;overflow-wrap:anywhere}
        .inventory-section-nav a:hover{background:#e2eee8;color:#0f5c4b}
        .inventory-section-nav a.is-active{background:#258663;color:#fff;box-shadow:0 2px 6px rgba(37,134,99,.2)}
        .inventory-section-nav a:focus-visible{outline:3px solid #0f766e;outline-offset:3px}
        .inventory-section-nav svg{flex:0 0 18px;width:18px;height:18px}
        @media(max-width:480px){.inventory-section-nav{display:flex;width:100%}.inventory-section-nav a{flex:1 1 10rem}}
    </style>
@endonce
<nav class="inventory-section-nav page-section-nav {{ $inventoryNavigationClass ?? '' }} no-print" aria-label="Inventory sections">
    <a href="{{ route('inventory.index', $inventoryNavigationFilters) }}" title="View current stock and batches" @class(['is-active' => $inventoryNavigationTab === 'inventory']) @if($inventoryNavigationTab === 'inventory') aria-current="page" @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
        Inventory
    </a>
    <a href="{{ route('stock_logs.index', $inventoryNavigationFilters) }}" title="Find individual stock movements" @class(['is-active' => $inventoryNavigationTab === 'logs']) @if($inventoryNavigationTab === 'logs') aria-current="page" @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
        Stock logs
    </a>
    <a href="{{ route('inventory.index', ['view' => 'history'] + $inventoryNavigationFilters) }}" title="Review stock balances and activity for a period" @class(['is-active' => $inventoryNavigationTab === 'history']) @if($inventoryNavigationTab === 'history') aria-current="page" @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
        Inventory history
    </a>
</nav>
