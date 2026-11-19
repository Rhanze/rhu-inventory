(() => {
    'use strict';

    const pendingKey = 'rhu:scroll:pending';
    const pagePath = location.origin + location.pathname;
    if ('scrollRestoration' in history) history.scrollRestoration = 'auto';

    function readPending() {
        try {
            const value = JSON.parse(sessionStorage.getItem(pendingKey) || 'null');
            sessionStorage.removeItem(pendingKey);
            return value && Date.now() - value.savedAt < 120000 ? value : null;
        } catch { return null; }
    }

    function snapshot(source) {
        const anchor = source?.closest('[data-scroll-anchor], section[id], details[id], tr[id], [data-dashboard-panel]');
        return {
            path: pagePath, x: window.scrollX, y: window.scrollY, savedAt: Date.now(),
            anchor: anchor?.id || null, offset: anchor?.getBoundingClientRect().top ?? 0,
            details: Array.from(document.querySelectorAll('#main-content details[id][open]'), item => item.id)
        };
    }

    function remember(source) {
        try { sessionStorage.setItem(pendingKey, JSON.stringify(snapshot(source))); } catch {}
    }

    function rememberForm(form) {
        if (!form.closest('#main-content')) return;
        const destination = new URL(form.action || location.href, location.href);
        if (destination.origin !== location.origin || form.target || form.method === 'dialog') return;
        if (form.method.toLowerCase() !== 'get' || destination.pathname === location.pathname) remember(form);
    }

    document.addEventListener('submit', event => {
        // Wait for validation, confirmation and AJAX handlers to decide whether navigation will occur.
        queueMicrotask(() => {
            if (!event.defaultPrevented) rememberForm(event.target);
        });
    });

    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
            || link.target || link.hasAttribute('download') || !link.closest('#main-content')) return;
        const destination = new URL(link.href, location.href);
        if (destination.origin !== location.origin || destination.pathname !== location.pathname || destination.hash) return;
        queueMicrotask(() => {
            if (!event.defaultPrevented) remember(link);
        });
    });

    window.addEventListener('pagehide', () => {
        // Store state on this history entry so Back and Reload can restore expanded sections as well.
        const current = history.state && typeof history.state === 'object' ? history.state : {};
        history.replaceState({...current, rhuScroll: snapshot()}, '');
    });

    function expandParents(element) {
        for (let parent = element?.parentElement; parent; parent = parent.parentElement) {
            if (parent.tagName === 'DETAILS') parent.open = true;
        }
    }

    function goToHash(hash = location.hash) {
        let target = null;
        try { target = document.getElementById(decodeURIComponent(hash.slice(1))); } catch {}
        if (target) {
            if (target.tagName === 'DETAILS') target.open = true;
            expandParents(target);
            requestAnimationFrame(() => {
                if (document.querySelector('dialog[open]')) return;
                if (target.tagName === 'TR') {
                    target.querySelectorAll('td, th').forEach(cell => cell.style.backgroundColor = '#e5e7eb');
                }
                const top = target.getBoundingClientRect().top + window.scrollY - 100;
                window.scrollTo({left: 0, top: Math.max(0, top), behavior: 'instant'});
            });
            return true;
        }
        return false;
    }

    function restorePosition(position) {
        if (!position || position.path !== pagePath) return;
        (position.details || []).forEach(id => {
            const details = document.getElementById(id);
            if (details?.tagName === 'DETAILS') details.open = true;
        });
        requestAnimationFrame(() => requestAnimationFrame(() => {
            if (document.querySelector('dialog[open]')) return;
            const anchor = position.anchor && document.getElementById(position.anchor);
            if (anchor) expandParents(anchor);
            const top = anchor ? anchor.getBoundingClientRect().top + window.scrollY - position.offset : position.y;
            window.scrollTo({left: position.x, top: Math.max(0, top), behavior: 'instant'});
        }));
    }

    window.RhuNavigation = {remember, rememberForm, capturePosition: snapshot, restorePosition, goToHash};
    let restored = false;
    function restoreInitialPosition() {
        if (restored) return;
        restored = true;
        const pending = readPending();
        if (goToHash()) return;
        const type = performance.getEntriesByType('navigation')[0]?.type;
        restorePosition(pending?.path === pagePath ? pending
            : (type === 'reload' || type === 'back_forward' ? history.state?.rhuScroll : null));
    }
    document.addEventListener('DOMContentLoaded', restoreInitialPosition);
    window.addEventListener('load', restoreInitialPosition);
})();
