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
        $dateUntil = Carbon::parse($filters['date_until'] ?? now())->endOfDay();
        $minimumDateFrom = $dateUntil->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay();
        $dateFrom = Carbon::parse($filters['date_from'] ?? $minimumDateFrom)->startOfDay();
        $outletFilter = $this->outletFilter();

        if ($dateFrom->gt($dateUntil)) {
            $dateFrom = $minimumDateFrom;
        }

        if ($dateFrom->gt($minimumDateFrom)) {
            $dateFrom = $minimumDateFrom;
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
            (() => {
                const mobile = window.matchMedia('(max-width: 768px)').matches;
                const compact = (value) => new Intl.NumberFormat('id-ID', {
                    notation: 'compact',
                    compactDisplay: 'short',
                    maximumFractionDigits: mobile ? 0 : 1,
                }).format(value);

                return {
                aspectRatio: mobile ? 1.35 : 2,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                layout: {
                    padding: mobile ? { left: 0, right: 0 } : {},
                },
                scales: {
                    ...(mobile ? { x: {
                        grid: {
                            display: false,
                        },
                        ticks: {
                            autoSkip: true,
                            maxRotation: 0,
                            minRotation: 0,
                            maxTicksLimit: mobile ? 4 : 8,
                            font: {
                                size: 10,
                            },
                            callback: function (value) {
                                const label = this.getLabelForValue(value);

                                return mobile ? label.replace(/\s20\d{2}/, '').replace(' - ', '\n') : label;
                            },
                        },
                    } } : {}),
                    y: {
                        beginAtZero: true,
                        title: {
                            display: ! mobile,
                            text: 'Sales',
                        },
                        ticks: {
                            ...(mobile ? { maxTicksLimit: 4 } : {}),
                            ...(mobile ? { font: { size: 10 } } : {}),
                            callback: (value) => 'Rp ' + compact(value),
                        },
                    },
                },
                plugins: {
                    legend: {
                        display: ! mobile,
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                return context.dataset.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.y || 0);
                            },
                        },
                    },
                },
            };
            })()
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
