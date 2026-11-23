<?php

namespace App\Console\Commands;

use App\Services\VaccinationScheduleService;
use Illuminate\Console\Command;

class MaintainVaccinationSchedule extends Command
{
    protected $signature = 'vaccinations:maintain-schedule';

    protected $description = 'Mark overdue vaccination appointments missed and schedule eligible replacements';

    public function handle(VaccinationScheduleService $schedules): int
    {
        $result = $schedules->maintainSchedule();
        $this->info('Appointments marked missed: '.$result['missed']);
        $this->info('Replacement appointments created: '.$result['rescheduled']);

        return self::SUCCESS;
    }
}
