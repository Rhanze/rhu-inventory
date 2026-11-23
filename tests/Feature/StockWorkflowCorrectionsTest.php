<?php

namespace Tests\Feature;

use App\Models\DohShipment;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\TransferRequest;
use App\Models\User;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StockWorkflowCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('receiptCounts')]
    public function test_transfer_receipt_credits_only_the_counted_reserved_stock(int $count, int $credited): void
    {
        $source = HealthUnit::create(['name' => 'Supply facility']);
        $destination = HealthUnit::create(['name' => 'Receiving facility']);
        $receiver = User::factory()->create(['role' => 'admin', 'health_unit_id' => $destination->id]);
        $medicine = Medicine::create(['medicine_name' => 'Fixture stock', 'unit' => 'Tablet']);
        $batch = Inventory::create(['health_unit_id' => $source->id, 'medicine_id' => $medicine->id,
            'available_stock' => 20, 'allocated_stock' => 0]);
        $transfer = TransferRequest::create(['from_health_unit_id' => $source->id, 'to_health_unit_id' => $destination->id,
            'medicine_id' => $medicine->id, 'requested_by' => $receiver->id, 'quantity' => 10, 'status' => 'pending']);
        app(TransferService::class)->reserve($transfer->id, $source->id);
        $this->withSession(['user_id' => $receiver->id]);
        $url = route('transfers.receive', $transfer->id);

        $this->post($url)->assertSessionHasErrors('received_quantity');
        $this->post($url, ['received_quantity' => $count])->assertSessionHasErrors('evidence_reference');
        $this->assertSame([10, 10], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
        $receipt = ['received_quantity' => $count, 'evidence_reference' => 'Verified count on facility receipt'];
        $this->post($url, $receipt)->assertSessionHas('success');
        $this->post($url, $receipt)->assertSessionHas('success');
        $this->post($url, array_replace($receipt, ['received_quantity' => $count + 1]))
            ->assertSessionHasErrors('received_quantity');

        $this->assertSame([10, 0], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
        $this->assertSame($credited, (int) Inventory::where('health_unit_id', $destination->id)->sum('available_stock'));
        $this->assertSame($credited, (int) StockLog::where('transfer_id', $transfer->id)->where('movement_type', 'transfer_in')->sum('quantity_added'));
        $this->assertDatabaseHas('transfer_receipt_reviews', ['transfer_request_id' => $transfer->id,
            'expected_quantity' => 10, 'received_quantity' => $count, 'reviewed_by' => $receiver->id]);
        $this->assertDatabaseCount('transfer_receipt_reviews', 1);
        $this->get(route('transfers.receipt_review', $transfer->id))->assertOk()->assertSee('Discrepancy recorded');
    }

    public static function receiptCounts(): array
    {
        return ['shortage' => [7, 7], 'nothing arrived' => [0, 0], 'surplus' => [12, 10]];
    }

    public function test_transfer_limit_uses_the_largest_eligible_batch_while_lookup_keeps_total_stock(): void
    {
        $source = HealthUnit::create(['name' => 'Supply facility']);
        $destination = HealthUnit::create(['name' => 'Receiving facility']);
        $actor = User::factory()->create(['role' => 'staff', 'health_unit_id' => $destination->id]);
        $medicine = Medicine::create(['medicine_name' => 'Fixture stock']);
        foreach ([6, 4] as $index => $quantity) {
            Inventory::create(['health_unit_id' => $source->id, 'medicine_id' => $medicine->id,
                'available_stock' => $quantity, 'allocated_stock' => 0, 'batch_no' => 'LOT-'.$index]);
        }
        $this->withSession(['user_id' => $actor->id])->getJson(route('stock_lookup.search', ['medicine_id' => $medicine->id]))
            ->assertOk()->assertJsonPath('results.0.available_stock', 10)->assertJsonPath('results.0.largest_batch_stock', 6);
        $payload = ['source_rhu_id' => $source->id, 'medicine_id' => $medicine->id];
        $this->post(route('transfers.store'), $payload + ['quantity' => 10])->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('transfer_requests', 0);
        $this->get(route('transfers.create'))->assertOk()->assertSee('No unexpired batch at the source can supply this quantity.');
        $this->post(route('transfers.store'), $payload + ['quantity' => 6])->assertSessionHas('success');
        $this->assertDatabaseCount('transfer_requests', 1);
    }

    public function test_a_pho_shipment_submission_is_safe_to_retry_even_after_reversal(): void
    {
        $facility = HealthUnit::create(['name' => 'PHO fixture', 'facility_type' => 'Provincial Health Office']);
        $actor = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $medicine = Medicine::create(['medicine_name' => 'Fixture stock']);
        $this->withSession(['user_id' => $actor->id]);
        $token = $this->get(route('inventory.pho_receive.create'))->assertOk()->viewData('submissionToken');
        $payload = ['submission_token' => $token, 'shipment_no' => 'DOH-FIXTURE-01', 'received_date' => today()->toDateString(),
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 10, 'batch_no' => 'LOT']]];

        $this->post(route('inventory.pho_receive.store'), $payload)->assertSessionHas('success');
        $shipment = DohShipment::sole();
        $this->post(route('inventory.pho_receive.store'), $payload)
            ->assertRedirect(route('inventory.pho_receive.show', $shipment->id))->assertSessionHas('success');
        $this->assertDatabaseCount('doh_shipments', 1);
        $this->assertSame(10, (int) Inventory::where('health_unit_id', $facility->id)->sum('available_stock'));

        $this->delete(route('inventory.pho_receive.destroy', $shipment->id))->assertSessionHas('success');
        $this->post(route('inventory.pho_receive.store'), $payload)->assertSessionHas('error');
        $this->assertDatabaseCount('doh_shipments', 0);
        $this->assertSame(0, (int) Inventory::where('health_unit_id', $facility->id)->sum('available_stock'));
    }

    public function test_manual_batch_creation_requires_allocations_to_have_a_workflow_owner(): void
    {
        $facility = HealthUnit::create(['name' => 'RHU fixture']);
        $actor = User::factory()->create(['role' => 'admin', 'health_unit_id' => $facility->id]);
        $medicine = Medicine::create(['medicine_name' => 'Fixture stock']);
        $this->withSession(['user_id' => $actor->id]);
        $payload = ['medicine_id' => $medicine->id, 'available_stock' => 10];
        $this->post(route('inventory.store'), $payload + ['allocated_stock' => 4])->assertSessionHasErrors('allocated_stock');
        $this->assertDatabaseCount('inventory', 0);
        $this->post(route('inventory.store'), $payload)->assertSessionHas('success');
        $this->assertDatabaseHas('inventory', $payload + ['allocated_stock' => 0]);
    }

    public function test_medicine_edits_preserve_the_historical_stock_unit(): void
    {
        $facility = HealthUnit::create(['name' => 'RHU fixture']);
        $actor = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $medicine = Medicine::create(['medicine_name' => 'Fixture stock', 'category' => 'medicine',
            'dosage' => '10 mg', 'dosage_form' => 'Capsule', 'unit' => 'Capsule', 'description' => 'Original description']);
        Inventory::create(['health_unit_id' => $facility->id, 'medicine_id' => $medicine->id,
            'available_stock' => 20, 'allocated_stock' => 0]);
        $this->withSession(['user_id' => $actor->id])->get(route('medicine.edit', $medicine->id))
            ->assertOk()->assertSee('value="Capsule" selected', false);
        $fields = ['medicine_name' => $medicine->medicine_name, 'category' => 'medicine', 'dosage' => '10 mg',
            'dosage_form' => 'Capsule', 'description' => 'Edited description'];
        $this->put(route('medicine.update', $medicine->id), $fields + ['unit' => ''])->assertSessionHas('success');
        $this->put(route('medicine.update', $medicine->id), $fields + ['unit' => 'box'])->assertSessionHasErrors('unit');
        $this->assertSame('Capsule', $medicine->fresh()->unit);
        $this->assertSame('Edited description', $medicine->fresh()->description);
    }
}
