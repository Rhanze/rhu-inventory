@once
<style>
    .program-plan-dialog { position:fixed;inset:0;margin:auto;box-sizing:border-box;width:calc(100% - 32px);max-width:900px;height:fit-content;max-height:calc(100dvh - 32px);padding:0;border:1px solid #d6e5df;border-radius:20px;background:#fff;color:#334155;font:inherit;font-size:1.0625rem;line-height:1.5;overflow:auto;box-shadow:0 24px 60px rgba(0,0,0,.25); }
    .program-plan-dialog::backdrop { background:rgba(15,23,42,.55);backdrop-filter:blur(4px); }
    .program-plan-dialog header { padding:22px 26px 16px;border-bottom:1px solid #e2e8f0; }
    .program-plan-dialog h3 { font-size:1.375rem;line-height:1.3;color:#0f172a;font-weight:700;margin:0 0 8px;overflow-wrap:break-word; }
    .program-plan-dialog p { margin:8px 0;overflow-wrap:break-word; }
    .program-plan-body { display:grid;gap:20px;padding:20px 26px; }
    .program-plan-item { border:1px solid #d6e5df;border-radius:14px;padding:18px;min-width:0;margin:0; }
    .program-plan-item legend { padding:0 8px;color:#0f172a;font-size:1.125rem;font-weight:700;max-width:100%;overflow-wrap:break-word; }
    .program-plan-metrics { display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,15rem),1fr));gap:14px 20px;margin:0; }
    .program-plan-metrics dt { color:#475569;font-size:1rem; }
    .program-plan-metrics dd { font-size:1.125rem;font-weight:700;color:#0f172a;margin:4px 0 0; }
    .program-plan-help { font-size:1rem;color:#475569; }
    .program-plan-result { padding:12px 14px;border:1px solid #cbd5e1;border-radius:10px;margin-top:16px; }
    .program-plan-result.needs-stock { background:#fffbeb;border-color:#f59e0b;color:#92400e; }
    .program-plan-result.covered { background:#f0fdf7;border-color:#a7d7c5;color:#0f5c4b; }
    .program-plan-dialog label { display:block;color:#0f172a;font-weight:600;margin-top:16px; }
    .program-plan-dialog input[type=number],.program-plan-dialog select { display:block;font:inherit;width:100%;max-width:100%;box-sizing:border-box;min-height:48px;padding:10px 12px;border:2px solid #bec6cf;border-radius:10px;background:#fff;color:#0f172a;margin-top:8px; }
    .program-plan-dialog footer { position:sticky;bottom:0;padding:16px 26px;border-top:1px solid #e2e8f0;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;background:#fafbfc; }
    .program-plan-dialog footer button,.program-plan-dialog footer a { font:inherit;font-weight:600;line-height:1.4;min-height:44px;padding:10px 14px;border-radius:10px;border:1px solid #cbd5e1;background:white;color:#334155;text-decoration:none;cursor:pointer; }
    .program-plan-dialog footer .program-plan-save { background:#0f766e;color:#fff;border-color:#0f766e; }
    .program-plan-dialog :is(a,button,input,select):focus-visible { outline:3px solid #0f766e;outline-offset:3px; }
    @media(max-width:600px) { .program-plan-dialog header,.program-plan-body,.program-plan-dialog footer { padding:16px; } .program-plan-item { padding:14px; } }
</style>
@endonce
@php($canEditPlan = in_array(session('role'), ['admin', 'staff'], true) && $plan['date'] >= today()->toDateString() && $plan['date'] <= today()->addYear()->toDateString())
@php($restorePlan = $canEditPlan && $errors->any() && (int) old('material_plan_program_id') === (int) $plan['program_id'] && old('event_date') === $plan['date'])
<dialog id="{{ $planDialogId }}" class="program-plan-dialog" aria-labelledby="{{ $planDialogId }}-title" onclick="if(event.target === this) this.close()">
    <header>
        <h3 id="{{ $planDialogId }}-title">{{ $plan['name'] }} &mdash; material plan</h3>
        <p>{{ \Carbon\Carbon::parse($plan['date'])->format('M j, Y') }}@if($plan['end_date'] !== $plan['date']) &ndash; {{ \Carbon\Carbon::parse($plan['end_date'])->format('M j, Y') }}@endif &middot; {{ $plan['where'] }}</p>
        <p class="program-plan-help">Compare usable stock with the quantity needed for the whole program. @if($canEditPlan)Save changes to recalculate the estimates.@endif</p>
    </header>
    @if($canEditPlan)
    <form method="POST" action="{{ route('vaccination.program_materials.store', $plan['program_id']) }}">
        @csrf
        <input type="hidden" name="event_date" value="{{ $plan['date'] }}">
        <input type="hidden" name="material_plan_program_id" value="{{ $plan['program_id'] }}">
    @endif
        <div class="program-plan-body">
            @if($restorePlan)
                <div role="alert" class="program-plan-result needs-stock">
                    <strong>Review the plan errors</strong>
                    <ul style="padding-left:20px;margin:8px 0 0;">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                </div>
            @endif
            @forelse($plan['editable_items'] as $item)
                @php($excluded = $item['planned_quantity'] !== null && (int) $item['planned_quantity'] === 0)
                <fieldset class="program-plan-item">
                    <legend>{{ $item['name'] }} {{ $item['dosage'] }}</legend>
                    <dl class="program-plan-metrics">
                        <div><dt>RHU shared stock available now</dt><dd>{{ number_format($item['current_stock']) }} {{ $item['unit'] }}</dd></div>
                        <div><dt>Current stock valid on the start date</dt><dd>{{ number_format($item['expiry_eligible_stock']) }} {{ $item['unit'] }}</dd></div>
                        <div><dt>Expected balance at program start</dt><dd>{{ $item['projected_stock'] === null ? 'Estimate unavailable' : number_format($item['projected_stock']).' '.$item['unit'] }}</dd></div>
                        <div><dt>Quantity planned for the whole program</dt><dd>{{ $excluded ? 'Excluded from this plan' : ($item['target_quantity'] === null ? 'Not estimated; set a quantity' : number_format($item['target_quantity']).' '.$item['unit']) }}</dd></div>
                    </dl>
                    <p class="program-plan-help">Stock valid on the start date excludes batches that expire earlier. The expected balance also accounts for estimated routine use before the program.</p>
                    @if(!$item['history_ready'])
                        <p class="program-plan-help" style="color:#92400e;">
                            @if($item['history_weeks'] < config('forecasting.minimum_trend_weeks', 4))
                                @if($item['history_weeks'] === 0)No recorded release history is available.@else Release history: {{ $item['history_weeks'] }} completed weekly periods.@endif
                                At least {{ config('forecasting.minimum_trend_weeks', 4) }} weekly periods are needed to estimate routine use.
                            @else
                                The available forecast does not cover this program's full period.
                            @endif
                            @if($item['target_quantity'] === null){{ $canEditPlan ? 'Enter a local program quantity to calculate a minimum stock-up requirement.' : 'Ask RHU inventory staff to set a program quantity so a minimum stock-up requirement can be calculated.' }}@endif
                        </p>
                    @endif
                    @if($excluded)
                        <div class="program-plan-result">This item is excluded.@if($canEditPlan) Enter a positive quantity or leave it blank to use an available history-based estimate.@endif</div>
                    @elseif($item['stock_up'] === null)
                        <div class="program-plan-result">Additional stock needed cannot be calculated until a program quantity is set.</div>
                    @elseif($item['stock_up'] > 0)
                        <div class="program-plan-result needs-stock">
                            <strong>Stock up {{ $item['history_ready'] ? '' : 'at least ' }}{{ number_format($item['stock_up']) }} {{ $item['unit'] }}</strong>
                            @if($item['history_ready'])
                                <p>Before-program shortage: {{ number_format($item['routine_shortage_before_program']) }} {{ $item['unit'] }}.<br>Program shortage: {{ number_format($item['program_shortage']) }} {{ $item['unit'] }}.</p>
                                <p>Total additional stock = {{ number_format($item['routine_shortage_before_program']) }} + {{ number_format($item['program_shortage']) }} = {{ number_format($item['stock_up']) }} {{ $item['unit'] }}.</p>
                            @else
                                <p>This minimum covers the saved program quantity. Routine use before the program still needs local review.</p>
                            @endif
                        </div>
                    @else
                        <div class="program-plan-result covered">{{ $item['history_ready'] ? 'No projected shortfall for this program quantity.' : 'Expiry-eligible stock covers this program quantity. Confirm routine use before the program.' }}</div>
                    @endif
                    @if($item['expires_during_program'] > 0)
                        <p class="program-plan-help" style="color:#92400e;">{{ number_format($item['expires_during_program']) }} {{ $item['unit'] }} available at the start expire during the program. The shortage estimate checks expiry each day.</p>
                    @endif
                    <p class="program-plan-help">Quantity source: {{ $item['basis'] }}.</p>
                    @if($canEditPlan)
                        <input type="hidden" name="items[{{ $loop->index }}][medicine_id]" value="{{ $item['medicine_id'] }}">
                        <label for="{{ $planDialogId }}-quantity-{{ $item['medicine_id'] }}">Your planned quantity ({{ $item['unit'] }})</label>
                        <input id="{{ $planDialogId }}-quantity-{{ $item['medicine_id'] }}" type="number" min="0" max="100000000" step="1" name="items[{{ $loop->index }}][quantity]" value="{{ $restorePlan ? old('items.'.$loop->index.'.quantity', $item['planned_quantity']) : $item['planned_quantity'] }}" placeholder="Use release history" aria-describedby="{{ $planDialogId }}-quantity-help-{{ $item['medicine_id'] }}">
                        <p id="{{ $planDialogId }}-quantity-help-{{ $item['medicine_id'] }}" class="program-plan-help">Enter the total for the entire program, in {{ $item['unit'] }}. This total is spread evenly across the program days when checking expiry. Leave blank to use sufficient release history; enter 0 to exclude this material.</p>
                    @endif
                </fieldset>
            @empty
                <p>No materials are configured for this program.@if($canEditPlan) Choose an additional material below.@endif</p>
            @endforelse
            @if($canEditPlan)
                <fieldset class="program-plan-item">
                    <legend>Add another material</legend>
                    <label for="{{ $planDialogId }}-additional">Catalog material</label>
                    <select id="{{ $planDialogId }}-additional" name="additional_medicine_id">
                        <option value="">Select a catalog item</option>
                        @foreach($planningMedicines->whereNotIn('id', collect($plan['editable_items'])->pluck('medicine_id')) as $medicine)<option value="{{ $medicine->id }}" @selected($restorePlan && (string) old('additional_medicine_id') === (string) $medicine->id)>{{ $medicine->medicine_name }} {{ $medicine->dosage }} &middot; {{ $medicine->unit }}</option>@endforeach
                    </select>
                    <label for="{{ $planDialogId }}-additional-quantity">Total quantity for the program</label>
                    <input id="{{ $planDialogId }}-additional-quantity" type="number" min="0" max="100000000" step="1" name="additional_quantity" value="{{ $restorePlan ? old('additional_quantity') : '' }}" placeholder="Enter quantity or use release history">
                    <p class="program-plan-help">Use the selected item's unit. A blank quantity uses sufficient release history; otherwise the item remains unquantified.</p>
                </fieldset>
            @endif
        </div>
        <footer>
            @if($plan['source'])<a href="{{ $plan['source'] }}" target="_blank" rel="noopener noreferrer">Material reference</a>@endif
            <button type="button" onclick="this.closest('dialog').close()">Close</button>
            @if($canEditPlan)
                <button type="submit" class="program-plan-save">Save plan</button>
                <button type="submit" name="reset_plan" value="1">Reset to suggested materials</button>
            @endif
        </footer>
    @if($canEditPlan)</form>@endif
</dialog>
@if($restorePlan)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dialog = document.getElementById({{ \Illuminate\Support\Js::from($planDialogId) }});
    if (dialog && typeof dialog.showModal === 'function' && !dialog.open) dialog.showModal();
}, { once: true });
</script>
@endif
