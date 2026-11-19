(() => {
    'use strict';
    const headings = new Map();
    let sequence = 0;

    function closeActions(state, restoreFocus = false) {
        state.open = false;
        state.header.classList.remove('is-actions-open');
        if (state.actions && state.compactActions) state.actions.hidden = true;
        state.toggle?.setAttribute('aria-expanded', 'false');
        if (restoreFocus && state.toggle && !state.toggle.hidden) state.toggle.focus();
    }

    function resize(state) {
        const {header, main, nav, actions, toggle} = state;
        if (!header.isConnected || header.clientWidth === 0) return;
        // Measure the original controls, not the compact selector or open panel.
        header.classList.remove('is-compact-nav', 'is-compact-actions', 'is-narrow-heading');
        if (actions) actions.hidden = false;
        if (toggle) toggle.hidden = true;
        const style = getComputedStyle(header);
        const gap = parseFloat(style.columnGap) || 12;
        const width = header.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
        const titleMinimum = Math.min(240, Math.max(90, width * .25));
        const navWidth = nav ? nav.scrollWidth : 0;
        const actionWidth = actions ? actions.scrollWidth : 0;
        const mainGap = nav ? (parseFloat(getComputedStyle(main).columnGap) || gap) : 0;
        state.compactActions = Boolean(actions && titleMinimum + mainGap + navWidth + gap + actionWidth > width);
        const narrow = width < 440;
        const controlsWidth = actions ? (state.compactActions ? (narrow ? 44 : 116) : actionWidth) + gap : 0;
        const compactNav = Boolean(nav && titleMinimum + mainGap + navWidth + controlsWidth > width);
        header.classList.toggle('is-compact-nav', compactNav);
        header.classList.toggle('is-compact-actions', state.compactActions);
        header.classList.toggle('is-narrow-heading', narrow);
        if (toggle) toggle.hidden = !state.compactActions;
        if (!state.compactActions) closeActions(state);
        if (actions) actions.hidden = state.compactActions && !state.open;
    }

    function initialize(header) {
        if (headings.has(header)) return;
        const main = header.querySelector('.page-heading-main');
        if (!main) return;
        const nav = main.querySelector('.page-section-nav');
        const actions = Array.from(header.children).find(node => node.classList.contains('page-heading-actions'));
        const state = {header, main, nav, actions, toggle: null, open: false, compactActions: false};
        if (nav) {
            const select = document.createElement('select');
            select.className = 'page-section-select';
            select.setAttribute('aria-label', nav.getAttribute('aria-label') || 'Page sections');
            Array.from(nav.children).filter(node => node.tagName === 'A').forEach(link => {
                const option = document.createElement('option');
                option.value = link.href;
                option.textContent = link.textContent.trim().replace(/\s+/g, ' ');
                option.selected = link.getAttribute('aria-current') === 'page' || link.classList.contains('is-active');
                select.appendChild(option);
            });
            select.addEventListener('change', () => {
                const destination = new URL(select.value, location.href);
                if (destination.origin === location.origin) location.assign(destination.href);
            });
            nav.appendChild(select);
        }
        header.querySelectorAll('.page-heading-title :is(h1, h2, h3)').forEach(title => {
            if (!title.hasAttribute('title')) title.title = title.textContent.trim().replace(/\s+/g, ' ');
        });
        if (actions && actions.children.length) {
            actions.id ||= 'page-heading-actions-' + (++sequence);
            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'page-heading-actions-toggle';
            toggle.hidden = true;
            toggle.setAttribute('aria-label', 'Page actions');
            toggle.setAttribute('aria-controls', actions.id);
            toggle.setAttribute('aria-expanded', 'false');
            toggle.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg><span>Actions</span>';
            toggle.addEventListener('click', () => {
                const open = !state.open;
                headings.forEach(other => closeActions(other));
                state.open = open;
                actions.hidden = !open;
                header.classList.toggle('is-actions-open', open);
                toggle.setAttribute('aria-expanded', String(open));
            });
            header.insertBefore(toggle, actions);
            state.toggle = toggle;
        } else {
            state.actions = null;
        }
        headings.set(header, state);
        header.classList.add('heading-enhanced');
        resize(state);
        observer?.observe(header);
    }

    const observer = typeof ResizeObserver === 'function'
        ? new ResizeObserver(entries => entries.forEach(entry => {
            const state = headings.get(entry.target);
            if (state) resize(state);
        })) : null;
    function refresh() {
        headings.forEach((state, header) => {
            if (!header.isConnected) { observer?.unobserve(header); headings.delete(header); }
        });
        document.querySelectorAll('.page-heading').forEach(initialize);
        headings.forEach(resize);
    }
    function start() {
        refresh();
        const main = document.getElementById('main-content');
        if (main && typeof MutationObserver === 'function') {
            new MutationObserver(records => {
                if (records.some(record => Array.from(record.addedNodes).some(node => node.nodeType === 1
                    && (node.matches('.page-heading') || node.querySelector('.page-heading'))))) refresh();
            }).observe(main, {childList: true, subtree: true});
        }
        document.fonts?.ready.then(refresh);
    }
    document.addEventListener('click', event => headings.forEach(state => {
        if (state.open && !state.header.contains(event.target)) closeActions(state);
    }));
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        headings.forEach(state => {
            if (state.open && state.header.contains(document.activeElement)) { closeActions(state, true); event.preventDefault(); }
        });
    });
    document.addEventListener('focusin', event => headings.forEach(state => {
        if (state.open && !state.header.contains(event.target)) closeActions(state);
    }));
    window.addEventListener('resize', () => headings.forEach(resize));
    window.RhuPageHeadings = {refresh};
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once: true});
    else start();
})();
