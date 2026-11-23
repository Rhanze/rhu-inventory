<?php

namespace Tests\Feature;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConsumptionSearchBehaviorTest extends TestCase
{
    private function evaluate(string $scenario): array
    {
        $node = (new ExecutableFinder)->find('node');
        if ($node === null) $this->markTestSkipped('Node.js is required for consumption search behavior checks.');
        $source = json_encode(file_get_contents(public_path('js/consumption-search.js')), JSON_THROW_ON_ERROR);
        $harness = <<<'JS'
const vm = require('node:vm');
class Event {
    constructor(type, options = {}) {this.type = type; Object.assign(this, options); this.defaultPrevented = false;}
    preventDefault() {this.defaultPrevented = true;}
}
function element(tagName) {
    return {tagName: tagName.toUpperCase(), id: '', value: '', textContent: '', hidden: false,
        dataset: {}, attributes: {}, children: [], events: {}, disabled: false, selectedText: false,
        set innerHTML(value) {throw new Error('Search content must use safe textContent, not HTML.');},
        setAttribute(name, value) {this.attributes[name] = String(value);},
        getAttribute(name) {return this.attributes[name] ?? null;},
        removeAttribute(name) {delete this.attributes[name];},
        append(...children) {this.children.push(...children);},
        replaceChildren(...children) {this.children = children;},
        querySelectorAll(selector) {return selector === '[role="option"]' ? this.children.filter(child => child.attributes.role === 'option') : [];},
        addEventListener(name, callback) {(this.events[name] ||= []).push(callback);},
        dispatchEvent(event) {for (const callback of this.events[event.type] || []) callback(event); return !event.defaultPrevented;},
        fire(type, properties = {}) {const event = new Event(type, properties); this.dispatchEvent(event); return event;},
        select() {this.selectedText = true;},
        scrollIntoView(options) {this.lastScroll = options;}
    };
}
const catalog = [
    {value: '1', textContent: 'Paracetamol 500 mg Tablet (tablet)'},
    {value: '2', textContent: 'Amoxicillin 500 mg Capsule (capsule)'},
    {value: '3', textContent: 'Amoxicillin 250 mg/5 mL Oral suspension (bottle)'},
    {value: '4', textContent: 'Cétirizine 10 mg Tablet (tablet)'},
    {value: '5', textContent: 'Archived medicine 5 mg Tablet', disabled: true}
];
function page(options = {}) {
    const select = element('select');
    select.id = 'consumption-medicine';
    select.value = options.value || '1';
    select.options = options.catalog || catalog;
    select.disabled = !!options.disabled;
    select.dataset = options.dataset || {};
    select.setAttribute('aria-label', 'Medicine consumption graph');
    const wrappers = [];
    select.insertAdjacentElement = (where, wrapper) => {if (where !== 'afterend') throw new Error('Unexpected insertion'); wrappers.push(wrapper);};
    const labels = [{htmlFor: select.id}, {htmlFor: 'another-control'}];
    const window = {};
    const context = {window, Event, document: {createElement: element, querySelectorAll: selector => selector === 'label' ? labels : []}};
    vm.runInNewContext(source, context);
    const changes = [];
    select.addEventListener('change', event => changes.push({value: select.value, bubbles: !!event.bubbles}));
    const before = {hidden: select.hidden, wrappers: wrappers.length};
    const controls = window.RhuConsumptionSearch.enhance(select);
    const app = {window, select, labels, wrappers, changes, before, ...(controls || {}),
        matches() {return this.list.querySelectorAll('[role="option"]');},
        search(value) {this.input.value = value; this.input.fire('input');},
        key(key) {return this.input.fire('keydown', {key});},
        messages() {return this.list.children.filter(child => child.attributes.role === 'status').map(child => child.textContent);}};
    return app;
}
JS;
        $process = new Process([$node]);
        $process->setInput('const source = '.$source.";\n".$harness."\n".$scenario);
        $process->mustRun();
        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_recommendations_match_name_strength_and_form_without_changing_the_selected_graph(): void
    {
        $result = $this->evaluate(<<<'JS'
const app = page();
app.search('amoxicillin capsule 500');
const capsule = app.matches().map(item => item.textContent);
app.search('250 suspension');
const suspension = app.matches().map(item => item.textContent);
app.search('CETIRIZINE tablet 10');
const accented = app.matches().map(item => item.textContent);
app.search('archived');
process.stdout.write(JSON.stringify({capsule, suspension, accented, disabledMatches: app.matches().length,
    value: app.select.value, changes: app.changes}));
JS);
        $this->assertSame(['Amoxicillin 500 mg Capsule (capsule)'], $result['capsule']);
        $this->assertSame(['Amoxicillin 250 mg/5 mL Oral suspension (bottle)'], $result['suspension']);
        $this->assertSame(['Cétirizine 10 mg Tablet (tablet)'], $result['accented']);
        $this->assertSame(0, $result['disabledMatches']);
        $this->assertSame('1', $result['value']);
        $this->assertSame([], $result['changes']);
    }

    public function test_keyboard_and_pointer_recommendations_dispatch_the_same_graph_change_event(): void
    {
        $result = $this->evaluate(<<<'JS'
const app = page();
app.input.fire('focus');
const focused = {selectedText: app.input.selectedText, expanded: app.input.getAttribute('aria-expanded')};
app.search('amoxicillin');
const down = app.key('ArrowDown');
const first = {id: app.input.getAttribute('aria-activedescendant'), selected: app.matches()[0].getAttribute('aria-selected')};
app.key('ArrowDown');
const second = {id: app.input.getAttribute('aria-activedescendant'), selected: app.matches()[1].getAttribute('aria-selected')};
const enter = app.key('Enter');
const keyboard = {value: app.select.value, text: app.input.value, hidden: app.list.hidden, expanded: app.input.getAttribute('aria-expanded')};
app.search('paracetamol');
const recommendation = app.matches()[0];
const mousedown = recommendation.fire('mousedown');
recommendation.fire('click');
process.stdout.write(JSON.stringify({focused, first, second, downPrevented: down.defaultPrevented,
    enterPrevented: enter.defaultPrevented, keyboard, mousePrevented: mousedown.defaultPrevented,
    pointer: {value: app.select.value, text: app.input.value, hidden: app.list.hidden}, changes: app.changes}));
JS);
        $this->assertTrue($result['focused']['selectedText']);
        $this->assertSame('true', $result['focused']['expanded']);
        $this->assertNotSame($result['first']['id'], $result['second']['id']);
        $this->assertSame('true', $result['first']['selected']);
        $this->assertSame('true', $result['second']['selected']);
        $this->assertTrue($result['downPrevented']);
        $this->assertTrue($result['enterPrevented']);
        $this->assertSame(['value' => '3', 'text' => 'Amoxicillin 250 mg/5 mL Oral suspension (bottle)', 'hidden' => true, 'expanded' => 'false'], $result['keyboard']);
        $this->assertTrue($result['mousePrevented']);
        $this->assertSame(['value' => '1', 'text' => 'Paracetamol 500 mg Tablet (tablet)', 'hidden' => true], $result['pointer']);
        $this->assertSame([['value' => '3', 'bubbles' => true], ['value' => '1', 'bubbles' => true]], $result['changes']);
    }

    public function test_unknown_text_and_cancel_keys_leave_the_original_medicine_selected(): void
    {
        $result = $this->evaluate(<<<'JS'
const app = page();
app.search('not a medicine 123');
const noMatches = {count: app.matches().length, messages: app.messages(), expanded: app.input.getAttribute('aria-expanded')};
app.key('Enter');
const afterEnter = app.select.value;
app.key('Escape');
const escape = {text: app.input.value, hidden: app.list.hidden, active: app.input.getAttribute('aria-activedescendant')};
app.search('unknown medicine'); app.key('Tab');
const tab = {text: app.input.value, hidden: app.list.hidden};
app.search('unselected custom text'); app.input.fire('blur');
process.stdout.write(JSON.stringify({noMatches, afterEnter, escape, tab,
    blur: {text: app.input.value, hidden: app.list.hidden}, value: app.select.value, changes: app.changes}));
JS);
        $this->assertSame(0, $result['noMatches']['count']);
        $this->assertStringContainsString('No matching medicine', $result['noMatches']['messages'][0]);
        $this->assertSame('true', $result['noMatches']['expanded']);
        $this->assertSame('1', $result['afterEnter']);
        $this->assertSame(['text' => 'Paracetamol 500 mg Tablet (tablet)', 'hidden' => true, 'active' => null], $result['escape']);
        foreach (['tab', 'blur'] as $action) $this->assertSame(['text' => 'Paracetamol 500 mg Tablet (tablet)', 'hidden' => true], $result[$action]);
        $this->assertSame('1', $result['value']);
        $this->assertSame([], $result['changes']);
    }

    public function test_native_fallback_external_changes_safe_labels_and_result_limits(): void
    {
        $result = $this->evaluate(<<<'JS'
const app = page();
const initial = {before: app.before, hidden: app.select.hidden, label: app.labels[0].htmlFor,
    unrelatedLabel: app.labels[1].htmlFor, role: app.input.getAttribute('role'), controls: app.input.getAttribute('aria-controls'),
    ariaLabel: app.input.getAttribute('aria-label'), text: app.input.value};
app.search('amoxicillin');
app.select.value = '4'; app.select.fire('change');
const external = {text: app.input.value, hidden: app.list.hidden};
app.window.RhuConsumptionSearch.enhance(app.select);
const disabled = page({disabled: true});
const empty = page({catalog: []});
const unavailable = page({catalog: [{value: '', textContent: 'Choose'}, {value: 'x', textContent: 'Disabled', disabled: true}]});
const unsafeLabel = 'Medicine <img src=x onerror=alert(1)> 500 mg Tablet';
const unsafe = page({catalog: [{value: '1', textContent: unsafeLabel}]});
unsafe.search('medicine');
const safe = {text: unsafe.matches()[0].textContent, children: unsafe.matches()[0].children.length};
const many = page({catalog: Array.from({length: 12}, (_, index) => ({value: String(index + 1), textContent: 'Medicine ' + (index + 1) + ' tablet'}))});
many.search('medicine');
process.stdout.write(JSON.stringify({initial, external, wrappers: app.wrappers.length,
    disabled: {hidden: disabled.select.hidden, wrappers: disabled.wrappers.length},
    empty: {hidden: empty.select.hidden, wrappers: empty.wrappers.length},
    unavailable: {hidden: unavailable.select.hidden, wrappers: unavailable.wrappers.length}, safe,
    many: {count: many.matches().length, messages: many.messages()}}));
JS);
        $this->assertSame(['hidden' => false, 'wrappers' => 0], $result['initial']['before']);
        $this->assertTrue($result['initial']['hidden']);
        $this->assertSame('consumption-medicine-search', $result['initial']['label']);
        $this->assertSame('another-control', $result['initial']['unrelatedLabel']);
        $this->assertSame('combobox', $result['initial']['role']);
        $this->assertSame('consumption-medicine-suggestions', $result['initial']['controls']);
        $this->assertSame('Medicine consumption graph', $result['initial']['ariaLabel']);
        $this->assertSame('Paracetamol 500 mg Tablet (tablet)', $result['initial']['text']);
        $this->assertSame(['text' => 'Cétirizine 10 mg Tablet (tablet)', 'hidden' => true], $result['external']);
        $this->assertSame(1, $result['wrappers']);
        foreach (['disabled', 'empty', 'unavailable'] as $guard) $this->assertSame(['hidden' => false, 'wrappers' => 0], $result[$guard]);
        $this->assertSame(['text' => 'Medicine <img src=x onerror=alert(1)> 500 mg Tablet', 'children' => 0], $result['safe']);
        $this->assertSame(8, $result['many']['count']);
        $this->assertStringContainsString('Keep typing to narrow', $result['many']['messages'][0]);
    }
}
