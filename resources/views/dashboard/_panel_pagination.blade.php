<script>
document.addEventListener('DOMContentLoaded', () => {
    const pageKeys = {
        'dashboard-low-stock': 'low_stock_page',
        'dashboard-near-expiry': 'near_expiry_page',
        'dashboard-stock-activity': 'stock_logs_page',
        'dashboard-dispensing': 'dispensing_page'
    };
    const pending = new Set();
    const pageState = new URL(window.location.href);

    document.addEventListener('click', async (event) => {
        const link = event.target.closest('.dashboard-panel-pagination a');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
        const panel = link.closest('[data-dashboard-panel]');
        const key = panel && pageKeys[panel.id];
        if (!key) return;
        const target = new URL(link.href, window.location.href);
        if (target.origin !== window.location.origin || target.pathname !== pageState.pathname || !target.searchParams.has(key)) return;
        event.preventDefault();
        if (pending.has(panel.id)) return;

        // Use only this card's page parameter; other links may predate another card's update.
        const url = new URL(pageState);
        url.searchParams.set(key, target.searchParams.get(key));
        url.hash = '';
        const status = panel.querySelector('[data-pagination-status]');
        const focused = panel.contains(document.activeElement);
        pending.add(panel.id);
        panel.setAttribute('aria-busy', 'true');
        status.classList.remove('dashboard-pagination-error');
        status.textContent = 'Loading page…';
        const height = panel.getBoundingClientRect().height;
        try {
            const response = await fetch(url.href, {
                credentials: 'same-origin',
                headers: {'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest'}
            });
            if (!response.ok || response.redirected) throw new Error('Dashboard request failed.');
            const html = new DOMParser().parseFromString(await response.text(), 'text/html');
            const replacement = html.getElementById(panel.id);
            if (!replacement || !replacement.hasAttribute('data-dashboard-panel')) throw new Error('Dashboard panel missing.');

            // Keep the dashboard and its charts mounted. Stable card height avoids scroll jumps.
            panel.style.boxSizing = 'border-box';
            panel.style.minHeight = height + 'px';
            panel.replaceChildren(...replacement.childNodes);
            pageState.searchParams.set(key, url.searchParams.get(key));
            pageState.hash = '';
            window.history.replaceState(window.history.state, '', pageState.href);
            panel.querySelector('[data-pagination-status]').textContent = 'Page ' + url.searchParams.get(key) + ' loaded.';
            if (focused) {
                const current = panel.querySelector('[aria-current="page"]');
                if (current) {
                    current.setAttribute('tabindex', '-1');
                    current.focus({preventScroll: true});
                }
            }
        } catch (error) {
            status.textContent = 'Page could not be loaded. Please try again, or refresh the dashboard.';
            status.classList.add('dashboard-pagination-error');
        } finally {
            pending.delete(panel.id);
            panel.removeAttribute('aria-busy');
        }
    });
});
</script>
<style>
    .dashboard-pagination-status { position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap; }
    .dashboard-pagination-status.dashboard-pagination-error { position:static;width:auto;height:auto;margin:8px 0 0;overflow:visible;clip:auto;white-space:normal;color:#991b1b;font-size:13px; }
    [data-dashboard-panel][aria-busy="true"] .dashboard-panel-pagination { cursor:progress; }
</style>
