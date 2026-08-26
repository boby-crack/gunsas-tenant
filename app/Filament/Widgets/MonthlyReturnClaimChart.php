<?php

namespace App\Filament\Widgets;

use App\Models\Outlet;
use App\Models\ProductReturn;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class MonthlyReturnClaimChart extends ChartWidget
{
    use InteractsWithPageFilters;

    public ?array $dashboardFilters = null;

    protected static ?string $heading = 'Claim Retur Bulanan';

    protected static ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        [$dateFrom, $dateUntil] = $this->monthlyRange();
        $rows = $this->monthlyReturnClaims($dateFrom, $dateUntil);
        $labels = [];
        $submittedKg = [];
        $acceptedKg = [];
        $refundAmount = [];

        for ($month = $dateFrom->copy(); $month->lte($dateUntil); $month->addMonth()) {
            $key = $month->format('Y-m');
            $labels[] = $month->translatedFormat('M Y');
            $submittedKg[] = $rows[$key]['submitted_kg'] ?? 0;
            $acceptedKg[] = $rows[$key]['accepted_kg'] ?? 0;
            $refundAmount[] = $rows[$key]['refund_amount'] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Retur Diajukan',
                    'data' => $submittedKg,
                    'type' => 'bar',
                    'yAxisID' => 'kg',
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.22)',
                ],
                [
                    'label' => 'Diterima Supplier',
                    'data' => $acceptedKg,
                    'type' => 'bar',
                    'yAxisID' => 'kg',
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.22)',
                ],
                [
                    'label' => 'Refund',
                    'data' => $refundAmount,
                    'type' => 'line',
                    'yAxisID' => 'amount',
                    'borderColor' => '#34b9cf',
                    'backgroundColor' => 'rgba(52, 185, 207, 0.14)',
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
                const mobile = window.matchMedia('(max-width: 640px)').matches;
                const compact = (value, digits = 1) => new Intl.NumberFormat('id-ID', {
                    notation: 'compact',
                    compactDisplay: 'short',
                    maximumFractionDigits: mobile ? 0 : digits,
                }).format(value);

                return {
                aspectRatio: mobile ? 1.25 : 2,
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
                    kg: {
                        beginAtZero: true,
                        position: 'left',
                        title: {
                            display: ! mobile,
                            text: 'KG Retur',
                        },
                        ticks: {
                            ...(mobile ? { maxTicksLimit: 4 } : {}),
                            callback: (value) => mobile ? compact(value) : compact(value, 2) + ' kg',
                        },
                    },
                    amount: {
                        beginAtZero: true,
                        display: ! mobile,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                        title: {
                            display: ! mobile,
                            text: 'Refund',
                        },
                        ticks: {
                            maxTicksLimit: 5,
                            callback: (value) => 'Rp ' + compact(value),
                        },
                    },
                },
                plugins: {
                    legend: mobile ? {
                        position: 'bottom',
                        labels: {
                            boxHeight: mobile ? 10 : 12,
                            boxWidth: mobile ? 10 : 12,
                            padding: mobile ? 10 : 16,
                            usePointStyle: true,
                        },
                    } : {},
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                if (context.dataset.yAxisID === 'amount') {
                                    return context.dataset.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.y || 0);
                                }

                                return context.dataset.label + ': ' + new Intl.NumberFormat('id-ID', {
                                    maximumFractionDigits: 3,
                                }).format(context.parsed.y || 0) + ' kg';
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

    private function monthlyReturnClaims(Carbon $dateFrom, Carbon $dateUntil): array
    {
        return $this->returnQuery($dateFrom, $dateUntil)
            ->get(['date', 'qty_kg', 'supplier_accepted_qty_kg', 'refund_amount'])
            ->groupBy(fn (ProductReturn $return): string => Carbon::parse($return->date)->format('Y-m'))
            ->map(fn ($rows): array => [
                'submitted_kg' => round((float) $rows->sum('qty_kg'), 3),
                'accepted_kg' => round((float) $rows->sum('supplier_accepted_qty_kg'), 3),
                'refund_amount' => round((float) $rows->sum('refund_amount'), 2),
            ])
            ->all();
    }

    private function returnQuery(Carbon $dateFrom, Carbon $dateUntil): Builder
    {
        return $this->applyOutletFilter(ProductReturn::query(), $this->outletFilter())
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
