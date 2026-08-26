<?php

namespace App\Filament\Widgets;

use App\Models\Purchase;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class MonthlyPurchaseTrendChart extends ChartWidget
{
    use InteractsWithPageFilters;

    public ?array $dashboardFilters = null;

    protected static ?string $heading = 'Tren Bulanan Purchase';

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
        $purchases = [];
        $purchasesByMonth = $this->purchasesByMonth($dateFrom, $dateUntil);

        for ($month = $dateFrom->copy(); $month->lte($dateUntil); $month->addMonth()) {
            $key = $month->format('Y-m');

            $labels[] = $month->translatedFormat('M Y');
            $purchases[] = $purchasesByMonth[$key] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Purchase',
                    'data' => $purchases,
                    'borderColor' => '#34b9cf',
                    'backgroundColor' => 'rgba(52, 185, 207, 0.22)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            (() => {
                const mobile = window.matchMedia('(max-width: 640px)').matches;
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
                            text: 'Purchase',
                        },
                        ticks: {
                            ...(mobile ? { maxTicksLimit: 4 } : {}),
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
        return 'bar';
    }

    private function purchasesByMonth(Carbon $dateFrom, Carbon $dateUntil): array
    {
        return Purchase::query()
            ->where('date', '>=', $dateFrom->toDateString())
            ->where('date', '<=', $dateUntil->toDateString())
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as month_key, SUM(total_amount + generic_total_amount) as total")
            ->groupBy('month_key')
            ->pluck('total', 'month_key')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    private function dashboardFilters(): array
    {
        return $this->dashboardFilters ?? $this->filters ?? [];
    }
}
