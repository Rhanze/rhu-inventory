@php
    // Pass: $formAction (route name)
    $formAction = $formAction ?? url()->current();
    $current    = request('expiry_filter', '');
@endphp

<form method="GET" action="{{ $formAction }}" style="display: flex; gap: 8px; align-items: center;">
    @foreach(request()->except('expiry_filter', 'page') as $k => $v)
        @if(!is_array($v))
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endif
    @endforeach
    <select name="expiry_filter" onchange="this.form.submit()" style="
        height: 40px; padding: 0 12px; font-size: 14px;
        border: 2px solid #bec6cf; border-radius: 10px; outline: none;
        background: white; color: #0f172a; appearance: auto;
    ">
        <option value=""              {{ $current === ''              ? 'selected' : '' }}>All Expiry</option>
        <option value="near_expiry"   {{ $current === 'near_expiry'   ? 'selected' : '' }}>Near Expiry (≤180 days)</option>
        <option value="expired"       {{ $current === 'expired'       ? 'selected' : '' }}>Expired Only</option>
        <option value="not_expired"   {{ $current === 'not_expired'   ? 'selected' : '' }}>Not Expiring Soon</option>
    </select>
</form>