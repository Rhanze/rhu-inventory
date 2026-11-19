<div class="program-stock-reminder" style="margin-top:10px; font-size:14px; color:#334155; overflow-wrap:anywhere;">
    @forelse($plan['items'] as $item)
        <p style="margin:6px 0;">
            <strong>{{ $item['name'] }} {{ $item['dosage'] }}</strong>
            &mdash;
            @if($plan['date'] < today()->toDateString())
                Check current stock
            @elseif($item['stock_up'] === null)
                Check stock before the event
            @elseif($item['stock_up'] > 0)
                <span style="font-weight:600; color:#92400e;">Stock up {{ $item['history_ready'] ? '' : 'at least ' }}{{ number_format($item['stock_up']) }} {{ $item['unit'] }}</span>
            @else
                {{ $item['history_ready'] ? 'Stock is sufficient' : 'Confirm stock before the event' }}
            @endif
        </p>
    @empty
        <p style="margin:6px 0;">No materials listed for this event.</p>
    @endforelse

    @if($plan['date'] >= today()->toDateString())
        @php($planDialogId = 'program-plan-'.$plan['program_id'].'-'.$plan['date'])
        @php($canEditPlan = in_array(session('role'), ['admin', 'staff'], true) && $plan['date'] <= today()->addYear()->toDateString())
        @if($errors->any() && (int) old('material_plan_program_id') === $plan['program_id'] && old('event_date') === $plan['date'])
            <p role="alert" style="margin:8px 0; color:#92400e;">The material plan was not saved.</p>
        @endif
        <button type="button" class="cal-nav-btn" aria-haspopup="dialog" aria-controls="{{ $planDialogId }}" onclick="document.getElementById('{{ $planDialogId }}').showModal()" style="margin-top:8px; font-size:14px;">
            {{ $canEditPlan ? 'Review and edit material plan' : 'View stock details' }}
        </button>
        @push('programPlanningDialogs')
            @include('vaccination._program_plan_dialog', ['plan' => $plan, 'planDialogId' => $planDialogId])
        @endpush
    @endif
</div>
