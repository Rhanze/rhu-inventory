@php
    $selectedPatient = $selectedPatient ?? null;
    $savedPatientLink = (bool) ($record->public_user_id ?? null);
    $unlinkPatient = $savedPatientLink && (bool) old('unlink_patient');
    $recipientName = old('patient_name', $unlinkPatient ? '' : ($record->patient_name ?? ''));
@endphp

<div data-dispensing-patient-lookup="{{ route('dispensing.patient_lookup') }}" data-saved-link="{{ $savedPatientLink ? 'true' : 'false' }}"
     style="display:grid;gap:8px;">
    <label for="dispensing-patient-query" style="font-weight:600;color:#0f172a;">Registered patient (optional)</label>
    <p id="dispensing-patient-help" style="margin:0;color:#475569;">Select the patient's record to include this dispensing in their history.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <input id="dispensing-patient-query" type="search" autocomplete="off" minlength="2" maxlength="80" aria-describedby="dispensing-patient-help"
               placeholder="Search by patient name" style="flex:1 1 14rem;min-width:0;padding:10px 14px;border:2px solid #bec6cf;border-radius:14px;color:#0f172a;">
        <button type="button" data-patient-search style="padding:10px 14px;border:1px solid #0f5c4b;border-radius:12px;color:#0f5c4b;font-weight:600;">Search patients</button>
    </div>
    <p data-patient-status role="status" style="margin:0;color:#475569;"></p>
    <div data-patient-results style="display:grid;gap:6px;"></div>
    <input type="hidden" name="public_user_id" value="{{ $selectedPatient['id'] ?? '' }}">
    <p data-patient-selection style="margin:0;font-weight:600;color:#0f5c4b;" @if(!$selectedPatient && !$savedPatientLink) hidden @endif>
        @if($unlinkPatient)
            The saved patient link will be removed on save.
        @elseif($selectedPatient)
            {{ $selectedPatient['name'] }} · Patient #{{ $selectedPatient['id'] }}@if($selectedPatient['birth_date']) · Date of birth {{ $selectedPatient['birth_date'] }}@endif
        @elseif($savedPatientLink)
            The saved patient link will be kept.
        @endif
    </p>
    @if($savedPatientLink)
        <label style="display:flex;align-items:center;gap:.45rem;">
            <input type="checkbox" name="unlink_patient" value="1" @checked($unlinkPatient)>
            Clear the saved patient link on save
        </label>
    @endif
    <button type="button" data-clear-patient hidden style="justify-self:start;text-decoration:underline;color:#0f5c4b;">Undo patient selection</button>
    @error('public_user_id')<p style="margin:0;color:#b91c1c;" role="alert">{{ $message }}</p>@enderror

    <div data-manual-recipient @if(!$unlinkPatient && ($selectedPatient || $savedPatientLink)) hidden @endif>
        <label for="dispensing-patient" style="display:block;margin-bottom:6px;font-size:1rem;font-weight:600;color:#0f172a;">Patient or recipient name (optional)</label>
        <input id="dispensing-patient" type="text" name="patient_name" maxlength="255" value="{{ $recipientName }}" @disabled(!$unlinkPatient && ($selectedPatient || $savedPatientLink))
               style="width:100%;padding:12px 16px;font-size:1rem;border:2px solid #bec6cf;border-radius:14px;background:white;color:#0f172a;box-sizing:border-box;">
        @error('patient_name')<p style="margin-top:4px;font-size:1rem;color:#dc2626;">{{ $message }}</p>@enderror
    </div>
</div>
<script src="{{ asset('js/dispensing-patient-lookup.js') }}" defer></script>
