<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\TransferRequest;
use App\Services\TransferService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ProcessTransfers extends Command
{
    protected $signature = 'transfers:process';

    protected $description = 'Send reminders and auto-complete accepted transfers';

    public function handle(): int
    {
        $this->info('▶ Processing transfers...');

        // Day 2: first warning reminder
        $this->sendReminders(2, 'warning', 'First reminder: confirm transfer receipt');

        // Day 5: final reminder
        $this->sendReminders(5, 'danger', 'Final reminder: confirm transfer receipt');

        // Day 7: auto-complete
        $this->autoComplete(7);

        $this->info('✔ Done.');

        return self::SUCCESS;
    }

    // ================================================================
    // Reminders
    // ================================================================
    private function sendReminders(int $daysAgo, string $severity, string $title): void
    {
        $cutoff = now()->subDays($daysAgo);

        $transfers = TransferRequest::with(['fromHealthUnit', 'toHealthUnit', 'medicine'])
            ->where('status', 'accepted')
            ->whereNull('received_at')
            ->where('approved_at', '<=', $cutoff)
            // Avoid duplicate reminders at the same tier:
            // only remind if reminder_sent_at is null OR older than (daysAgo - 3)
            ->where(function ($q) use ($daysAgo) {
                $q->whereNull('reminder_sent_at')
                    ->orWhere('reminder_sent_at', '<=', now()->subDays($daysAgo - 3));
            })
            ->get();

        foreach ($transfers as $t) {
            Notification::create([
                'health_unit_id' => $t->to_health_unit_id,
                'transfer_id' => $t->id,
                'type' => 'transfer_reminder',
                'link' => route('transfers.index'),
                'title' => $title,
                'message' => 'You have not yet confirmed receipt of '
                                    .$t->formatQuantity($t->quantity)
                                    .' of '.($t->medicine->medicine_name ?? 'medicine')
                                    .' from '.$t->fromHealthUnit->name.'. '
                                    .'Confirm in the notifications page or it will be auto-completed on Day 7.',
                'severity' => $severity,
                'is_read' => false,
                'created_at' => now(),
            ]);

            $t->update(['reminder_sent_at' => now()]);
        }

        $this->info("  · Sent {$transfers->count()} reminder(s) for tier {$daysAgo}d.");
    }

    // ================================================================
    // Auto-complete
    // ================================================================
    private function autoComplete(int $daysAgo): void
    {
        $cutoff = now()->subDays($daysAgo);

        $transfers = TransferRequest::with(['fromHealthUnit', 'toHealthUnit', 'medicine'])
            ->where('status', 'accepted')
            ->whereNull('received_at')
            ->where('approved_at', '<=', $cutoff)
            ->get();

        foreach ($transfers as $transfer) {
            try {
                app(TransferService::class)->complete($transfer->id, null, null, true);
                $this->info('Completed transfer #'.$transfer->id);
            } catch (ValidationException $e) {
                $this->warn('Skipped transfer #'.$transfer->id.': '.$e->getMessage());
            }
        }
    }
}
