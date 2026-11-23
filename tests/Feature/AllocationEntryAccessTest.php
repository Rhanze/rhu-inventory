<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\MidwifeBarangay;
use App\Models\StockLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllocationEntryAccessTest extends TestCase
{
    use RefreshDatabase;

    private function document($response): \DOMXPath
    {
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return new \DOMXPath($document);
    }

    public function test_pho_add_action_opens_the_provincial_workflow_and_moves_only_its_own_unexpired_batches(): void
    {
        $pho = HealthUnit::create(['name' => 'Provincial Health Office', 'facility_type' => 'Provincial Health Office']);
        $rhu = HealthUnit::create(['name' => 'Allocation receiving RHU', 'facility_type' => 'Rural Health Unit']);
        $viewer = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $medicine = Medicine::create(['medicine_name' => 'Allocation entry medicine', 'unit' => 'tablet']);
        $early = Inventory::create(['health_unit_id' => $pho->id, 'medicine_id' => $medicine->id,
            'batch_no' => 'ENTRY-EARLY', 'available_stock' => 10, 'expiration_date' => today()->addMonth()]);
        $later = Inventory::create(['health_unit_id' => $pho->id, 'medicine_id' => $medicine->id,
            'batch_no' => 'ENTRY-LATER', 'available_stock' => 20, 'expiration_date' => today()->addMonths(2)]);
        $expired = Inventory::create(['health_unit_id' => $pho->id, 'medicine_id' => $medicine->id,
            'batch_no' => 'ENTRY-EXPIRED', 'available_stock' => 50, 'expiration_date' => today()->subDay()]);
        $expiredOnly = Medicine::create(['medicine_name' => 'Expired PHO stock']);
        Inventory::create(['health_unit_id' => $pho->id, 'medicine_id' => $expiredOnly->id,
            'available_stock' => 50, 'expiration_date' => today()->subDay()]);
        $otherUnitOnly = Medicine::create(['medicine_name' => 'RHU stock only']);
        Inventory::create(['health_unit_id' => $rhu->id, 'medicine_id' => $otherUnitOnly->id, 'available_stock' => 20]);
        $page = $this->withSession(['user_id' => $viewer->id])->get(route('allocation.index'))->assertOk()
            ->assertSee('class="csv-import-link"', false);
        $links = $this->document($page)->query('//a[@href="'.route('allocation.province').'"][contains(normalize-space(.), "Add Allocation")]');
        $this->assertSame(1, $links->length);
        $this->get(route('allocation.create'))->assertRedirect(route('allocation.province'));
        $form = $this->get(route('allocation.province'))->assertOk()->assertDontSee('class="csv-import-link"', false);
        $fields = $this->document($form);
        $this->assertSame(1, $fields->query('//form[@action="'.route('allocation.province.store').'"]')->length);
        $medicineOptions = $fields->query('//select[@name="medicine_id"]/option[@value!=""]');
        $this->assertSame(1, $medicineOptions->length);
        $this->assertSame((string) $medicine->id, $medicineOptions->item(0)->getAttribute('value'));
        $this->assertStringContainsString('30 available', $medicineOptions->item(0)->textContent);
        $destinationOptions = $fields->query('//select[@name="destination_health_unit_id"]/option[@value!=""]');
        $this->assertSame(1, $destinationOptions->length);
        $this->assertSame((string) $rhu->id, $destinationOptions->item(0)->getAttribute('value'));
        $this->post(route('allocation.province.store'), ['medicine_id' => $medicine->id,
            'destination_health_unit_id' => $rhu->id, 'quantity_allocated' => 15, 'date_allocated' => today()->toDateString()])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame([0, 15, 50], [$early->fresh()->available_stock, $later->fresh()->available_stock, $expired->fresh()->available_stock]);
        $this->assertSame(15, (int) Inventory::where('health_unit_id', $rhu->id)->where('medicine_id', $medicine->id)->sum('available_stock'));
        $this->assertSame(0, (int) Inventory::sum('allocated_stock'));
        $this->assertDatabaseHas('allocations', ['health_unit_id' => $pho->id,
            'destination_health_unit_id' => $rhu->id, 'recipient_user_id' => null, 'quantity_allocated' => 15]);
        $this->assertSame(4, StockLog::count());
    }

    public function test_rhu_suggests_only_its_midwives_assigned_barangays_and_keeps_typed_destinations(): void
    {
        $unit = HealthUnit::create(['name' => 'Destination entry RHU', 'facility_type' => 'Rural Health Unit']);
        $viewer = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $secondMidwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $inactiveMidwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id, 'is_active' => false]);
        $otherUnit = HealthUnit::create(['name' => 'Other RHU']);
        $otherMidwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $otherUnit->id]);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Suggested barangay']);
        MidwifeBarangay::create(['user_id' => $secondMidwife->id, 'barangay' => 'Suggested barangay']);
        MidwifeBarangay::create(['user_id' => $inactiveMidwife->id, 'barangay' => 'Inactive barangay']);
        MidwifeBarangay::create(['user_id' => $otherMidwife->id, 'barangay' => 'Other RHU barangay']);
        $medicine = Medicine::create(['medicine_name' => 'Destination medicine', 'unit' => 'tablet']);
        $batch = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'available_stock' => 100, 'expiration_date' => today()->addMonth()]);
        DispensingRecord::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'barangay' => 'Historic dispensing area', 'quantity_dispensed' => 1, 'date_dispensed' => today()]);
        $this->withSession(['user_id' => $viewer->id]);
        $page = $this->get(route('allocation.create'))->assertOk()->assertDontSee('class="csv-import-link"', false);
        $document = $this->document($page);
        $field = $document->query('//input[@name="destination"]')->item(0);
        $this->assertSame('search', $field->getAttribute('type'));
        $this->assertTrue($field->hasAttribute('data-choice-search'));
        $this->assertTrue($field->hasAttribute('required'));
        $this->assertFalse($field->hasAttribute('readonly'));
        $this->assertFalse($field->hasAttribute('disabled'));
        $suggestions = $document->query('//datalist[@id="'.$field->getAttribute('list').'"]/option');
        $this->assertSame(1, $suggestions->length);
        $this->assertSame('Suggested barangay', $suggestions->item(0)->getAttribute('value'));
        $payload = ['inventory_id' => $batch->id, 'quantity_allocated' => 12,
            'date_allocated' => today()->toDateString(), 'destination' => 'New outreach <site> & clinic'];
        $this->post(route('allocation.store'), $payload)->assertSessionHasNoErrors()->assertSessionHas('success');
        $record = Allocation::firstOrFail();
        $this->assertSame($payload['destination'], $record->destination);
        $this->assertSame([88, 12], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
        $this->get(route('allocation.create'))->assertOk()->assertDontSee($payload['destination']);
        $edit = $this->get(route('allocation.edit', $record->id))->assertOk();
        $this->assertSame($payload['destination'], $this->document($edit)->query('//input[@name="destination"]')->item(0)->getAttribute('value'));
        $this->put(route('allocation.update', $record->id), array_replace($payload, ['destination' => 'Another new outreach']))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('Another new outreach', $record->fresh()->destination);
        $this->assertSame([88, 12], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
        $this->assertSame(1, StockLog::whereNull('voided_at')->count());
        $this->assertSame(1, Allocation::count());
    }

    public function test_destination_entry_stays_available_without_suggestions_and_survives_a_rejected_save(): void
    {
        $unit = HealthUnit::create(['name' => 'First destination RHU']);
        $viewer = User::factory()->create(['role' => 'admin', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'First destination medicine']);
        $batch = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'available_stock' => 10]);
        $this->withSession(['user_id' => $viewer->id]);
        $page = $this->get(route('allocation.create'))->assertOk()->assertSee('No barangays are assigned to active midwives at this RHU.');
        $this->assertSame(0, $this->document($page)->query('//datalist[@id="assigned-barangays"]/option')->length);
        $this->from(route('allocation.create'))->post(route('allocation.store'), ['inventory_id' => $batch->id,
            'destination' => 'First new destination', 'quantity_allocated' => 0, 'date_allocated' => today()->toDateString()])
            ->assertSessionHasErrors('quantity_allocated');
        $page = $this->get(route('allocation.create'))->assertOk();
        $this->assertSame('First new destination', $this->document($page)->query('//input[@name="destination"]')->item(0)->getAttribute('value'));
        $this->assertDatabaseCount('allocations', 0);
        $this->assertSame(10, $batch->fresh()->available_stock);
    }
}
