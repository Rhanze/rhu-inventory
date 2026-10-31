<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\FamilyPlanning;
use App\Observers\FamilyPlanningObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Auto-compute family_planning.next_supply_date from method interval
        // whenever a visit is created or its method/date is changed.
        FamilyPlanning::observe(FamilyPlanningObserver::class);
    }
}