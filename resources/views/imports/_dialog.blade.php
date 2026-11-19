@once
<style>
    .csv-import-dialog { position:fixed;inset:0;margin:auto;box-sizing:border-box;width:calc(100% - 40px);max-width:520px;height:fit-content;max-height:calc(100dvh - 40px);padding:0;border:0;border-radius:20px;color:#0f172a;background:#fff;box-shadow:0 24px 60px rgba(0,0,0,.25);font:inherit;font-size:1.0625rem;line-height:1.5;overflow:auto; }
    .csv-import-dialog::backdrop { background:rgba(15,23,42,.55);backdrop-filter:blur(4px); }
    .csv-import-dialog header { padding:22px 26px 14px;border-bottom:1px solid #f1f5f9; }
    .csv-import-dialog h3 { font-size:1.1875rem;font-weight:700;margin:0 0 4px;color:#0f172a; }
    .csv-import-dialog p { color:#64748b;margin:0; }
    .csv-import-dialog .csv-import-body { padding:20px 26px; }
    .csv-import-dialog label { display:block;font-weight:700;margin-bottom:8px; }
    .csv-import-dialog input[type=file] { font:inherit;width:100%;max-width:100%;box-sizing:border-box;padding:10px;border:2px solid #bec6cf;border-radius:10px; }
    .csv-import-dialog footer { padding:16px 26px 20px;border-top:1px solid #f1f5f9;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;background:#fafbfc; }
    .csv-import-dialog footer a,.csv-import-dialog footer button { display:inline-flex;align-items:center;justify-content:center;font:inherit;font-weight:600;padding:9px 16px;border:1px solid #cbd5e1;border-radius:10px;color:#334155;background:#fff;text-decoration:none;cursor:pointer; }
    .csv-import-dialog footer .csv-import-template { background:#E8F7F4;border-color:#42ad8a;color:#0f5c4b; }
    .csv-import-dialog footer .csv-import-submit { background:#42ad8a;border-color:#42ad8a;color:#fff; }
    .csv-import-dialog :is(a,input,button):focus-visible { outline:3px solid #0f766e;outline-offset:3px; }
</style>
@endonce
<dialog id="{{ $dialogId }}" class="csv-import-dialog" aria-labelledby="{{ $dialogId }}-title" onclick="if(event.target === this) this.close()">
    <header>
        <h3 id="{{ $dialogId }}-title">Import {{ $importDefinition['title'] }}</h3>
        <p>Choose a CSV file to import.</p>
    </header>
    <form action="{{ route('imports.store', ['section' => $importSection]) }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="import_token" value="{{ $dialogToken }}">
        <input type="hidden" name="import_dialog" value="1">
        <div class="csv-import-body">
            <label for="{{ $dialogId }}-file">CSV file</label>
            <input id="{{ $dialogId }}-file" type="file" name="file" accept=".csv,.txt,text/csv" required aria-describedby="{{ $dialogId }}-help">
            <p id="{{ $dialogId }}-help" style="margin-top:8px;">CSV with a header row. Maximum {{ \App\Services\CsvImportService::MAX_ROWS }} rows and 4 MB.</p>
        </div>
        <footer>
            <button type="button" onclick="document.getElementById('{{ $dialogId }}').close()">Cancel</button>
            <a class="csv-import-template" href="{{ route('imports.template', ['section' => $importSection]) }}">Download template</a>
            <button type="submit" class="csv-import-submit">Import</button>
        </footer>
    </form>
</dialog>
