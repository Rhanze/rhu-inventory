@extends('layouts.app')

@section('title', 'Prescription History')

@section('content')
<section style="max-width:44rem;padding:1.5rem;border-radius:1rem;background:#fff;">
    <a href="{{ route('dispensing.index') }}" style="color:#0f5c4b;text-decoration:underline;">Dispensing records</a>
    <h1 style="font-size:1.5rem;font-weight:700;margin:.75rem 0;">Prescription history for dispensing #{{ $record->id }}</h1>
    @if(!$record->trashed() && $record->prescription_image)
        <p><a href="{{ route('dispensing.prescription', $record->id) }}" style="color:#0f5c4b;text-decoration:underline;">Open current prescription</a></p>
    @endif
    <ul style="display:grid;gap:.75rem;margin-top:1rem;">
        @forelse($versions as $version)
            <li>
                <a href="{{ route('dispensing.prescription', ['id' => $record->id, 'version' => $version->id]) }}" style="color:#0f5c4b;text-decoration:underline;">Open archived version {{ $version->id }}</a>
                <span>{{ ucfirst($version->reason) }} on {{ \Carbon\Carbon::parse($version->archived_at)->format('M j, Y g:i A') }}</span>
            </li>
        @empty
            <li>No archived prescriptions are recorded.</li>
        @endforelse
    </ul>
</section>
@endsection
