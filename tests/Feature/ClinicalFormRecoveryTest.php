<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\PublicUser;
use App\Models\User;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ClinicalFormRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private PublicUser $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-15 14:00:00', 'Asia/Manila'));
        $unit = HealthUnit::create(['name' => 'Clinical recovery RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $this->patient = PublicUser::create(['health_unit_id' => $unit->id, 'assigned_midwife_id' => $midwife->id,
            'first_name' => 'Fictional', 'last_name' => 'Recovery patient', 'birth_date' => '2000-01-01',
            'barangay' => 'Recovery village', 'address' => 'Fictional address', 'is_pregnant' => true]);
        $this->withSession(['user_id' => $midwife->id]);
    }

    public static function rejectedForms(): array
    {
        return [
            'prenatal' => ['prenatal', 'prenatal-modal', 'prenatal-errors', 'prenatal_visits', 'next_visit_date', [
                'visit_date' => '2026-10-03', 'next_visit_date' => '2026-10-01', 'trimester' => '2nd',
                'blood_pressure' => '110/70', 'weight' => '58.25', 'fundal_height' => '20.5',
                'fetal_heart_tone' => '140 bpm', 'tt_dose' => 'TT2', 'remarks' => 'Keep <entered> prenatal details.',
            ]],
            'postpartum' => ['postpartum', 'pp-modal', 'postpartum-errors', 'postpartum_visits', 'visit_date', [
                'delivery_date' => '2026-10-03', 'visit_date' => '2026-10-01', 'next_visit_date' => '2026-10-05',
                'place_of_delivery' => 'Fictional RHU', 'attended_by' => 'Fictional Midwife',
                'visit_number' => '3', 'remarks' => 'Keep <entered> postpartum details.',
            ]],
            'family planning' => ['family_planning', 'fp-modal', 'family-planning-errors', 'family_planning', 'next_supply_date', [
                'method' => 'Pills', 'date_accepted' => '2026-10-03', 'next_supply_date' => '2026-10-01',
                'remarks' => 'Keep <entered> family planning details.',
            ]],
        ];
    }

    #[DataProvider('rejectedForms')]
    public function test_rejected_save_preserves_every_entered_field_explains_errors_and_reopens_modal(
        string $route, string $modalId, string $errorId, string $table, string $errorField, array $input
    ): void {
        $input['patient_id'] = (string) $this->patient->id;
        $this->from(route($route.'.index'))->post(route($route.'.store'), $input)
            ->assertRedirect(route($route.'.index'))->assertSessionHasErrors($errorField);
        $this->assertDatabaseCount($table, 0);
        $page = $this->get(route($route.'.index'))->assertOk();

        $dom = new DOMDocument;
        $dom->loadHTML($page->getContent(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);
        $summary = $xpath->query('//*[@id="'.$errorId.'" and @role="alert"]');
        $this->assertSame(1, $summary->length);
        $this->assertSame(1, $xpath->query('//dialog[@id="'.$modalId.'" and @aria-labelledby]')->length);
        $this->assertGreaterThan(0, $xpath->query('.//li', $summary->item(0))->length);
        foreach ($input as $name => $value) {
            $controls = $xpath->query('//*[@id="'.$modalId.'"]//*[@name="'.$name.'"]');
            $this->assertSame(1, $controls->length, $name);
            $control = $controls->item(0);
            $this->assertSame(1, $xpath->query('//label[@for="'.$control->getAttribute('id').'"]')->length, $name);
            $actual = match ($control->nodeName) {
                'textarea' => $control->textContent,
                'select' => $xpath->query('./option[@selected]', $control)->item(0)?->getAttribute('value'),
                default => $control->getAttribute('value'),
            };
            $this->assertSame($value, $actual, $name.' must survive the rejected save');
        }

        $state = $this->runModalScript($page->getContent(), $modalId);
        $this->assertTrue($state['open']);
        $this->assertSame($errorId, $state['focused']);
    }

    public function test_historical_followups_save_and_browser_minimum_follows_the_selected_visit_date(): void
    {
        foreach (['prenatal' => 'prenatal', 'postpartum' => 'postpartum'] as $route => $prefix) {
            $payload = ['patient_id' => $this->patient->id, 'visit_date' => '2026-10-03', 'next_visit_date' => '2026-10-05'];
            if ($route === 'postpartum') {
                $payload['delivery_date'] = '2026-10-01';
            }
            $this->from(route($route.'.index'))->post(route($route.'.store'), $payload)
                ->assertSessionHasNoErrors()->assertSessionHas('success');
            $table = $route === 'prenatal' ? 'prenatal_visits' : 'postpartum_visits';
            $this->assertDatabaseHas($table, ['patient_id' => $this->patient->id,
                'visit_date' => '2026-10-03', 'next_visit_date' => '2026-10-05']);
            $page = $this->get(route($route.'.index'))->assertOk();
            $modalId = $route === 'prenatal' ? 'prenatal-modal' : 'pp-modal';
            $state = $this->runModalScript($page->getContent(), $modalId, $prefix);
            $this->assertFalse($state['open'], 'Successful saves leave the dialog closed');
            $this->assertSame('2026-10-04', $state['initialMinimum']);
            $this->assertSame('2027-01-01', $state['changedMinimum']);
            $this->assertNull($state['clearedMinimum']);
        }
    }

    private function runModalScript(string $html, string $modalId, ?string $datePrefix = null): array
    {
        $node = (new ExecutableFinder)->find('node');
        if ($node === null) {
            $this->markTestSkipped('Node.js is required for clinical form interaction checks.');
        }
        preg_match_all('/<script>(.*?)<\/script>/s', $html, $scripts);
        $source = collect($scripts[1])->first(fn ($script) => str_contains($script, 'function openModal()'));
        $this->assertNotNull($source);
        $script = json_encode($source, JSON_THROW_ON_ERROR);
        $modal = json_encode($modalId, JSON_THROW_ON_ERROR);
        $prefix = json_encode($datePrefix, JSON_THROW_ON_ERROR);
        $process = new Process([$node]);
        $process->setInput(<<<JS
const vm = require('node:vm');
const modalId = $modal;
const prefix = $prefix;
const nodes = {};
let focused = null;
const document = {
    addEventListener() {},
    getElementById(id) {
        return nodes[id] ??= {value: id.endsWith('-visit-date') ? '2026-10-03' : '', open: false, callbacks: {},
            showModal() {this.open = true;}, close() {this.open = false;},
            addEventListener(event, handler) {this.callbacks[event] = handler;},
            removeAttribute(name) {this[name] = null;}, focus() {focused = id;}};
    },
};
vm.runInNewContext($script, {document, Date});
const state = {open: document.getElementById(modalId).open, focused};
if (prefix) {
    const visit = document.getElementById(prefix + '-visit-date');
    const next = document.getElementById(prefix + '-next-visit-date');
    state.initialMinimum = next.min;
    visit.value = '2026-12-31';
    visit.callbacks.input();
    state.changedMinimum = next.min;
    visit.value = '';
    visit.callbacks.input();
    state.clearedMinimum = next.min;
}
process.stdout.write(JSON.stringify(state));
JS);
        $process->mustRun();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }
}
