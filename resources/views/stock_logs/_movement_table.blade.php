@if($movements->isEmpty())
    <p class="stock-movement-empty">No stock movements match these filters.</p>
@else
    <ul class="stock-movement-list" role="list">
        @foreach($movements as $movement)
            @php
                $display = \App\Support\StockMovementDisplay::describe($movement);
                $isLog = ($movement->event_source ?? 'log') === 'log';
                $id = $movement->event_id ?? $movement->id;
                $isHighlighted = $isLog && (string) request('highlight') === (string) $id;
                $date = \Carbon\Carbon::make($movement->stock_effective_date ?? $movement->date_added);
                $entered = \Carbon\Carbon::make($movement->recorded_at ?? null);
                $activeLog = $movement instanceof \App\Models\StockLog;
                $medicineRecord = $activeLog ? $movement->medicine : null;
                $medicine = $movement->medicine_name ?? $medicineRecord?->medicine_name ?? 'Medicine no longer available';
                $dosage = $movement->dosage ?? $medicineRecord?->dosage;
                $form = $movement->dosage_form ?? $medicineRecord?->dosage_form;
                $facility = $movement->facility_name ?? ($activeLog ? $movement->healthUnit?->name : null);
                $showFacility = session('health_unit_id') === null;
                $actor = $movement->actor_name ?? ($activeLog ? $movement->addedByUser?->name : null);
                $reference = match (true) {
                    $movement->transfer_id !== null => 'Transfer #'.$movement->transfer_id,
                    $movement->allocation_id !== null => 'Allocation #'.$movement->allocation_id,
                    $movement->dispensing_record_id !== null => 'Dispensing #'.$movement->dispensing_record_id,
                    default => ($isLog ? 'Stock log #' : 'Stock movement #').$id,
                };
                $details = [
                    'Batch' => filled($movement->batch_no) ? $movement->batch_no : 'Not recorded',
                    'Recorded by' => $actor ?: ($movement->added_by ? 'User #'.$movement->added_by : 'Not recorded'),
                    'Reference' => $reference,
                    'Facility' => $showFacility ? null : $facility,
                    'From' => $movement->source_name ?? null,
                    'To' => $movement->destination_name ?? null,
                    'Entered' => $entered?->format('M d, Y g:i A'),
                    'Storage' => $movement->storage_location,
                ] + $display['stock_changes'] + [
                    'Method' => $movement->method ?? null,
                    'Notes' => $movement->report_remarks,
                ];
            @endphp
            <li class="stock-movement-entry">
                <details id="{{ $isLog ? 'stock-log-' : 'stock-movement-' }}{{ $id }}"
                         @class(['stock-movement-details', 'is-highlighted' => $isHighlighted]) @if($isHighlighted) open @endif>
                    <summary @class(['stock-movement-summary', 'has-facility' => $showFacility])>
                        @if($date)
                            <time class="stock-movement-date" datetime="{{ $date->toDateString() }}">{{ $date->format('M d, Y') }}</time>
                        @else
                            <span class="stock-movement-date">Date unavailable</span>
                        @endif
                        <span class="stock-movement-medicine">
                            <strong>{{ $medicine }}</strong>
                            @if($dosage || $form)
                                <span class="stock-movement-secondary">{{ implode(' · ', array_filter([$dosage, $form])) }}</span>
                            @endif
                        </span>
                        <span class="stock-movement-action">
                            <strong>{{ $display['label'] }}</strong>
                            @if($display['effect'])
                                <span class="stock-movement-secondary">{{ $display['effect'] }}</span>
                            @endif
                            @if($display['status'])
                                <span class="stock-movement-status">{{ $display['status'] }}</span>
                            @endif
                        </span>
                        @if($display['quantity'] !== null)
                            <strong class="stock-movement-quantity is-{{ $display['tone'] }}">{{ $display['quantity'] }}</strong>
                        @endif
                        @if($showFacility)
                            <span class="stock-movement-facility">{{ $facility ?? 'Facility unavailable' }}</span>
                        @endif
                        <span class="stock-movement-toggle">Details</span>
                    </summary>
                    <dl class="stock-movement-metadata">
                        @foreach($details as $label => $value)
                            @if(filled($value))
                                <div @class(['stock-movement-note' => $label === 'Notes'])>
                                    <dt>{{ $label }}</dt>
                                    <dd>{{ $value }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </details>
            </li>
        @endforeach
    </ul>

    @once
        <script>
            (() => {
                let opened = [];
                window.addEventListener('beforeprint', () => {
                    opened = Array.from(document.querySelectorAll('.stock-movement-details:not([open])'));
                    opened.forEach(details => { details.open = true; });
                });
                window.addEventListener('afterprint', () => {
                    opened.forEach(details => { details.open = false; });
                    opened = [];
                });
            })();
        </script>
    @endonce
@endif
