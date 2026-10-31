@extends('layouts.app')
@section('content')
<div style="background:white;padding:24px;border-radius:16px;overflow:auto">
    <h1>Redistribution suggestions</h1>
    <p>Compare usable stock and recent consumption before arranging a transfer.</p>
    <a href="{{ route('redistribution.export') }}">Export CSV</a>
    <table style="width:100%;margin-top:20px;text-align:left">
        <thead><tr><th>Medicine</th><th>From RHU</th><th>To RHU</th><th>Quantity</th><th>Reason</th></tr></thead>
        <tbody>
        @forelse($suggestions as $suggestion)
        <tr>
            <td>{{ $suggestion['medicine_name'] }}</td><td>{{ $suggestion['from_rhu'] }}</td>
            <td>{{ $suggestion['to_rhu'] }}</td><td>{{ $suggestion['suggested_quantity'] }}</td><td>{{ $suggestion['reason'] }}</td>
        </tr>
        @empty
        <tr><td colspan="5">No redistribution suggestions based on current stock and consumption.</td></tr>
        @endforelse
        </tbody>
    </table>
    <p>{{ $overstocked->count() }} overstocked item(s); {{ $understocked->count() }} understocked item(s).</p>
</div>
@endsection
