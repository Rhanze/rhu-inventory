<?php

namespace Tests\Feature;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ScrollNavigationTest extends TestCase
{
    private function evaluate(string $scenario): array
    {
        $node = (new ExecutableFinder)->find('node');
        if ($node === null) {
            $this->markTestSkipped('Node.js is required for scroll navigation behavior checks.');
        }
        $source = json_encode(file_get_contents(public_path('js/scroll-navigation.js')), JSON_THROW_ON_ERROR);
        $harness = <<<'JS'
const vm = require('node:vm');
function page(options = {}) {
    const location = new URL(options.url || 'https://management.test/disease-reports?view=reports');
    const storage = options.storage || new Map();
    const nodes = new Map();
    const documentEvents = {};
    const windowEvents = {};
    const microtasks = [];
    const frames = [];
    const scrolls = [];
    let dialogOpen = Boolean(options.dialogOpen);
    const main = {};
    const history = {
        scrollRestoration: 'manual', state: options.state || null,
        replaceState(value) { this.state = value; }
    };
    const document = {
        addEventListener(name, callback) { (documentEvents[name] ||= []).push(callback); },
        getElementById(id) { return nodes.get(id) || null; },
        querySelector(selector) { return selector === 'dialog[open]' && dialogOpen ? {} : null; },
        querySelectorAll() { return [...nodes.values()].filter(node => node.tagName === 'DETAILS' && node.open && node.inMain); }
    };
    const window = {
        scrollX: options.x || 0, scrollY: options.y || 0,
        addEventListener(name, callback) { (windowEvents[name] ||= []).push(callback); },
        scrollTo(position) { scrolls.push(position); this.scrollX = position.left; this.scrollY = position.top; }
    };
    const context = {
        window, document, history, location, URL, Date,
        sessionStorage: {
            getItem(key) { return storage.get(key) || null; },
            setItem(key, value) { storage.set(key, value); },
            removeItem(key) { storage.delete(key); }
        },
        queueMicrotask(callback) { microtasks.push(callback); },
        requestAnimationFrame(callback) { frames.push(callback); },
        performance: {getEntriesByType() { return [{type: options.navigationType || 'navigate'}]; }}
    };
    vm.runInNewContext(source, context);
    function element(tagName, attributes = {}) {
        const element = {
            tagName, inMain: true, target: '', open: false, parentElement: null,
            href: location.href, action: location.href, method: 'get', cells: [],
            ...attributes,
            closest(selector) {
                if (selector === 'a[href]') return this.tagName === 'A' ? this : this.link || null;
                if (selector === '#main-content') return this.inMain ? main : null;
                return this.anchor || null;
            },
            hasAttribute(name) { return name === 'download' && Boolean(this.download); },
            getBoundingClientRect() { return {top: this.top || 0}; },
            querySelectorAll() { return this.cells; }
        };
        if (element.id) nodes.set(element.id, element);
        return element;
    }
    return {
        window, history, storage, scrolls, element,
        pending() { return JSON.parse(storage.get('rhu:scroll:pending') || 'null'); },
        documentEvent(name, values = {}) {
            const event = {button: 0, defaultPrevented: false, ...values};
            (documentEvents[name] || []).forEach(callback => callback(event));
            return event;
        },
        windowEvent(name) { (windowEvents[name] || []).forEach(callback => callback()); },
        flushMicrotasks() { while (microtasks.length) microtasks.shift()(); },
        flushFrames() { while (frames.length) frames.shift()(); },
        setDialogOpen(value) { dialogOpen = value; }
    };
}
JS;
        $process = new Process([$node]);
        $process->setInput('const source = '.$source.";\n".$harness."\n".$scenario);
        $process->mustRun();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_same_page_navigation_remembers_position_without_interfering_with_ajax_or_other_destinations(): void
    {
        $result = $this->evaluate(<<<'JS'
const app = page({x: 14, y: 740});
const section = app.element('SECTION', {id: 'report-history', top: -35});
app.element('DETAILS', {id: 'case-estimates', open: true});
const link = app.element('A', {href: 'https://management.test/disease-reports?view=reports&page=2', anchor: section});
app.documentEvent('click', {target: link});
const beforeHandlersFinish = app.pending();
app.flushMicrotasks();
const saved = app.pending();
const ignored = [];
for (const attributes of [
    {href: 'https://management.test/settings'},
    {href: 'https://outside.test/disease-reports'},
    {href: 'https://management.test/disease-reports#report-history'},
    {target: '_blank'}, {download: true}, {inMain: false}
]) {
    const other = page();
    other.documentEvent('click', {target: other.element('A', attributes)});
    other.flushMicrotasks();
    ignored.push(other.pending());
}
const ajax = page();
const ajaxClick = ajax.documentEvent('click', {target: ajax.element('A')});
ajaxClick.defaultPrevented = true;
ajax.flushMicrotasks();
const modified = page();
modified.documentEvent('click', {target: modified.element('A'), ctrlKey: true});
modified.flushMicrotasks();
process.stdout.write(JSON.stringify({nativeRestoration: app.history.scrollRestoration,
    beforeHandlersFinish, saved, ignored, ajax: ajax.pending(), modified: modified.pending()}));
JS);
        $this->assertSame('auto', $result['nativeRestoration']);
        $this->assertNull($result['beforeHandlersFinish']);
        $this->assertSame(14, $result['saved']['x']);
        $this->assertSame(740, $result['saved']['y']);
        $this->assertSame('report-history', $result['saved']['anchor']);
        $this->assertSame(-35, $result['saved']['offset']);
        $this->assertSame(['case-estimates'], $result['saved']['details']);
        $this->assertSame(array_fill(0, 6, null), $result['ignored']);
        $this->assertNull($result['ajax']);
        $this->assertNull($result['modified']);
    }

    public function test_filter_and_confirmed_forms_save_position_after_handlers_allow_navigation(): void
    {
        $result = $this->evaluate(<<<'JS'
const results = [];
for (const attributes of [
    {},
    {method: 'post', action: 'https://management.test/disease-reports/7'},
    {action: 'https://management.test/search'},
    {method: 'post', action: 'https://outside.test/action'},
    {method: 'dialog'}, {target: '_blank'}, {inMain: false}
]) {
    const app = page({y: 810});
    app.documentEvent('submit', {target: app.element('FORM', attributes)});
    app.flushMicrotasks();
    results.push(app.pending()?.y ?? null);
}
const ajax = page({y: 500});
const event = ajax.documentEvent('submit', {target: ajax.element('FORM', {method: 'post'})});
event.defaultPrevented = true;
ajax.flushMicrotasks();
const confirmed = page({y: 920});
confirmed.window.RhuNavigation.rememberForm(confirmed.element('FORM', {method: 'post', action: 'https://management.test/disease-reports/7'}));
process.stdout.write(JSON.stringify({results, ajax: ajax.pending(), confirmedY: confirmed.pending()?.y}));
JS);
        $this->assertSame([810, 810, null, null, null, null, null], $result['results']);
        $this->assertNull($result['ajax']);
        $this->assertSame(920, $result['confirmedY']);
    }

    public function test_filter_navigation_restores_the_section_offset_and_expanded_details_or_falls_back_to_coordinates(): void
    {
        $result = $this->evaluate(<<<'JS'
const original = page({x: 15, y: 600});
const sourceSection = original.element('SECTION', {id: 'report-history', top: 90});
original.element('DETAILS', {id: 'case-estimates', open: true});
original.documentEvent('click', {target: original.element('A', {anchor: sourceSection})});
original.flushMicrotasks();
const destination = page({storage: original.storage, url: 'https://management.test/disease-reports?page=2'});
const parent = destination.element('DETAILS', {id: 'history-wrapper'});
destination.element('SECTION', {id: 'report-history', top: 1200, parentElement: parent});
const estimates = destination.element('DETAILS', {id: 'case-estimates'});
destination.windowEvent('load');
destination.flushFrames();
const plain = page({x: 5, y: 755});
plain.documentEvent('click', {target: plain.element('A')});
plain.flushMicrotasks();
const fallback = page({storage: plain.storage});
fallback.windowEvent('load');
fallback.flushFrames();
process.stdout.write(JSON.stringify({anchored: destination.scrolls, parentOpen: parent.open,
    savedDetailsOpen: estimates.open, pendingConsumed: destination.pending(), fallback: fallback.scrolls}));
JS);
        $this->assertSame([['left' => 15, 'top' => 1110, 'behavior' => 'instant']], $result['anchored']);
        $this->assertTrue($result['parentOpen']);
        $this->assertTrue($result['savedDetailsOpen']);
        $this->assertNull($result['pendingConsumed']);
        $this->assertSame([['left' => 5, 'top' => 755, 'behavior' => 'instant']], $result['fallback']);
    }

    public function test_explicit_record_hash_wins_over_saved_position_and_opens_containing_details(): void
    {
        $result = $this->evaluate(<<<'JS'
const original = page({y: 1700});
original.documentEvent('click', {target: original.element('A')});
original.flushMicrotasks();
const destination = page({storage: original.storage, y: 70,
    url: 'https://management.test/disease-reports#report%20row'});
const outer = destination.element('DETAILS', {id: 'outer'});
const inner = destination.element('DETAILS', {id: 'inner', parentElement: outer});
destination.element('TR', {id: 'report row', top: 800, parentElement: inner});
destination.windowEvent('load');
destination.flushFrames();
process.stdout.write(JSON.stringify({scrolls: destination.scrolls, outerOpen: outer.open,
    innerOpen: inner.open, pendingConsumed: destination.pending()}));
JS);
        $this->assertSame([['left' => 0, 'top' => 770, 'behavior' => 'instant']], $result['scrolls']);
        $this->assertTrue($result['outerOpen']);
        $this->assertTrue($result['innerOpen']);
        $this->assertNull($result['pendingConsumed']);
    }

    public function test_section_navigation_keeps_report_styling_and_restores_once_without_moving_open_dialogs(): void
    {
        $result = $this->evaluate(<<<'JS'
const destination = page({url: 'https://management.test/disease-reports#disease-report-history'});
const cell = {style: {}};
destination.element('SECTION', {id: 'disease-report-history', top: 500, cells: [cell]});
destination.documentEvent('DOMContentLoaded');
destination.flushFrames();
destination.windowEvent('load');
destination.flushFrames();
const dialog = page({url: 'https://management.test/disease-reports#disease-report-history', dialogOpen: true});
dialog.element('SECTION', {id: 'disease-report-history', top: 500});
dialog.documentEvent('DOMContentLoaded');
dialog.flushFrames();
process.stdout.write(JSON.stringify({scrolls: destination.scrolls, cellStyle: cell.style, dialogScrolls: dialog.scrolls}));
JS);
        $this->assertSame([['left' => 0, 'top' => 400, 'behavior' => 'instant']], $result['scrolls']);
        $this->assertEmpty($result['cellStyle']);
        $this->assertSame([], $result['dialogScrolls']);
    }

    public function test_reload_and_back_restore_history_state_without_reusing_it_for_new_page_navigation(): void
    {
        $result = $this->evaluate(<<<'JS'
const original = page({x: 12, y: 505, state: {existing: 'keep'}});
original.element('DETAILS', {id: 'case-estimates', open: true});
original.windowEvent('pagehide');
const restored = [];
for (const navigationType of ['reload', 'back_forward', 'navigate']) {
    const destination = page({state: original.history.state, navigationType});
    const details = destination.element('DETAILS', {id: 'case-estimates'});
    destination.windowEvent('load');
    destination.flushFrames();
    restored.push({scrolls: destination.scrolls, detailsOpen: details.open});
}
process.stdout.write(JSON.stringify({existingState: original.history.state.existing, restored}));
JS);
        $this->assertSame('keep', $result['existingState']);
        foreach (array_slice($result['restored'], 0, 2) as $restored) {
            $this->assertSame([['left' => 12, 'top' => 505, 'behavior' => 'instant']], $restored['scrolls']);
            $this->assertTrue($restored['detailsOpen']);
        }
        $this->assertSame([], $result['restored'][2]['scrolls']);
        $this->assertFalse($result['restored'][2]['detailsOpen']);
    }

    public function test_restoring_position_does_not_move_the_page_behind_an_open_dialog(): void
    {
        $result = $this->evaluate(<<<'JS'
const original = page({y: 950});
original.documentEvent('click', {target: original.element('A')});
original.flushMicrotasks();
const destination = page({storage: original.storage, dialogOpen: true});
destination.windowEvent('load');
destination.flushFrames();
process.stdout.write(JSON.stringify({scrolls: destination.scrolls}));
JS);
        $this->assertSame([], $result['scrolls']);
    }
}
