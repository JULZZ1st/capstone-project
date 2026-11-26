<?php

namespace App\Providers;

use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Services\Donors\ResolveDonorAddressOptions;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(WorkspaceContext::class, fn (): WorkspaceContext => new WorkspaceContext());
        $this->app->scoped(ResolveDonorAddressOptions::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'contribution' => Contribution::class,
            'cash_disbursement' => CashDisbursement::class,
        ]);

        if ($this->app->environment('local') && config('app.local_built_assets')) {
            Vite::useHotFile(storage_path('framework/local-built-assets.hot'));
        }
    }
}
