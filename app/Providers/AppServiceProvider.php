<?php

namespace App\Providers;

use App\Services\Donors\ResolveDonorAddressOptions;
use App\Support\Workspaces\WorkspaceContext;
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
        if ($this->app->environment('local') && config('app.local_built_assets')) {
            Vite::useHotFile(storage_path('framework/local-built-assets.hot'));
        }
    }
}
