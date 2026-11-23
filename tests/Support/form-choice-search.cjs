const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const observers = new Map();
const frames = [];
let focus;

class Element {
    constructor(tag) {
        this.tag = tag;
        this.children = [];
        this.attributes = new Map();
        this.events = new Map();
        this.style = {};
        this.classList = {add() {}};
        this.hidden = false;
        this.disabled = false;
        this.required = false;
        this.size = 0;
        this.isConnected = true;
        this.value = '';
    }
    get options() { return this.querySelectorAll('option'); }
    get index() { return this.closest('select').options.indexOf(this); }
    get selectedIndex() { return this.options.findIndex(option => option.value === this.value); }
    set selectedIndex(index) { this.value = this.options[index].value; }
    get validity() { return {valid: !this.required || !!this.value}; }
    setAttribute(key, value) { this.attributes.set(key, String(value)); }
    getAttribute(key) { return this.attributes.get(key) ?? null; }
    hasAttribute(key) { return this.attributes.has(key); }
    removeAttribute(key) { this.attributes.delete(key); }
    append(...children) {
        children.forEach(child => {
            if (child.parent) child.parent.children = child.parent.children.filter(node => node !== child);
            child.parent = this;
        });
        this.children.push(...children);
    }
    replaceChildren(...children) { this.children = []; this.append(...children); }
    insertAdjacentElement(position, node) {
        assert.equal(position, 'afterend');
        node.parent = this.parent;
        this.parent.children.splice(this.parent.children.indexOf(this) + 1, 0, node);
    }
    matches(selector) {
        if (selector.includes(', ')) return selector.split(', ').some(part => this.matches(part));
        if (selector === ':disabled') return this.disabled;
        if (selector === '[role="option"]') return this.getAttribute('role') === 'option';
        if (selector.startsWith('[')) return this.hasAttribute(selector.slice(1, -1));
        if (selector === 'input[data-choice-search]') return this.tag === 'input' && this.hasAttribute('data-choice-search');
        return selector === this.tag;
    }
    closest(selector) {
        for (let node = this; node; node = node.parent) if (node.matches(selector)) return node;
        return null;
    }
    querySelectorAll(selector) {
        return this.children.flatMap(child => [
            ...(child.matches(selector) ? [child] : []), ...child.querySelectorAll(selector),
        ]);
    }
    addEventListener(name, callback) {
        this.events.set(name, [...(this.events.get(name) ?? []), callback]);
    }
    dispatchEvent(event) {
        event.target ??= this;
        for (const callback of this.events.get(event.type) ?? []) callback(event);
        if (event.bubbles) this.parent?.dispatchEvent(event);
    }
    focus() { focus = this; this.dispatchEvent(new BrowserEvent('focus')); }
    select() {}
    scrollIntoView() {}
}

class BrowserEvent {
    constructor(type, options = {}) { this.type = type; Object.assign(this, options); }
    preventDefault() { this.defaultPrevented = true; }
    stopPropagation() { this.bubbles = false; }
}

function fixture(method, name, required = false) {
    const form = new Element('form');
    form.method = method;
    const select = new Element('select');
    select.name = name;
    select.form = form;
    select.required = required;
    select.validationMessage = 'Choose an option.';
    select.labels = [new Element('label')];
    form.append(select);
    return {form, select};
}
function option(select, value, label, attributes = {}) {
    const choice = Object.assign(new Element('option'), {value, label}, attributes);
    select.append(choice);
    return choice;
}
function key(element, value) {
    element.dispatchEvent(new BrowserEvent('keydown', {key: value}));
}
function type(element, value) {
    element.value = value;
    element.dispatchEvent(new BrowserEvent('input'));
}

const root = new Element('body');
const entry = fixture('post', 'inventory_id', true);
const filter = fixture('get', 'facility');
const unnamed = fixture('post', '');
const optional = fixture('post', 'recipient_user_id');
const destinationForm = new Element('form');
const destination = new Element('input');
destination.name = 'destination';
destination.id = 'destination-input';
destination.setAttribute('list', 'assigned-barangays');
destination.setAttribute('data-choice-search', '');
destination.list = new Element('datalist');
destinationForm.append(destination, destination.list);
const chart = fixture('get', 'chart');
chart.select.setAttribute('data-consumption-search', '');
const forecast = fixture('get', 'usage_days');
forecast.form.setAttribute('data-native-select', '');
const ranking = new Element('select');
ranking.name = 'usage_ranking';
forecast.form.append(ranking);

root.append(entry.form, filter.form, unnamed.form, optional.form, destinationForm, chart.form, forecast.form);
option(entry.select, '', 'Choose a batch');
option(entry.select, '51', 'Bátch 51, tablets', {dataset: {remaining: '12'}});
option(entry.select, '52', 'Batch 52 unavailable', {disabled: true});
option(entry.select, '53', 'Batch 53 hidden', {hidden: true});
option(optional.select, '', 'No recipient');
option(optional.select, '3', 'Sample midwife');
option(filter.select, '', 'All facilities');
option(filter.select, '14', 'Hamtic RHU');
option(unnamed.select, 'vaccine', 'Vaccine');
option(destination.list, 'Barangay Dos', '');
option(destination.list, 'San Pedro', '');

const context = {
    window: {addEventListener() {}},
    document: {body: root, createElement: tag => new Element(tag)},
    Event: BrowserEvent,
    MutationObserver: class {
        constructor(callback) { this.callback = callback; }
        observe(target) { observers.set(target, this.callback); }
    },
    getComputedStyle: node => node.style,
    requestAnimationFrame: callback => frames.push(callback),
    queueMicrotask,
};
vm.runInNewContext(fs.readFileSync(process.argv[2], 'utf8'), context);

assert.equal(chart.form.children.length, 1, 'existing chart combobox keeps its own enhancement');
assert.deepEqual(forecast.form.children, [forecast.select, ranking], 'both forecast filters stay native');
const [filterInput, filterList] = filter.form.children[1].children;
assert.equal(filterInput.value, 'All facilities');
filterInput.focus();
type(filterInput, 'hamtic');
filterList.querySelectorAll('[role="option"]')[0].dispatchEvent(new BrowserEvent('click'));
assert.equal(filter.select.value, '14');
const [unnamedInput] = unnamed.form.children[1].children;
unnamedInput.focus();
type(unnamedInput, 'vac');
key(unnamedInput, 'Enter');
assert.equal(unnamed.select.value, 'vaccine');
const [input, list, message] = entry.form.children[1].children;
assert.equal(input.type, 'search');
assert.equal(entry.select.labels[0].htmlFor, input.id);
assert.equal(input.name, undefined, 'only the original select contributes to form data');
let inputEvents = 0, changeEvents = 0;
entry.form.addEventListener('input', () => inputEvents++);
entry.form.addEventListener('change', () => changeEvents++);

const invalid = new BrowserEvent('invalid');
entry.select.dispatchEvent(invalid);
assert.equal(focus, input);
assert.equal(message.hidden, false);
assert.equal(invalid.defaultPrevented, true);

type(input, 'batch');
assert.deepEqual(list.querySelectorAll('[role="option"]').map(row => row.textContent), ['Bátch 51, tablets']);
key(input, 'ArrowDown');
key(input, 'Enter');
assert.equal(entry.select.value, '51');
assert.equal(entry.select.options[entry.select.selectedIndex].dataset.remaining, '12');
assert.equal(input.value, 'Bátch 51, tablets');
assert.equal(message.hidden, true);
assert.equal(inputEvents, 1);
assert.equal(changeEvents, 1);

type(input, 'another');
key(input, 'Escape');
assert.equal(entry.select.value, '51');
type(input, 'another');
input.dispatchEvent(new BrowserEvent('blur'));
assert.equal(entry.select.value, '', 'an unconfirmed replacement cannot submit the previous required choice');

entry.select.replaceChildren();
option(entry.select, '73', 'Replacement batch');
entry.select.value = '73';
observers.get(entry.select)();
assert.equal(input.value, 'Replacement batch');
entry.select.style.pointerEvents = 'none';
observers.get(entry.select)();
assert.equal(input.readOnly, true);
assert.equal(list.hidden, true);

const [recipient, recipients] = optional.form.children[1].children;
recipient.focus();
type(recipient, 'midwife');
key(recipient, 'Enter');
assert.equal(optional.select.value, '3');
type(recipient, 'no recipient');
recipients.querySelectorAll('[role="option"]')[0].dispatchEvent(new BrowserEvent('click'));
assert.equal(optional.select.value, '');
assert.equal(recipient.value, 'No recipient');

assert.equal(destination.hasAttribute('list'), false, 'only one recommendation list is active');
assert.equal(destination.name, 'destination');
const [, destinations] = destinationForm.children[0].children;
destination.focus();
type(destination, 'pedro');
assert.deepEqual(destinations.querySelectorAll('[role="option"]').map(row => row.textContent), ['San Pedro']);
key(destination, 'Enter');
assert.equal(destination.value, 'San Pedro');
type(destination, 'Mobile clinic');
destination.dispatchEvent(new BrowserEvent('blur'));
assert.equal(destination.value, 'Mobile clinic');

const dynamic = fixture('get', 'new_filter');
root.append(dynamic.form);
observers.get(root)([{addedNodes: [dynamic.form]}]);
assert.equal(dynamic.form.children[1].children[0].type, 'search');
console.log('Search choices passed validation, keyboard, filters and destination checks.');
