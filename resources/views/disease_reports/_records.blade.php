<section class="disease-card" id="recorded-consumption" aria-labelledby="recorded-consumption-heading">
    <h2 id="recorded-consumption-heading" class="disease-section-heading">Recorded medicine consumption</h2>
    <form method="GET" action="{{ route('disease_reports.index') }}#recorded-consumption" class="disease-row" style="margin:20px 0;">
        @foreach(collect($viewFilters)->except('consumption_search') as $filterName => $filterValue)
            @if(is_scalar($filterValue))<input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">@endif
        @endforeach
        <div style="flex:1 1 22rem;">
            <label for="consumption-record-search">Search medicine, strength or form</label>
            <input id="consumption-record-search" class="disease-control" style="width:100%;" type="search" name="consumption_search" maxlength="160" value="{{ $consumptionSearch }}" placeholder="Search saved consumption">
        </div>
        <button type="submit" class="disease-button">Search records</button>
        @if($consumptionSearch !== '')
            <a class="disease-button disease-secondary" href="{{ route('disease_reports.index', collect($viewFilters)->except('consumption_search')->all()) }}#recorded-consumption">Clear search</a>
        @endif
    </form>
    <p class="disease-muted" id="consumption-record-count">
        @if($consumptionRecords->total())Showing {{ number_format($consumptionRecords->firstItem()) }}–{{ number_format($consumptionRecords->lastItem()) }} of {{ number_format($consumptionRecords->total()) }} saved records.
        @else No saved consumption matches the selected area, dates and search.
        @endif
    </p>
    <div class="disease-scroll" tabindex="0" role="region" aria-label="Saved medicine consumption records" aria-describedby="consumption-record-count">
        <table class="disease-table mw-mobile-records" style="min-width:800px;">
            <thead><tr><th scope="col">Date dispensed</th><th scope="col">Medicine</th><th scope="col">Quantity</th><th scope="col">Unit</th><th scope="col">Barangay</th><th scope="col">Stock source</th></tr></thead>
            <tbody>
                @forelse($consumptionRecords as $consumptionRecord)
                    <tr>
                        <td>{{ $consumptionRecord->date_dispensed->toDateString() }}</td>
                        <td><strong>{{ trim($consumptionRecord->medicine->medicine_name.' '.$consumptionRecord->medicine->dosage) }}</strong>@if($consumptionRecord->medicine->dosage_form)<br>{{ $consumptionRecord->medicine->dosage_form }}@endif</td>
                        <td>{{ number_format($consumptionRecord->quantity_dispensed) }}</td>
                        <td>{{ $consumptionRecord->medicine->unit ?: 'units' }}</td>
                        <td>{{ $consumptionRecord->barangay ?: 'Not recorded' }}</td>
                        <td>{{ $consumptionRecord->stock_source === 'allocation' ? 'Received supplies' : 'Shared RHU stock' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No saved consumption records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($consumptionRecords->hasPages())
        @php
            $recordPageStart = max(1, min($consumptionRecords->currentPage() - 4, $consumptionRecords->lastPage() - 9));
            $recordPageEnd = min($consumptionRecords->lastPage(), $recordPageStart + 9);
        @endphp
        <nav class="disease-row" aria-label="Consumption record pages" style="margin-top:20px;justify-content:center;align-items:center;gap:8px;">
            @if($consumptionRecords->previousPageUrl())<a class="disease-button disease-secondary" rel="prev" href="{{ $consumptionRecords->previousPageUrl() }}">Previous</a>@endif
            @foreach($consumptionRecords->getUrlRange($recordPageStart, $recordPageEnd) as $recordPage => $recordPageUrl)
                @if($recordPage === $consumptionRecords->currentPage())<span class="disease-button" aria-current="page">{{ $recordPage }}</span>
                @else<a class="disease-button disease-secondary" href="{{ $recordPageUrl }}">{{ $recordPage }}</a>@endif
            @endforeach
            @if($consumptionRecords->nextPageUrl())<a class="disease-button disease-secondary" rel="next" href="{{ $consumptionRecords->nextPageUrl() }}">Next</a>@endif
        </nav>
    @endif
</section>
