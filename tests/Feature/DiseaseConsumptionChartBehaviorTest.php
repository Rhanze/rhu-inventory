<?php

namespace Tests\Feature;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DiseaseConsumptionChartBehaviorTest extends TestCase
{
    private function evaluate(string $scenario): array
    {
        $node = (new ExecutableFinder)->find('node');
        if ($node === null) {
            $this->markTestSkipped('Node.js is required for consumption chart behavior checks.');
        }
        $source = json_encode(file_get_contents(public_path('js/disease-consumption-charts.js')), JSON_THROW_ON_ERROR);
        $harness = <<<'JS'
const vm = require('node:vm');
const history = quantities => quantities.map((quantity, index) => ({start: '2026-10-' + (12 + index * 7), end: '2026-10-' + (18 + index * 7),
    included_start: '2026-10-' + (12 + index * 7), included_end: '2026-10-' + (18 + index * 7), quantity, transactions: quantity === null ? 0 : 1}));
const medicines = [
    {id: 1, name: 'New liquid', dosage: '5 mg', dosage_form: 'liquid', unit: 'mL', status: 'insufficient', diseases: ['First condition'],
        current_quantity: 3, baseline_average: null, change_percent: null, total_quantity: 3, history: history([null, 3])},
    {id: 2, name: 'Rising tablets', dosage: '10 mg', dosage_form: 'tablet', unit: 'tablet', status: 'elevated', diseases: ['First condition', 'Second condition'],
        current_quantity: 7, baseline_average: 4, change_percent: 75, total_quantity: 11, history: history([4, 7])},
    {id: 3, name: 'Typical capsules', dosage: '', dosage_form: 'capsule', unit: 'capsule', status: 'typical', diseases: ['Second condition'],
        current_quantity: 8, baseline_average: 10, change_percent: -20, total_quantity: 8, history: history([null, 8])}
];
function page(options = {}) {
    const elements = new Map();
    function element(id) {return {id, textContent: '', hidden: false, style: {}, children: [], value: '', events: {},
        replaceChildren() {this.children = [];}, appendChild(child) {this.children.push(child);},
        addEventListener(name, callback) {this.events[name] = callback;}};}
    for (const id of ['disease-consumption-chart-data', 'consumption-chart-medicine', 'consumption-chart-associations',
        'consumption-graph-unit', 'consumption-graph-rows', 'consumption-chart-summary', 'consumption-chart-unavailable',
        'diseaseConsumptionHistoryChart', 'consumption-comparison-wrap', 'consumption-comparison-note',
        'consumption-chart-mode', 'consumption-graph-dates']) elements.set(id, element(id));
    if (!options.historical) elements.set('diseaseConsumptionChangeChart', element('diseaseConsumptionChangeChart'));
    elements.get('consumption-chart-unavailable').hidden = true;
    elements.get('disease-consumption-chart-data').textContent = JSON.stringify({medicines: options.medicines || medicines,
        historical: !!options.historical, period_type: options.period_type || 'weekly', threshold_percent: 50,
        default_mode: options.default_mode || 'periods', saved_history: !!options.saved_history,
        window: options.window || {from: '2026-10-10', to: '2026-10-10'}});
    const charts = [];
    class Chart {
        constructor(canvas, config) {this.id = canvas.id; this.config = config; this.destroyed = false; charts.push(this);}
        destroy() {this.destroyed = true;}
    }
    const context = {document: {getElementById: id => elements.get(id) || null, createElement: tag => element(tag)},
        window: {matchMedia: () => ({matches: true})}, Intl};
    if (!options.missingChart) context.Chart = Chart;
    vm.runInNewContext(source, context);
    return {elements, charts, select(id) {const selector = elements.get('consumption-chart-medicine'); selector.value = String(id); selector.events.change();},
        mode(value) {const selector = elements.get('consumption-chart-mode'); selector.value = value; selector.events.change();},
        rows() {return elements.get('consumption-graph-rows').children.map(row => row.children.map(cell => cell.textContent));}};
}
JS;
        $process = new Process([$node]);
        $process->setInput('const source = '.$source.";\n".$harness."\n".$scenario);
        $process->mustRun();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_same_date_transactions_are_separate_bars_and_switch_back_to_the_true_weekly_total(): void
    {
        $result = $this->evaluate(<<<'JS'
const medicine = {...medicines[1], diseases: [], status: 'historical', total_quantity: 23,
    history: [{start:'2026-10-05',end:'2026-10-11',included_start:'2026-10-10',included_end:'2026-10-10',quantity:23,transactions:2}],
    transactions_data:[{date:'2026-10-10',quantity:9,record_id:41},{date:'2026-10-10',quantity:14,record_id:42}]};
const app = page({historical:true, saved_history:true, default_mode:'transactions', medicines:[medicine]});
const initial = {type:app.charts[0].config.type, data:app.charts[0].config.data, rows:app.rows(),
    associations:app.elements.get('consumption-chart-associations').textContent,
    summary:app.elements.get('consumption-chart-summary').textContent};
app.mode('periods');
process.stdout.write(JSON.stringify({initial,weeklyType:app.charts[1].config.type,weeklyData:app.charts[1].config.data,
    weeklyRows:app.rows()}));
JS);
        $this->assertSame('bar', $result['initial']['type']);
        $this->assertSame([9, 14], $result['initial']['data']['datasets'][0]['data']);
        $this->assertSame([['2026-10-10', 'Record 1'], ['2026-10-10', 'Record 2']], $result['initial']['data']['labels']);
        $this->assertSame([['2026-10-10 / record 1', '9', '1'], ['2026-10-10 / record 2', '14', '1']], $result['initial']['rows']);
        $this->assertStringContainsString('No configured disease association', $result['initial']['associations']);
        $this->assertStringContainsString('2 saved dispensing transactions, totaling 23 tablet', $result['initial']['summary']);
        $this->assertStringContainsString('not a time-trend estimate', $result['initial']['summary']);
        $this->assertSame('line', $result['weeklyType']);
        $this->assertSame([23], $result['weeklyData']['datasets'][0]['data']);
        $this->assertSame([['2026-10-10 to 2026-10-10', '23', '2']], $result['weeklyRows']);
    }

    public function test_switching_medicine_updates_chart_units_associations_rows_and_baseline(): void
    {
        $result = $this->evaluate(<<<'JS'
const app = page();
const initial = {selected: app.elements.get('consumption-chart-medicine').value,
    associations: app.elements.get('consumption-chart-associations').textContent,
    data: app.charts[0].config.data, comparison: app.charts[1].config.data};
app.select(1);
const latest = app.charts[2];
process.stdout.write(JSON.stringify({initial, oldDestroyed: app.charts[0].destroyed, data: latest.config.data,
    yLabel: latest.config.options.scales.y.title.text, rows: app.rows(),
    summary: app.elements.get('consumption-chart-summary').textContent,
    associations: app.elements.get('consumption-chart-associations').textContent}));
JS);
        $this->assertSame('2', $result['initial']['selected']);
        $this->assertStringContainsString('First condition, Second condition', $result['initial']['associations']);
        $this->assertSame([4, 7], $result['initial']['data']['datasets'][0]['data']);
        $this->assertSame([4, 4], $result['initial']['data']['datasets'][1]['data']);
        $this->assertSame([75, -20], $result['initial']['comparison']['datasets'][0]['data']);
        $this->assertTrue($result['oldDestroyed']);
        $this->assertCount(1, $result['data']['datasets']);
        $this->assertSame([null, 3], $result['data']['datasets'][0]['data']);
        $this->assertSame('Consumed (mL)', $result['yLabel']);
        $this->assertSame('Before first recorded consumption', $result['rows'][0][1]);
        $this->assertSame(['2026-10-19 to 2026-10-25', '3', '1'], $result['rows'][1]);
        $this->assertStringContainsString('3 mL', $result['summary']);
        $this->assertStringContainsString('Four prior completed periods are needed', $result['summary']);
        $this->assertSame('Associated diseases: First condition', $result['associations']);
    }

    public function test_historical_chart_keeps_exact_selected_dates_and_does_not_show_baseline_or_change_chart(): void
    {
        $result = $this->evaluate(<<<'JS'
const medicine = {...medicines[1], total_quantity: 5,
    history: [{start: '2024-01-01', end: '2024-01-31', included_start: '2024-01-15', included_end: '2024-01-15', quantity: 5, transactions: 1}]};
const app = page({historical: true, period_type: 'monthly', medicines: [medicine]});
const chart = app.charts[0];
process.stdout.write(JSON.stringify({chartCount: app.charts.length, data: chart.config.data, rows: app.rows(),
    title: chart.config.options.plugins.tooltip.callbacks.title([{dataIndex: 0}]),
    summary: app.elements.get('consumption-chart-summary').textContent}));
JS);
        $this->assertSame(1, $result['chartCount']);
        $this->assertCount(1, $result['data']['datasets']);
        $this->assertSame([5], $result['data']['datasets'][0]['data']);
        $this->assertSame([['2024-01-15 to 2024-01-15', '5', '1']], $result['rows']);
        $this->assertSame('2024-01-15 to 2024-01-15', $result['title']);
        $this->assertStringContainsString('Selected dates only: 5 tablet consumed', $result['summary']);
    }

    public function test_missing_chart_library_leaves_quantities_and_selection_available(): void
    {
        $result = $this->evaluate(<<<'JS'
const app = page({missingChart: true});
app.select(1);
process.stdout.write(JSON.stringify({unavailable: !app.elements.get('consumption-chart-unavailable').hidden,
    rows: app.rows(), charts: app.charts.length, unit: app.elements.get('consumption-graph-unit').textContent}));
JS);
        $this->assertTrue($result['unavailable']);
        $this->assertSame(0, $result['charts']);
        $this->assertSame('Consumed (mL)', $result['unit']);
        $this->assertSame('3', $result['rows'][1][1]);
    }

    public function test_zero_baseline_or_short_history_is_not_rendered_as_a_percentage_bar(): void
    {
        $result = $this->evaluate(<<<'JS'
const app = page({medicines: [medicines[0], {...medicines[1], status: 'new', baseline_average: 0, change_percent: null}]});
process.stdout.write(JSON.stringify({chartCount: app.charts.length, hidden: app.elements.get('consumption-comparison-wrap').hidden,
    note: app.elements.get('consumption-comparison-note').textContent}));
JS);
        $this->assertSame(1, $result['chartCount']);
        $this->assertTrue($result['hidden']);
        $this->assertStringContainsString('No percentage comparisons yet', $result['note']);
    }

    public function test_initial_selection_uses_recorded_consumption_when_the_first_medicine_has_no_history(): void
    {
        $result = $this->evaluate(<<<'JS'
const emptyMedicine = {...medicines[0], history: history([null, null]), total_quantity: 0};
const recordedMedicine = {...medicines[1], status: 'typical'};
const app = page({medicines: [emptyMedicine, recordedMedicine]});
process.stdout.write(JSON.stringify({selected: app.elements.get('consumption-chart-medicine').value,
    quantities: app.charts[0].config.data.datasets[0].data}));
JS);
        $this->assertSame('2', $result['selected']);
        $this->assertSame([4, 7], $result['quantities']);
    }
}
