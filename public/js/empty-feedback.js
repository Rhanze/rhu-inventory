(() => {
    'use strict';
    const pendingKey = 'rhu:empty-feedback:pending';
    const dialog = document.getElementById('empty-result-alert');
    if (!dialog) return;
    let returnFocus = null;
    let scrollPosition = null;

    function show(message) {
        if (document.querySelector('dialog[open]')) return false;
        returnFocus = document.activeElement;
        scrollPosition = {left: window.scrollX, top: window.scrollY, behavior: 'instant'};
        document.getElementById('empty-result-message').textContent = message;
        dialog.showModal();
        return true;
    }

    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const rect = dialog.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
    });
    dialog.addEventListener('close', () => {
        if (returnFocus?.isConnected) returnFocus.focus({preventScroll: true});
        if (scrollPosition) window.scrollTo(scrollPosition);
        returnFocus = null;
        scrollPosition = null;
    });

    function actionFrom(element) {
        const key = element?.dataset.emptyAction;
        if (!key || !/^[a-z0-9_-]+$/i.test(key)) return null;
        return {key, message: element.dataset.emptyMessage || null};
    }

    function notify(action, root = document.getElementById('main-content')) {
        if (!action || !/^[a-z0-9_-]+$/i.test(action.key || '') || !root || root.querySelector('[role="alert"]') || document.querySelector('dialog[open]')) return false;
        const result = root.querySelector('[data-empty-result="' + action.key + '"]');
        const count = result?.dataset.emptyCount;
        if (typeof count !== 'string' || count.trim() === '' || Number(count) !== 0) return false;
        return show(action.message || result.dataset.emptyMessage || 'No existing history yet.');
    }

    function canonical(url) {
        const destination = new URL(url, location.href);
        destination.hash = '';
        destination.searchParams.sort();
        return destination.href;
    }

    function remember(action, url) {
        try {
            sessionStorage.setItem(pendingKey, JSON.stringify({action, destination: canonical(url), expiresAt: Date.now() + 120000}));
        } catch {}
    }

    document.addEventListener('submit', event => {
        const form = event.target;
        const action = actionFrom(event.submitter) || actionFrom(form);
        if (!action || form.method.toLowerCase() !== 'get' || form.target) return;
        const url = new URL(form.action || location.href, location.href);
        if (url.origin !== location.origin) return;
        url.search = '';
        new FormData(form).forEach((value, key) => url.searchParams.append(key, value));
        if (event.submitter?.name) url.searchParams.append(event.submitter.name, event.submitter.value);
        queueMicrotask(() => { if (!event.defaultPrevented) remember(action, url.href); });
    });

    document.addEventListener('click', event => {
        if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const guard = event.target.closest('[data-empty-guard]');
        const closingDetails = guard?.tagName === 'SUMMARY' && guard.parentElement?.open;
        if (guard && !closingDetails && !guard.target && guard.dataset.emptyCount?.trim() !== '' && Number(guard.dataset.emptyCount) === 0) {
            event.preventDefault();
            show(guard.dataset.emptyMessage || 'No existing history yet.');
            return;
        }
        const link = event.target.closest('a[data-empty-action]');
        const action = actionFrom(link);
        if (!action || link.target || link.hasAttribute('download')) return;
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin) return;
        queueMicrotask(() => { if (!event.defaultPrevented) remember(action, url.href); });
    });

    document.addEventListener('DOMContentLoaded', () => {
        let pending = null;
        try {
            pending = JSON.parse(sessionStorage.getItem(pendingKey) || 'null');
            sessionStorage.removeItem(pendingKey);
        } catch {}
        if (!pending || !Number.isFinite(pending.expiresAt) || pending.expiresAt <= Date.now() || pending.destination !== canonical(location.href)) return;
        requestAnimationFrame(() => requestAnimationFrame(() => notify(pending.action)));
    });

    window.RhuFeedback = {actionFrom, notify, show};
})();
