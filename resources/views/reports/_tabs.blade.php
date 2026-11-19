@php
    $reportTabs = [
        ['reports.inventory',   'Inventory'],
        ['reports.stock',       'Stock Logs'],
        ['reports.allocation',  'Allocation'],
    ];

    if (session('health_unit_id')) {
        $reportTabs[] = ['reports.dispensing', 'Dispensing'];
    }

    $reportTabs[] = ['reports.near_expiry', 'Near Expiry'];
    $reportTabs[] = ['elmis.export',        'ELMIS'];
@endphp

<nav class="page-section-nav" aria-label="Report sections" style="display:flex; align-items:baseline; gap:20px; flex-wrap:wrap;">
    @foreach($reportTabs as $tab)
        @php
            $routeName  = $tab[0];
            $shortLabel = $tab[1];
            $isActive   = request()->routeIs($routeName);
        @endphp

        <a href="{{ route($routeName) }}" @if($isActive) aria-current="page" @endif
           @if($isActive)
               style="
                   font-size:1.0625rem; font-weight:700; text-decoration:none;
                   color:#0f5c4b; padding-bottom:4px;
                   border-bottom:2px solid #42ad8a;
                   white-space:nowrap;
               "
           @else
               style="
                   font-size:1.0625rem; font-weight:600; text-decoration:none;
                   color:#94a3b8; white-space:nowrap;
                   transition: color 0.2s;
               "
               onmouseover="this.style.color='#0f5c4b'"
               onmouseout="this.style.color='#94a3b8'"
           @endif
        >
            {{ $shortLabel }}
        </a>
    @endforeach
</nav>