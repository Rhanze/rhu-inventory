<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class ScanOverdueDoses extends Command
{
    protected $signature   = 'notifications:scan-overdue-doses
                              {--lookback=14 : How many days back to look for overdue doses}';
    protected $description = 'Create a per-RHU digest notification of overdue vaccine doses';

    public function handle(NotificationService $service): int
    {
        $lookback = (int) $this->option('lookback');

        $count = $service->scanOverdueDoses($lookback);

        $this->info("Created {$count} overdue-dose notification(s).");

        return self::SUCCESS;
    }
}