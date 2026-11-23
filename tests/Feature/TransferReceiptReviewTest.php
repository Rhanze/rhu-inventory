<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\TransferRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferReceiptReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_receiving_facility_can_record_evidence_without_posting_stock_again(): void
    {
        $source = HealthUnit::create(['name' => 'Fixture source']);
        $destination = HealthUnit::create(['name' => 'Fixture destination']);
        $sender = User::factory()->create(['role' => 'staff', 'health_unit_id' => $source->id]);
        $receiver = User::factory()->create(['role' => 'staff', 'health_unit_id' => $destination->id]);
        $medicine = Medicine::create(['medicine_name' => 'Fixture stock', 'unit' => 'tablet']);
        $batch = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $destination->id,
            'available_stock' => 20, 'allocated_stock' => 0]);
        $transfer = TransferRequest::create(['from_health_unit_id' => $source->id, 'to_health_unit_id' => $destination->id,
            'medicine_id' => $medicine->id, 'requested_by' => $receiver->id, 'quantity' => 20,
            'status' => 'received', 'received_at' => now()->subMonth(), 'auto_completed' => true]);
        $url = route('transfers.receipt_review.store', $transfer->id);
        $evidence = ['received_quantity' => 12, 'evidence_reference' => 'Synthetic receiving sheet A'];

        $this->withSession(['user_id' => $sender->id])->post($url, $evidence)->assertNotFound();
        $this->withSession(['user_id' => $receiver->id])->get(route('transfers.index', ['receipt_review' => 1]))
            ->assertOk()->assertSee('Awaiting delivery review')->assertSee('Review delivery');
        $this->post($url, $evidence)->assertRedirect(route('transfers.receipt_review', $transfer->id));
        $this->get(route('transfers.receipt_review', $transfer->id))->assertOk()->assertSee('Discrepancy recorded');
        $this->post($url, ['received_quantity' => 20, 'evidence_reference' => 'Other reference'])
            ->assertSessionHasErrors('received_quantity');
        $this->assertDatabaseHas('transfer_receipt_reviews', ['transfer_request_id' => $transfer->id,
            'reviewed_by' => $receiver->id, 'expected_quantity' => 20, 'received_quantity' => 12]);
        $this->assertDatabaseCount('transfer_receipt_reviews', 1);
        $this->assertSame(20, $batch->fresh()->available_stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_resolution_links_the_exact_unused_adjustment_and_preserves_stock(): void
    {
        $source = HealthUnit::create(['name' => 'Fixture source']);
        $destination = HealthUnit::create(['name' => 'Fixture destination']);
        $receiver = User::factory()->create(['role' => 'staff', 'health_unit_id' => $destination->id]);
        $admin = User::factory()->create(['role' => 'admin', 'health_unit_id' => $destination->id]);
        $medicine = Medicine::create(['medicine_name' => 'Fixture stock', 'unit' => 'tablet']);
        $batch = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $destination->id,
            'available_stock' => 40, 'allocated_stock' => 0]);
        $stockRow = ['medicine_id' => $medicine->id, 'health_unit_id' => $destination->id, 'inventory_id' => $batch->id,
            'date_added' => today(), 'added_by' => $admin->id];
        $transfers = [];
        foreach (['Receiving sheet A', 'Receiving sheet B'] as $reference) {
            $transfer = TransferRequest::create(['from_health_unit_id' => $source->id, 'to_health_unit_id' => $destination->id,
                'medicine_id' => $medicine->id, 'requested_by' => $receiver->id, 'quantity' => 20,
                'status' => 'received', 'received_at' => now()->subDay(), 'auto_completed' => true]);
            StockLog::create($stockRow + ['transfer_id' => $transfer->id, 'movement_type' => 'transfer_in', 'quantity_added' => 20]);
            $this->withSession(['user_id' => $receiver->id])->post(route('transfers.receipt_review.store', $transfer->id),
                ['received_quantity' => 12, 'evidence_reference' => $reference])->assertSessionHas('success');
            $transfers[] = $transfer;
        }
        [$first, $second] = $transfers;
        $wrong = StockLog::create($stockRow + ['movement_type' => 'adjustment_in', 'quantity_added' => 8]);
        $url = route('transfers.receipt_review.resolve', $first->id);
        $evidence = ['decision' => 'adjustment', 'reason' => 'Counted and reconciled against the receiving sheet.'];
        $this->post($url, $evidence + ['stock_log_id' => $wrong->id])->assertForbidden();
        $this->withSession(['user_id' => $admin->id])->post($url, $evidence + ['stock_log_id' => $wrong->id])
            ->assertSessionHasErrors('stock_log_id');

        $this->put(route('inventory.adjust', $batch->id), ['available_stock' => 32, 'expected_available_stock' => 40,
            'remarks' => 'Verified discrepancy on receiving sheet A'])->assertSessionHas('success');
        $correction = StockLog::where('movement_type', 'adjustment_out')->sole();
        $this->post($url, $evidence + ['stock_log_id' => $correction->id])->assertSessionHas('success');
        $this->get(route('transfers.receipt_review', $first->id))->assertOk()->assertSee('Discrepancy resolved');
        $this->post(route('transfers.receipt_review.resolve', $second->id), $evidence + ['stock_log_id' => $correction->id])
            ->assertSessionHasErrors('stock_log_id');

        $this->assertDatabaseHas('transfer_receipt_resolutions', ['transfer_receipt_review_id' => $first->receiptReview->id,
            'stock_log_id' => $correction->id, 'resolved_by' => $admin->id]);
        $this->assertDatabaseCount('transfer_receipt_resolutions', 1);
        $this->assertSame(32, $batch->fresh()->available_stock);
        $this->assertDatabaseCount('stock_logs', 4);
    }
}
