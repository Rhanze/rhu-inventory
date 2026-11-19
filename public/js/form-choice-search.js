(() => {
    'use strict';

    const controls = new Map();
    const normalize = text => text.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
    const limit = 12;
    let nextId = 0;
    let openControl;
    let invalidControlFocused = false;

    function isSearchableSelect(select) {
        return !select.multiple && select.size <= 1
            && !select.closest('[data-native-select]')
            && !select.matches('[data-consumption-search], #consumption-chart-medicine');
    }

    function enhance(source) {
        if (controls.has(source)) return;
        const select = source.matches('select') ? source : null;
        const optionSource = select || source.list;
        if (!optionSource || (select && !isSearchableSelect(select))) return;

        const id = 'record-choice-' + ++nextId;
        const field = document.createElement('div');
        field.className = 'record-choice';
        const input = select ? document.createElement('input') : source;
        input.type = 'search';
        input.id ||= id;
        input.classList.add('record-choice-input');
        input.placeholder = source.getAttribute('data-search-placeholder') || input.placeholder || 'Type to search';
        input.autocomplete = 'off';
        input.spellcheck = false;
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', id + '-suggestions');

        const list = document.createElement('div');
        list.id = id + '-suggestions';
        list.className = 'record-choice-list';
        list.hidden = true;
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-label', 'Recommendations');
        const usesPopover = typeof list.showPopover === 'function';
        if (usesPopover) list.setAttribute('popover', 'manual');
        const message = document.createElement('p');
        source.insertAdjacentElement('afterend', field);
        field.append(input, list);
        if (select) {
            const labels = Array.from(select.labels || []);
            ['aria-label', 'aria-labelledby', 'aria-describedby', 'aria-invalid'].forEach(attribute => {
                if (select.hasAttribute(attribute)) input.setAttribute(attribute, select.getAttribute(attribute));
            });
            if (!labels.length && !input.hasAttribute('aria-label') && !input.hasAttribute('aria-labelledby')) {
                const label = (select.name || select.id || '').replace(/[-_\[\]]+/g, ' ').trim();
                input.setAttribute('aria-label', label || select.title || 'Search choices');
            }
            ['width', 'minWidth', 'maxWidth', 'margin', 'flex'].forEach(property => {
                if (select.style[property]) field.style[property] = select.style[property];
            });
            message.id = id + '-message';
            message.className = 'record-choice-message';
            message.setAttribute('role', 'status');
            message.hidden = true;
            input.setAttribute('aria-describedby', [input.getAttribute('aria-describedby'), message.id].filter(Boolean).join(' '));
            field.append(message);
            labels.forEach(label => { label.htmlFor = input.id; });
            select.classList.add('record-choice-native');
            select.tabIndex = -1;
            select.setAttribute('aria-hidden', 'true');
        } else {
            input.removeAttribute('list');
        }

        let matches = [];
        let active = -1;
        let unconfirmed = false;
        const optionLabel = option => option.label || option.value;
        const selectedLabel = () => {
            if (!select || (select.required && !select.value)) return '';
            const option = select.options[select.selectedIndex];
            return option ? optionLabel(option) : '';
        };

        function position() {
            if (!usesPopover || list.hidden) return;
            const rect = input.getBoundingClientRect();
            if (rect.width === 0 || rect.height === 0) { close(); return; }
            const viewportTop = window.visualViewport?.offsetTop ?? 0;
            const viewportBottom = viewportTop + (window.visualViewport?.height ?? window.innerHeight);
            const below = viewportBottom - rect.bottom - 8;
            const above = rect.top - viewportTop - 8;
            const placeAbove = below < 180 && above > below;
            list.style.left = rect.left + 'px';
            list.style.width = rect.width + 'px';
            list.style.top = placeAbove ? 'auto' : (rect.bottom + 4) + 'px';
            list.style.bottom = placeAbove ? (window.innerHeight - rect.top + 4) + 'px' : 'auto';
            list.style.maxHeight = Math.min(290, Math.max(72, placeAbove ? above : below)) + 'px';
        }

        function close() {
            if (usesPopover && list.matches(':popover-open')) list.hidePopover();
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            active = -1;
            unconfirmed = false;
            if (select) input.value = selectedLabel();
            if (openControl === control) openControl = undefined;
        }

        function sync() {
            if (!select) return;
            const style = getComputedStyle(select);
            field.hidden = select.hidden || style.display === 'none' || style.visibility === 'hidden';
            input.disabled = select.matches(':disabled');
            input.readOnly = style.pointerEvents === 'none';
            input.setAttribute('aria-required', String(select.required));
            if (field.hidden || input.disabled || input.readOnly) close();
            if (list.hidden) input.value = selectedLabel();
            if (select.validity.valid && !message.hidden) {
                message.hidden = true;
                input.removeAttribute('aria-invalid');
            }
        }

        function choose(option) {
            if (!available(option)) return;
            if (select) select.selectedIndex = option.index;
            else input.value = option.value;
            input.removeAttribute('aria-invalid');
            notifyChange();
            close();
        }

        function notifyChange() {
            source.dispatchEvent(new Event('input', {bubbles: true}));
            source.dispatchEvent(new Event('change', {bubbles: true}));
        }

        function available(option) {
            const group = option.closest('optgroup');
            return option.closest(select ? 'select' : 'datalist') === optionSource && !option.disabled && !option.hidden
                && option.style.display !== 'none' && !group?.disabled && !group?.hidden
                && (option.value !== '' || (select && !select.required));
        }

        function show(text) {
            if (field.hidden || input.disabled || input.readOnly) return;
            if (openControl && openControl !== control) openControl.close();
            const words = normalize(text.trim()).split(/\s+/).filter(Boolean);
            const found = Array.from(optionSource.options).filter(option => {
                if (!available(option)) return false;
                const label = normalize(optionLabel(option));
                return words.every(word => label.includes(word));
            });
            matches = found.slice(0, limit);
            active = -1;
            input.removeAttribute('aria-activedescendant');
            list.replaceChildren();
            matches.forEach((option, index) => {
                const row = document.createElement('div');
                row.id = list.id + '-' + index;
                row.setAttribute('role', 'option');
                row.setAttribute('aria-selected', 'false');
                row.textContent = optionLabel(option) || 'None';
                row.addEventListener('pointerdown', event => event.preventDefault());
                row.addEventListener('click', () => choose(option));
                list.append(row);
            });
            if (!matches.length || found.length > matches.length) {
                const note = document.createElement('div');
                note.className = 'record-choice-note';
                note.setAttribute('role', 'status');
                const empty = source.getAttribute('data-search-empty');
                note.textContent = matches.length ? 'Keep typing to narrow the choices.'
                    : (empty || (words.length ? 'No matches. Try another word.' : 'No choices available.'));
                list.append(note);
            }
            list.hidden = false;
            if (usesPopover && !list.matches(':popover-open')) list.showPopover();
            input.setAttribute('aria-expanded', 'true');
            openControl = control;
            position();
        }

        function highlight(index) {
            active = index;
            Array.from(list.querySelectorAll('[role="option"]')).forEach((row, position) => {
                row.setAttribute('aria-selected', String(position === index));
                if (position === index) {
                    input.setAttribute('aria-activedescendant', row.id);
                    row.scrollIntoView({block: 'nearest'});
                }
            });
        }

        const control = {sync, close, position};
        controls.set(source, control);
        sync();
        input.addEventListener('focus', () => { sync(); input.select(); show(''); });
        input.addEventListener('click', () => {
            if (list.hidden) { input.select(); show(''); }
        });
        input.addEventListener('input', () => {
            unconfirmed = Boolean(select?.required && select.value && input.value !== selectedLabel());
            show(input.value);
        });
        input.addEventListener('blur', () => {
            if (unconfirmed) {
                select.value = '';
                notifyChange();
            }
            close();
        });
        input.addEventListener('keydown', event => {
            if (input.readOnly || event.isComposing) return;
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (list.hidden) show('');
                if (!matches.length) return;
                const step = event.key === 'ArrowDown' ? 1 : -1;
                highlight(active < 0 ? (step === 1 ? 0 : matches.length - 1)
                    : (active + step + matches.length) % matches.length);
            } else if (!list.hidden && event.key === 'Enter') {
                event.preventDefault();
                if (matches.length) choose(matches[Math.max(0, active)]);
            } else if (!list.hidden && event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                close();
            }
        });
        select?.addEventListener('change', () => { close(); sync(); });
        select?.addEventListener('invalid', event => {
            if (field.hidden || input.disabled || input.readOnly) return;
            event.preventDefault();
            message.textContent = select.validationMessage || 'Choose an option.';
            message.hidden = false;
            input.setAttribute('aria-invalid', 'true');
            if (!invalidControlFocused) {
                invalidControlFocused = true;
                input.focus();
                queueMicrotask(() => { invalidControlFocused = false; });
            }
        });
        new MutationObserver(() => {
            sync();
            if (!list.hidden) show(input.value === selectedLabel() ? '' : input.value);
        }).observe(optionSource, { childList: true, subtree: true, attributes: true, characterData: true,
            attributeFilter: ['disabled', 'hidden', 'selected', 'value', 'label', 'required', 'style', 'class'] });
    }

    const selector = 'select, input[data-choice-search]';
    function scan(node) {
        if (node.matches?.(selector)) enhance(node);
        node.querySelectorAll?.(selector).forEach(enhance);
    }

    let syncRequested = false;
    function syncAfterInteraction() {
        if (syncRequested) return;
        syncRequested = true;
        requestAnimationFrame(() => {
            syncRequested = false;
            controls.forEach((control, source) => {
                if (source.isConnected) control.sync();
                else {
                    control.close();
                    controls.delete(source);
                }
            });
            openControl?.position();
        });
    }

    const root = document.body;
    if (!root) return;
    scan(root);
    new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(scan)))
        .observe(root, {childList: true, subtree: true});
    ['click', 'change'].forEach(event => root.addEventListener(event, syncAfterInteraction, true));
    root.addEventListener('reset', event => {
        controls.forEach((control, source) => {
            if (source.form === event.target) control.close();
        });
        syncAfterInteraction();
    }, true);
    window.addEventListener('pageshow', syncAfterInteraction);
    window.addEventListener('resize', () => openControl?.position());
    window.addEventListener('scroll', () => openControl?.position(), true);
    window.visualViewport?.addEventListener('resize', () => openControl?.position());
    window.visualViewport?.addEventListener('scroll', () => openControl?.position());
})();
