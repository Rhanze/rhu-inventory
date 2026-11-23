<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\TransferRequest;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessTransfers extends Command
{
    protected $signature = 'transfers:process';

    protected $description = 'Remind destination facilities to confirm accepted transfers';

    public function handle(): int
    {
        $now = now();
        $firstCutoff = $now->copy()->subDays(2);
        $count = 0;

        TransferRequest::query()
            ->awaitingReceipt()
            ->where('approved_at', '<=', $firstCutoff)
            ->select(['id', 'approved_at', 'reminder_sent_at'])
            ->eachById(function (TransferRequest $candidate) use ($now, $firstCutoff, &$count) {
                if (! $this->reminderDue($candidate, $now)) {
                    return;
                }

                $count += DB::transaction(function () use ($candidate, $now, $firstCutoff) {
                    $transfer = TransferRequest::whereKey($candidate->id)
                        ->awaitingReceipt()
                        ->where('approved_at', '<=', $firstCutoff)
                        ->lockForUpdate()->first();

                    if (! $transfer || ! $this->reminderDue($transfer, $now)) {
                        return 0;
                    }

                    $overdue = $now->gte($transfer->receiptOverdueAt());
                    Notification::create([
                        'health_unit_id' => $transfer->to_health_unit_id,
                        'transfer_id' => $transfer->id,
                        'type' => 'transfer_reminder',
                        'link' => route('transfers.index', $overdue ? ['overdue' => 1] : []),
                        'title' => $overdue ? 'Transfer receipt overdue' : 'Confirm transfer receipt',
                        'message' => 'Confirm receipt of '.$transfer->formatQuantity($transfer->quantity)
                            .' of '.($transfer->medicine?->medicine_name ?? 'medicine')
                            .' from '.($transfer->fromHealthUnit?->name ?? 'the supplying facility')
                            .' once the supplies arrive. Contact the supplying facility if they are missing.',
                        'severity' => $overdue ? 'danger' : 'warning',
                        'is_read' => false,
                        'created_at' => $now,
                    ]);
                    $transfer->update(['reminder_sent_at' => $now]);

                    return 1;
                }, 3);
            });

        $this->info("Sent {$count} transfer receipt reminder(s).");

        return self::SUCCESS;
    }

    private function reminderDue(TransferRequest $transfer, CarbonInterface $now): bool
    {
        $overdueAt = $transfer->receiptOverdueAt();

        return ! $transfer->reminder_sent_at
            || ($now->gte($overdueAt) && $transfer->reminder_sent_at->lt($overdueAt));
    }
}
