<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Support\ClinicalAudit;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PruneActivityLogs extends Command
{
    protected $signature = 'logs:prune {--days=30 : Delete logs older than this many days}';
    protected $description = 'Delete old routine activity logs while retaining clinical audit events';

    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);
        $count = ActivityLog::where('created_at', '<', $cutoff)->where('module', '!=', ClinicalAudit::MODULE)->delete();

        $this->info("Deleted {$count} activity log(s) older than {$days} days.");
    }
}
