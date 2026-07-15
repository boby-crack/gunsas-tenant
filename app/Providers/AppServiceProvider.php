<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use App\Observers\AuditObserver;
use App\Policies\AuditLogPolicy;
use App\Policies\OperationalModelPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
        $appUrl = (string) config('app.url');
        $appHost = parse_url($appUrl, PHP_URL_HOST);

        if (! app()->runningInConsole()
            && $appHost
            && request()->getHost() === $appHost
            && str_starts_with($appUrl, 'https://')) {
            URL::forceRootUrl($appUrl);
            URL::forceScheme('https');
        }

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => auth()->check()
                ? Blade::render('@livewire(\App\Livewire\AiGunsasChat::class)')
                : '',
        );

        foreach ($this->auditedModels() as $model) {
            $model::observe(AuditObserver::class);
        }

        Gate::policy(\App\Models\AuditLog::class, AuditLogPolicy::class);
        Gate::policy(\App\Models\User::class, UserPolicy::class);

        foreach ($this->operationalModels() as $model) {
            Gate::policy($model, OperationalModelPolicy::class);
        }
    }

    private function auditedModels(): array
    {
        return [
            \App\Models\DurianVariety::class,
            \App\Models\Expense::class,
            \App\Models\InventoryItem::class,
            \App\Models\Outlet::class,
            \App\Models\ProductConversion::class,
            \App\Models\ProductReturn::class,
            \App\Models\Production::class,
            \App\Models\Purchase::class,
            \App\Models\Sale::class,
            \App\Models\SalesTarget::class,
            \App\Models\Shipment::class,
            \App\Models\StockOpname::class,
            \App\Models\WhatsappReport::class,
            \App\Models\User::class,
        ];
    }

    private function operationalModels(): array
    {
        return [
            \App\Models\DurianVariety::class,
            \App\Models\Expense::class,
            \App\Models\InventoryItem::class,
            \App\Models\Outlet::class,
            \App\Models\ProductConversion::class,
            \App\Models\ProductReturn::class,
            \App\Models\Production::class,
            \App\Models\Purchase::class,
            \App\Models\Sale::class,
            \App\Models\SalesTarget::class,
            \App\Models\Shipment::class,
            \App\Models\StockOpname::class,
            \App\Models\WhatsappReport::class,
        ];
    }
}
