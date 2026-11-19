(() => {
    'use strict';
    const pagePath = location.pathname;
    let request = null;
    let sequence = 0;

    async function update(url, {replace = false, position = null, submitter = null, feedback = null} = {}) {
        request?.abort();
        request = new AbortController();
        const current = ++sequence;
        const main = document.getElementById('main-content');
        const previousPosition = position || window.RhuNavigation.capturePosition();
        main.setAttribute('aria-busy', 'true');
        if (submitter) submitter.disabled = true;
        try {
            const response = await fetch(url.href, {
                signal: request.signal, credentials: 'same-origin',
                headers: {'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest'}
            });
            const finalUrl = new URL(response.url || url.href);
            if (!response.ok || finalUrl.origin !== location.origin || finalUrl.pathname !== pagePath
                || !response.headers.get('content-type')?.includes('text/html')) throw new Error('Full navigation required');
            const html = new DOMParser().parseFromString(await response.text(), 'text/html');
            const replacement = html.getElementById('main-content');
            if (!replacement?.querySelector('.disease-stack')) throw new Error('Report content missing');
            if (current !== sequence) return;

            if (!replace) {
                history.replaceState({...history.state, rhuScroll: previousPosition}, '');
                finalUrl.hash = url.hash;
                history.pushState({rhuDisease: true}, '', finalUrl.href);
            }
            main.querySelectorAll('canvas').forEach(canvas => window.Chart?.getChart(canvas)?.destroy());
            main.replaceChildren(...Array.from(replacement.childNodes, node => document.importNode(node, true)));
            document.title = html.title;

            // Re-run only scripts belonging to this page; the header, sidebar, and their handlers stay mounted.
            for (const script of Array.from(main.querySelectorAll('script'))) {
                // Keep embedded chart JSON as data, including the ID used by chart initializers.
                if (script.type && !['text/javascript', 'application/javascript', 'module'].includes(script.type)) continue;
                if (script.src && window.Chart && new URL(script.src).pathname.endsWith('/lib/chartjs/chart.umd.js')) continue;
                const executable = document.createElement('script');
                if (script.type) executable.type = script.type;
                if (script.src) {
                    executable.src = script.src;
                    const loaded = new Promise((resolve, reject) => {
                        executable.onload = resolve;
                        executable.onerror = reject;
                    });
                    script.replaceWith(executable);
                    await loaded;
                } else {
                    executable.textContent = script.textContent;
                    script.replaceWith(executable);
                }
            }
            if (current !== sequence) return;
            window.RhuEnhanceRecords?.(main);
            if (!replace && window.RhuFeedback?.notify(feedback, main)) return;
            const alert = main.querySelector('[role="alert"]');
            if (alert && !main.querySelector('dialog[open]')) {
                alert.scrollIntoView({block: 'center', behavior: 'instant'});
            } else if (!window.RhuNavigation.goToHash(location.hash)) {
                window.RhuNavigation.restorePosition(previousPosition);
            }
        } catch (error) {
            if (error.name !== 'AbortError' && current === sequence) location.assign(url.href);
        } finally {
            if (current === sequence) main.removeAttribute('aria-busy');
            if (submitter?.isConnected) submitter.disabled = false;
        }
    }

    document.addEventListener('submit', event => {
        const form = event.target;
        if (event.defaultPrevented || form.method.toLowerCase() !== 'get' || form.target || !form.closest('#main-content')) return;
        const url = new URL(form.action || location.href, location.href);
        if (url.origin !== location.origin || url.pathname !== pagePath) return;
        event.preventDefault();
        url.search = '';
        new FormData(form).forEach((value, key) => url.searchParams.append(key, value));
        if (event.submitter?.name) url.searchParams.append(event.submitter.name, event.submitter.value);
        update(url, {submitter: event.submitter, feedback: window.RhuFeedback?.actionFrom(event.submitter) || window.RhuFeedback?.actionFrom(form)});
    });

    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (event.defaultPrevented || !link || event.button !== 0 || event.ctrlKey || event.metaKey
            || event.shiftKey || event.altKey || link.target || link.hasAttribute('download') || !link.closest('#main-content')) return;
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || url.pathname !== pagePath) return;
        event.preventDefault();
        if (link.matches('.disease-view-link[aria-current="page"]')) return;
        if (url.search === location.search && url.hash && document.getElementById(decodeURIComponent(url.hash.slice(1)))) {
            history.pushState({...history.state}, '', url.href);
            window.RhuNavigation.goToHash(url.hash);
            return;
        }
        update(url, {feedback: window.RhuFeedback?.actionFrom(link)});
    });

    window.addEventListener('popstate', event => {
        if (location.pathname !== pagePath) return;
        update(new URL(location.href), {replace: true, position: event.state?.rhuScroll || {
            path: location.origin + pagePath, x: 0, y: 0, details: []
        }});
    });
})();
