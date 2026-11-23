<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\TransferRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferPhoReviewTest extends TestCase
{
    use RefreshDatabase;

    private function requestTransfer(): array
    {
        $source = HealthUnit::create(['name' => 'Source RHU', 'facility_type' => 'Rural Health Unit']);
        $destination = HealthUnit::create(['name' => 'Requesting RHU', 'facility_type' => 'Rural Health Unit']);
        $supplier = User::factory()->create(['role' => 'admin', 'health_unit_id' => $source->id]);
        $requester = User::factory()->create(['role' => 'staff', 'health_unit_id' => $destination->id]);
        $pho = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $medicine = Medicine::create(['medicine_name' => 'Review fixture medicine', 'unit' => 'tablet']);
        $batch = Inventory::create(['health_unit_id' => $source->id, 'medicine_id' => $medicine->id,
            'batch_no' => 'REVIEW-BATCH', 'available_stock' => 20, 'allocated_stock' => 0,
            'expiration_date' => today()->addYear()]);

        $this->withSession(['user_id' => $requester->id])->post(route('transfers.store'), [
            'source_rhu_id' => $source->id, 'medicine_id' => $medicine->id, 'quantity' => 7,
            'notes' => 'Clinic review fixture request',
        ])->assertRedirect(route('transfers.index'))->assertSessionHas('success');

        return [TransferRequest::sole(), $supplier, $requester, $pho, $batch];
    }

    public function test_pho_approval_makes_a_request_visible_to_the_supplier_without_reserving_stock(): void
    {
        [$transfer, $supplier, $requester, $pho, $batch] = $this->requestTransfer();
        $index = route('transfers.index');
        $approve = route('transfers.pho_approve', $transfer->id);
        $phoReject = route('transfers.pho_reject', $transfer->id);
        $accept = route('transfers.accept', $transfer->id);
        $search = ['scope' => 'transfers', 'q' => $transfer->notes];
        $row = 'id="transfer-'.$transfer->id.'"';

        $this->assertSame('pending', $transfer->pho_review_status);
        $this->assertNull($transfer->pho_reviewed_at);
        $this->assertDatabaseHas('notifications', ['transfer_id' => $transfer->id, 'type' => 'transfer_pho_review_requested']);
        $this->assertDatabaseMissing('notifications', ['transfer_id' => $transfer->id, 'health_unit_id' => $supplier->health_unit_id]);
        $this->get($index)->assertOk()->assertSee($row, false)->assertSee('Awaiting PHO approval')->assertDontSee($approve);

        $this->withSession(['user_id' => $supplier->id]);
        $this->get($index)->assertOk()->assertDontSee($row, false);
        $this->get(route('search.index', $search))->assertOk()->assertViewHas('transferResults', fn ($rows) => $rows->isEmpty());
        $this->getJson(route('search.autocomplete', $search))->assertOk()->assertExactJson([]);
        $this->getJson(route('notifications.recent'))->assertOk()->assertJsonCount(0, 'notifications');
        $this->get(route('notifications.index'))->assertOk()->assertDontSee($accept);
        $this->post($accept)->assertForbidden();
        $this->post(route('transfers.reject', $transfer->id))->assertForbidden();

        foreach ([$supplier, $requester] as $unauthorized) {
            $this->withSession(['user_id' => $unauthorized->id])->post($approve)->assertForbidden();
            $this->post($phoReject)->assertForbidden();
        }
        $unassignedStaff = User::factory()->create(['role' => 'staff', 'health_unit_id' => null]);
        foreach ([$approve, $phoReject] as $reviewAction) {
            $this->withSession(['user_id' => $unassignedStaff->id])->post($reviewAction)
                ->assertRedirect(route('login'))->assertSessionMissing('user_id');
        }

        $this->withSession(['user_id' => $pho->id]);
        $this->get($index)->assertOk()->assertSee($row, false)->assertSee($approve)->assertSee($phoReject);
        $this->getJson(route('notifications.recent'))->assertOk()->assertJsonCount(1, 'notifications');
        $this->post($approve)->assertSessionHas('success');
        $transfer->refresh();
        $this->assertSame('pending', $transfer->status);
        $this->assertSame('approved', $transfer->pho_review_status);
        $this->assertEquals($pho->id, $transfer->pho_reviewed_by);
        $this->assertNotNull($transfer->pho_reviewed_at);
        $this->assertNull($transfer->approved_at);
        $this->assertFalse($transfer->stock_reserved);
        $this->assertSame(20, $batch->fresh()->available_stock);
        $this->assertSame(0, $batch->fresh()->allocated_stock);
        $this->assertDatabaseMissing('stock_movements', ['owner_type' => 'transfer_requests', 'owner_id' => $transfer->id]);
        $this->assertDatabaseHas('notifications', ['transfer_id' => $transfer->id,
            'health_unit_id' => $requester->health_unit_id, 'type' => 'transfer_pho_approved']);

        $this->post($approve)->assertSessionHasErrors();
        $this->post($phoReject)->assertSessionHasErrors();
        $this->assertSame(1, Notification::where('transfer_id', $transfer->id)
            ->where('health_unit_id', $supplier->health_unit_id)->where('type', 'transfer_request')->count());

        $this->withSession(['user_id' => $supplier->id]);
        $this->get($index)->assertOk()->assertSee($row, false)->assertSee('Awaiting source acceptance')->assertSee($accept);
        $this->getJson(route('search.autocomplete', $search))->assertOk()->assertJsonCount(1)->assertJsonPath('0.type', 'Transfer');
        $this->getJson(route('notifications.recent'))->assertOk()->assertJsonCount(1, 'notifications');
        $this->get(route('notifications.index'))->assertOk()->assertSee($accept);
        $this->post($accept)->assertSessionHas('success');
        $this->assertSame('accepted', $transfer->fresh()->status);
        $this->assertSame(13, $batch->fresh()->available_stock);
        $this->assertSame(7, $batch->fresh()->allocated_stock);

        $this->withSession(['user_id' => $requester->id])->post(route('transfers.receive', $transfer->id), ['received_quantity' => 7])
            ->assertSessionHas('success');
        $this->assertSame('received', $transfer->fresh()->status);
        $this->assertSame(13, $batch->fresh()->available_stock);
        $this->assertSame(0, $batch->fresh()->allocated_stock);
        $this->assertDatabaseHas('inventory', ['health_unit_id' => $requester->health_unit_id,
            'medicine_id' => $transfer->medicine_id, 'batch_no' => $batch->batch_no, 'available_stock' => 7]);
    }

    public function test_pho_rejection_notifies_the_requester_and_keeps_the_transfer_hidden_from_the_supplier(): void
    {
        [$transfer, $supplier, $requester, $pho, $batch] = $this->requestTransfer();
        $reason = 'Another facility is supplying this request.';
        $this->withSession(['user_id' => $pho->id])->post(route('transfers.pho_reject', $transfer->id), [
            'rejection_reason' => $reason,
        ])->assertSessionHas('success');

        $transfer->refresh();
        $this->assertSame('rejected', $transfer->status);
        $this->assertSame('rejected', $transfer->pho_review_status);
        $this->assertEquals($pho->id, $transfer->pho_reviewed_by);
        $this->assertNotNull($transfer->pho_reviewed_at);
        $this->assertSame($reason, $transfer->rejection_reason);
        $this->assertSame(20, $batch->fresh()->available_stock);
        $this->assertSame(0, $batch->fresh()->allocated_stock);
        $this->assertDatabaseMissing('stock_movements', ['owner_type' => 'transfer_requests', 'owner_id' => $transfer->id]);
        $this->post(route('transfers.pho_approve', $transfer->id))->assertSessionHasErrors();

        $this->withSession(['user_id' => $requester->id]);
        $this->get(route('transfers.index'))->assertOk()->assertSee($reason);
        $this->getJson(route('notifications.recent'))->assertOk()->assertJsonCount(1, 'notifications');
        $this->assertDatabaseHas('notifications', ['transfer_id' => $transfer->id,
            'health_unit_id' => $requester->health_unit_id, 'type' => 'transfer_pho_rejected']);

        $this->withSession(['user_id' => $supplier->id]);
        $this->get(route('transfers.index'))->assertOk()->assertDontSee('id="transfer-'.$transfer->id.'"', false);
        $this->getJson(route('search.autocomplete', ['scope' => 'transfers', 'q' => $transfer->notes]))->assertOk()->assertExactJson([]);
        $this->getJson(route('notifications.recent'))->assertOk()->assertJsonCount(0, 'notifications');
        $this->assertDatabaseMissing('notifications', ['transfer_id' => $transfer->id, 'health_unit_id' => $supplier->health_unit_id]);
    }

    public function test_cancelling_a_request_before_pho_review_leaves_the_supplier_unaware(): void
    {
        [$transfer, $supplier, $requester, $pho, $batch] = $this->requestTransfer();
        $this->withSession(['user_id' => $requester->id])->post(route('transfers.cancel', $transfer->id))
            ->assertSessionHas('success');
        $this->assertSame('cancelled', $transfer->fresh()->status);
        $this->assertSame(20, $batch->fresh()->available_stock);
        $this->assertSame(0, $batch->fresh()->allocated_stock);

        $this->withSession(['user_id' => $pho->id])->post(route('transfers.pho_approve', $transfer->id))
            ->assertSessionHasErrors();
        $this->withSession(['user_id' => $supplier->id])->get(route('transfers.index'))
            ->assertOk()->assertDontSee('id="transfer-'.$transfer->id.'"', false);
        $this->assertDatabaseMissing('notifications', ['transfer_id' => $transfer->id, 'health_unit_id' => $supplier->health_unit_id]);
    }

    public function test_overdue_receipts_can_be_found_and_confirmed_by_the_receiving_facility(): void
    {
        $this->freezeSecond();
        [$transfer, $supplier, $requester, $pho, $batch] = $this->requestTransfer();
        $index = route('transfers.index');
        $overdue = route('transfers.index', ['overdue' => 1]);
        $receive = route('transfers.receive', $transfer->id);
        $row = 'id="transfer-'.$transfer->id.'"';

        $this->withSession(['user_id' => $pho->id])->post(route('transfers.pho_approve', $transfer->id))->assertSessionHas('success');
        $this->withSession(['user_id' => $supplier->id])->post(route('transfers.accept', $transfer->id))->assertSessionHas('success');
        $this->withSession(['user_id' => $requester->id])->get($overdue)->assertOk()->assertDontSee($row, false);

        $this->travel(8)->days();
        $this->artisan('transfers:process')->assertSuccessful();
        $this->get($index)->assertOk()->assertSee($overdue)->assertSee('Receipt overdue');
        $this->get($overdue)->assertOk()->assertSee($row, false)->assertSee($receive);
        $this->assertDatabaseHas('notifications', ['transfer_id' => $transfer->id, 'type' => 'transfer_reminder',
            'health_unit_id' => $requester->health_unit_id, 'link' => $overdue, 'is_read' => false]);
        $this->assertSame('accepted', $transfer->fresh()->status);
        $this->assertNull($transfer->fresh()->received_by);
        $this->assertSame([13, 7], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
        $this->assertDatabaseMissing('inventory', ['health_unit_id' => $requester->health_unit_id, 'medicine_id' => $transfer->medicine_id]);

        foreach ([$supplier, $pho] as $viewer) {
            $this->withSession(['user_id' => $viewer->id])->get($overdue)->assertOk()->assertSee($row, false)->assertDontSee($receive);
            $this->post($receive, ['received_quantity' => 7])->assertForbidden();
        }

        $notifications = route('notifications.index');
        $this->withSession(['user_id' => $requester->id])->from($notifications)->post($receive)->assertSessionHasErrors('received_quantity');
        $this->get($notifications)->assertOk()->assertSee('action="'.$receive.'"', false)
            ->assertSee('name="received_quantity"', false)->assertSee('name="evidence_reference"', false);
        $this->from($notifications)->post($receive, ['receipt_for' => $transfer->id, 'received_quantity' => 6])
            ->assertSessionHasErrors('evidence_reference');
        $this->get($notifications)->assertOk()->assertSee('Enter a receiving document or evidence reference');
        $this->post($receive, ['receipt_for' => $transfer->id, 'received_quantity' => 7])->assertSessionHas('success');
        $this->post($receive, ['received_quantity' => 7])->assertSessionHas('success');
        $this->assertSame('received', $transfer->fresh()->status);
        $this->assertSame($requester->id, $transfer->fresh()->received_by);
        $this->assertSame([13, 0], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
        $this->assertDatabaseHas('inventory', ['health_unit_id' => $requester->health_unit_id,
            'medicine_id' => $transfer->medicine_id, 'batch_no' => $batch->batch_no, 'available_stock' => 7]);
        $this->assertDatabaseCount('stock_movements', 3);
        $this->assertDatabaseHas('notifications', ['transfer_id' => $transfer->id, 'type' => 'transfer_reminder', 'is_read' => true]);
        $this->get($overdue)->assertOk()->assertDontSee($row, false);
    }
}
