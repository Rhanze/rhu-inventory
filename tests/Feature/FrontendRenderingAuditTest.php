<?php

namespace Tests\Feature;

use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FrontendRenderingAuditTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = '<img src=x onerror=alert(1)>';

    private function evaluate(string $script): array
    {
        $node = (new ExecutableFinder)->find('node');
        if ($node === null) {
            $this->markTestSkipped('Node.js is required to execute the JavaScript rendering checks.');
        }
        $process = new Process([$node]);
        $process->setInput($script);
        $process->mustRun();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function functionSource(string $view, string $name, string $indent = ''): string
    {
        $source = file_get_contents(resource_path('views/'.$view.'.blade.php'));
        $pattern = '/'.preg_quote($indent, '/').'function '.preg_quote($name, '/').'\([^\n]*\) \{.*?\r?\n'.preg_quote($indent, '/').'\}/s';
        $this->assertSame(1, preg_match($pattern, $source, $matches), $view.' function '.$name.' must be available');

        return $matches[0];
    }

    public function test_dashboard_embeds_chart_data_safely_and_renders_stored_labels_as_text(): void
    {
        $unit = HealthUnit::create(['name' => self::PAYLOAD, 'facility_type' => 'Rural Health Unit']);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => self::PAYLOAD, 'dosage' => self::PAYLOAD, 'category' => self::PAYLOAD]);
        DispensingRecord::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id, 'dispensed_by' => $staff->id,
            'quantity_dispensed' => 10, 'date_dispensed' => today()->subDay()]);
        $pho = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $response = $this->withSession(['user_id' => $pho->id])->get(route('dashboard'))->assertOk();
        preg_match_all('/<script>(.*?)<\/script>/s', $response->getContent(), $scripts);
        $script = collect($scripts[1])->first(fn ($source) => str_contains($source, 'const utilizationDetails'));
        $this->assertNotNull($script);
        $this->assertStringNotContainsString(self::PAYLOAD, $script);
        $encodedScript = json_encode($script, JSON_THROW_ON_ERROR);
        $result = $this->evaluate(<<<JS
const vm = require('node:vm');
const tbody = {innerHTML: ''};
const callbacks = [];
const document = {getElementById: id => id === 'utilizationTableBody' ? tbody : null,
    addEventListener: (event, callback) => callbacks.push(callback)};
vm.runInNewContext($encodedScript, {document});
callbacks.forEach(callback => callback());
process.stdout.write(JSON.stringify({html: tbody.innerHTML}));
JS);
        $this->assertStringContainsString('&lt;img', $result['html']);
        $this->assertStringNotContainsString('<img', $result['html']);
        $this->assertGreaterThanOrEqual(4, substr_count($result['html'], '&lt;img'));
    }

    public function test_packaging_previews_never_parse_stored_unit_or_pack_names_as_html(): void
    {
        foreach (['dispensing/create', 'dispensing/edit', 'allocation/create'] as $view) {
            $functions = $this->functionSource($view, 'pluralUnit', '    ')."\n".$this->functionSource($view, 'updateQtyPreview', '    ');
            $payload = json_encode(self::PAYLOAD, JSON_THROW_ON_ERROR);
            $result = $this->evaluate(<<<JS
const inventorySelect = {selectedIndex: 0, options: [{value: '1', dataset: {unit: $payload, packType: $payload, piecesPerPack: '10'}}]};
const qtyInput = {value: '23'};
const qtyHelp = {textContent: '', style: {}};
Object.defineProperty(qtyHelp, 'innerHTML', {set() {throw new Error('Packaging was parsed as HTML');}});
$functions
updateQtyPreview();
process.stdout.write(JSON.stringify({text: qtyHelp.textContent}));
JS);
            $this->assertStringContainsString(self::PAYLOAD, $result['text']);
            $this->assertStringContainsString('23', $result['text']);
        }
    }

    public function test_transfer_and_supply_quantity_helpers_keep_units_as_text(): void
    {
        foreach (['transfers/create', 'transfers/distribute', 'supply_requests/create'] as $view) {
            $function = $this->functionSource($view, 'renderQtyHelp');
            $payload = json_encode(self::PAYLOAD, JSON_THROW_ON_ERROR);
            $result = $this->evaluate(<<<JS
const pickedMedicine = {unit: $payload};
const help = {textContent: '', style: {}};
Object.defineProperty(help, 'innerHTML', {set() {throw new Error('Unit was parsed as HTML');}});
const document = {getElementById: id => id === 'qty-help' ? help : {value: '10'}};
$function
renderQtyHelp();
process.stdout.write(JSON.stringify({text: help.textContent}));
JS);
            $this->assertStringContainsString(self::PAYLOAD, $result['text']);
        }
    }

    public function test_stock_lookup_renders_all_stored_metadata_and_facility_names_as_text(): void
    {
        $functions = implode("\n", array_map(fn ($function) => $this->functionSource('stock_lookup/index', $function),
            ['escapeHtml', 'highlightMatch', 'renderSuggestions', 'renderResults']));
        $payload = json_encode(self::PAYLOAD, JSON_THROW_ON_ERROR);
        $result = $this->evaluate(<<<JS
const nodes = {};
const document = {getElementById: id => nodes[id] ??= {innerHTML: '', style: {}}};
$functions
const medicine = {id: 1, medicine_name: $payload, dosage: $payload, dosage_form: $payload, category: $payload};
renderSuggestions([medicine], '');
renderResults({medicine, results: [{rhu_id: 2, rhu_name: $payload, municipality: $payload, distance_km: 2, available_stock: 10}]});
process.stdout.write(JSON.stringify({html: Object.values(nodes).map(node => node.innerHTML).join('')}));
JS);
        $this->assertStringContainsString('&lt;img', $result['html']);
        $this->assertStringNotContainsString('<img', $result['html']);
    }

    public function test_stock_lookup_requests_open_a_prefilled_transfer_form_only_for_rhu_users(): void
    {
        $source = HealthUnit::create(['name' => 'Lookup source', 'facility_type' => 'Rural Health Unit']);
        $destination = HealthUnit::create(['name' => 'Lookup destination', 'facility_type' => 'Rural Health Unit']);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $destination->id]);
        $pho = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $medicine = Medicine::create(['medicine_name' => 'Lookup medicine']);
        $batch = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $source->id, 'available_stock' => 20]);
        $filters = ['source_rhu_id' => $source->id, 'medicine_id' => $medicine->id];
        $createTransfer = route('transfers.create', $filters);

        foreach ([$staff, $pho] as $viewer) {
            $lookup = $this->withSession(['user_id' => $viewer->id])->get(route('stock_lookup.index'))->assertOk();
            preg_match_all('/<script>(.*?)<\/script>/s', $lookup->getContent(), $scripts);
            $script = collect($scripts[1])->first(fn ($source) => str_contains($source, 'function renderResults'));
            $this->assertNotNull($script);
            $data = $this->getJson(route('stock_lookup.search', ['medicine_id' => $medicine->id]))->assertOk()->json();
            $this->assertSame(20, $data['results'][0]['available_stock']);
            $encodedScript = json_encode($script, JSON_THROW_ON_ERROR);
            $encodedData = json_encode($data, JSON_THROW_ON_ERROR);
            $result = $this->evaluate(<<<JS
const vm = require('node:vm');
const nodes = {};
const document = {addEventListener() {}, getElementById: id => nodes[id] ??= {innerHTML: '', style: {}}};
const context = {document};
vm.createContext(context);
vm.runInContext($encodedScript, context);
context.renderResults($encodedData);
process.stdout.write(JSON.stringify({html: nodes['result-body'].innerHTML}));
JS);

            if ($viewer->health_unit_id === null) {
                $this->get($createTransfer)->assertRedirect(route('transfers.index'));
                $this->post(route('stock_lookup.request'), $filters)->assertRedirect(route('transfers.index'));
                $this->assertStringNotContainsString('Request stock', $result['html']);

                continue;
            }

            $this->assertStringContainsString('Request stock', $result['html']);
            $this->assertStringContainsString(htmlspecialchars($createTransfer), $result['html']);
            $this->post(route('stock_lookup.request'), $filters)->assertRedirect($createTransfer);
            $this->get($createTransfer)->assertOk()->assertViewHas('previousMedicine', fn ($item) => $item['id'] === $medicine->id)
                ->assertViewHas('selectedFacilityId', fn ($id) => (int) $id === $source->id);
            $this->post(route('transfers.store'), $filters + ['quantity' => 5])
                ->assertRedirect(route('transfers.index'))->assertSessionHas('success');
        }

        $this->assertDatabaseHas('transfer_requests', ['medicine_id' => $medicine->id, 'quantity' => 5, 'status' => 'pending',
            'from_health_unit_id' => $source->id, 'to_health_unit_id' => $destination->id, 'requested_by' => $staff->id]);
        $this->assertDatabaseCount('transfer_requests', 1);
        $this->assertSame(20, $batch->fresh()->available_stock);
    }

    public function test_notification_actions_preserve_rejection_text_and_surface_failed_requests(): void
    {
        $unit = HealthUnit::create(['name' => 'Notification frontend RHU']);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $response = $this->withSession(['user_id' => $staff->id])->get(route('notifications.index'))->assertOk();
        preg_match_all('/<script>(.*?)<\/script>/s', $response->getContent(), $scripts);
        $script = collect($scripts[1])->first(fn ($source) => str_contains($source, 'async function updateNotification'));
        $this->assertNotNull($script);
        $encodedScript = json_encode($script, JSON_THROW_ON_ERROR);
        $payload = json_encode('&quot; <img src=x> &amp; exact rejection', JSON_THROW_ON_ERROR);
        $result = $this->evaluate(<<<JS
const vm = require('node:vm');
const forms = [];
const alerts = [];
let reloads = 0;
let remembered = 0;
const document = {
    getElementById: id => ({id}),
    createElement: tag => {
        const node = {tag, children: [], appendChild(child) {this.children.push(child);}, submit() {this.submitted = true;}};
        Object.defineProperty(node, 'innerHTML', {set() {throw new Error('Rejection was parsed as HTML');}});
        return node;
    },
    body: {appendChild(form) {forms.push(form);}}
};
const context = {document, window: {RhuNavigation: {remember() {remembered++;}}}, location: {reload() {reloads++;}}, alert: message => alerts.push(message), encodeURIComponent,
    fetch: async () => ({ok: false, json: async () => ({success: false, message: 'Session expired'})})};
vm.createContext(context);
vm.runInContext($encodedScript, context);
context.submitNotificationRejection('/review', $payload);
(async () => {
    await context.updateNotification('/archive');
    const failedReloads = reloads;
    context.fetch = async () => ({ok: true, json: async () => ({success: true})});
    await context.updateNotification('/archive');
    process.stdout.write(JSON.stringify({reason: forms[0].children.find(input => input.name === 'rejection_reason').value,
        submitted: forms[0].submitted, remembered, failedReloads, reloads, alerts}));
})().catch(error => {process.stderr.write(error.stack); process.exitCode = 1;});
JS);
        $this->assertSame('&quot; <img src=x> &amp; exact rejection', $result['reason']);
        $this->assertTrue($result['submitted']);
        $this->assertSame(1, $result['remembered']);
        $this->assertSame(0, $result['failedReloads']);
        $this->assertSame(1, $result['reloads']);
        $this->assertSame(['Session expired'], $result['alerts']);
    }
}
