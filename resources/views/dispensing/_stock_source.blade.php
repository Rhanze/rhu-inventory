@if(session('role') === 'midwife' || (isset($record) && $record->stock_source === 'allocation'))
    <div>
        <label for="stock_source" style="display:block;margin-bottom:6px;font-size:1rem;font-weight:600;color:#0f172a;">Stock source</label>
        <select id="stock_source" name="stock_source" style="width:100%;padding:12px 16px;font-size:1rem;border:2px solid #bec6cf;border-radius:14px;background:white;color:#0f172a;">
            <option value="rhu" @selected(old('stock_source', $record->stock_source ?? 'rhu') === 'rhu')>Shared RHU stock</option>
            <option value="allocation" @selected(old('stock_source', $record->stock_source ?? 'rhu') === 'allocation')>{{ session('role') === 'midwife' ? 'My received supplies' : "Original recipient's received supplies" }}</option>
        </select>
        <p style="margin:6px 0 0;font-size:1rem;color:#64748b;">Choose received supplies when dispensing medicines already allocated to you. Only new tracked allocations with confirmed receipt are available here.</p>
        <p style="margin:6px 0 0;font-size:1rem;color:#475569;">Received supplies reduce only the balance held by the recipient. Shared RHU stock stays unchanged because it was deducted when the supplies were issued.</p>
        @error('stock_source')<p style="margin-top:4px;font-size:1rem;color:#dc2626;">{{ $message }}</p>@enderror
    </div>
@endif
