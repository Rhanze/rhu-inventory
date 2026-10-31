<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ActivityLog;
use Carbon\Carbon;

class PruneActivityLogs extends Command
{
    protected $signature = 'logs:prune {--days=30 : Delete logs older than this many days}';
    protected $description = 'Delete old activity logs';

    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);
        $count = ActivityLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Deleted {$count} activity log(s) older than {$days} days.");
    }
}