<section data-dispensing-batch-preview="{{ isset($record) ? route('dispensing.preview_edit', $record->id) : route('dispensing.preview') }}"
         aria-labelledby="dispensing-batch-preview-title"
         style="padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;">
    <h3 id="dispensing-batch-preview-title" style="margin:0 0 6px;font-size:1rem;font-weight:700;color:#0f172a;">Batches to issue</h3>
    <p data-batch-preview-status role="status" style="margin:0;color:#475569;">Loading the batch preview. Reload the page if the preview does not appear.</p>
    <ol data-batch-preview-lots hidden style="margin:6px 0 0;padding-left:22px;list-style:decimal;color:#0f172a;"></ol>
    <button type="button" data-batch-preview-retry hidden style="margin-top:8px;font-weight:600;color:#0f766e;">Check batches again</button>
    <input type="hidden" name="lot_preview">
    <noscript>Enable JavaScript to check the batches and save this record.</noscript>
</section>
<script src="{{ asset('js/dispensing-stock-preview.js') }}" defer></script>
