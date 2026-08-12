<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use App\Models\{Item, PurchaseOrder, StockMovement};
use App\Policies\{ItemPolicy, PurchaseOrderPolicy, StockMovementPolicy};
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Paginator::useBootstrapFive();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(StockMovement::class, StockMovementPolicy::class);
    }
}
