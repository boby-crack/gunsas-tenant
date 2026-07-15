<?php

namespace App\Filament\Widgets;

use App\Models\Outlet;
use App\Models\Sale;
use App\Models\SalesTarget;
use App\Services\BusinessInsightsCalculator;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class DashboardKpiOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    public ?array $dashboardFilters = null;

    protected static ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected int | string | array $columns = [
        'xl' => 4,
    ];

    protected function getStats(): array
    {
        $pageFilters = $this->dashboardFilters();

        $filters = [
            'outlet_group' => Outlet::normalizeGroupName($pageFilters['outlet_group'] ?? null),
            'outlet_id' => $pageFilters['outlet_id'] ?? null,
            'date_from' => $pageFilters['date_from'] ?? now()->startOfMonth()->toDateString(),
            'date_until' => $pageFilters['date_until'] ?? now()->toDateString(),
        ];

        $insights = app(BusinessInsightsCalculator::class)->calculate($filters);
        $targetSummary = $this->targetSummary($filters);

        return [
            Stat::make('Sales Net', 'Rp ' . number_format($insights['sales']['net_sales'], 0, ',', '.'))
                ->description('Basis target dan bagi hasil')
                ->color('info'),

            Stat::make('Profit Bersih', 'Rp ' . number_format($insights['profit']['net_profit'], 0, ',', '.'))
                ->description('Setelah HPP, expense, inventory terpakai, retur, opname')
                ->color($insights['profit']['net_profit'] >= 0 ? 'success' : 'danger'),

            Stat::make('Margin Bersih Sebenarnya', number_format($insights['profit']['net_margin'], 2, ',', '.') . '%')
                ->description('Profit bersih / pendapatan Gunsas')
                ->color($insights['profit']['net_margin'] >= 20 ? 'success' : ($insights['profit']['net_margin'] >= 10 ? 'warning' : 'danger')),

            Stat::make('Outlet Capai Target', "{$targetSummary['achieved']} / {$targetSummary['total']}")
                ->description($targetSummary['total'] > 0 ? 'Outlet dengan target aktif pada periode ini' : 'Belum ada target aktif')
                ->color($targetSummary['missed'] === 0 && $targetSummary['total'] > 0 ? 'success' : 'warning'),
        ];
    }

    private function targetSummary(array $filters): array
    {
        $outletIds = Outlet::query()
            ->when($filters['outlet_id'], fn ($query, $outletId) => $query->whereKey($outletId))
            ->when(! $filters['outlet_id'] && ($filters['outlet_group'] ?? null), fn ($query) => $query->where('group_name', $filters['outlet_group']))
            ->pluck('id');

        if ($outletIds->isEmpty()) {
            return [
                'total' => 0,
                'achieved' => 0,
                'missed' => 0,
            ];
        }

        $targets = SalesTarget::query()
            ->where('metric', 'net_sales')
            ->whereIn('outlet_id', $outletIds)
            ->when($filters['date_from'], fn (Builder $query, $date) => $query->where('period_end', '>=', $date))
            ->when($filters['date_until'], fn (Builder $query, $date) => $query->where('period_start', '<=', $date))
            ->selectRaw('outlet_id, SUM(target_amount) as target_amount')
            ->groupBy('outlet_id')
            ->pluck('target_amount', 'outlet_id');

        $actuals = Sale::query()
            ->whereIn('outlet_id', $outletIds)
            ->when($filters['date_from'], fn (Builder $query, $date) => $query->where('date', '>=', $date))
            ->when($filters['date_until'], fn (Builder $query, $date) => $query->where('date', '<=', $date))
            ->selectRaw('outlet_id, SUM(CASE WHEN net_sales > 0 THEN net_sales ELSE GREATEST(grand_total_revenue - discount_amount - COALESCE(sales_return_amount, 0), 0) END) as actual_amount')
            ->groupBy('outlet_id')
            ->pluck('actual_amount', 'outlet_id');

        $total = 0;
        $achieved = 0;

        foreach ($targets as $outletId => $target) {
            if ($target <= 0) {
                continue;
            }

            $total++;
            $actual = (float) ($actuals[$outletId] ?? 0);

            if ($actual >= $target) {
                $achieved++;
            }
        }

        return [
            'total' => $total,
            'achieved' => $achieved,
            'missed' => max(0, $total - $achieved),
        ];
    }

    private function dashboardFilters(): array
    {
        return $this->dashboardFilters ?? $this->filters ?? [];
    }
}
