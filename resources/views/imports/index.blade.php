@extends('layouts.app')
@section('title', 'CSV Imports')

@section('content')
<div style="max-width:70rem;margin:0 auto;display:grid;gap:1.25rem;">
    <section style="background:#fff;border:1px solid #d7e8e1;border-radius:1rem;padding:clamp(1rem,2vw,1.75rem);">
        <h2 style="margin:0;color:#0f5c4b;font-size:clamp(1.5rem,2vw,1.875rem);line-height:1.3;">Import</h2>
        <p style="margin:.75rem 0 0;line-height:1.6;">Choose the records that supply the report. Available imports follow your current role and RHU assignment.</p>
        <p style="margin:.75rem 0 0;line-height:1.6;">Clinical, patient, and dispensing imports use an account assigned to the records’ RHU. Each import page includes a downloadable CSV template. Summary reports may need their underlying record details added before import.</p>
    </section>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,18rem),1fr));gap:1rem;">
        @foreach($sections as $key => $definition)
            <a href="{{ route('imports.create', ['section' => $key]) }}" style="display:block;min-width:0;padding:1.25rem;border:1px solid #d7e8e1;border-radius:.875rem;background:#fff;color:#0f5c4b;text-decoration:none;font-size:1.125rem;font-weight:600;line-height:1.5;">
                {{ $definition['title'] }}
            </a>
        @endforeach
    </div>
</div>
@endsection
