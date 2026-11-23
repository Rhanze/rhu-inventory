const dispensingBatchPreview = document.querySelector('[data-dispensing-batch-preview]');

if (dispensingBatchPreview) {
    const form = dispensingBatchPreview.closest('form');
    const medicine = form.elements.medicine_id;
    const quantity = form.elements.quantity_dispensed;
    const date = form.elements.date_dispensed;
    const source = form.elements.stock_source;
    const token = form.elements.lot_preview;
    const submit = form.querySelector('button[type="submit"]');
    const title = dispensingBatchPreview.querySelector('h3');
    const status = dispensingBatchPreview.querySelector('[data-batch-preview-status]');
    const list = dispensingBatchPreview.querySelector('[data-batch-preview-lots]');
    const retry = dispensingBatchPreview.querySelector('[data-batch-preview-retry]');
    let request;
    let timer;

    async function loadBatches() {
        request = new AbortController();
        const { signal } = request;
        try {
            const response = await fetch(dispensingBatchPreview.dataset.dispensingBatchPreview, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': form.elements._token.value,
                },
                body: JSON.stringify({
                    medicine_id: medicine.value,
                    quantity_dispensed: quantity.value,
                    date_dispensed: date.value,
                    stock_source: source?.value || 'rhu',
                }),
                signal,
            });
            if (response.status === 422) {
                const error = await response.json();
                throw new Error(error.message);
            }
            if (!response.ok || response.redirected) {
                throw new Error('Unable to check batches. Try again or reload the form.');
            }

            const preview = await response.json();
            if (signal.aborted) return;
            title.textContent = preview.recorded ? 'Recorded batches' : 'Batches to issue';
            for (const lot of preview.lots) {
                const item = document.createElement('li');
                const details = [lot.batch_no ? `Batch ${lot.batch_no}` : 'Unnumbered batch',
                    `${lot.quantity} ${lot.quantity === 1 ? 'unit' : 'units'}`];
                if (lot.expiration_date) details.push(`Expires ${lot.expiration_date}`);
                if (lot.storage_location) details.push(lot.storage_location);
                item.textContent = details.join(' · ');
                list.appendChild(item);
            }
            list.hidden = preview.lots.length === 0;
            status.textContent = preview.recorded
                ? (preview.lots.length ? 'This edit keeps the original issue.' : 'The original batch quantities are unavailable.')
                : 'Check the saved batch quantities before handing out stock.';
            token.value = preview.token;
            submit.disabled = false;
        } catch (error) {
            if (signal.aborted) return;
            status.textContent = error instanceof TypeError ? 'Unable to check batches. Try again.' : error.message;
            retry.hidden = false;
        }
    }

    function schedulePreview() {
        clearTimeout(timer);
        request?.abort();
        token.value = '';
        submit.disabled = true;
        list.replaceChildren();
        list.hidden = true;
        retry.hidden = true;
        title.textContent = 'Batches to issue';
        if (!medicine.value || !quantity.validity.valid || !date.validity.valid) {
            status.textContent = 'Select a medicine and enter a valid quantity and date.';
            return;
        }
        status.textContent = 'Checking available batches…';
        timer = setTimeout(loadBatches, 250);
    }

    medicine.addEventListener('change', schedulePreview);
    quantity.addEventListener('input', schedulePreview);
    date.addEventListener('input', schedulePreview);
    retry.addEventListener('click', schedulePreview);
    form.addEventListener('submit', (event) => {
        if (!token.value) {
            event.preventDefault();
        } else if (!event.defaultPrevented) {
            submit.disabled = true;
        }
    });
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) schedulePreview();
    });
    schedulePreview();
}
