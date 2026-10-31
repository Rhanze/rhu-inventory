@php
    // Pass: $batches (collection or array of batch_no strings), $formAction (route name)
    $batches    = $batches    ?? collect();
    $formAction = $formAction ?? url()->current();
@endphp

@if($batches->isNotEmpty())
    <form method="GET" action="{{ $formAction }}" style="display: flex; gap: 8px; align-items: center;">
        @foreach(request()->except('batch_filter', 'page') as $k => $v)
            @if(!is_array($v))
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endif
        @endforeach
        <select name="batch_filter" onchange="this.form.submit()" style="
            height: 40px; padding: 0 12px; font-size: 14px;
            border: 2px solid #bec6cf; border-radius: 10px; outline: none;
            background: white; color: #0f172a; appearance: auto;
        ">
            <option value="">All Batches</option>
            @foreach($batches as $bn)
                <option value="{{ $bn }}" {{ request('batch_filter') == $bn ? 'selected' : '' }}>
                    {{ $bn }}
                </option>
            @endforeach
        </select>
    </form>
@endif