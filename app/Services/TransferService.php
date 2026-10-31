<?php

namespace App\Services;

use App\Helpers\ActivityLogger;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Notification;
use App\Models\StockLog;
use App\Models\TransferRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferService
{
    public function reserve(int $id, ?int $sourceUnitId = null): TransferRequest
    {
        return DB::transaction(function () use ($id, $sourceUnitId) {
            $transfer = TransferRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if($sourceUnitId !== null && $transfer->from_health_unit_id != $sourceUnitId, 403);
            if ($transfer->status !== 'pending') {
                $this->fail('This request has already been processed.');
            }
            $batch = Inventory::availableFefo($transfer->from_health_unit_id, $transfer->medicine_id)
                ->where('available_stock', '>=', $transfer->quantity)->lockForUpdate()->first();
            if (! $batch) {
                $this->fail('No unexpired batch has enough available stock.');
            }
            $batch->decrement('available_stock', $transfer->quantity);
            $batch->increment('allocated_stock', $transfer->quantity);
            app(StockMovementService::class)->record($transfer, $batch, -$transfer->quantity, $transfer->quantity);
            $transfer->update([
                'status' => 'accepted', 'stock_reserved' => true, 'inventory_id' => $batch->id,
                'batch_no' => $batch->batch_no, 'pack_type' => $batch->pack_type,
                'pieces_per_pack' => $batch->pieces_per_pack, 'approved_by' => session('user_id'), 'approved_at' => now(),
            ]);
            $this->notify($transfer, 'transfer_accepted', 'Transfer accepted', 'Stock has been reserved. Confirm receipt when it arrives.', [$transfer->to_health_unit_id]);

            return $transfer;
        }, 3);
    }

    public function complete(int $id, ?int $actorId, ?int $destinationUnitId = null, bool $automatic = false): TransferRequest
    {
        return DB::transaction(function () use ($id, $actorId, $destinationUnitId, $automatic) {
            $transfer = TransferRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if($destinationUnitId !== null && $transfer->to_health_unit_id != $destinationUnitId, 403);
            if ($transfer->status === 'received') {
                return $transfer;
            }
            if ($transfer->status !== 'accepted') {
                $this->fail('This transfer is not awaiting receipt.');
            }
            $source = Inventory::whereKey($transfer->inventory_id)->lockForUpdate()->first();
            if (! $source || ($source->expiration_date && $source->expiration_date->lt(today()))) {
                $this->fail('The original batch is missing or expired. Cancel and arrange a new transfer.');
            }
            if (! $transfer->stock_reserved) {
                // Accepted before reservation support: reserve under this same lock.
                if ($source->available_stock < $transfer->quantity) {
                    $this->fail('The historical transfer has insufficient stock.');
                }
                $source->decrement('available_stock', $transfer->quantity);
                $source->increment('allocated_stock', $transfer->quantity);
                app(StockMovementService::class)->record($transfer, $source, -$transfer->quantity, $transfer->quantity);
            }
            if ($source->allocated_stock < $transfer->quantity) {
                $this->fail('The reserved stock balance needs reconciliation.');
            }
            // Serialize creation of a destination batch even when no inventory row exists yet.
            HealthUnit::whereKey($transfer->to_health_unit_id)->lockForUpdate()->firstOrFail();
            $destination = Inventory::firstOrCreate([
                'health_unit_id' => $transfer->to_health_unit_id, 'medicine_id' => $transfer->medicine_id,
                'batch_no' => $source->batch_no, 'expiration_date' => $source->expiration_date,
            ], ['available_stock' => 0, 'allocated_stock' => 0, 'storage_location' => $source->storage_location,
                'pack_type' => $source->pack_type, 'pieces_per_pack' => $source->pieces_per_pack]);
            $source->decrement('allocated_stock', $transfer->quantity);
            $destination->increment('available_stock', $transfer->quantity);
            app(StockMovementService::class)->record($transfer, $source, 0, -$transfer->quantity);
            app(StockMovementService::class)->record($transfer, $destination, $transfer->quantity);
            foreach ([[$source, 'Released', 'transfer_out'], [$destination, 'Added', 'transfer_in']] as [$batch, $verb, $type]) {
                StockLog::create(['medicine_id' => $transfer->medicine_id, 'health_unit_id' => $batch->health_unit_id,
                    'inventory_id' => $batch->id, 'quantity_added' => $transfer->quantity, 'batch_no' => $batch->batch_no,
                    'date_added' => today(), 'added_by' => $actorId, 'movement_type' => $type,
                    'remarks' => $verb.' — Transfer #'.$transfer->id, 'transfer_id' => $transfer->id]);
            }
            $transfer->update(['status' => 'received', 'stock_reserved' => false, 'received_at' => now(),
                'received_by' => $actorId, 'completed_at' => now(), 'auto_completed' => $automatic]);
            $this->notify($transfer, 'transfer_received', 'Transfer received', 'Receipt recorded and batch stock updated.', [$transfer->from_health_unit_id, $transfer->to_health_unit_id]);
            ActivityLogger::log('Transfer', $automatic ? 'Auto-Complete' : 'Receive', 'Completed transfer #'.$id, $transfer->from_health_unit_id, $actorId);

            return $transfer;
        }, 3);
    }

    public function cancel(int $id): void
    {
        DB::transaction(function () use ($id) {
            $transfer = TransferRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless((session('role') === 'admin' && ! session('health_unit_id')) || $transfer->to_health_unit_id == session('health_unit_id'), 403);
            if (! in_array($transfer->status, ['pending', 'accepted'])) {
                $this->fail('This transfer can no longer be cancelled.');
            }
            if ($transfer->stock_reserved) {
                app(StockMovementService::class)->reverse($transfer);
            }
            $transfer->update(['status' => 'cancelled', 'stock_reserved' => false]);
            $this->notify($transfer, 'transfer_cancelled', 'Transfer cancelled', 'Any reserved stock has been returned to availability.', [$transfer->from_health_unit_id]);
        }, 3);
    }

    private function notify(TransferRequest $transfer, string $type, string $title, string $message, array $units): void
    {
        foreach (array_unique($units) as $unit) {
            Notification::create(['health_unit_id' => $unit, 'transfer_id' => $transfer->id,
                'type' => $type, 'title' => $title, 'message' => 'Transfer #'.$transfer->id.': '.$message,
                'link' => route('transfers.index'), 'severity' => 'info', 'is_read' => false, 'created_at' => now()]);
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['stock' => $message]);
    }
}
