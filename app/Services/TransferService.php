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
    public function notifyPhoReviewRequested(TransferRequest $transfer): void
    {
        $this->notify($transfer, Notification::TYPE_TRANSFER_PHO_REVIEW_REQUESTED, 'Stock transfer awaiting PHO review',
            $transfer->toHealthUnit->name.' requested '.$transfer->formatQuantity().' of '.$transfer->medicine->medicine_name
            .' from '.$transfer->fromHealthUnit->name.'.', [null], 'warning');
    }

    public function reviewByPho(int $id, int $reviewerId, bool $approved, ?string $reason = null): void
    {
        DB::transaction(function () use ($id, $reviewerId, $approved, $reason) {
            $transfer = TransferRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            if (! $transfer->isAwaitingPhoReview()) {
                throw ValidationException::withMessages(['transfer' => 'This request is no longer awaiting PHO review.']);
            }

            $reviewedAt = now();
            $transfer->update([
                'pho_review_status' => $approved ? TransferRequest::PHO_REVIEW_APPROVED : TransferRequest::PHO_REVIEW_REJECTED,
                'pho_reviewed_by' => $reviewerId,
                'pho_reviewed_at' => $reviewedAt,
            ]);

            if ($approved) {
                $this->notify($transfer, 'transfer_request', 'PHO-approved stock transfer request',
                    $transfer->toHealthUnit->name.' requested '.$transfer->formatQuantity().' of '
                    .($transfer->medicine?->medicine_name ?? 'medicine').'. Review the request to accept or decline it.'
                    .($transfer->notes ? ' Notes: '.$transfer->notes : ''), [$transfer->from_health_unit_id], 'warning');
                $this->notify($transfer, 'transfer_pho_approved', 'PHO approved your transfer request',
                    'The supplying RHU has been notified to review your request.', [$transfer->to_health_unit_id]);
            } else {
                $transfer->update(['status' => 'rejected', 'rejected_by' => $reviewerId, 'rejected_at' => $reviewedAt,
                    'rejection_reason' => $reason]);
                $this->notify($transfer, 'transfer_pho_rejected', 'PHO rejected your transfer request',
                    'Your request was declined by PHO.'.($reason ? ' Reason: '.$reason : ''), [$transfer->to_health_unit_id], 'danger');
            }

            $this->markPhoReviewNoticeRead($transfer);
            ActivityLogger::log('Transfer', $approved ? 'PHO Approve' : 'PHO Reject', 'Reviewed transfer #'.$id, null, $reviewerId);
        }, 3);
    }

    public function reserve(int $id, ?int $sourceUnitId = null): TransferRequest
    {
        return DB::transaction(function () use ($id, $sourceUnitId) {
            $transfer = TransferRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if($sourceUnitId !== null && $transfer->from_health_unit_id != $sourceUnitId, 403);
            abort_unless($transfer->isVisibleToSource(), 403);
            if (! $transfer->isAwaitingSourceResponse()) {
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

    public function rejectBySource(int $id, int $sourceUnitId, int $actorId, ?string $reason): void
    {
        DB::transaction(function () use ($id, $sourceUnitId, $actorId, $reason) {
            $transfer = TransferRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $transfer->from_health_unit_id === $sourceUnitId && $transfer->isVisibleToSource(), 403);
            if (! $transfer->isAwaitingSourceResponse()) {
                throw ValidationException::withMessages(['transfer' => 'This request is no longer awaiting the supplying RHU.']);
            }

            $transfer->update(['status' => 'rejected', 'rejected_by' => $actorId, 'rejected_at' => now(),
                'rejection_reason' => $reason]);
            $this->notify($transfer, 'transfer_rejected', 'Transfer request rejected',
                $transfer->fromHealthUnit->name.' rejected your request for '.$transfer->formatQuantity().' of '
                .($transfer->medicine?->medicine_name ?? 'medicine').'.'.($reason ? ' Reason: '.$reason : ''),
                [$transfer->to_health_unit_id], 'danger');
            Notification::where('transfer_id', $id)->where('health_unit_id', $sourceUnitId)
                ->where('type', 'transfer_request')->where('is_read', false)->update(['is_read' => true]);
            ActivityLogger::log('Transfer', 'Reject', 'Rejected transfer #'.$id, $sourceUnitId, $actorId);
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
                app(StockMovementService::class)->record($transfer, $source, -$transfer->quantity, $transfer->quantity, 'Original accepted batch reservation');
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
                app(StockMovementService::class)->reverse($transfer, 'Transfer cancelled and reserved stock returned');
            }
            $transfer->update(['status' => 'cancelled', 'stock_reserved' => false]);
            if ($transfer->isVisibleToSource()) {
                $this->notify($transfer, 'transfer_cancelled', 'Transfer cancelled', 'Any reserved stock has been returned to availability.', [$transfer->from_health_unit_id]);
            } else {
                $this->markPhoReviewNoticeRead($transfer);
            }
        }, 3);
    }

    private function markPhoReviewNoticeRead(TransferRequest $transfer): void
    {
        Notification::where('transfer_id', $transfer->id)->where('type', Notification::TYPE_TRANSFER_PHO_REVIEW_REQUESTED)
            ->where('is_read', false)->update(['is_read' => true]);
    }

    private function notify(TransferRequest $transfer, string $type, string $title, string $message, array $units, string $severity = 'info'): void
    {
        foreach (array_unique($units) as $unit) {
            Notification::create(['health_unit_id' => $unit, 'transfer_id' => $transfer->id,
                'type' => $type, 'title' => $title, 'message' => 'Transfer #'.$transfer->id.': '.$message,
                'link' => route('transfers.index'), 'severity' => $severity, 'is_read' => false, 'created_at' => now()]);
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['stock' => $message]);
    }
}
