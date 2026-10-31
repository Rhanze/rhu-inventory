@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

@php
    $headerEyebrow = $isRhuAdmin ? 'Your RHU Network'      : 'Provincial Health Office';
    $headerTitle   = $isRhuAdmin ? 'GIS Map — Partner RHUs' : 'GIS Map — Rural Health Units';
    $headerSub     = $isRhuAdmin
        ? 'Your RHU and other health units you could request stock from.'
        : 'Location and stock overview for every RHU in the province.';
@endphp

<div style="display:flex; flex-direction:column; gap:20px; padding-bottom:2rem;">

    {{-- Header --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(12px);
        border-radius: 20px; padding: 24px 28px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
    ">
        <p style="font-size:13px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#42ad8a; margin:0 0 6px 0;">
            {{ $headerEyebrow }}
        </p>
        <h2 style="font-size:24px; font-weight:700; color:#0f172a; margin:0;">{{ $headerTitle }}</h2>
        <p style="font-size:14px; color:#475569; margin:6px 0 0 0;">{{ $headerSub }}</p>
    </div>

    {{-- RHU Admin: Set location card --}}
    @if($isRhuAdmin && $ownRhu)
        <div id="set-location-card" style="
            background: {{ $ownRhuHasCoords ? 'rgba(255,255,255,0.9)' : '#FEF3C7' }};
            backdrop-filter: blur(8px);
            border-radius: 20px; padding: 20px 24px;
            border: 1px solid {{ $ownRhuHasCoords ? 'rgba(255,255,255,0.4)' : '#FCD34D' }};
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 16px;
        ">
            <div style="min-width:0; flex:1;">
                @if($ownRhuHasCoords)
                    <p style="font-size:13px; font-weight:700; text-transform:uppercase; color:#42ad8a; letter-spacing:0.06em; margin:0 0 4px 0;">
                        Your RHU Location
                    </p>
                    <p style="font-size:15px; font-weight:600; color:#0f172a; margin:0 0 4px 0;">
                        {{ $ownRhu->name }} is on the map
                    </p>
                    <p style="font-size:13px; color:#64748b; margin:0;">
                        Coordinates: {{ number_format((float) $ownRhu->latitude, 5) }},
                        {{ number_format((float) $ownRhu->longitude, 5) }}
                    </p>
                @else
                    <p style="font-size:13px; font-weight:700; text-transform:uppercase; color:#92400E; letter-spacing:0.06em; margin:0 0 4px 0;">
                        ⚠ Location Not Set
                    </p>
                    <p style="font-size:15px; font-weight:600; color:#78350F; margin:0 0 4px 0;">
                        Your RHU isn't on the map yet
                    </p>
                    <p style="font-size:13px; color:#78350F; margin:0;">
                        Help others find you — drop a pin at your RHU's exact location.
                    </p>
                @endif
            </div>

            <button type="button" id="set-location-btn" onclick="enterPinMode()" style="
                display:inline-flex; align-items:center; gap:8px;
                font-size:15px; font-weight:600; padding:12px 24px;
                border-radius:12px;
                background: {{ $ownRhuHasCoords ? '#ffffff' : '#42ad8a' }};
                color: {{ $ownRhuHasCoords ? '#334155' : '#ffffff' }};
                border: 1px solid {{ $ownRhuHasCoords ? '#e2e8f0' : '#42ad8a' }};
                cursor: pointer; transition: all 0.2s; white-space: nowrap;
            " onmouseover="if('{{ $ownRhuHasCoords ? '1' : '0' }}'==='0'){this.style.background='#369f7d'}else{this.style.background='#f8fafc';this.style.borderColor='#42ad8a';}"
               onmouseout="if('{{ $ownRhuHasCoords ? '1' : '0' }}'==='0'){this.style.background='#42ad8a'}else{this.style.background='#ffffff';this.style.borderColor='#e2e8f0';}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7z"/>
                    <circle cx="12" cy="9" r="2.5"/>
                </svg>
                {{ $ownRhuHasCoords ? 'Update Location' : 'Set Location on Map' }}
            </button>
        </div>
    @endif

    {{-- Pin mode instruction banner --}}
    <div id="pin-banner" style="
        display: none;
        background: #DBEAFE; border: 2px solid #3b82f6; border-radius: 16px;
        padding: 18px 24px;
        box-shadow: 0 8px 20px rgba(59,130,246,0.15);
        align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 14px;
    ">
        <div style="display:flex; align-items:center; gap:14px; min-width:0; flex:1;">
            <div style="width:40px; height:40px; border-radius:10px; background:#3b82f6; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7z"/>
                    <circle cx="12" cy="9" r="2.5"/>
                </svg>
            </div>
            <div>
                <p style="font-size:15px; font-weight:700; color:#1e3a8a; margin:0 0 2px 0;">
                    Click anywhere on the map — inside Antique
                </p>
                <p style="font-size:13px; color:#1e40af; margin:0;">
                    Drop a pin at your RHU's exact location. Locations outside the province are not allowed.
                </p>
            </div>
        </div>
        <button type="button" onclick="exitPinMode()" style="
            font-size:14px; font-weight:600; padding:9px 18px;
            border-radius:10px; background:#ffffff; color:#334155;
            border: 1px solid #e2e8f0; cursor: pointer;
            transition: background 0.2s; white-space: nowrap;
        " onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#ffffff'">
            Cancel
        </button>
    </div>

    {{-- Confirm card --}}
    <div id="confirm-card" style="
        display: none;
        background: #DCFCE7; border: 2px solid #86EFAC; border-radius: 16px;
        padding: 20px 24px;
        box-shadow: 0 8px 20px rgba(22,101,52,0.12);
        align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 14px;
    ">
        <div style="min-width:0; flex:1;">
            <p style="font-size:13px; font-weight:700; text-transform:uppercase; color:#166534; letter-spacing:0.06em; margin:0 0 6px 0;">
                ✓ New Location Selected
            </p>
            <p style="font-size:14px; color:#14532d; margin:0; font-family: monospace;">
                Latitude: <strong id="selected-lat">—</strong> &nbsp;&middot;&nbsp;
                Longitude: <strong id="selected-lng">—</strong>
            </p>
        </div>
        <div style="display:flex; gap:10px;">
            <button type="button" onclick="cancelSelection()" style="
                font-size:14px; font-weight:600; padding:10px 20px;
                border-radius:10px; background:#ffffff; color:#334155;
                border: 1px solid #e2e8f0; cursor: pointer;
            " onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#ffffff'">
                Cancel
            </button>
            <button type="button" id="save-location-btn" onclick="saveLocation()" style="
                font-size:14px; font-weight:700; padding:10px 24px;
                border-radius:10px; background:#16a34a; color:#ffffff;
                border: none; cursor: pointer;
                display: inline-flex; align-items: center; gap: 8px;
            " onmouseover="this.style.background='#15803d'" onmouseout="this.style.background='#16a34a'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                <span id="save-btn-label">Save Location</span>
            </button>
        </div>
    </div>

    {{-- Map --}}
    <div style="
        background: rgba(255,255,255,0.9);
        border-radius: 20px;
        padding: 0;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        overflow: hidden;
    ">
        <div id="map" style="height:560px; width:100%;"></div>
    </div>

    {{-- Legend --}}
    <div style="
        background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);
        border-radius: 16px; padding: 16px 22px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        display:flex; align-items:center; gap:24px; flex-wrap:wrap;
    ">
        <span style="font-size:12px; font-weight:700; text-transform:uppercase; color:#475569; letter-spacing:0.06em;">Legend</span>

        @if($isRhuAdmin)
            <span style="display:inline-flex; align-items:center; gap:8px; font-size:13px; color:#334155;">
                <span style="width:16px; height:16px; border-radius:50%; background:#42ad8a; border:3px solid #facc15; box-shadow:0 0 0 1px #facc15;"></span>
                <strong>Your RHU</strong>
            </span>
        @endif

        <span style="display:inline-flex; align-items:center; gap:8px; font-size:13px; color:#334155;">
            <span style="width:14px; height:14px; border-radius:50%; background:#42ad8a; border:2px solid white; box-shadow:0 0 0 1px #42ad8a;"></span>
            Healthy stock
        </span>
        <span style="display:inline-flex; align-items:center; gap:8px; font-size:13px; color:#334155;">
            <span style="width:14px; height:14px; border-radius:50%; background:#facc15; border:2px solid white; box-shadow:0 0 0 1px #facc15;"></span>
            Low stock
        </span>
        <span style="display:inline-flex; align-items:center; gap:8px; font-size:13px; color:#334155;">
            <span style="width:14px; height:14px; border-radius:50%; background:#dc2626; border:2px solid white; box-shadow:0 0 0 1px #dc2626;"></span>
            Critical
        </span>
        <span style="display:inline-flex; align-items:center; gap:8px; font-size:13px; color:#334155;">
            <span style="width:14px; height:14px; border-radius:50%; background:#94a3b8; border:2px solid white; box-shadow:0 0 0 1px #94a3b8;"></span>
            No coordinates
        </span>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
// ─── Program categories injected from server ───
const PROGRAM_CATEGORIES = @json($categories);

// ─── State ───
let map;
let pinMode        = false;
let tempMarker     = null;
let selectedLat    = null;
let selectedLng    = null;

const OWN_RHU_ID = {{ $isRhuAdmin && $ownRhu ? (int) $ownRhu->id : 'null' }};
const CSRF_TOKEN = '{{ csrf_token() }}';

// ─── Antique province bounds ───
const ANTIQUE_BOUNDS = {
    minLat: 10.38,
    maxLat: 11.95,
    minLng: 121.40,
    maxLng: 122.30,
};

function isInsideAntique(lat, lng) {
    return lat >= ANTIQUE_BOUNDS.minLat
        && lat <= ANTIQUE_BOUNDS.maxLat
        && lng >= ANTIQUE_BOUNDS.minLng
        && lng <= ANTIQUE_BOUNDS.maxLng;
}

// ─── Init map ───
document.addEventListener('DOMContentLoaded', function () {
    map = L.map('map').setView([11.0, 122.0], 10);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    loadMarkers();

    map.on('click', function (e) {
        if (!pinMode) return;
        placeTempPin(e.latlng.lat, e.latlng.lng);
    });
});

// ─── Load RHU markers ───
function loadMarkers() {
    fetch('{{ route("gis.data") }}', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            const rhus     = data.rhus || [];
            const bounds   = [];
            let ownMarker  = null;

            rhus.forEach(rhu => {
                if (!rhu.has_coords) return;

                const totalStock = rhu.total_stock || 0;
                const isOwn      = !!rhu.is_own_rhu;

                let color = '#dc2626';
                if (totalStock > 100) color = '#42ad8a';
                else if (totalStock >= 30) color = '#facc15';

                const marker = L.circleMarker([rhu.latitude, rhu.longitude], {
                    radius:      isOwn ? 16 : 12,
                    fillColor:   isOwn ? '#42ad8a' : color,
                    color:       isOwn ? '#facc15' : '#ffffff',
                    weight:      isOwn ? 4 : 3,
                    opacity:     1,
                    fillOpacity: 0.9
                }).addTo(map);

                if (isOwn) ownMarker = marker;

                const ownBadge = isOwn
                    ? `<span style="display:inline-block; padding:2px 8px; font-size:10px; font-weight:700; background:#facc15; color:#78350F; border-radius:4px; margin-left:6px;">YOUR RHU</span>`
                    : '';

                const typeOptions = Object.entries(PROGRAM_CATEGORIES)
                    .map(([key, label]) => `<option value="${key}">${label}</option>`)
                    .join('');

                const requestButton = isOwn ? '' : `
                    <button type="button"
                            onclick="goToTransfer(${rhu.id})"
                            style="
                                width:100%; margin-top:10px; height:38px;
                                font-size:13px; font-weight:700;
                                background:#42ad8a; color:white;
                                border:none; border-radius:8px; cursor:pointer;
                                transition:background 0.2s;
                                display:inline-flex; align-items:center; justify-content:center; gap:6px;
                            "
                            onmouseover="this.style.background='#369f7d'"
                            onmouseout="this.style.background='#42ad8a'">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Make Request
                    </button>
                `;

                const popupHtml = `
                    <div style="min-width:280px; font-family:Arial, sans-serif;">
                        <p style="font-size:15px; font-weight:700; color:#0f172a; margin:0 0 4px 0;">
                            ${rhu.name}${ownBadge}
                        </p>
                        <p style="font-size:12px; color:#64748b; margin:0 0 12px 0;">
                            ${rhu.municipality || '—'}
                        </p>

                        <div style="border-top:1px solid #e2e8f0; padding-top:12px;">
                            <label style="display:block; font-size:10px; font-weight:700; text-transform:uppercase; color:#475569; letter-spacing:0.06em; margin-bottom:5px;">
                                Program Category
                            </label>
                            <select id="popup-type-${rhu.id}"
                                    onchange="fetchCategoryCount(${rhu.id}, this.value)"
                                    style="
                                        width:100%; height:34px; padding:0 10px; font-size:13px;
                                        border:2px solid #bec6cf; border-radius:8px; background:white;
                                        color:#0f172a; appearance:auto; box-sizing:border-box; outline:none;
                                    ">
                                <option value="">— Select a category —</option>
                                ${typeOptions}
                            </select>

                            <div id="popup-result-${rhu.id}" style="margin-top:10px; display:none;"></div>

                            ${requestButton}
                        </div>
                    </div>
                `;

                marker.bindPopup(popupHtml, { maxWidth: 340, minWidth: 300 });
                bounds.push([rhu.latitude, rhu.longitude]);
            });

            if (ownMarker) {
                map.setView(ownMarker.getLatLng(), 11);
                ownMarker.openPopup();
            } else if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [50, 50], maxZoom: 12 });
            }
        })
        .catch(err => console.error('GIS data fetch failed:', err));
}

// ─── Fetch category count for one RHU ───
function fetchCategoryCount(rhuId, category) {
    const resultEl = document.getElementById('popup-result-' + rhuId);
    if (!resultEl) return;

    if (!category) {
        resultEl.style.display = 'none';
        resultEl.innerHTML = '';
        return;
    }

    resultEl.style.display = 'block';
    resultEl.innerHTML = `
        <div style="padding:10px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; text-align:center;">
            <p style="font-size:12px; color:#64748b; margin:0;">Loading...</p>
        </div>
    `;

    fetch(`{{ route('gis.category_count') }}?rhu_id=${rhuId}&category=${encodeURIComponent(category)}`, {
        headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        const types = data.medicine_types ?? 0;
        const stock = data.total_stock ?? 0;

        if (types === 0) {
            resultEl.innerHTML = `
                <div style="padding:10px 12px; background:#FEF3C7; border:1px solid #FCD34D; border-radius:8px;">
                    <p style="font-size:12px; color:#78350F; margin:0; font-weight:600;">
                        No stock in this category
                    </p>
                </div>
            `;
            return;
        }

        resultEl.innerHTML = `
            <div style="padding:10px 12px; background:#E8F7F4; border:1px solid #42ad8a; border-radius:8px;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                    <div>
                        <p style="font-size:10px; font-weight:700; text-transform:uppercase; color:#0f5c4b; letter-spacing:0.06em; margin:0 0 2px 0;">
                            Medicines
                        </p>
                        <p style="font-size:18px; font-weight:700; color:#0f5c4b; margin:0; line-height:1;">
                            ${types}
                        </p>
                    </div>
                    <div>
                        <p style="font-size:10px; font-weight:700; text-transform:uppercase; color:#0f5c4b; letter-spacing:0.06em; margin:0 0 2px 0;">
                            Total Stock
                        </p>
                        <p style="font-size:18px; font-weight:700; color:#0f5c4b; margin:0; line-height:1;">
                            ${stock}
                        </p>
                    </div>
                </div>
            </div>
        `;
    })
    .catch(err => {
        console.error('Category count error:', err);
        resultEl.innerHTML = `
            <div style="padding:10px 12px; background:#FEE2E2; border:1px solid #FCA5A5; border-radius:8px;">
                <p style="font-size:12px; color:#991B1B; margin:0; font-weight:600;">
                    Could not load count
                </p>
            </div>
        `;
    });
}

// ─── Go to transfer page with RHU prefilled ───
function goToTransfer(rhuId) {
    if (OWN_RHU_ID !== null && parseInt(rhuId) === OWN_RHU_ID) {
        showToast('❌ You cannot request stock from your own RHU.', 'error');
        return;
    }

    const typeSelect = document.getElementById('popup-type-' + rhuId);
    const type = typeSelect ? typeSelect.value : '';

    let url = '{{ route("transfers.create") }}?to_rhu=' + encodeURIComponent(rhuId);
    if (type) {
        url += '&category=' + encodeURIComponent(type);
    }

    window.location.href = url;
}

// ─── Pin mode ───
function enterPinMode() {
    pinMode = true;
    document.getElementById('pin-banner').style.display   = 'flex';
    document.getElementById('confirm-card').style.display = 'none';
    document.getElementById('set-location-card').style.display = 'none';
    map.getContainer().style.cursor = 'crosshair';
    map.closePopup();

    if (!window.antiqueBoundsLayer) {
        window.antiqueBoundsLayer = L.rectangle(
            [
                [ANTIQUE_BOUNDS.minLat, ANTIQUE_BOUNDS.minLng],
                [ANTIQUE_BOUNDS.maxLat, ANTIQUE_BOUNDS.maxLng],
            ],
            {
                color: '#42ad8a',
                weight: 2,
                fill: true,
                fillColor: '#42ad8a',
                fillOpacity: 0.06,
                dashArray: '6,6',
                interactive: false,
            }
        ).addTo(map);
    }
}

function exitPinMode() {
    pinMode = false;
    document.getElementById('pin-banner').style.display   = 'none';
    document.getElementById('confirm-card').style.display = 'none';
    document.getElementById('set-location-card').style.display = 'flex';
    map.getContainer().style.cursor = '';

    if (tempMarker) {
        map.removeLayer(tempMarker);
        tempMarker = null;
    }

    if (window.antiqueBoundsLayer) {
        map.removeLayer(window.antiqueBoundsLayer);
        window.antiqueBoundsLayer = null;
    }

    selectedLat = null;
    selectedLng = null;
}

function placeTempPin(lat, lng) {
    if (!isInsideAntique(lat, lng)) {
        showToast('❌ That location is outside Antique province. Please click inside the dashed boundary.', 'error');
        return;
    }

    selectedLat = lat;
    selectedLng = lng;

    if (tempMarker) map.removeLayer(tempMarker);

    tempMarker = L.circleMarker([lat, lng], {
        radius:      16,
        fillColor:   '#16a34a',
        color:       '#ffffff',
        weight:      4,
        opacity:     1,
        fillOpacity: 0.95
    }).addTo(map).bindPopup('New location — click Save below').openPopup();

    document.getElementById('selected-lat').textContent = lat.toFixed(5);
    document.getElementById('selected-lng').textContent = lng.toFixed(5);

    document.getElementById('pin-banner').style.display   = 'none';
    document.getElementById('confirm-card').style.display = 'flex';
}

function cancelSelection() {
    if (tempMarker) {
        map.removeLayer(tempMarker);
        tempMarker = null;
    }
    selectedLat = null;
    selectedLng = null;

    document.getElementById('confirm-card').style.display = 'none';
    document.getElementById('pin-banner').style.display   = 'flex';
}

// ─── Save location ───
function saveLocation() {
    if (selectedLat === null || selectedLng === null) {
        alert('Pick a location on the map first.');
        return;
    }

    if (!isInsideAntique(selectedLat, selectedLng)) {
        showToast('❌ The selected coordinates are outside Antique province.', 'error');
        return;
    }

    if (!OWN_RHU_ID) {
        alert('Could not determine your RHU id. Please log in again.');
        return;
    }

    const btn   = document.getElementById('save-location-btn');
    const label = document.getElementById('save-btn-label');

    btn.disabled = true;
    label.textContent = 'Saving...';

    fetch(`/gis/coordinates/${OWN_RHU_ID}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept':       'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN,
        },
        body: JSON.stringify({
            latitude:  selectedLat,
            longitude: selectedLng,
        })
    })
    .then(async r => {
        const text = await r.text();
        let data = null;
        try { data = JSON.parse(text); } catch (e) { /* not JSON */ }

        if (!r.ok) {
            let detail;
            if (data) {
                if (data.message)      detail = data.message;
                else if (data.error)   detail = data.error;
                else if (data.errors)  detail = JSON.stringify(data.errors, null, 2);
                else                   detail = JSON.stringify(data);
            } else {
                detail = text ? text.substring(0, 400) : '(empty response body)';
            }
            throw new Error(`HTTP ${r.status} ${r.statusText}\n\n${detail}`);
        }

        return data;
    })
    .then(data => {
        label.textContent = 'Saved ✓';
        setTimeout(() => {
            window.location.reload();
        }, 400);
    })
    .catch(err => {
        console.error('Save location error:', err);
        alert('Could not save location:\n\n' + err.message);
        btn.disabled = false;
        label.textContent = 'Save Location';
    });
}

// ─── Toast helper ───
function showToast(message, type = 'info') {
    const colors = {
        error:   { bg: '#FEE2E2', border: '#FCA5A5', color: '#991B1B' },
        info:    { bg: '#DBEAFE', border: '#93C5FD', color: '#1E40AF' },
        success: { bg: '#DCFCE7', border: '#86EFAC', color: '#166534' },
    };
    const c = colors[type] || colors.info;

    const el = document.createElement('div');
    el.style.cssText = `
        position: fixed; top: 20px; right: 20px; z-index: 99999;
        background: ${c.bg}; border: 2px solid ${c.border}; color: ${c.color};
        padding: 14px 20px; border-radius: 12px;
        font-size: 14px; font-weight: 600;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        max-width: 420px; line-height: 1.4;
        opacity: 0; transition: opacity 0.2s ease;
    `;
    el.textContent = message;
    document.body.appendChild(el);

    setTimeout(() => { el.style.opacity = '1'; }, 10);

    setTimeout(() => {
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 300);
    }, 3500);
}
</script>
@endsection