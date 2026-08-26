<?php

namespace App\Filament\Widgets;

use App\Services\BusinessInsightsCalculator;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

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

        if ($dateFrom->diffInMonths($dateUntil) < 1) {
            $dateFrom = $dateUntil->copy()->subMonths(5)->startOfMonth();
        }

        $labels = [];
        $expenses = [];
        $expensesByMonth = $this->expensesByMonth($dateFrom, $dateUntil);

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

                                return mobile ? label.replace(/\s20\d{2}/, '') : label;
                            },
                        },
                    } } : {}),
                    y: {
                        beginAtZero: true,
                        title: {
                            display: ! mobile,
                            text: 'Expense',
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

    private function expensesByMonth(Carbon $dateFrom, Carbon $dateUntil): array
    {
        $calculator = app(BusinessInsightsCalculator::class);
        $baseFilters = $this->dashboardFilters();
        $expenses = [];

        for ($month = $dateFrom->copy(); $month->lte($dateUntil); $month->addMonth()) {
            $monthFilters = [
                ...$baseFilters,
                'date_from' => $month->copy()->startOfMonth()->toDateString(),
                'date_until' => $month->copy()->endOfMonth()->toDateString(),
            ];

            $expenses[$month->format('Y-m')] = (float) ($calculator->expenseBreakdown($monthFilters)['total'] ?? 0);
        }

        return $expenses;
    }

    private function dashboardFilters(): array
    {
        return $this->dashboardFilters ?? $this->filters ?? [];
    }
}
