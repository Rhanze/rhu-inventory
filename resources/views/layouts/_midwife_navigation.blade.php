<div class="mw-menu-backdrop" id="midwife-menu-backdrop" aria-hidden="true"></div>
<nav class="mw-bottom-bar" aria-label="Midwife quick navigation">
    <a href="{{ route('dashboard.midwife') }}" class="mw-bottom-btn {{ request()->routeIs('dashboard', 'dashboard.midwife') ? 'active' : '' }}" @if(request()->routeIs('dashboard', 'dashboard.midwife')) aria-current="page" @endif>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10H3Z"/><path d="M9 20v-7h6v7"/></svg><span>Home</span>
    </a>
    <a href="{{ route('dispensing.index') }}" class="mw-bottom-btn {{ request()->routeIs('dispensing.*') ? 'active' : '' }}" @if(request()->routeIs('dispensing.*')) aria-current="page" @endif>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 14 9-9a5 5 0 0 1 7 7l-9 9a5 5 0 0 1-7-7Z"/><path d="m9 10 7 7"/></svg><span>Dispense</span>
    </a>
    <a href="{{ route('immunization.tcl') }}" class="mw-bottom-btn {{ request()->routeIs('immunization.*', 'vaccination.*') ? 'active' : '' }}" @if(request()->routeIs('immunization.*', 'vaccination.*')) aria-current="page" @endif>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 3 7 7M12 5l7 7M6 11l7 7M3 21l4-4M7 17l-2-2 9-9 4 4-9 9Z"/></svg><span>Vaccines</span>
    </a>
    <a href="{{ route('public_users.index') }}" class="mw-bottom-btn {{ request()->routeIs('public_users.*', 'patients.*') ? 'active' : '' }}" @if(request()->routeIs('public_users.*', 'patients.*')) aria-current="page" @endif>
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg><span>Patients</span>
    </a>
    <button type="button" class="mw-bottom-btn" id="midwife-menu-toggle" aria-expanded="false" aria-controls="app-sidebar">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg><span>More</span>
    </button>
</nav>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('midwife-menu-toggle');
    const sidebar = document.getElementById('app-sidebar');
    const backdrop = document.getElementById('midwife-menu-backdrop');
    const close = sidebar.querySelector('[data-midwife-menu-close]');
    const mobile = window.matchMedia('(max-width: 900px)');
    const content = document.getElementById('main-content').parentElement;

    function setMenu(open, returnFocus = true) {
        const show = open && mobile.matches;
        document.body.classList.toggle('mw-menu-open', show);
        toggle.setAttribute('aria-expanded', String(show));
        content.inert = show;
        if (show) close.focus();
        else if (returnFocus && mobile.matches) toggle.focus();
    }
    toggle.addEventListener('click', () => setMenu(!document.body.classList.contains('mw-menu-open')));
    close.addEventListener('click', () => setMenu(false));
    backdrop.addEventListener('click', () => setMenu(false));
    sidebar.addEventListener('submit', () => setMenu(false, false));
    mobile.addEventListener('change', () => setMenu(false, false));
    document.addEventListener('keydown', function (event) {
        if (!document.body.classList.contains('mw-menu-open')) return;
        if (event.key === 'Escape') { event.preventDefault(); setMenu(false); }
        if (event.key !== 'Tab') return;
        const focusable = Array.from(sidebar.querySelectorAll('a[href], button, input, select, textarea, [tabindex="0"]')).filter(el => !el.disabled && el.getClientRects().length);
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });

    // Simple record lists become labelled cards; multi-level clinical matrices keep their scrollable table.
    window.RhuEnhanceRecords = function (root = document) {
    root.querySelectorAll('table.mw-mobile-records').forEach(table => {
        if (!table.tHead || table.tHead.rows.length !== 1) return;
        const headers = Array.from(table.tHead.rows[0].cells);
        if (headers.some(cell => cell.colSpan !== 1 || cell.rowSpan !== 1)) return;
        Array.from(table.tBodies).forEach(body => Array.from(body.rows).forEach(row => {
            if (row.cells.length !== headers.length || Array.from(row.cells).some(cell => cell.colSpan !== 1)) return;
            Array.from(row.cells).forEach((cell, index) => cell.dataset.label = headers[index].textContent.trim());
        }));
        table.classList.add('mw-records-enhanced');
    });
    };
    window.RhuEnhanceRecords();
});
</script>
