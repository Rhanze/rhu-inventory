<?php

use App\Services\NotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('logs:prune --days=30')->daily()->withoutOverlapping();
Schedule::call(fn () => app(NotificationService::class)->scanAndGenerate())
    ->name('inventory-notifications')->hourly()->withoutOverlapping();
Schedule::command('notifications:scan')->dailyAt('01:00')->withoutOverlapping();
Schedule::command('transfers:process')->dailyAt('02:00')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
