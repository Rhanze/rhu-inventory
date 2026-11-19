@extends('layouts.app')
@section('title', 'Import')
@section('content')
<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:20px;border:1px solid #e2e8f0;box-shadow:0 8px 20px rgba(0,0,0,.06);overflow:hidden;font-size:1.0625rem;line-height:1.5;">
    <div style="padding:22px 26px 14px;border-bottom:1px solid #f1f5f9;">
        <h2 style="font-size:1.1875rem;font-weight:700;color:#0f172a;margin:0 0 4px;">Import {{ $definition['title'] }}</h2>
        <p style="color:#64748b;margin:0;">Choose a CSV file to import.</p>
    </div>
    <form action="{{ route('imports.store', ['section' => $section]) }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="import_token" value="{{ $token }}">
        <div style="padding:20px 26px;">
            @if($errors->any())
                <div role="alert" style="padding:14px;border:1px solid #fca5a5;border-radius:10px;color:#991b1b;background:#fff7f7;margin-bottom:20px;">
                    <strong>Review the import errors</strong>
                    <p>The file was not saved. Correct the listed rows and upload it again.</p>
                    <ul style="padding-left:20px;">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                </div>
            @endif
            <label for="csv-section" style="display:block;font-weight:700;color:#0f172a;margin-bottom:8px;">Section</label>
            <select id="csv-section" onchange="window.location.href=this.value" style="width:100%;max-width:100%;font:inherit;padding:10px 14px;border:2px solid #bec6cf;border-radius:10px;margin-bottom:20px;">
                @foreach($sections as $key => $available)<option value="{{ route('imports.create', ['section' => $key]) }}" @selected($key === $section)>{{ $available['title'] }}</option>@endforeach
            </select>
            <label for="csv-file" style="display:block;font-weight:700;color:#0f172a;margin-bottom:8px;">CSV file</label>
            <input id="csv-file" name="file" type="file" accept=".csv,.txt,text/csv" required aria-describedby="csv-file-help" style="width:100%;max-width:100%;box-sizing:border-box;font:inherit;padding:10px;border:2px solid #bec6cf;border-radius:10px;">
            <p id="csv-file-help" style="color:#64748b;margin:8px 0 0;">CSV with a header row. Maximum {{ \App\Services\CsvImportService::MAX_ROWS }} rows and 4 MB.</p>
        </div>
        <div style="padding:16px 26px 20px;border-top:1px solid #f1f5f9;display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end;background:#fafbfc;">
            <a href="{{ route($definition['index']) }}" style="font:inherit;font-weight:600;padding:9px 16px;border:1px solid #cbd5e1;border-radius:10px;color:#334155;background:#fff;text-decoration:none;">Cancel</a>
            <a href="{{ route('imports.template', ['section' => $section]) }}" style="font:inherit;font-weight:600;padding:9px 16px;border:1px solid #42ad8a;border-radius:10px;color:#0f5c4b;background:#E8F7F4;text-decoration:none;">Download template</a>
            <button type="submit" style="font:inherit;font-weight:600;padding:9px 18px;border:0;border-radius:10px;color:#fff;background:#42ad8a;cursor:pointer;">Import</button>
        </div>
    </form>
</div>
@endsection
