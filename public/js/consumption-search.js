(() => {
    'use strict';
    if (window.RhuConsumptionSearch) return;
    const normalize = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

    function enhance(select) {
        if (!select || select.disabled || select.dataset.consumptionSearchReady || !select.options.length) return;
        const options = Array.from(select.options).filter(option => !option.disabled && option.value !== '')
            .map(option => ({value: option.value, label: option.textContent.trim()}));
        if (!options.length) return;
        select.dataset.consumptionSearchReady = 'true';
        const wrapper = document.createElement('div');
        wrapper.className = 'consumption-search';
        const input = document.createElement('input');
        input.type = 'search';
        input.id = select.id + '-search';
        input.className = 'consumption-search-input';
        input.placeholder = select.dataset.searchPlaceholder || 'Search medicine, strength, or form';
        input.autocomplete = 'off';
        input.spellcheck = false;
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-haspopup', 'listbox');
        input.setAttribute('aria-expanded', 'false');
        const list = document.createElement('div');
        list.id = select.id + '-suggestions';
        list.className = 'consumption-search-suggestions';
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-label', 'Matching recommendations');
        list.hidden = true;
        input.setAttribute('aria-controls', list.id);
        const hint = document.createElement('p');
        hint.id = select.id + '-search-hint';
        hint.className = 'consumption-search-hint';
        hint.textContent = 'Type to search, then choose a recommendation to update the graph.';
        input.setAttribute('aria-describedby', hint.id);
        wrapper.append(input, list, hint);
        select.insertAdjacentElement('afterend', wrapper);
        document.querySelectorAll('label').forEach(label => {
            if (label.htmlFor === select.id) label.htmlFor = input.id;
        });
        if (select.getAttribute('aria-label')) input.setAttribute('aria-label', select.getAttribute('aria-label'));
        select.hidden = true;
        let matches = [], active = -1;
        const current = () => options.find(option => option.value === select.value);
        const sync = () => { input.value = current()?.label || ''; };

        function close() {
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            active = -1;
        }
        function choose(option) {
            select.value = option.value;
            sync();
            close();
            select.dispatchEvent(new Event('change', {bubbles: true}));
        }
        function highlight(index) {
            active = index;
            Array.from(list.querySelectorAll('[role="option"]')).forEach((element, position) => {
                element.setAttribute('aria-selected', String(position === active));
                if (position === active) {
                    input.setAttribute('aria-activedescendant', element.id);
                    element.scrollIntoView({block: 'nearest'});
                }
            });
        }
        function show(query) {
            const terms = normalize(query.trim()).split(/\s+/).filter(Boolean);
            const found = options.filter(option => terms.every(term => normalize(option.label).includes(term)));
            matches = found.slice(0, 8);
            active = -1;
            input.removeAttribute('aria-activedescendant');
            list.replaceChildren();
            matches.forEach((option, index) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.id = list.id + '-' + index;
                button.className = 'consumption-search-option';
                button.tabIndex = -1;
                button.setAttribute('role', 'option');
                button.setAttribute('aria-selected', 'false');
                button.textContent = option.label;
                // Keep input focus so selecting a recommendation cannot be interrupted by blur.
                button.addEventListener('mousedown', event => event.preventDefault());
                button.addEventListener('click', () => choose(option));
                list.append(button);
            });
            if (!matches.length || found.length > matches.length) {
                const message = document.createElement('div');
                message.className = 'consumption-search-empty';
                message.setAttribute('role', 'status');
                message.textContent = matches.length ? 'Showing 8 recommendations. Keep typing to narrow the results.' : 'No matching medicine. Try another name, strength, or form.';
                list.append(message);
            }
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }
        sync();
        input.addEventListener('focus', () => { input.select(); show(''); });
        input.addEventListener('input', () => show(input.value));
        input.addEventListener('blur', () => {close(); sync();});
        input.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (list.hidden) show(input.value === current()?.label ? '' : input.value);
                if (matches.length) {
                    const direction = event.key === 'ArrowDown' ? 1 : -1;
                    highlight(active < 0 ? (direction > 0 ? 0 : matches.length - 1) : (active + direction + matches.length) % matches.length);
                }
            } else if (event.key === 'Enter' && !list.hidden) {
                event.preventDefault();
                if (matches.length) choose(matches[Math.max(0, active)]);
            } else if (event.key === 'Escape') {
                event.preventDefault(); close(); sync();
            } else if (event.key === 'Tab') {
                close(); sync();
            }
        });
        select.addEventListener('change', () => {sync(); close();});
        return {input, list};
    }
    window.RhuConsumptionSearch = {enhance};
})();
