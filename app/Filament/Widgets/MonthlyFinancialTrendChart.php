<?php

namespace App\Filament\Widgets;

use App\Models\Outlet;
use App\Models\Sale;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class MonthlyFinancialTrendChart extends ChartWidget
{
    use InteractsWithPageFilters;

    public ?array $dashboardFilters = null;

    protected static ?string $heading = 'Tren Mingguan Sales';

    protected static ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $filters = $this->dashboardFilters();
        $dateFrom = Carbon::parse($filters['date_from'] ?? now()->subWeeks(7)->startOfWeek())->startOfDay();
        $dateUntil = Carbon::parse($filters['date_until'] ?? now())->endOfDay();
        $outletFilter = $this->outletFilter();

        if ($dateFrom->gt($dateUntil)) {
            $dateFrom = $dateUntil->copy()->startOfWeek();
        }

        $labels = [];
        $sales = [];
        $salesByWeek = $this->netSalesByWeek($dateFrom, $dateUntil, $outletFilter);

        for ($week = $dateFrom->copy()->startOfWeek(); $week->lte($dateUntil); $week->addWeek()) {
            $weekStart = $week->copy()->max($dateFrom);
            $weekEnd = $week->copy()->endOfWeek()->min($dateUntil);
            $key = $week->format('o-W');

            $labels[] = $weekStart->isSameDay($weekEnd)
                ? $weekStart->translatedFormat('d M')
                : $weekStart->translatedFormat('d M') . ' - ' . $weekEnd->translatedFormat('d M');
            $sales[] = $salesByWeek[$key] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Sales Net',
                    'data' => $sales,
                    'type' => 'line',
                    'yAxisID' => 'y',
                    'borderColor' => '#f26a00',
                    'backgroundColor' => 'rgba(242, 106, 0, 0.12)',
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Sales',
                        },
                        ticks: {
                            callback: (value) => {
                                return 'Rp ' + new Intl.NumberFormat('id-ID', {
                                    notation: 'compact',
                                    compactDisplay: 'short',
                                    maximumFractionDigits: 1,
                                }).format(value);
                            },
                        },
                    },
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                return context.dataset.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.y || 0);
                            },
                        },
                    },
                },
            }
        JS);
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function netSalesByWeek(Carbon $dateFrom, Carbon $dateUntil, mixed $outletId): array
    {
        return $this->applyOutletFilter(Sale::query(), $outletId)
            ->where('date', '>=', $dateFrom->toDateString())
            ->where('date', '<=', $dateUntil->toDateString())
            ->selectRaw("DATE_FORMAT(date, '%x-%v') as week_key, SUM(CASE WHEN net_sales > 0 THEN net_sales ELSE GREATEST(grand_total_revenue - discount_amount - COALESCE(sales_return_amount, 0), 0) END) as total")
            ->groupBy('week_key')
            ->pluck('total', 'week_key')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    private function outletFilter(): mixed
    {
        $filters = $this->dashboardFilters();

        if (! empty($filters['outlet_id'])) {
            return $filters['outlet_id'];
        }

        if (! empty($filters['outlet_group'])) {
            return Outlet::query()
                ->where('group_name', Outlet::normalizeGroupName($filters['outlet_group']))
                ->pluck('id')
                ->all();
        }

        return null;
    }

    private function applyOutletFilter(Builder $query, mixed $outletFilter): Builder
    {
        if (is_array($outletFilter)) {
            return count($outletFilter) > 0
                ? $query->whereIn('outlet_id', $outletFilter)
                : $query->whereRaw('1 = 0');
        }

        return $outletFilter ? $query->where('outlet_id', $outletFilter) : $query;
    }

    private function dashboardFilters(): array
    {
        return $this->dashboardFilters ?? $this->filters ?? [];
    }
}
