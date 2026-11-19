<input type="hidden" name="view" value="{{ $activeView }}">
@if(session('role') === 'midwife' && ! ($assignedAreaForm ?? false))<input type="hidden" name="consumption_scope" value="{{ $consumptionScope }}">@endif
@foreach(['year', 'date_from', 'date_to'] as $historyParameter)
    @if(request()->filled($historyParameter))<input type="hidden" name="{{ $historyParameter }}" value="{{ request($historyParameter) }}">@endif
@endforeach
