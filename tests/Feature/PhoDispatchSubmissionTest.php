<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\TransferRequest;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhoDispatchSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pho_dispatch_replays_once_and_allows_intentional_identical_dispatches(): void
    {
        [$source, $destination] = array_map(fn ($name) => HealthUnit::create([
            'name' => $name, 'facility_type' => 'Rural Health Unit',
        ]), ['Dispatch source', 'Dispatch destination']);
        $phoAdmin = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $medicine = Medicine::create(['medicine_name' => 'Dispatch fixture', 'is_dispensable' => true]);
        $batch = Inventory::create(['health_unit_id' => $source->id, 'medicine_id' => $medicine->id,
            'batch_no' => 'DISPATCH-QA', 'available_stock' => 100, 'allocated_stock' => 0,
            'expiration_date' => today()->addMonth()]);
        $formUrl = route('transfers.distribute');
        $storeUrl = route('transfers.distribute.store');
        $details = ['from_health_unit_id' => $source->id, 'to_health_unit_id' => $destination->id,
            'medicine_id' => $medicine->id, 'quantity' => 40, 'notes' => 'Keep this batch together.'];
        $this->withSession(['user_id' => $phoAdmin->id]);

        $this->from($formUrl)->post($storeUrl, $details)->assertSessionHasErrors('submission_token');
        $this->post($storeUrl, $details + ['submission_token' => 'invalid'])->assertSessionHasErrors('submission_token');
        $this->assertDatabaseCount('transfer_requests', 0);
        $this->assertSame([100, 0], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);

        $firstToken = $this->get($formUrl)->assertOk()->viewData('submissionToken');
        $first = $details + ['submission_token' => $firstToken];
        $this->post($storeUrl, $first)->assertRedirect(route('transfers.index'))->assertSessionHas('success');
        $this->post($storeUrl, array_replace($first, ['quantity' => '40']))
            ->assertRedirect(route('transfers.index'))->assertSessionHas('success', 'This dispatch has already been processed.');
        foreach ([['quantity' => 41], ['notes' => 'A different dispatch instruction.']] as $change) {
            $this->from($formUrl)->post($storeUrl, array_replace($first, $change))->assertRedirect($formUrl)
                ->assertSessionHas('error', 'This dispatch was already processed with different details. Open a new dispatch form to send another directive.');
        }
        $this->assertDispatchBalances($batch, $destination, $medicine, $phoAdmin, 1);

        $secondToken = $this->get($formUrl)->assertOk()->viewData('submissionToken');
        $this->assertNotSame($firstToken, $secondToken);
        $this->post($storeUrl, $details + ['submission_token' => $secondToken])
            ->assertRedirect(route('transfers.index'))->assertSessionHas('success');
        $this->post($storeUrl, $first)->assertRedirect(route('transfers.index'))
            ->assertSessionHas('success', 'This dispatch has already been processed.');

        $thirdToken = $this->get($formUrl)->assertOk()->viewData('submissionToken');
        $this->from($formUrl)->post($storeUrl, $details + ['submission_token' => $thirdToken])->assertRedirect($formUrl)
            ->assertSessionHas('error', 'The selected source RHU does not have enough stock in a single batch.');
        $this->assertDispatchBalances($batch, $destination, $medicine, $phoAdmin, 2);

        [$savedFirst, $savedSecond] = TransferRequest::oldest('id')->get();
        $this->assertNotEmpty($savedFirst->dispatch_submission_token);
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('transfer_requests')->where('id', $savedSecond->id)
            ->update(['dispatch_submission_token' => $savedFirst->dispatch_submission_token]);
    }

    private function assertDispatchBalances(Inventory $batch, HealthUnit $destination, Medicine $medicine, User $phoAdmin, int $count): void
    {
        $this->assertDatabaseCount('transfer_requests', $count);
        $this->assertDatabaseCount('stock_movements', $count);
        $this->assertDatabaseCount('stock_logs', 0);
        $this->assertSame($count, ActivityLog::where('user_id', $phoAdmin->id)->where('module', 'Transfer')
            ->where('action', 'Distribute')->count());
        foreach (TransferRequest::all() as $transfer) {
            $this->assertSame('accepted', $transfer->status);
            $this->assertTrue($transfer->stock_reserved);
            $this->assertSame(40, $transfer->quantity);
            $this->assertSame($batch->id, (int) $transfer->inventory_id);
            $this->assertNull($transfer->received_by);
            $this->assertMovements('transfer_requests', $transfer->id, [[-40, 40]]);
            $this->assertSame(['pho_directive', 'pho_directive', 'transfer_accepted'],
                Notification::where('transfer_id', $transfer->id)->orderBy('type')->pluck('type')->all());
        }
        $available = 100 - 40 * $count;
        $this->assertSame([$available, 40 * $count], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
        $this->assertPublicStock(HealthUnit::findOrFail($batch->health_unit_id), $medicine, $available);
        $this->assertPublicStock($destination, $medicine, 0);
    }

    private function assertPublicStock(HealthUnit $unit, Medicine $medicine, int $expected): void
    {
        $filters = ['q' => $medicine->medicine_name, 'health_unit_id' => $unit->id];
        $this->get(route('public.home.citizen', $filters))->assertOk()
            ->assertViewHas('medicines', fn ($rows) => $expected === 0 ? $rows->isEmpty() : (int) $rows->sole()->total_available === $expected);
        $this->get(route('public.medicine.show', ['id' => $medicine->id] + $filters))->assertOk()
            ->assertViewHas('availability', fn ($rows) => $expected === 0 ? $rows->isEmpty() : (int) $rows->sole()->total_available === $expected);
    }

    private function assertMovements(string $owner, int $id, array $expected): void
    {
        $movements = DB::table('stock_movements')->where('owner_type', $owner)->where('owner_id', $id)->orderBy('id')->get();
        $this->assertSame($expected, $movements->map(fn ($row) => [(int) $row->available_delta, (int) $row->allocated_delta])->all());
    }
}
