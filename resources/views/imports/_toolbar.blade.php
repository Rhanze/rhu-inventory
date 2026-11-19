@if(app(\App\Services\CsvImportService::class)->canImport($section) || (($fallback ?? false) && session('user_id')))
    <div class="no-print" style="display:flex;justify-content:flex-end;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem;">
        @include('imports._link', ['section' => $section, 'fallback' => $fallback ?? false])
    </div>
@endif
