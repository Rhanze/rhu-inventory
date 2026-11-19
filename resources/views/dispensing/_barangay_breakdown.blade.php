@php
    $barangayGroups = $barangayBreakdown->groupBy(fn ($row) => mb_strtolower($row->recorded_area ?? ''));
    $missingBarangayRows = $barangayGroups->pull('');
    if ($missingBarangayRows) {
        $barangayGroups->put('', $missingBarangayRows);
    }
    $breakdownRoute = request()->routeIs('reports.dispensing') ? 'reports.dispensing' : 'dispensing.index';
    $breakdownFilters = request()->except('page', 'barangay', 'barangay_missing', 'medicine_id');
@endphp
<section style="padding:20px;background:#fff;border:1px solid #dbe5e1;border-radius:16px;" aria-labelledby="dispensing-area-heading">
    <h3 id="dispensing-area-heading" style="margin:0 0 8px;font-size:1.125rem;font-weight:700;color:#0f172a;">Dispensing by barangay</h3>
    @if($barangayGroups->isEmpty())
        <p style="margin:0;color:#475569;">No dispensing records match these filters.</p>
    @else
        <p style="margin:0 0 12px;color:#475569;">{{ number_format($facilityRecordCount) }} dispensing {{ $facilityRecordCount === 1 ? 'record' : 'records' }} match these filters. Open a group to see totals by medicine.</p>
        <div style="display:grid;gap:8px;">
            @foreach($barangayGroups as $rows)
                @php
                    $barangay = $rows->first()->recorded_area;
                    $missing = $barangay === null;
                    $groupCount = (int) $rows->sum('record_count');
                    $groupLabel = $missing ? 'No barangay recorded' : $barangay;
                    $groupFilter = $missing ? ['barangay_missing' => 1] : ['barangay' => $barangay];
                    $groupOpen = request()->boolean('print') || ($missing
                        ? request()->boolean('barangay_missing')
                        : $barangayGroups->count() === 1 || request()->filled('barangay'));
                @endphp
                <details @if($groupOpen) open @endif style="border:1px solid #dbe5e1;border-radius:10px;overflow:hidden;">
                    <summary style="padding:12px 16px;background:#f8fafc;color:#0f5c4b;cursor:pointer;font-weight:600;">
                        {{ $groupLabel }}
                        <span style="margin-inline-start:8px;color:#475569;font-weight:400;">{{ number_format($groupCount) }} {{ $groupCount === 1 ? 'record' : 'records' }}</span>
                    </summary>
                    <div role="region" aria-label="Medicine totals for {{ $groupLabel }}" tabindex="0" style="overflow-x:auto;">
                        <table style="width:100%;min-width:26rem;border-collapse:collapse;text-align:left;">
                            <thead>
                                <tr>
                                    <th scope="col" style="padding:10px 16px;">Medicine</th>
                                    <th scope="col" style="padding:10px 16px;">Quantity dispensed</th>
                                    <th scope="col" style="padding:10px 16px;">Records</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $row)
                                    <tr style="border-top:1px solid #e2e8f0;">
                                        <td style="padding:10px 16px;">{{ $row->medicine?->medicine_name ?? 'N/A' }} {{ $row->medicine?->dosage }}</td>
                                        <td style="padding:10px 16px;white-space:nowrap;">{{ number_format($row->quantity) }} {{ $row->medicine?->unit ?: 'units' }}</td>
                                        <td style="padding:10px 16px;white-space:nowrap;">
                                            <a href="{{ route($breakdownRoute, $breakdownFilters + ['medicine_id' => $row->medicine_id] + $groupFilter) }}" aria-label="View dispensing records for {{ $row->medicine?->medicine_name ?? 'this medicine' }}" style="color:#0f5c4b;text-decoration:underline;">{{ number_format($row->record_count) }} {{ $row->record_count == 1 ? 'record' : 'records' }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endforeach
        </div>
    @endif
</section>
