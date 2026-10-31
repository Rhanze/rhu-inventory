<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Prune activity logs older than 30 days every day
        $schedule->command('logs:prune --days=30')->daily();

        // Scan inventory and generate notifications every hour
        $schedule->call(function () {
            $service = new \App\Services\NotificationService();
            $created = $service->scanAndGenerate();
            Log::info('Notification scan completed', ['created' => $created]);
        })->hourly();

        // ▼ NEW: Transfer reminders (Day 2, Day 5) + auto-complete (Day 7)
        // Runs at 2 AM so it doesn't conflict with the hourly notification scan.
        $schedule->command('transfers:process')
                 ->dailyAt('02:00')
                 ->withoutOverlapping()
                 ->onOneServer();   // safe if you ever run multiple app servers
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}