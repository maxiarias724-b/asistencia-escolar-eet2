<?php

namespace App\Providers;

use App\Models\DisciplinaryRecord;
use App\Models\QrIntervention;
use App\Policies\DisciplinaryRecordPolicy;
use App\Policies\QrInterventionPolicy;
use App\Services\InstitutionalStateSyncService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(DisciplinaryRecord::class, DisciplinaryRecordPolicy::class);
        Gate::policy(QrIntervention::class, QrInterventionPolicy::class);

        if (! $this->app->runningUnitTests()) {
            app(InstitutionalStateSyncService::class)->importSnapshotIfNewer();
        }
    }
}
