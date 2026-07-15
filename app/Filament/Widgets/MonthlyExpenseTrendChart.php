<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Outlet;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class MonthlyExpenseTrendChart extends ChartWidget
{
    use InteractsWithPageFilters;

    public ?array $dashboardFilters = null;

    protected static ?string $heading = 'Tren Bulanan Expense';

    protected static ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $filters = $this->dashboardFilters();
        $dateFrom = Carbon::parse($filters['date_from'] ?? now()->subMonths(5)->startOfMonth())->startOfMonth();
        $dateUntil = Carbon::parse($filters['date_until'] ?? now())->endOfMonth();
        $outletFilter = $this->outletFilter();

        if ($dateFrom->diffInMonths($dateUntil) < 1) {
            $dateFrom = $dateUntil->copy()->subMonths(5)->startOfMonth();
        }

        $labels = [];
        $expenses = [];
        $expensesByMonth = $this->expensesByMonth($dateFrom, $dateUntil, $outletFilter);

        for ($month = $dateFrom->copy(); $month->lte($dateUntil); $month->addMonth()) {
            $key = $month->format('Y-m');

            $labels[] = $month->translatedFormat('M Y');
            $expenses[] = $expensesByMonth[$key] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Expense',
                    'data' => $expenses,
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.12)',
                    'fill' => true,
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
                            text: 'Expense',
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

    private function expensesByMonth(Carbon $dateFrom, Carbon $dateUntil, mixed $outletFilter): array
    {
        return $this->applyExpenseFilter(Expense::query(), $outletFilter)
            ->where('date', '>=', $dateFrom->toDateString())
            ->where('date', '<=', $dateUntil->toDateString())
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as month_key, SUM(amount) as total")
            ->groupBy('month_key')
            ->pluck('total', 'month_key')
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

    private function applyExpenseFilter(Builder $query, mixed $outletFilter): Builder
    {
        if (is_array($outletFilter)) {
            return count($outletFilter) > 0
                ? $query->where(fn (Builder $query) => $query->whereIn('outlet_id', $outletFilter)->orWhereNull('outlet_id'))
                : $query->whereRaw('1 = 0');
        }

        return $outletFilter ? $query->where('outlet_id', $outletFilter) : $query;
    }

    private function dashboardFilters(): array
    {
        return $this->dashboardFilters ?? $this->filters ?? [];
    }
}
