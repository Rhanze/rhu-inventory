<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\DohShipment;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DohShipmentReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_delivery_retried_from_separate_forms_is_received_once(): void
    {
        $pho = HealthUnit::create(['name' => 'PHO fixture', 'facility_type' => 'Provincial Health Office']);
        $firstReceiver = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $secondReceiver = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $medicine = Medicine::create(['medicine_name' => 'Fixture stock']);
        $form = route('inventory.pho_receive.create');
        $store = route('inventory.pho_receive.store');
        $payload = [
            'shipment_no' => 'DOH-DUPLICATE-01',
            'reference_no' => 'DR-1234',
            'received_date' => today()->toDateString(),
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 50, 'batch_no' => 'LOT-A']],
        ];

        $firstToken = $this->withSession(['user_id' => $firstReceiver->id])->get($form)->assertOk()
            ->assertSee('method="POST" action="'.$store.'"', false)->viewData('submissionToken');
        $secondToken = $this->withSession(['user_id' => $secondReceiver->id])->get($form)->assertOk()->viewData('submissionToken');
        $this->assertNotSame($firstToken, $secondToken);

        $this->withSession(['user_id' => $firstReceiver->id])->post($store, $payload + ['submission_token' => $firstToken])
            ->assertSessionHas('success');
        $shipment = DohShipment::sole();

        $retry = $this->withSession(['user_id' => $secondReceiver->id])->post($store, $payload + ['submission_token' => $secondToken]);
        $this->assertSame(50, (int) Inventory::where('health_unit_id', $pho->id)->sum('available_stock'));
        $this->assertDatabaseCount('doh_shipments', 1);
        $this->assertDatabaseCount('doh_shipment_items', 1);
        $this->assertSame(1, ActivityLog::where('module', 'Inventory')->where('action', 'PHO Receive')->count());
        $movement = StockLog::where('movement_type', 'receipt')->sole();
        $this->assertSame(50, $movement->quantity_added);
        $retry->assertRedirect(route('inventory.pho_receive.show', $shipment->id))->assertSessionHas('success');

        $this->get(route('inventory.pho_receive.show', $shipment->id))->assertOk()->assertSee('DR-1234');
        $this->get(route('inventory.pho_receive'))->assertOk()->assertViewHas('shipments', fn ($rows) => $rows->total() === 1);
        $export = $this->get(route('inventory.pho_receive.export', ['shipment_no' => $shipment->shipment_no]))->assertOk()->streamedContent();
        $this->assertSame(1, substr_count($export, $shipment->shipment_no));
        $historyFilters = ['rhu_filter' => 'pho', 'movement_type' => 'receipt'];
        $this->get(route('stock_logs.index', $historyFilters))->assertOk()
            ->assertViewHas('stockLogs', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $movement->id);
        $historyExport = $this->get(route('stock_logs.export', $historyFilters))->assertOk()->streamedContent();
        $this->assertSame(1, substr_count($historyExport, '[Shipment #'.$shipment->id.']'));
    }

    public function test_named_legacy_receipts_can_be_retried_and_distinct_deliveries_post_separately(): void
    {
        $pho = HealthUnit::create(['name' => 'PHO fixture', 'facility_type' => 'Provincial Health Office']);
        $receiver = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $medicine = Medicine::create(['medicine_name' => 'Fixture stock']);
        $store = route('inventory.pho_receive.store');
        $item = ['medicine_id' => $medicine->id, 'quantity' => 50, 'batch_no' => 'LOT-A',
            'expiration_date' => today()->addYear()->toDateString()];
        $payload = ['shipment_no' => 'SHIP-1', 'reference_no' => 'REF-1', 'received_date' => today()->toDateString(),
            'items' => [$item]];
        $this->withSession(['user_id' => $receiver->id]);
        $this->post($store, $payload + ['submission_token' => (string) Str::uuid()])->assertSessionHas('success');
        $shipment = DohShipment::sole();
        $shipment->update(['delivery_key' => null]);

        $this->post($store, array_replace($payload, ['shipment_no' => '  ship-1 ', 'reference_no' => 'ref-1'])
            + ['submission_token' => (string) Str::uuid()])->assertRedirect(route('inventory.pho_receive.show', $shipment->id));
        $this->post($store, array_replace($payload, ['items' => [array_replace($item, ['quantity' => 60])]])
            + ['submission_token' => (string) Str::uuid()])->assertSessionHasErrors('reference_no');
        $this->assertDatabaseCount('doh_shipments', 1);
        foreach ([['reference_no' => 'REF-2'], ['shipment_no' => 'SHIP-2'], ['reference_no' => null]] as $differentDelivery) {
            $this->post($store, array_replace($payload, $differentDelivery) + ['submission_token' => (string) Str::uuid()])
                ->assertSessionHas('success');
        }
        $this->assertDatabaseCount('doh_shipments', 4);
        $this->assertDatabaseCount('doh_shipment_items', 4);
        $this->assertSame(4, StockLog::where('movement_type', 'receipt')->count());
        $this->assertSame(200, Inventory::where('health_unit_id', $pho->id)->sole()->available_stock);
    }

    public function test_failed_audit_write_rolls_back_the_entire_receipt(): void
    {
        HealthUnit::create(['name' => 'PHO fixture', 'facility_type' => 'Provincial Health Office']);
        $receiver = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $medicine = Medicine::create(['medicine_name' => 'Fixture stock']);
        $payload = ['shipment_no' => 'SHIP-1', 'reference_no' => 'REF-1', 'received_date' => today()->toDateString(),
            'submission_token' => (string) Str::uuid(), 'items' => [['medicine_id' => $medicine->id, 'quantity' => 50]]];
        $rejectAudit = true;
        ActivityLog::creating(function (ActivityLog $entry) use (&$rejectAudit) {
            if ($rejectAudit && $entry->action === 'PHO Receive') {
                throw new \RuntimeException('Simulated receipt audit failure.');
            }
        });

        $this->withSession(['user_id' => $receiver->id])->post(route('inventory.pho_receive.store'), $payload)
            ->assertSessionHas('error', 'Unable to save the shipment. Please try again.');
        foreach (['doh_shipments', 'doh_shipment_items', 'doh_shipment_submissions', 'inventory', 'stock_logs', 'activity_logs'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }

        $rejectAudit = false;
        $this->post(route('inventory.pho_receive.store'), $payload)->assertSessionHas('success');
        $this->assertSame(50, Inventory::sole()->available_stock);
        $this->assertSame('PHO Receive', ActivityLog::sole()->action);
    }

    public function test_reversed_delivery_cannot_be_reposted_from_a_fresh_form(): void
    {
        HealthUnit::create(['name' => 'PHO fixture', 'facility_type' => 'Provincial Health Office']);
        $receiver = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $medicine = Medicine::create(['medicine_name' => 'Fixture stock']);
        $form = route('inventory.pho_receive.create');
        $store = route('inventory.pho_receive.store');
        $payload = ['shipment_no' => 'SHIP-REV', 'reference_no' => 'REF-REV',
            'received_date' => today()->toDateString(),
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 50]]];
        $this->withSession(['user_id' => $receiver->id]);
        $token = $this->get($form)->assertOk()->viewData('submissionToken');
        $this->post($store, $payload + ['submission_token' => $token])->assertSessionHas('success');
        $shipment = DohShipment::sole();
        $this->delete(route('inventory.pho_receive.destroy', $shipment->id))->assertSessionHas('success');

        $newToken = $this->get($form)->assertOk()->viewData('submissionToken');
        $this->assertNotSame($token, $newToken);
        $this->post($store, $payload + ['submission_token' => $newToken])->assertSessionHas('error');
        $this->assertDatabaseCount('doh_shipments', 0);
        $this->assertSame(0, Inventory::sole()->available_stock);
        $this->assertSame(1, StockLog::where('movement_type', 'receipt')->count());
        $this->assertSame(1, StockLog::where('movement_type', 'adjustment_out')->count());

        $newToken = $this->get($form)->assertOk()->viewData('submissionToken');
        $this->post($store, array_replace($payload, ['reference_no' => 'REF-NEXT']) + ['submission_token' => $newToken])
            ->assertSessionHas('success');
        $this->assertSame(50, Inventory::sole()->available_stock);
    }

    public function test_failed_reversal_audit_preserves_stock_and_the_receipt(): void
    {
        HealthUnit::create(['name' => 'PHO fixture', 'facility_type' => 'Provincial Health Office']);
        $receiver = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $medicine = Medicine::create(['medicine_name' => 'Fixture stock']);
        $payload = ['shipment_no' => 'SHIP-REV', 'reference_no' => 'REF-REV',
            'submission_token' => (string) Str::uuid(), 'received_date' => today()->toDateString(),
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 50]]];
        $this->withSession(['user_id' => $receiver->id])->post(route('inventory.pho_receive.store'), $payload)
            ->assertSessionHas('success');
        $shipment = DohShipment::sole();
        $rejectAudit = true;
        ActivityLog::creating(function () use (&$rejectAudit) {
            if ($rejectAudit) {
                throw new \RuntimeException('Simulated reversal audit failure.');
            }
        });

        $reverse = route('inventory.pho_receive.destroy', $shipment->id);
        $this->delete($reverse)->assertSessionHas('error');
        $this->assertSame(50, Inventory::sole()->available_stock);
        $this->assertDatabaseCount('doh_shipments', 1);
        $this->assertDatabaseCount('doh_shipment_items', 1);
        $this->assertDatabaseCount('stock_logs', 1);
        $this->assertDatabaseCount('activity_logs', 1);

        $rejectAudit = false;
        $this->delete($reverse)->assertSessionHas('success');
        $this->assertSame(0, Inventory::sole()->available_stock);
        $this->assertDatabaseCount('activity_logs', 2);
    }

    public function test_receipt_forms_and_actions_require_a_pho_administrator(): void
    {
        $rhu = HealthUnit::create(['name' => 'RHU fixture', 'facility_type' => 'Rural Health Unit']);
        foreach (['admin', 'staff'] as $role) {
            $user = User::factory()->create(['role' => $role, 'health_unit_id' => $rhu->id]);
            $this->withSession(['user_id' => $user->id]);
            foreach (['inventory.pho_receive', 'inventory.pho_receive.create', 'inventory.pho_receive.export'] as $name) {
                $this->get(route($name))->assertRedirect(route('dashboard'));
            }
            $this->post(route('inventory.pho_receive.store'))->assertRedirect(route('dashboard'));
            $this->get(route('inventory.pho_receive.show', 1))->assertRedirect(route('dashboard'));
            $this->delete(route('inventory.pho_receive.destroy', 1))->assertRedirect(route('dashboard'));
        }
        $this->assertDatabaseCount('doh_shipments', 0);
    }
}
