const patientLookup = document.querySelector('[data-dispensing-patient-lookup]');

if (patientLookup) {
    const query = patientLookup.querySelector('input[type="search"]');
    const search = patientLookup.querySelector('[data-patient-search]');
    const status = patientLookup.querySelector('[data-patient-status]');
    const results = patientLookup.querySelector('[data-patient-results]');
    const selection = patientLookup.querySelector('[data-patient-selection]');
    const patientId = patientLookup.querySelector('[name="public_user_id"]');
    const manualRecipient = patientLookup.querySelector('[data-manual-recipient]');
    const manualName = manualRecipient.querySelector('[name="patient_name"]');
    const undo = patientLookup.querySelector('[data-clear-patient]');
    const unlink = patientLookup.querySelector('[name="unlink_patient"]');
    const csrf = patientLookup.closest('form').querySelector('[name="_token"]').value;
    const initialId = patientId.value;
    const initialLabel = selection.textContent.trim();
    const savedLink = patientLookup.dataset.savedLink === 'true';
    let activeRequest;

    function setSelection(id, label) {
        patientId.value = id;
        selection.textContent = label;
        selection.hidden = !label;
        manualRecipient.hidden = manualName.disabled = Boolean(id || (savedLink && !unlink?.checked));
        undo.hidden = id === initialId;
    }

    function clearSearch() {
        activeRequest?.abort();
        status.textContent = '';
        results.replaceChildren();
    }

    async function findPatients() {
        clearSearch();
        const name = query.value.trim();
        if (name.length < 2) {
            status.textContent = 'Enter at least two characters of the patient’s name.';
            return;
        }

        activeRequest = new AbortController();
        const request = activeRequest;
        status.textContent = 'Searching…';

        try {
            const response = await fetch(patientLookup.dataset.dispensingPatientLookup, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ query: name }),
                signal: request.signal,
            });
            if (request.signal.aborted) {
                return;
            }
            if (response.status === 422) {
                status.textContent = 'Search using letters or numbers in the patient’s name.';
                return;
            }
            if (!response.ok) {
                throw new Error('Patient lookup request failed');
            }
            const matches = await response.json();
            if (request.signal.aborted) {
                return;
            }
            status.textContent = matches.length ? 'Select the matching patient record.' : 'No matching patients.';
            for (const patient of matches) {
                const choice = document.createElement('button');
                const details = [patient.name, `Patient #${patient.id}`];
                if (patient.birth_date) {
                    details.push(`Date of birth ${patient.birth_date}`);
                }
                const label = details.join(' · ');
                choice.type = 'button';
                choice.className = 'rounded-xl border border-slate-300 bg-white px-3 py-2 text-left text-slate-900';
                choice.textContent = label;
                choice.addEventListener('click', () => {
                    if (unlink) unlink.checked = false;
                    setSelection(String(patient.id), label);
                    clearSearch();
                    status.textContent = 'Patient selected.';
                    query.value = '';
                    query.focus();
                });
                results.appendChild(choice);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                status.textContent = 'Patient lookup is unavailable. Try again.';
            }
        }
    }

    search.addEventListener('click', findPatients);
    query.addEventListener('input', clearSearch);
    query.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            findPatients();
        }
    });
    undo.addEventListener('click', () => {
        if (unlink) unlink.checked = false;
        setSelection(initialId, initialLabel);
        status.textContent = 'Patient selection restored.';
        query.focus();
    });
    unlink?.addEventListener('change', () => {
        if (unlink.checked) {
            manualName.value = '';
            setSelection('', 'The saved patient link will be removed on save.');
            manualName.focus();
        } else {
            setSelection(initialId, initialId ? initialLabel : 'The saved patient link will be kept.');
        }
    });
}
