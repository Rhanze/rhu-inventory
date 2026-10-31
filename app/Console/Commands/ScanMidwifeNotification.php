<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class ScanMidwifeNotification extends Command
{
    protected $signature = 'notifications:scan
                            {--lookback=14 : Days back for overdue doses}
                            {--stock-threshold=10 : Inventory count for low-stock alert}';

    protected $description = 'Nightly scan: inventory, overdue doses, low stock, EPI reminders';

    public function handle(NotificationService $service): int
    {
        $this->info('Running nightly notification scans...');

        $inventory = $service->scanAndGenerate();
        $this->line("  Inventory alerts (shared):        {$inventory}");

        $overdue = $service->scanOverdueDoses((int) $this->option('lookback'));
        $this->line("  Overdue dose notices (personal):  {$overdue}");

        $lowStock = $service->scanLowStockDigest((int) $this->option('stock-threshold'));
        $this->line("  Low stock digests:                {$lowStock}");

        $epi = $service->scanEpiReminders();
        $this->line("  EPI reminders:                    {$epi}");

        $this->info('Done.');
        return self::SUCCESS;
    }
}