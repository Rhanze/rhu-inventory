@php
    $importAllowed = app(\App\Services\CsvImportService::class)->canImport($section);
    $importDialogId = 'csv-import-'.\Illuminate\Support\Str::uuid();
@endphp
@if($importAllowed || (($fallback ?? false) && session('user_id')))
    <a href="{{ $importAllowed ? route('imports.create', ['section' => $section]) : route('imports.index') }}" class="csv-import-link"
       @if($importAllowed) onclick="const dialog = document.getElementById('{{ $importDialogId }}'); if (dialog &amp;&amp; typeof dialog.showModal === 'function') { event.preventDefault(); dialog.showModal(); }" @endif
       style="display:inline-flex;align-items:center;justify-content:center;gap:.5rem;min-height:2.75rem;padding:.625rem 1rem;border:1px solid #cbd5e1;border-radius:.625rem;background:#fff;color:#0f5c4b;font-size:1.0625rem;font-weight:600;line-height:1.4;text-decoration:none;white-space:nowrap;">Import</a>
    @if($importAllowed)
        @push('importDialogs')
            @include('imports._dialog', ['importSection' => $section, 'importDefinition' => app(\App\Services\CsvImportService::class)->definition($section), 'dialogId' => $importDialogId, 'dialogToken' => (string) \Illuminate\Support\Str::uuid()])
        @endpush
    @endif
@endif
