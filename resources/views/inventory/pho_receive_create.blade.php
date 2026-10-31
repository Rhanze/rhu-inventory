@extends('layouts.app')

@php
    $medicinesJson = $medicines->map(function ($m) {
        return [
            'id'          => $m->id,
            'name'        => $m->medicine_name,
            'unit'        => $m->unit,
            'dosage'      => $m->dosage,
            'dosage_form' => $m->dosage_form,
        ];
    })->values();

    $today = now()->toDateString();
@endphp

@section('content')
<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    <style>
        .form-card input, .form-card select, .form-card textarea {
            width:100%; padding:12px 16px; font-size:17px;
            border:2px solid #bec6cf; border-radius:12px;
            background:white; color:#0f172a; box-sizing:border-box; outline:none;
        }
        .form-card input:focus, .form-card select:focus, .form-card textarea:focus {
            border-color:#42ad8a; box-shadow:0 0 0 3px rgba(66,173,138,0.1);
        }
        .form-card label {
            display:block; font-size:16px; font-weight:600; color:#0f172a; margin-bottom:6px;
        }
        .items-table { width:100%; border-collapse:collapse; font-size:16px; table-layout:fixed; }
        .items-table thead th {
            background:#f8fafc; border-bottom:1px solid #e2e8f0;
            font-size:14px; font-weight:600; text-transform:uppercase; letter-spacing:.06em;
            color:#475569; padding:14px 12px; text-align:left; white-space:nowrap;
        }
        .items-table thead th.right { text-align:right; }
        .items-table tbody td {
            padding:8px 10px; border-bottom:1px solid #f1f5f9; vertical-align:top;
        }
        .items-table tbody td input {
            padding:10px 12px; font-size:16px; border-width:1px;
        }
        .med-cell { position:relative; }
        .med-suggestions {
            display:none; position:absolute; top:100%; left:0; right:0;
            background:#fff; border:2px solid #42ad8a; border-radius:10px;
            box-shadow:0 10px 24px rgba(0,0,0,0.12);
            max-height:260px; overflow-y:auto; z-index:9999;
        }
        .med-suggestions .sug { padding:12px 16px; cursor:pointer; border-bottom:1px solid #f1f5f9; }
        .med-suggestions .sug:last-child { border-bottom:none; }
        .med-suggestions .sug:hover { background:#E8F7F4; }
        .med-suggestions .sug-name { font-weight:600; font-size:16px; color:#0f172a; }
        .med-suggestions .sug-meta { font-size:14px; color:#64748b; margin-top:2px; }
        .items-table tbody td input.expiry.invalid {
            border-color:#dc2626 !important;
            background:#fef2f2;
        }
    </style>

    {{-- Header --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius:20px; padding:24px 28px;
        border:1px solid rgba(255,255,255,0.4);
        box-shadow:0 8px 20px rgba(0,0,0,0.06);
        display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
        <div>
            <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">
                Provincial Health Office
            </p>
            <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">
                New DOH Receive Record
            </h2>
        </div>
        <a href="{{ route('inventory.pho_receive') }}" style="
            padding:10px 18px; border-radius:10px; background:white; color:#334155;
            border:1px solid #e2e8f0; font-size:16px; font-weight:600; text-decoration:none;">
            ← Back to Receive Records
        </a>
    </div>

    @if($errors->any())
        <div style="background:#fcebeb; border:1px solid #f7c1c1; border-radius:12px; padding:14px 20px; color:#a32d2d; font-size:17px;">
            <strong>Please fix the following:</strong>
            <ul style="margin:6px 0 0 0; padding-left:20px;">
                @foreach($errors->all() as $err) <li>{{ $err }}</li> @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('inventory.pho_receive.store') }}" id="shipment-form">
        @csrf

        {{-- Header section --}}
        <div class="form-card" style="
            background:rgba(255,255,255,0.95); border-radius:20px;
            border:1px solid rgba(66,173,138,0.15); box-shadow:0 8px 20px rgba(0,0,0,0.06);
            padding:28px; margin-bottom:20px;">
            <p style="font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0 0 16px 0;">
                Receive Details
            </p>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label>Shipment No.</label>
                    <input type="text" name="shipment_no"
                           value="{{ old('shipment_no', $nextShipmentNo) }}"
                           placeholder="e.g. DOH-2026-0001">
                </div>
                <div>
                    <label>DOH Reference No.</label>
                    <input type="text" name="reference_no"
                           value="{{ old('reference_no') }}"
                           placeholder="e.g. DR-2026-0123">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label>Supplier</label>
                    <input type="text" name="supplier"
                           value="{{ old('supplier', 'Department of Health') }}"
                           placeholder="Department of Health">
                </div>
                <div>
                    <label>Received Date <span style="color:#dc2626;">*</span></label>
                    <input type="date" name="received_date" required
                           value="{{ old('received_date', $today) }}">
                </div>
            </div>

            <div>
                <label>Remarks</label>
                <textarea name="remarks" rows="2"
                          placeholder="Optional notes about this receive record...">{{ old('remarks') }}</textarea>
            </div>
        </div>

        {{-- Items section --}}
        <div class="form-card" style="
            background:rgba(255,255,255,0.95); border-radius:20px;
            border:1px solid rgba(66,173,138,0.15); box-shadow:0 8px 20px rgba(0,0,0,0.06);
            padding:28px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
                <p style="font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#42ad8a; margin:0;">
                    Received Items
                </p>
                <button type="button" onclick="addRow()"
                        style="padding:9px 16px; border-radius:8px; background:#E8F7F4; color:#0f5c4b; border:1px solid #42ad8a; font-size:15px; font-weight:600; cursor:pointer;">
                    + Add Item
                </button>
            </div>

            <table class="items-table">
                <colgroup>
                    <col style="width:44px;">
                    <col>
                    <col style="width:180px;">
                    <col style="width:190px;">
                    <col style="width:130px;">
                    <col style="width:50px;">
                </colgroup>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Medicine / Item</th>
                        <th>Batch No.</th>
                        <th>Expiry <span style="color:#dc2626;">*</span></th>
                        <th class="right">Quantity <span style="color:#dc2626;">*</span></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="items-tbody"></tbody>
            </table>

            <p id="no-items-msg" style="font-size:16px; color:#64748b; text-align:center; padding:24px 0; margin:0;">
                No items yet. Click <strong>+ Add Item</strong> to begin.
            </p>
        </div>

        {{-- Buttons --}}
        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
            <a href="{{ route('inventory.pho_receive') }}" style="
                padding:10px 20px; border-radius:10px; background:white; color:#334155;
                border:1px solid #e2e8f0; font-size:16px; font-weight:600; text-decoration:none;">
                Cancel
            </a>
            <button type="submit" style="
                padding:10px 20px; border-radius:10px; background:#42ad8a; color:white;
                font-size:16px; font-weight:600; border:none; cursor:pointer;">
                Save Receive Record
            </button>
        </div>
    </form>
</div>

<template id="row-template">
    <tr class="item-row">
        <td style="text-align:center; color:#94a3b8; font-weight:600;"><span class="row-num">1</span></td>
        <td class="med-cell">
            <input type="text" class="med-search" autocomplete="off" placeholder="Type medicine name...">
            <input type="hidden" class="med-id" name="" required>
            <div class="med-suggestions"></div>
        </td>
        <td><input type="text" class="batch-no" name="" placeholder="Batch"></td>
        <td><input type="date" class="expiry" name="" min="{{ $today }}"></td>
        <td><input type="number" class="qty" name="" min="1" value="1" style="text-align:right;"></td>
        <td style="text-align:center;">
            <button type="button" class="remove-btn"
                    style="background:transparent; border:none; color:#cbd5e1; cursor:pointer; font-size:18px; padding:4px;"
                    title="Remove line">✕</button>
        </td>
    </tr>
</template>

<script>
const ALL_MEDICINES = @json($medicinesJson);
const TODAY_ISO    = "{{ $today }}";

let rowSeq = 0;

function capitalize(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }
function escapeHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function addRow() {
    const tpl  = document.getElementById('row-template');
    const body = document.getElementById('items-tbody');
    const row  = tpl.content.firstElementChild.cloneNode(true);

    const id = ++rowSeq;
    row.dataset.rowId = id;
    row.querySelector('.med-id').name   = `items[${id}][medicine_id]`;
    row.querySelector('.batch-no').name = `items[${id}][batch_no]`;
    row.querySelector('.expiry').name   = `items[${id}][expiration_date]`;
    row.querySelector('.qty').name      = `items[${id}][quantity]`;

    body.appendChild(row);
    document.getElementById('no-items-msg').style.display = 'none';
    bindRow(row);
    renumber();
    row.querySelector('.med-search').focus();
}

function removeRow(row) {
    row.remove();
    renumber();
    if (!document.querySelectorAll('#items-tbody .item-row').length) {
        document.getElementById('no-items-msg').style.display = 'block';
    }
}

function renumber() {
    document.querySelectorAll('#items-tbody .item-row').forEach((r, i) => {
        r.querySelector('.row-num').textContent = i + 1;
    });
}

function bindRow(row) {
    const input    = row.querySelector('.med-search');
    const hidden   = row.querySelector('.med-id');
    const box      = row.querySelector('.med-suggestions');
    const expiry   = row.querySelector('.expiry');
    let suggestions = [];

    input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        hidden.value = '';
        if (q.length < 1) { box.style.display = 'none'; return; }

        suggestions = ALL_MEDICINES
            .filter(m => (m.name || '').toLowerCase().includes(q))
            .sort((a, b) => {
                const aS = a.name.toLowerCase().startsWith(q) ? 0 : 1;
                const bS = b.name.toLowerCase().startsWith(q) ? 0 : 1;
                return aS !== bS ? aS - bS : a.name.localeCompare(b.name);
            })
            .slice(0, 10);

        if (!suggestions.length) {
            box.innerHTML = `<div style="padding:12px 16px; font-size:15px; color:#94a3b8;">No match</div>`;
            box.style.display = 'block';
            return;
        }

        box.innerHTML = suggestions.map((m, i) => {
            const meta = [m.dosage, m.dosage_form].filter(Boolean).join(' · ');
            const unit = m.unit ? `per ${capitalize(m.unit)}` : '';
            return `
                <div class="sug" data-i="${i}">
                    <div class="sug-name">${escapeHtml(m.name)}</div>
                    <div class="sug-meta">${escapeHtml(meta)} ${unit ? '· ' + escapeHtml(unit) : ''}</div>
                </div>`;
        }).join('');
        box.style.display = 'block';

        box.querySelectorAll('.sug').forEach(el => {
            el.addEventListener('mousedown', (e) => {
                e.preventDefault();
                const m = suggestions[parseInt(el.dataset.i, 10)];
                if (!m) return;
                input.value = m.name;
                hidden.value = m.id;
                box.style.display = 'none';
                expiry.focus();
            });
        });
    });

    input.addEventListener('blur', () => {
        setTimeout(() => { box.style.display = 'none'; }, 200);
    });

    expiry.addEventListener('change', () => {
        if (expiry.value && expiry.value < TODAY_ISO) {
            expiry.classList.add('invalid');
        } else {
            expiry.classList.remove('invalid');
        }
    });

    row.querySelector('.remove-btn').addEventListener('click', () => removeRow(row));
}

document.getElementById('shipment-form').addEventListener('submit', function (e) {
    const rows = document.querySelectorAll('#items-tbody .item-row');

    if (!rows.length) {
        e.preventDefault();
        alert('Add at least one item before saving.');
        return;
    }

    for (let i = 0; i < rows.length; i++) {
        const row      = rows[i];
        const line     = i + 1;
        const expiryEl = row.querySelector('.expiry');
        const expiry   = expiryEl.value;

        if (!row.querySelector('.med-id').value) {
            e.preventDefault();
            row.querySelector('.med-search').focus();
            alert(`Line ${line}: Please type a medicine and click it from the dropdown.`);
            return;
        }

        if (!expiry) {
            e.preventDefault();
            expiryEl.focus();
            alert(`Line ${line}: Please enter an expiration date.`);
            return;
        }

        if (expiry < TODAY_ISO) {
            e.preventDefault();
            expiryEl.classList.add('invalid');
            expiryEl.focus();
            alert(`Line ${line}: Expiration date cannot be in the past (earliest allowed is ${TODAY_ISO}).`);
            return;
        }

        const qty = parseInt(row.querySelector('.qty').value || 0, 10);
        if (qty < 1) {
            e.preventDefault();
            row.querySelector('.qty').focus();
            alert(`Line ${line}: Quantity must be at least 1.`);
            return;
        }
    }
});

document.addEventListener('DOMContentLoaded', addRow);
</script>
@endsection