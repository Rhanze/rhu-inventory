<?php

namespace Tests\Feature;

use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DispensingSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_retried_form_uses_the_original_token_and_deducts_once(): void
    {
        [$batch] = $this->fixture();
        $form = $this->get(route('dispensing.create'))->assertOk();
        $token = $form->viewData('submissionToken');
        $this->assertTrue(Str::isUuid($token, 4));
        $form->assertSee('name="submission_token" value="'.$token.'"', false);

        $payload = ['inventory_id' => $batch->id, 'date_dispensed' => today()->toDateString(), 'submission_token' => $token];
        $this->from(route('dispensing.create'))->post(route('dispensing.store'), $payload + ['quantity_dispensed' => 0])
            ->assertSessionHasErrors('quantity_dispensed');
        $this->get(route('dispensing.create'))->assertOk()->assertViewHas('submissionToken', $token);

        $this->from(route('dispensing.create'))->post(route('dispensing.store'), $payload + ['quantity_dispensed' => 50])
            ->assertSessionHas('error');
        $this->assertDatabaseCount('dispensing_records', 0);

        $payload['quantity_dispensed'] = 3;
        $this->post(route('dispensing.store'), $payload)->assertSessionHas('success');
        $this->post(route('dispensing.store'), $payload)
            ->assertSessionHas('success', 'This dispensing submission has already been recorded.');

        $this->assertSame(17, $batch->fresh()->available_stock);
        $this->assertDatabaseCount('dispensing_records', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('stock_logs', 1);
    }

    public function test_the_token_remains_owned_by_the_submitter_and_survives_record_reversal(): void
    {
        [$batch, $originalActor] = $this->fixture();
        $anotherActor = User::factory()->create(['health_unit_id' => $batch->health_unit_id, 'role' => 'staff']);
        $payload = ['inventory_id' => $batch->id, 'date_dispensed' => today()->toDateString(),
            'quantity_dispensed' => 3, 'submission_token' => (string) Str::uuid()];

        $this->post(route('dispensing.store'), $payload)->assertSessionHas('success');
        $original = DispensingRecord::firstOrFail();
        $this->withSession(['user_id' => $anotherActor->id])->post(route('dispensing.store'), $payload)
            ->assertSessionHas('success');
        $this->assertSame(14, $batch->fresh()->available_stock);

        $this->delete(route('dispensing.destroy', $original))->assertSessionHas('success');
        $this->assertSame(17, $batch->fresh()->available_stock);
        $this->withSession(['user_id' => $originalActor->id])->post(route('dispensing.store'), $payload)
            ->assertSessionHas('success', 'This dispensing submission has already been recorded.');
        $this->assertSame(17, $batch->fresh()->available_stock);
        $this->assertDatabaseCount('dispensing_records', 2);
    }

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Submission test clinic']);
        $actor = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']);
        $medicine = Medicine::create(['medicine_name' => 'Submission test item', 'is_dispensable' => true]);
        $batch = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'available_stock' => 20, 'batch_no' => 'B001', 'expiration_date' => today()->addMonth()]);
        $this->withSession(['user_id' => $actor->id]);

        return [$batch, $actor];
    }
}
