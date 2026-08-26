<?php

namespace App\Filament\Pages;

use App\Models\Outlet;
use App\Services\SalesTargetCalculator;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Collection;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.pages.dashboard';

    public ?array $filters = [];

    public function mount(): void
    {
        $filters = request()->input('filters', []);

        $this->filters = [
            'outlet_group' => Outlet::normalizeGroupName($filters['outlet_group'] ?? null),
            'outlet_id' => filled($filters['outlet_id'] ?? null) ? $filters['outlet_id'] : null,
            'date_from' => filled($filters['date_from'] ?? null) ? $filters['date_from'] : now()->startOfMonth()->toDateString(),
            'date_until' => filled($filters['date_until'] ?? null) ? $filters['date_until'] : now()->toDateString(),
        ];
    }

    public function getOutletGroupOptions(): array
    {
        return Outlet::GROUPS;
    }

    public function getOutletOptions(): array
    {
        return Outlet::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\DashboardKpiOverview::class,
            \App\Filament\Widgets\MonthlyFinancialTrendChart::class,
            \App\Filament\Widgets\MonthlyExpenseTrendChart::class,
            \App\Filament\Widgets\MonthlyPurchaseTrendChart::class,
            \App\Filament\Widgets\MonthlyProductionShrinkageChart::class,
            \App\Filament\Widgets\MonthlyProcessedProductionChart::class,
            \App\Filament\Widgets\MonthlyReturnClaimChart::class,
        ];
    }

    public function getKpiWidgets(): array
    {
        return [
            \App\Filament\Widgets\DashboardKpiOverview::class,
        ];
    }

    public function getChartWidgets(): array
    {
        return [
            \App\Filament\Widgets\MonthlyFinancialTrendChart::class,
            \App\Filament\Widgets\MonthlyExpenseTrendChart::class,
            \App\Filament\Widgets\MonthlyPurchaseTrendChart::class,
            \App\Filament\Widgets\MonthlyProductionShrinkageChart::class,
            \App\Filament\Widgets\MonthlyProcessedProductionChart::class,
            \App\Filament\Widgets\MonthlyReturnClaimChart::class,
        ];
    }

    public function getTargetPerformanceRows(): array
    {
        $calculator = app(SalesTargetCalculator::class);
        $dateFrom = $this->filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateUntil = $this->filters['date_until'] ?? now()->toDateString();
        $outletId = $this->filters['outlet_id'] ?? null;
        $outletGroup = Outlet::normalizeGroupName($this->filters['outlet_group'] ?? null);

        return $this->targetOutlets($outletId, $outletGroup)
            ->map(function (Outlet $outlet) use ($calculator, $dateFrom, $dateUntil): array {
                $target = $calculator->targetAmount('net_sales', $dateFrom, $dateUntil, $outlet->id);
                $actual = $calculator->actual('net_sales', $dateFrom, $dateUntil, $outlet->id);
                $achievement = $target > 0 ? ($actual / $target) * 100 : 0;

                return [
                    'name' => $outlet->name,
                    'target' => $target,
                    'actual' => $actual,
                    'achievement' => $achievement,
                    'status' => $target <= 0 ? 'Belum ada target' : ($actual >= $target ? 'Capai target' : 'Belum capai'),
                    'gap' => $actual - $target,
                ];
            })
            ->all();
    }

    public function formatCurrency(float $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    private function targetOutlets(mixed $outletId, ?string $outletGroup): Collection
    {
        return Outlet::query()
            ->when($outletId, fn ($query, $outletId) => $query->whereKey($outletId))
            ->when(! $outletId && $outletGroup, fn ($query) => $query->where('group_name', $outletGroup))
            ->orderBy('name')
            ->get();
    }
}
