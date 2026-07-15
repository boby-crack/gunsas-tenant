<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class PriceSimulatorCalculator
{
    private const PRODUCTS = [
        'buah' => [
            'label' => 'Buah Utuh',
            'kg_field' => 'buah_sold_kg',
            'gross_field' => 'buah_subtotal',
            'price_filter' => 'buah_price_per_kg',
        ],
        'fresh' => [
            'label' => 'Kupas Fresh',
            'kg_field' => 'fresh_sold_kg',
            'gross_field' => 'fresh_subtotal',
            'price_filter' => 'fresh_price_per_kg',
        ],
        'frozen' => [
            'label' => 'Durpas Frozen',
            'kg_field' => 'frozen_sold_kg',
            'gross_field' => 'frozen_subtotal',
            'price_filter' => 'frozen_price_per_kg',
        ],
    ];

    public function calculate(array $filters): array
    {
        $dateFrom = $filters['date_from'] ?? now()->subDays(30)->toDateString();
        $dateUntil = $filters['date_until'] ?? now()->toDateString();
        $historyDays = $this->daysBetween($dateFrom, $dateUntil);
        $forecastDays = max(1, (int) ($filters['forecast_days'] ?? 30));
        $targetMargin = max(0, min(90, (float) ($filters['target_margin_percent'] ?? 15))) / 100;
        $includeOverhead = (bool) ($filters['include_overhead'] ?? true);
        $outletFilter = $this->outletFilter($filters);

        $insights = app(BusinessInsightsCalculator::class)->calculate([
            'outlet_group' => $filters['outlet_group'] ?? null,
            'outlet_id' => $filters['outlet_id'] ?? null,
            'date_from' => $dateFrom,
            'date_until' => $dateUntil,
        ]);

        $gunsasSharePercent = max(0, min(100, 100 - (float) ($insights['sales']['partner_share_percent'] ?? $this->partnerShare($outletFilter))));
        $gunsasRate = $gunsasSharePercent / 100;
        $adjustmentPercent = filled($filters['adjustment_percent'] ?? null)
            ? (float) $filters['adjustment_percent']
            : $this->historicalAdjustmentPercent($insights);
        $adjustmentPercent = max(0, min(80, $adjustmentPercent));
        $adjustmentRate = max(0.01, 1 - ($adjustmentPercent / 100));

        $periodOverhead = (float) ($insights['costs']['expenses'] ?? 0)
            + (float) ($insights['costs']['inventory_usage'] ?? 0)
            + (float) ($insights['returns']['loss_final'] ?? 0)
            + (float) ($insights['costs']['opname_loss'] ?? 0);
        $forecastOverhead = $includeOverhead ? ($periodOverhead / $historyDays) * $forecastDays : 0;

        $products = $this->historicalProductBreakdown($filters, $outletFilter, $insights);
        $historicalKg = collect($products)->sum('historical_kg');
        $forecastKgInput = (float) ($filters['forecast_kg'] ?? 0);
        $forecastKg = $forecastKgInput > 0
            ? $forecastKgInput
            : collect($products)->sum('forecast_kg');
        $overheadPerKg = $forecastKg > 0 ? $forecastOverhead / $forecastKg : 0;

        $products = $this->withPricesAndMix(
            $products,
            $filters,
            $forecastKg,
            $overheadPerKg,
            $gunsasRate,
            $targetMargin,
            $adjustmentRate
        );

        $hasAnyPriceInput = collect($products)->contains(fn (array $product) => $product['is_price_from_user']);
        $products = $hasAnyPriceInput
            ? $this->targetFromUserPrices($products, $forecastKg, $forecastOverhead, $gunsasRate, $targetMargin)
            : $this->targetFromRecommendedPrices($products);

        $totals = $this->totals($products, $forecastOverhead, $gunsasRate);
        $warnings = $this->warnings($products, $historicalKg, $gunsasRate, $hasAnyPriceInput, $totals);

        return [
            'mode' => $hasAnyPriceInput ? 'price_to_target' : 'margin_to_price',
            'filters' => [
                'date_from' => $dateFrom,
                'date_until' => $dateUntil,
                'outlet_name' => $insights['filters']['outlet_name'] ?? 'Semua Outlet',
                'target_margin_percent' => $targetMargin * 100,
                'forecast_days' => $forecastDays,
                'include_overhead' => $includeOverhead,
                'adjustment_percent' => $adjustmentPercent,
                'gunsas_share_percent' => $gunsasSharePercent,
            ],
            'historical' => [
                'days' => $historyDays,
                'kg' => $historicalKg,
                'daily_avg_kg' => $historyDays > 0 ? $historicalKg / $historyDays : 0,
                'forecast_from_daily_avg_kg' => collect($products)->sum('forecast_kg'),
                'net_sales' => collect($products)->sum('historical_net_sales'),
            ],
            'costs' => [
                'period_overhead' => $periodOverhead,
                'forecast_overhead' => $forecastOverhead,
                'overhead_per_kg' => $overheadPerKg,
                'hpp_forecast' => $totals['hpp'],
                'total_cost' => $totals['hpp'] + $forecastOverhead,
            ],
            'recommended' => [
                'forecast_kg' => $totals['target_kg'],
                'required_net_sales' => $totals['net_sales'],
                'required_cashier_sales' => $totals['cashier_sales'],
                'required_gunsas_revenue' => $totals['gunsas_revenue'],
                'cashier_price_per_kg' => $totals['target_kg'] > 0 ? $totals['cashier_sales'] / $totals['target_kg'] : 0,
                'min_net_price_per_kg' => $totals['target_kg'] > 0 ? $totals['net_sales'] / $totals['target_kg'] : 0,
                'gunsas_share_percent' => $gunsasSharePercent,
                'gunsas_rate' => $gunsasRate,
                'adjustment_percent' => $adjustmentPercent,
                'net_profit' => $totals['net_profit'],
                'net_margin' => $totals['net_margin'],
            ],
            'product_breakdown' => $products,
            'warnings' => $warnings,
        ];
    }

    private function historicalProductBreakdown(array $filters, mixed $outletFilter, array $insights): array
    {
        $rows = collect(self::PRODUCTS)
            ->mapWithKeys(fn (array $product, string $key) => [
                $key => [
                    'key' => $key,
                    'label' => $product['label'],
                    'historical_kg' => 0.0,
                    'historical_net_sales' => 0.0,
                    'historical_gross_sales' => 0.0,
                    'historical_avg_net_price_per_kg' => 0.0,
                    'unit_modal' => $this->unitModal($key, $insights),
                ],
            ])
            ->all();

        $query = Sale::query()
            ->when($filters['date_from'] ?? null, fn (Builder $query, $date) => $query->where('date', '>=', $date))
            ->when($filters['date_until'] ?? null, fn (Builder $query, $date) => $query->where('date', '<=', $date))
            ->when($filters['durian_variety_id'] ?? null, fn (Builder $query, $id) => $query->where('durian_variety_id', $id));

        $this->applyOutletFilter($query, $outletFilter);

        foreach ($query->get() as $sale) {
            $grossByType = [
                'buah' => (float) $sale->buah_subtotal,
                'fresh' => (float) $sale->fresh_subtotal,
                'frozen' => (float) $sale->frozen_subtotal,
            ];
            $rowGross = (float) $sale->grand_total_revenue > 0 ? (float) $sale->grand_total_revenue : array_sum($grossByType);
            $rowNet = (float) $sale->net_sales > 0
                ? (float) $sale->net_sales
                : max(0, $rowGross - (float) $sale->discount_amount - (float) $sale->sales_return_amount);

            foreach (self::PRODUCTS as $key => $product) {
                $gross = $grossByType[$key] ?? 0;
                $net = $rowGross > 0 ? ($gross / $rowGross) * $rowNet : $gross;

                $rows[$key]['historical_kg'] += (float) $sale->{$product['kg_field']};
                $rows[$key]['historical_gross_sales'] += $gross;
                $rows[$key]['historical_net_sales'] += $net;
            }
        }

        $totalNetSales = collect($rows)->sum('historical_net_sales');
        $totalKg = collect($rows)->sum('historical_kg');
        $historyDays = $this->daysBetween(
            $filters['date_from'] ?? now()->subDays(30)->toDateString(),
            $filters['date_until'] ?? now()->toDateString()
        );
        $forecastDays = max(1, (int) ($filters['forecast_days'] ?? 30));

        return collect($rows)
            ->map(function (array $row) use ($totalNetSales, $totalKg, $historyDays, $forecastDays): array {
                $row['historical_avg_net_price_per_kg'] = $row['historical_kg'] > 0
                    ? $row['historical_net_sales'] / $row['historical_kg']
                    : 0;
                $row['sales_mix_percent'] = $totalNetSales > 0
                    ? ($row['historical_net_sales'] / $totalNetSales) * 100
                    : 0;
                $row['kg_mix_percent'] = $totalKg > 0
                    ? ($row['historical_kg'] / $totalKg) * 100
                    : 0;
                $row['daily_avg_kg'] = $historyDays > 0 ? $row['historical_kg'] / $historyDays : 0;
                $row['forecast_kg'] = $row['daily_avg_kg'] * $forecastDays;

                return $row;
            })
            ->values()
            ->all();
    }

    private function withPricesAndMix(
        array $products,
        array $filters,
        float $forecastKg,
        float $overheadPerKg,
        float $gunsasRate,
        float $targetMargin,
        float $adjustmentRate
    ): array {
        $activeCount = collect($products)->filter(fn (array $row) => $row['historical_kg'] > 0 || $row['historical_net_sales'] > 0)->count();
        $fallbackShare = $activeCount > 0 ? 100 / $activeCount : 100 / count(self::PRODUCTS);

        return collect($products)
            ->map(function (array $row) use ($filters, $forecastKg, $overheadPerKg, $gunsasRate, $targetMargin, $adjustmentRate, $fallbackShare): array {
                $priceFilter = self::PRODUCTS[$row['key']]['price_filter'];
                $userCashierPrice = (float) ($filters[$priceFilter] ?? 0);
                $recommendedNetPrice = ($gunsasRate * (1 - $targetMargin)) > 0
                    ? ($row['unit_modal'] + $overheadPerKg) / ($gunsasRate * (1 - $targetMargin))
                    : 0;
                $recommendedCashierPrice = $recommendedNetPrice / $adjustmentRate;
                $cashierPrice = $userCashierPrice > 0 ? $userCashierPrice : $recommendedCashierPrice;
                $netPrice = $cashierPrice * $adjustmentRate;
                $kgShare = $row['kg_mix_percent'] > 0 ? $row['kg_mix_percent'] : $fallbackShare;

                return [
                    ...$row,
                    'mix_percent' => $kgShare,
                    'forecast_kg' => $forecastKg > 0 ? $forecastKg * ($kgShare / 100) : $row['forecast_kg'],
                    'is_price_from_user' => $userCashierPrice > 0,
                    'user_cashier_price_per_kg' => $userCashierPrice,
                    'cashier_price_per_kg' => $cashierPrice,
                    'net_price_per_kg' => $netPrice,
                    'recommended_cashier_price_per_kg' => $recommendedCashierPrice,
                    'recommended_net_price_per_kg' => $recommendedNetPrice,
                    'margin_safe_per_kg' => ($netPrice * $gunsasRate * (1 - $targetMargin)) - $row['unit_modal'],
                ];
            })
            ->all();
    }

    private function targetFromRecommendedPrices(array $products): array
    {
        return collect($products)
            ->map(function (array $row): array {
                return [
                    ...$row,
                    'target_kg' => $row['forecast_kg'],
                    'target_cashier_sales' => $row['forecast_kg'] * $row['cashier_price_per_kg'],
                    'target_net_sales' => $row['forecast_kg'] * $row['net_price_per_kg'],
                    'target_hpp' => $row['forecast_kg'] * $row['unit_modal'],
                ];
            })
            ->sortByDesc('target_net_sales')
            ->values()
            ->all();
    }

    private function targetFromUserPrices(array $products, float $forecastKg, float $forecastOverhead, float $gunsasRate, float $targetMargin): array
    {
        $blendedSafeContribution = collect($products)
            ->sum(fn (array $row) => ($row['mix_percent'] / 100) * $row['margin_safe_per_kg']);
        $requiredKg = $blendedSafeContribution > 0
            ? ($forecastOverhead > 0 ? $forecastOverhead / $blendedSafeContribution : $forecastKg)
            : 0;

        return collect($products)
            ->map(function (array $row) use ($requiredKg): array {
                $targetKg = $requiredKg * ($row['mix_percent'] / 100);

                return [
                    ...$row,
                    'target_kg' => $targetKg,
                    'target_cashier_sales' => $targetKg * $row['cashier_price_per_kg'],
                    'target_net_sales' => $targetKg * $row['net_price_per_kg'],
                    'target_hpp' => $targetKg * $row['unit_modal'],
                ];
            })
            ->sortByDesc('target_net_sales')
            ->values()
            ->all();
    }

    private function totals(array $products, float $forecastOverhead, float $gunsasRate): array
    {
        $targetKg = collect($products)->sum('target_kg');
        $netSales = collect($products)->sum('target_net_sales');
        $cashierSales = collect($products)->sum('target_cashier_sales');
        $hpp = collect($products)->sum('target_hpp');
        $gunsasRevenue = $netSales * $gunsasRate;
        $netProfit = $gunsasRevenue - $hpp - $forecastOverhead;

        return [
            'target_kg' => $targetKg,
            'net_sales' => $netSales,
            'cashier_sales' => $cashierSales,
            'hpp' => $hpp,
            'gunsas_revenue' => $gunsasRevenue,
            'net_profit' => $netProfit,
            'net_margin' => $gunsasRevenue > 0 ? ($netProfit / $gunsasRevenue) * 100 : 0,
        ];
    }

    private function unitModal(string $productType, array $insights): float
    {
        return match ($productType) {
            'fresh' => (float) ($insights['costs']['avg_modal_fresh'] ?? 0),
            'frozen' => (float) ($insights['costs']['avg_modal_frozen'] ?? 0),
            default => (float) ($insights['costs']['avg_modal_buah'] ?? 0),
        };
    }

    private function historicalAdjustmentPercent(array $insights): float
    {
        $gross = (float) ($insights['sales']['gross_sales'] ?? 0);
        $adjustment = (float) ($insights['sales']['discount_amount'] ?? 0)
            + (float) ($insights['sales']['sales_return_amount'] ?? 0);

        return $gross > 0 ? ($adjustment / $gross) * 100 : 0;
    }

    private function daysBetween(string $from, string $until): int
    {
        return (int) Carbon::parse($from)->diffInDays(Carbon::parse($until)) + 1;
    }

    private function outletFilter(array $filters): mixed
    {
        if (! empty($filters['outlet_id'])) {
            return $filters['outlet_id'];
        }

        if (! empty($filters['outlet_group'])) {
            return Outlet::query()
                ->where('group_name', $filters['outlet_group'])
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

    private function partnerShare(mixed $outletFilter): float
    {
        if (! $outletFilter) {
            return 15;
        }

        if (is_array($outletFilter)) {
            return (float) (Outlet::query()->whereKey($outletFilter)->avg('partner_share_percent') ?? 15);
        }

        return (float) (Outlet::query()->whereKey($outletFilter)->value('partner_share_percent') ?? 15);
    }

    private function warnings(array $products, float $historicalKg, float $gunsasRate, bool $hasAnyPriceInput, array $totals): array
    {
        return collect([
            $historicalKg <= 0 ? 'Belum ada histori KG penjualan. Komposisi produk belum bisa diprediksi akurat.' : null,
            $gunsasRate <= 0 ? 'Persentase bagian Gunsas tidak valid.' : null,
            $hasAnyPriceInput && $totals['target_kg'] <= 0 ? 'Harga yang diinput belum cukup untuk menutup modal, overhead, dan margin target.' : null,
            $hasAnyPriceInput && collect($products)->contains(fn (array $product) => ! $product['is_price_from_user'])
                ? 'Harga yang kosong otomatis memakai rekomendasi sistem.'
                : null,
        ])->filter()->values()->all();
    }
}
