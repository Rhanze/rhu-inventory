<?php

namespace Tests\Feature;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class EmptyFeedbackTest extends TestCase
{
    private function evaluate(string $scenario): array
    {
        $node = (new ExecutableFinder)->find('node');
        if ($node === null) {
            $this->markTestSkipped('Node.js is required for empty-result feedback behavior checks.');
        }
        $source = json_encode(file_get_contents(public_path('js/empty-feedback.js')), JSON_THROW_ON_ERROR);
        $harness = <<<'JS'
const vm = require('node:vm');
function page(options = {}) {
    const location = new URL(options.url || 'https://management.test/disease-reports?view=reports');
    const storage = options.storage || new Map();
    const documentEvents = {};
    const dialogEvents = {};
    const microtasks = [];
    const frames = [];
    const messages = [];
    const scrolls = [];
    const resultMarkers = new Map();
    const message = {textContent: ''};
    const trigger = {isConnected: true, focusOptions: [], focus(value) { this.focusOptions.push(value); }};
    let otherDialogOpen = Boolean(options.otherDialogOpen);
    let validationAlert = Boolean(options.validationAlert);
    const dialog = {
        open: false,
        addEventListener(name, callback) { (dialogEvents[name] ||= []).push(callback); },
        showModal() { this.open = true; messages.push(message.textContent); },
        close() { this.open = false; (dialogEvents.close || []).forEach(callback => callback()); },
        getBoundingClientRect() { return {left: 10, top: 10, right: 200, bottom: 200}; }
    };
    const main = {
        querySelector(selector) {
            if (selector === '[role="alert"]') return validationAlert ? {} : null;
            const match = selector.match(/^\[data-empty-result="([^"]+)"\]$/);
            return match ? resultMarkers.get(match[1]) || null : null;
        }
    };
    const document = {
        activeElement: trigger,
        addEventListener(name, callback) { (documentEvents[name] ||= []).push(callback); },
        getElementById(id) {
            return id === 'empty-result-alert' ? (options.missingDialog ? null : dialog)
                : id === 'empty-result-message' ? message : id === 'main-content' ? main : null;
        },
        querySelector(selector) { return selector === 'dialog[open]' && (otherDialogOpen || dialog.open) ? {} : null; }
    };
    const window = {
        scrollX: options.x || 0, scrollY: options.y || 0,
        scrollTo(position) { scrolls.push(position); this.scrollX = position.left; this.scrollY = position.top; }
    };
    class FormData {
        constructor(form) { this.fields = form.fields || []; }
        forEach(callback) { this.fields.forEach(([key, value]) => callback(value, key)); }
    }
    const context = {
        window, document, location, URL, FormData,
        Date: {now: () => options.now ?? 1000000},
        sessionStorage: {
            getItem(key) { return storage.get(key) || null; },
            setItem(key, value) { storage.set(key, value); },
            removeItem(key) { storage.delete(key); }
        },
        queueMicrotask(callback) { microtasks.push(callback); },
        requestAnimationFrame(callback) { frames.push(callback); }
    };
    vm.runInNewContext(source, context);
    function element(tagName, attributes = {}) {
        return {
            tagName, dataset: {}, target: '', method: 'get', action: location.href, href: location.href,
            ...attributes,
            closest(selector) {
                if (selector === '[data-empty-guard]') return this.guarded ? this : this.guard || null;
                if (selector === 'a[data-empty-action]') return this.tagName === 'A' && this.dataset.emptyAction ? this : this.link || null;
                return null;
            },
            hasAttribute(name) { return name === 'download' && Boolean(this.download); }
        };
    }
    return {
        window, document, main, dialog, storage, messages, scrolls, trigger, element,
        marker(key, count, text) {
            const dataset = {};
            if (count !== undefined) dataset.emptyCount = String(count);
            if (text !== undefined) dataset.emptyMessage = text;
            resultMarkers.set(key, {dataset});
        },
        pending() { return JSON.parse(storage.get('rhu:empty-feedback:pending') || 'null'); },
        event(name, values = {}) {
            const event = {button: 0, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...values};
            (documentEvents[name] || []).forEach(callback => callback(event));
            return event;
        },
        dialogClick(values = {}) { (dialogEvents.click || []).forEach(callback => callback({target: dialog, ...values})); },
        flush() {
            while (microtasks.length) microtasks.shift()();
            while (frames.length) frames.shift()();
        },
        setOtherDialog(value) { otherDialogOpen = value; },
        setValidationAlert(value) { validationAlert = value; }
    };
}
JS;
        $process = new Process([$node]);
        $process->setInput('const source = '.$source.";\n".$harness."\n".$scenario);
        $process->mustRun();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_empty_results_show_feedback_only_after_an_explicit_action_and_populated_results_do_not(): void
    {
        $result = $this->evaluate(<<<'JS'
const app = page();
app.marker('history', 0);
app.event('DOMContentLoaded'); app.flush();
const initialMessages = app.messages.slice();
const shown = app.window.RhuFeedback.notify({key: 'history'});
app.dialog.close();
const ignored = [];
for (const count of [1, 2, -1, '', ' ', 'unknown', undefined]) {
    app.marker('history', count);
    ignored.push(app.window.RhuFeedback.notify({key: 'history'}));
}
app.marker('reports', 0, 'No reports match the selected filters.');
const custom = app.window.RhuFeedback.notify({key: 'reports'});
process.stdout.write(JSON.stringify({initialMessages, shown, ignored, custom, messages: app.messages}));
JS);
        $this->assertSame([], $result['initialMessages']);
        $this->assertTrue($result['shown']);
        $this->assertSame(array_fill(0, 7, false), $result['ignored']);
        $this->assertTrue($result['custom']);
        $this->assertSame(['No existing history yet.', 'No reports match the selected filters.'], $result['messages']);
    }

    public function test_history_get_checks_the_destination_after_filters_change_and_consumes_feedback_once(): void
    {
        $result = $this->evaluate(<<<'JS'
const sourcePage = page();
sourcePage.marker('history', 0);
const form = sourcePage.element('FORM', {dataset: {emptyAction: 'history'},
    fields: [['view', 'reports'], ['year', '2026']], action: '/disease-reports#report-history'});
sourcePage.event('submit', {target: form}); sourcePage.flush();
const pending = sourcePage.pending();
const populated = page({storage: new Map(sourcePage.storage), url: 'https://management.test/disease-reports?year=2026&view=reports'});
populated.marker('history', 1);
populated.event('DOMContentLoaded'); populated.flush();
const empty = page({storage: sourcePage.storage, url: 'https://management.test/disease-reports?year=2026&view=reports#report-history'});
empty.marker('history', 0);
empty.event('DOMContentLoaded'); empty.flush(); empty.dialog.close();
empty.event('DOMContentLoaded'); empty.flush();
process.stdout.write(JSON.stringify({sourceMessages: sourcePage.messages, destination: pending.destination,
    populatedMessages: populated.messages, messages: empty.messages, pending: empty.pending()}));
JS);
        $this->assertSame([], $result['sourceMessages']);
        $this->assertSame('https://management.test/disease-reports?view=reports&year=2026', $result['destination']);
        $this->assertSame([], $result['populatedMessages']);
        $this->assertSame(['No existing history yet.'], $result['messages']);
        $this->assertNull($result['pending']);
    }

    public function test_prevented_ajax_submissions_and_unrelated_or_modified_links_do_not_store_feedback(): void
    {
        $result = $this->evaluate(<<<'JS'
const app = page();
const form = app.element('FORM', {dataset: {emptyAction: 'history'}, fields: [['year', '2026']]});
const submit = app.event('submit', {target: form});
submit.preventDefault(); app.flush();
const submitPending = app.pending();
const ignored = [];
for (const attributes of [{target: '_blank'}, {download: true}, {href: 'https://external.test/history'}]) {
    const link = app.element('A', {dataset: {emptyAction: 'history'}, ...attributes});
    app.event('click', {target: link}); app.flush(); ignored.push(app.pending());
}
const link = app.element('A', {dataset: {emptyAction: 'history'}});
app.event('click', {target: link, ctrlKey: true}); app.flush(); ignored.push(app.pending());
const click = app.event('click', {target: link}); click.preventDefault(); app.flush(); ignored.push(app.pending());
process.stdout.write(JSON.stringify({submitPending, ignored, messages: app.messages}));
JS);
        $this->assertNull($result['submitPending']);
        $this->assertSame(array_fill(0, 5, null), $result['ignored']);
        $this->assertSame([], $result['messages']);
    }

    public function test_expired_or_mismatched_intents_are_discarded_without_later_surprise_alerts(): void
    {
        $result = $this->evaluate(<<<'JS'
const sourcePage = page({now: 1000});
const form = sourcePage.element('FORM', {dataset: {emptyAction: 'history'}, fields: [['year', '2026']]});
sourcePage.event('submit', {target: form}); sourcePage.flush();
const expired = page({now: 121001, storage: new Map(sourcePage.storage), url: 'https://management.test/disease-reports?year=2026'});
expired.marker('history', 0); expired.event('DOMContentLoaded'); expired.flush();
const mismatch = page({now: 2000, storage: sourcePage.storage, url: 'https://management.test/disease-reports?year=2025'});
mismatch.marker('history', 0); mismatch.event('DOMContentLoaded'); mismatch.flush();
const later = page({now: 3000, storage: sourcePage.storage, url: 'https://management.test/disease-reports?year=2026'});
later.marker('history', 0); later.event('DOMContentLoaded'); later.flush();
process.stdout.write(JSON.stringify({expired: expired.messages, mismatch: mismatch.messages, later: later.messages,
    expiredPending: expired.pending(), mismatchPending: mismatch.pending()}));
JS);
        $this->assertSame([], $result['expired']);
        $this->assertSame([], $result['mismatch']);
        $this->assertSame([], $result['later']);
        $this->assertNull($result['expiredPending']);
        $this->assertNull($result['mismatchPending']);
    }

    public function test_validation_and_existing_dialogs_take_priority_over_empty_feedback(): void
    {
        $result = $this->evaluate(<<<'JS'
const app = page({validationAlert: true}); app.marker('history', 0);
const validation = app.window.RhuFeedback.notify({key: 'history'});
app.setValidationAlert(false); app.setOtherDialog(true);
const dialog = app.window.RhuFeedback.notify({key: 'history'});
app.setOtherDialog(false);
const shown = app.window.RhuFeedback.notify({key: 'history'});
const duplicate = app.window.RhuFeedback.notify({key: 'history'});
process.stdout.write(JSON.stringify({validation, dialog, shown, duplicate, messages: app.messages}));
JS);
        $this->assertFalse($result['validation']);
        $this->assertFalse($result['dialog']);
        $this->assertTrue($result['shown']);
        $this->assertFalse($result['duplicate']);
        $this->assertSame(['No existing history yet.'], $result['messages']);
    }

    public function test_known_empty_view_all_links_alert_without_navigation_and_closing_keeps_focus_and_scroll(): void
    {
        $result = $this->evaluate(<<<'JS'
const app = page({x: 12, y: 780});
const link = app.element('A', {guarded: true, dataset: {emptyCount: '0', emptyMessage: 'No requests to show yet.'}});
const click = app.event('click', {target: link}); app.flush();
app.window.scrollY = 0;
app.dialogClick({clientX: 50, clientY: 50});
const insideStillOpen = app.dialog.open;
app.dialogClick({clientX: 5, clientY: 5});
const populated = app.element('A', {guarded: true, dataset: {emptyCount: '1'}});
const allowed = app.event('click', {target: populated}); app.flush();
process.stdout.write(JSON.stringify({prevented: click.defaultPrevented, messages: app.messages, insideStillOpen,
    open: app.dialog.open, scrolls: app.scrolls, focusOptions: app.trigger.focusOptions, allowed: !allowed.defaultPrevented}));
JS);
        $this->assertTrue($result['prevented']);
        $this->assertSame(['No requests to show yet.'], $result['messages']);
        $this->assertTrue($result['insideStillOpen']);
        $this->assertFalse($result['open']);
        $this->assertSame([['left' => 12, 'top' => 780, 'behavior' => 'instant']], $result['scrolls']);
        $this->assertSame([['preventScroll' => true]], $result['focusOptions']);
        $this->assertTrue($result['allowed']);
    }
}
