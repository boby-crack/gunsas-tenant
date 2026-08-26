<?php

namespace App\Filament\Widgets;

use App\Models\Outlet;
use App\Models\Production;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class MonthlyProductionShrinkageChart extends ChartWidget
{
    use InteractsWithPageFilters;

    public ?array $dashboardFilters = null;

    protected static ?string $heading = 'Rata-rata Susut Buah ke Kupas Fresh';

    protected static ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        [$dateFrom, $dateUntil] = $this->monthlyRange();
        $rows = $this->monthlyShrinkage($dateFrom, $dateUntil);
        $labels = [];
        $data = [];

        for ($month = $dateFrom->copy(); $month->lte($dateUntil); $month->addMonth()) {
            $key = $month->format('Y-m');
            $labels[] = $month->translatedFormat('M Y');
            $data[] = $rows[$key] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Susut ke Fresh',
                    'data' => $data,
                    'borderColor' => '#f26a00',
                    'backgroundColor' => 'rgba(242, 106, 0, 0.16)',
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
                            text: 'Persen Susut',
                        },
                        ticks: {
                            ...(mobile ? { maxTicksLimit: 4 } : {}),
                            ...(mobile ? { font: { size: 10 } } : {}),
                            callback: (value) => value + '%',
                        },
                    },
                },
                plugins: {
                    legend: {
                        display: ! mobile,
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => context.dataset.label + ': ' + new Intl.NumberFormat('id-ID', {
                                maximumFractionDigits: 2,
                            }).format(context.parsed.y || 0) + '%',
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

    private function monthlyShrinkage(Carbon $dateFrom, Carbon $dateUntil): array
    {
        return $this->productionQuery($dateFrom, $dateUntil)
            ->get(['date', 'qty_buah_kg', 'qty_kupas_kg'])
            ->groupBy(fn (Production $production): string => Carbon::parse($production->date)->format('Y-m'))
            ->map(function ($rows): float {
                $buahKg = (float) $rows->sum('qty_buah_kg');
                $freshKg = (float) $rows->sum('qty_kupas_kg');

                if ($buahKg <= 0) {
                    return 0;
                }

                return round(max(0, (($buahKg - $freshKg) / $buahKg) * 100), 2);
            })
            ->all();
    }

    private function productionQuery(Carbon $dateFrom, Carbon $dateUntil): Builder
    {
        return $this->applyOutletFilter(Production::query(), $this->outletFilter())
            ->where('date', '>=', $dateFrom->toDateString())
            ->where('date', '<=', $dateUntil->toDateString());
    }

    private function monthlyRange(): array
    {
        $filters = $this->dashboardFilters();
        $dateFrom = Carbon::parse($filters['date_from'] ?? now()->subMonths(5)->startOfMonth())->startOfMonth();
        $dateUntil = Carbon::parse($filters['date_until'] ?? now())->endOfMonth();

        if ($dateFrom->diffInMonths($dateUntil) < 1) {
            $dateFrom = $dateUntil->copy()->subMonths(5)->startOfMonth();
        }

        return [$dateFrom, $dateUntil];
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
            return count($outletFilter) > 0 ? $query->whereIn('outlet_id', $outletFilter) : $query->whereRaw('1 = 0');
        }

        return $outletFilter ? $query->where('outlet_id', $outletFilter) : $query;
    }

    private function dashboardFilters(): array
    {
        return $this->dashboardFilters ?? $this->filters ?? [];
    }
}
