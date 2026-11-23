'use strict';
// This harness tests DOM behavior with supplied dimensions; it is not a browser layout test.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(process.argv[2], 'utf8');
let assertions = 0;
function equal(actual, expected, message) { assert.deepEqual(actual, expected, message); assertions++; }

function fixture(width = 640, hasActions = true) {
    const documentEvents = {};
    const windowEvents = {};
    const resizeCallbacks = [];
    const observed = new Set();
    const assigned = [];
    const headers = [];
    const document = {readyState: 'complete', activeElement: null,
        addEventListener(name, callback) { (documentEvents[name] ||= []).push(callback); },
        querySelectorAll(selector) { return selector === '.page-heading' ? headers.filter(header => header.isConnected) : []; },
        getElementById() { return null; },
        createElement(tag) { return new Node(tag); }};
    class Node {
        constructor(tag = 'div', className = '') {
            this.tagName = tag.toUpperCase(); this.className = className;
            this.children = []; this.attributes = {}; this.events = {}; this.parentElement = null;
            this.hidden = false; this.isConnected = true; this.textContent = ''; this.id = '';
            this.classList = {
                contains: name => this.className.split(/\s+/).includes(name),
                add: (...names) => { this.className = [...new Set([...this.className.split(/\s+/).filter(Boolean), ...names])].join(' '); },
                remove: (...names) => { this.className = this.className.split(/\s+/).filter(name => !names.includes(name)).join(' '); },
                toggle: (name, value) => { const present = value === undefined ? !this.classList.contains(name) : value;
                    if (present) this.classList.add(name); else this.classList.remove(name); return present; }
            };
        }
        appendChild(child) { child.parentElement = this; this.children.push(child); return child; }
        insertBefore(child, before) { child.parentElement = this; this.children.splice(this.children.indexOf(before), 0, child); }
        setAttribute(name, value) { this.attributes[name] = String(value); }
        getAttribute(name) { return this.attributes[name] ?? null; }
        hasAttribute(name) { return name in this.attributes; }
        addEventListener(name, callback) { (this.events[name] ||= []).push(callback); }
        fire(name) { (this.events[name] || []).forEach(callback => callback({target: this})); }
        focus() { document.activeElement = this; }
        contains(node) { return this === node || this.children.some(child => child.contains(node)); }
        all() { return this.children.flatMap(child => [child, ...child.all()]); }
        querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
        querySelectorAll(selector) {
            if (selector === '.page-heading-title :is(h1, h2, h3)') {
                return this.all().filter(node => ['H1', 'H2', 'H3'].includes(node.tagName) && node.parentElement?.classList.contains('page-heading-title'));
            }
            if (selector.startsWith('.')) return this.all().filter(node => node.classList.contains(selector.slice(1)));
            return [];
        }
        get value() { return this.tagName === 'SELECT' ? (this.explicitValue ?? this.children.find(child => child.selected)?.value ?? this.children[0]?.value ?? '') : this.storedValue; }
        set value(value) { if (this.tagName === 'SELECT') this.explicitValue = value; else this.storedValue = value; }
    }
    function heading(atWidth = width, actions = hasActions) {
        const header = new Node('section', 'page-heading'); header.clientWidth = atWidth;
        const main = header.appendChild(new Node('div', 'page-heading-main'));
        const title = main.appendChild(new Node('div', 'page-heading-title'));
        const text = title.appendChild(new Node('h1')); text.textContent = '  Inventory\n Records ';
        const nav = main.appendChild(new Node('nav', 'page-section-nav')); nav.scrollWidth = 420;
        nav.setAttribute('aria-label', 'Inventory sections');
        const urls = [
            'https://management.test/inventory?rhu_filter=7&year=2024&date_from=2024-01-01&date_to=2024-01-31',
            'https://management.test/stock-logs?rhu_filter=7&year=2024&date_from=2024-01-01&date_to=2024-01-31',
            'https://management.test/inventory?view=history&rhu_filter=7&year=2024&date_from=2024-01-01&date_to=2024-01-31#inventory-history'
        ];
        urls.forEach((url, index) => { const a = nav.appendChild(new Node('a', index === 2 ? 'is-active' : ''));
            a.href = url; a.textContent = ['Inventory', 'Stock logs', 'Inventory history'][index];
            if (index === 2) a.setAttribute('aria-current', 'page'); });
        let controls = null; let button = null;
        if (actions) {
            controls = header.appendChild(new Node('div', 'page-heading-actions')); controls.scrollWidth = 420;
            const form = controls.appendChild(new Node('form')); form.action = 'https://management.test/inventory';
            form.method = 'get'; button = form.appendChild(new Node('button')); button.textContent = 'Filter';
        }
        headers.push(header);
        return {header, main, title: text, nav, actions: controls, actionButton: button, urls};
    }
    const first = heading();
    const context = {document, URL, location: {href: 'https://management.test/inventory?view=history', origin: 'https://management.test', assign(url) { assigned.push(url); }},
        window: {addEventListener(name, callback) { (windowEvents[name] ||= []).push(callback); }},
        getComputedStyle: () => ({paddingLeft: '24', paddingRight: '24', columnGap: '16'}),
        ResizeObserver: class { constructor(callback) { resizeCallbacks.push(callback); } observe(node) { observed.add(node); } unobserve(node) { observed.delete(node); } }};
    vm.runInNewContext(source, context);
    return {first, heading, headers, context, observed, assigned,
        documentEvent(name, target, extra = {}) { const event = {target, prevented: false, preventDefault() { this.prevented = true; }, ...extra};
            (documentEvents[name] || []).forEach(callback => callback(event)); return event; },
        resize(nextWidth, item = first) { item.header.clientWidth = nextWidth; resizeCallbacks.forEach(callback => callback([{target: item.header}])); },
        refresh() { context.window.RhuPageHeadings.refresh(); }};
}

const widths = [];
for (const [width, compactActions, compactNav, narrow] of [[1440, false, false, false], [1024, true, false, false], [640, true, true, false], [320, true, true, true]]) {
    const f = fixture(width);
    const h = f.first.header;
    equal(h.classList.contains('is-compact-actions'), compactActions, 'Actions mode at '+width);
    equal(h.classList.contains('is-compact-nav'), compactNav, 'Section mode at '+width);
    equal(h.classList.contains('is-narrow-heading'), narrow, 'Narrow title mode at '+width);
    equal(f.first.actions.hidden, compactActions, 'Closed compact actions at '+width);
    widths.push({width, compactActions, compactNav, narrow});
}

const f = fixture(640);
const {header, nav, actions, actionButton, urls} = f.first;
const toggle = header.querySelector('.page-heading-actions-toggle');
const select = nav.querySelector('.page-section-select');
equal(select.children.map(option => option.value), urls, 'Selector preserves exact filtered destinations');
equal(select.children.map(option => Boolean(option.selected)), [false, false, true], 'Selector retains active section');
equal(select.getAttribute('aria-label'), 'Inventory sections');
equal(f.first.title.title, 'Inventory Records');
select.value = urls[1]; select.fire('change');
equal(f.assigned, [urls[1]], 'Section selection keeps original same-origin URL');
select.value = 'https://untrusted.example/steal'; select.fire('change');
equal(f.assigned, [urls[1]], 'Cross-origin selector values cannot navigate');
equal(toggle.getAttribute('aria-controls'), actions.id);
equal(actions.children[0].tagName, 'FORM', 'Enhancement retains original action form');
equal(actionButton.parentElement.action, 'https://management.test/inventory');
toggle.fire('click');
equal(actions.hidden, false); equal(toggle.getAttribute('aria-expanded'), 'true');
equal(header.classList.contains('is-actions-open'), true);
actionButton.focus();
const escape = f.documentEvent('keydown', actionButton, {key: 'Escape'});
equal(escape.prevented, true); equal(actions.hidden, true); equal(f.context.document.activeElement, toggle, 'Escape restores focus');
toggle.fire('click'); f.documentEvent('click', {outside: true});
equal(actions.hidden, true); equal(toggle.getAttribute('aria-expanded'), 'false');
toggle.fire('click'); f.documentEvent('focusin', {outside: true});
equal(actions.hidden, true, 'Focus leaving header dismisses actions');
toggle.fire('click'); f.documentEvent('click', actionButton);
equal(actions.hidden, false, 'Clicking original form controls keeps panel open');
const second = f.heading(640); f.refresh();
second.header.querySelector('.page-heading-actions-toggle').fire('click');
equal(actions.hidden, true, 'Opening another heading closes the first panel');
equal(second.actions.hidden, false);
f.resize(1440, second);
equal(second.actions.hidden, false, 'Wide layout restores inline actions');
equal(second.header.querySelector('.page-heading-actions-toggle').hidden, true);
equal(second.header.classList.contains('is-actions-open'), false);
for (let n = 0; n < 3; n++) f.refresh();
equal(nav.querySelectorAll('.page-section-select').length, 1, 'Repeated refresh does not duplicate selector');
equal(header.querySelectorAll('.page-heading-actions-toggle').length, 1, 'Repeated refresh does not duplicate toggle');
equal(f.observed.size, 2);
second.header.isConnected = false; f.refresh();
equal(f.observed.size, 1, 'Detached headers are no longer observed');
const plain = fixture(320, false);
equal(plain.first.header.querySelectorAll('.page-heading-actions-toggle').length, 0, 'Headers without controls receive no empty actions menu');
equal(plain.first.nav.querySelectorAll('.page-section-select').length, 1);
process.stdout.write(JSON.stringify({ok: true, assertions, widths, browser_geometry_verified: false}));
