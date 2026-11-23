<?php

namespace Tests\Feature;

use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispensingBatchPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Dispensing lot preview RHU']);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Lot preview medicine', 'is_dispensable' => true]);
        $late = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'batch_no' => 'B', 'available_stock' => 30, 'expiration_date' => today()->addMonths(4)]);
        $early = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'batch_no' => 'A', 'available_stock' => 10, 'expiration_date' => today()->addMonth(), 'storage_location' => 'Shelf A']);
        $this->withSession(['user_id' => $staff->id]);

        return [$early, $late, ['medicine_id' => $medicine->id, 'date_dispensed' => today()->toDateString()]];
    }

    public function test_the_form_previews_actual_batches_and_a_split_issue_records_each_one(): void
    {
        [$early, $late, $payload] = $this->fixture();
        $this->get(route('dispensing.create'))->assertOk()->assertSee('Batches to issue');
        $payload['quantity_dispensed'] = 12;

        $preview = $this->postJson(route('dispensing.preview'), $payload)->assertOk()
            ->assertJsonPath('lots.0.batch_no', 'A')->assertJsonPath('lots.0.quantity', 10)
            ->assertJsonPath('lots.0.storage_location', 'Shelf A')
            ->assertJsonPath('lots.1.batch_no', 'B')->assertJsonPath('lots.1.quantity', 2);
        $this->assertDatabaseCount('dispensing_records', 0);
        $this->assertDatabaseCount('stock_logs', 0);
        $this->assertDatabaseCount('stock_movements', 0);

        $this->post(route('dispensing.store'), $payload + ['lot_preview' => $preview->json('token')])
            ->assertRedirect(route('dispensing.index'))->assertSessionHas('success');
        $record = DispensingRecord::firstOrFail();
        $this->assertSame('A', $record->batch_no);
        $this->assertSame([[$early->id, 10], [$late->id, 2]], $record->issuedLots
            ->map(fn ($lot) => [(int) $lot->inventory_id, (int) $lot->quantity_added])->all());
        $this->assertSame([0, 28], [$early->fresh()->available_stock, $late->fresh()->available_stock]);
        foreach ([[$early, 10], [$late, 2]] as [$batch, $quantity]) {
            $this->assertDatabaseHas('stock_movements', ['owner_type' => 'dispensing_records', 'owner_id' => $record->id,
                'inventory_id' => $batch->id, 'available_delta' => -$quantity]);
        }
        $this->get(route('dispensing.index'))->assertOk()->assertSee('A (10)')->assertSee('B (2)');
    }

    public function test_another_issue_invalidates_the_preview_without_saving_different_lots(): void
    {
        [$early, $late, $payload] = $this->fixture();
        $actor = session('user_id');
        $payload['quantity_dispensed'] = 12;
        $preview = $this->postJson(route('dispensing.preview'), $payload)->assertOk();

        $other = User::factory()->create(['role' => 'staff', 'health_unit_id' => $early->health_unit_id]);
        $singleIssue = array_replace($payload, ['quantity_dispensed' => 4]);
        $singlePreview = $this->withSession(['user_id' => $other->id])->postJson(route('dispensing.preview'), $singleIssue)
            ->assertOk()->assertJsonCount(1, 'lots')->assertJsonPath('lots.0.inventory_id', $early->id);
        $this->post(route('dispensing.store'), $singleIssue + ['lot_preview' => $singlePreview->json('token')])
            ->assertSessionHas('success');
        $this->assertSame('A (4)', DispensingRecord::firstOrFail()->lotBreakdown());

        $this->withSession(['user_id' => $actor])->from(route('dispensing.create'))->post(
            route('dispensing.store'), $payload + ['lot_preview' => $preview->json('token')],
        )->assertSessionHas('error', 'The batches have changed. Review the refreshed batch quantities before saving again.');
        $this->assertDatabaseCount('dispensing_records', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('stock_logs', 1);
        $this->assertSame([6, 30], [$early->fresh()->available_stock, $late->fresh()->available_stock]);

        $refreshed = $this->postJson(route('dispensing.preview'), $payload)->assertOk()
            ->assertJsonPath('lots.0.quantity', 6)->assertJsonPath('lots.1.quantity', 6);
        $this->post(route('dispensing.store'), $payload + ['lot_preview' => $refreshed->json('token')])
            ->assertSessionHas('success');
        $this->assertSame([0, 24], [$early->fresh()->available_stock, $late->fresh()->available_stock]);
    }

    public function test_browser_form_cannot_issue_stock_with_an_empty_preview(): void
    {
        [$early, $late, $payload] = $this->fixture();
        $form = route('dispensing.create');
        $submissionToken = $this->get($form)->assertOk()->viewData('submissionToken');
        $this->from($form)->post(route('dispensing.store'), $payload + [
            'submission_token' => $submissionToken, 'quantity_dispensed' => 12, 'lot_preview' => '',
        ])->assertSessionHasErrors('lot_preview');
        $this->assertSame([10, 30], [$early->fresh()->available_stock, $late->fresh()->available_stock]);
        $this->assertDatabaseCount('dispensing_records', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_the_edit_shows_recorded_batches_or_previews_the_exact_reversal_and_reissue(): void
    {
        [$early, $late, $payload] = $this->fixture();
        $payload['quantity_dispensed'] = 12;
        $this->post(route('dispensing.store'), $payload)->assertSessionHas('success');
        $record = DispensingRecord::firstOrFail();
        $update = route('dispensing.update', $record);
        $previewUrl = route('dispensing.preview_edit', $record);
        $this->get(route('dispensing.edit', $record))->assertOk()->assertSee($previewUrl);

        $recorded = $this->postJson($previewUrl, $payload)->assertOk()->assertJsonPath('recorded', true)
            ->assertJsonPath('lots.0.quantity', 10)->assertJsonPath('lots.1.quantity', 2);
        $this->put($update, $payload + ['lot_preview' => $recorded->json('token'), 'patient_name' => 'Updated recipient'])
            ->assertSessionHas('success');
        $this->assertSame('Updated recipient', $record->fresh()->patient_name);
        $this->assertDatabaseCount('stock_movements', 2);

        $payload['quantity_dispensed'] = 9;
        $corrected = $this->postJson($previewUrl, $payload)->assertOk()->assertJsonPath('recorded', false)
            ->assertJsonCount(1, 'lots')->assertJsonPath('lots.0.inventory_id', $early->id)->assertJsonPath('lots.0.quantity', 9);
        $this->assertSame([0, 28], [$early->fresh()->available_stock, $late->fresh()->available_stock]);
        $this->put($update, $payload + ['lot_preview' => $corrected->json('token')])->assertSessionHas('success');
        $this->assertSame([1, 30], [$early->fresh()->available_stock, $late->fresh()->available_stock]);
        $this->assertSame('A (9)', $record->fresh()->lotBreakdown());
        $this->assertDatabaseCount('stock_movements', 5);
    }

    public function test_previews_use_the_existing_dispensing_permissions_and_facility_scope(): void
    {
        [$early, , $payload] = $this->fixture();
        $payload['quantity_dispensed'] = 4;
        $this->post(route('dispensing.store'), $payload)->assertSessionHas('success');
        $record = DispensingRecord::firstOrFail();

        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $early->health_unit_id]);
        $this->withSession(['user_id' => $midwife->id])->postJson(route('dispensing.preview_edit', $record), $payload)->assertNotFound();
        $this->postJson(route('dispensing.preview'), $payload)->assertOk()->assertJsonPath('lots.0.inventory_id', $early->id);

        $otherUnit = HealthUnit::create(['name' => 'Another preview RHU']);
        $otherStaff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $otherUnit->id]);
        $this->withSession(['user_id' => $otherStaff->id])->postJson(route('dispensing.preview_edit', $record), $payload)->assertNotFound();
        $this->postJson(route('dispensing.preview'), $payload)->assertUnprocessable()
            ->assertJsonValidationErrors('stock');

        $pho = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $this->withSession(['user_id' => $pho->id])->postJson(route('dispensing.preview'), $payload)->assertRedirect(route('dashboard'));
        $this->withSession(['user_id' => null])->postJson(route('dispensing.preview'), $payload)->assertRedirect(route('login'));
        $this->assertDatabaseCount('dispensing_records', 1);
        $this->assertDatabaseCount('stock_movements', 1);
    }
}
