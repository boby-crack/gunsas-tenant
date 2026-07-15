<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Outlet;
use App\Models\ProductConversion;
use App\Models\ProductReturn;
use App\Models\Production;
use App\Models\Sale;
use App\Models\Shipment;
use App\Models\StockOpname;
use Illuminate\Database\Eloquent\Builder;

class BusinessInsightsCalculator
{
    private const GLOBAL_EXPENSE_ALLOCATION_CATEGORIES = [
        'Bensin & Tol',
        'Logistik / Kurir',
        'Lain-lain',
        'Parkir',
    ];

    public function calculate(array $filters): array
    {
        $outletId = $filters['outlet_id'] ?? null;
        $outletFilter = $this->outletFilter($filters);

        $salesTotals = $this->salesTotals($filters, $outletFilter);
        $grossSales = $salesTotals['gross_sales'];
        $discountAmount = $salesTotals['discount_amount'];
        $salesReturnAmount = $salesTotals['sales_return_amount'];
        $netSales = $salesTotals['net_sales'];
        $netSales = $netSales > 0 ? $netSales : max(0, $grossSales - $discountAmount - $salesReturnAmount);
        $partnerCut = $salesTotals['partner_cut'];
        $gunsasRevenue = $salesTotals['gunsas_revenue'];

        $avgModalBuah = $this->weightedAverageModalBuah($filters, $outletFilter);
        $avgModalFresh = $this->averageModalFresh($filters, $outletFilter, $avgModalBuah);
        $avgModalFrozen = $this->averageModalFrozen($filters, $outletFilter, $avgModalFresh);

        $buahSoldKg = $salesTotals['buah_sold_kg'];
        $freshSoldKg = $salesTotals['fresh_sold_kg'];
        $frozenSoldKg = $salesTotals['frozen_sold_kg'];

        $hppSales = ($buahSoldKg * $avgModalBuah)
            + ($freshSoldKg * $avgModalFresh)
            + ($frozenSoldKg * $avgModalFrozen);

        $grossProfit = $gunsasRevenue - $hppSales;
        $expenseBreakdown = $this->expenseAmount($filters, $outletFilter);
        $expenses = $expenseBreakdown['total'];
        $returnSummary = $this->returnSummary($filters, $outletFilter, $avgModalBuah);
        $opnameLoss = $this->opnameLoss($filters, $outletFilter, $avgModalBuah, $avgModalFresh, $avgModalFrozen);
        $inventoryUsage = $this->inventoryUsage($filters, $outletFilter);
        $lossBreakdown = $this->lossBreakdown($filters, $outletFilter, $returnSummary, $opnameLoss, $avgModalBuah, $avgModalFresh);
        $netProfit = $grossProfit - $expenses - $inventoryUsage['amount'] - $returnSummary['loss_final'] - $opnameLoss['amount'];
        $netMargin = $gunsasRevenue > 0 ? ($netProfit / $gunsasRevenue) * 100 : 0;
        $inventory = $this->inventoryValuation($filters, $outletFilter, $avgModalBuah, $avgModalFresh, $avgModalFrozen);
        $productionEfficiency = $this->productionEfficiency($filters, $outletFilter);

        return [
            'filters' => [
                'date_from' => $filters['date_from'] ?? now()->startOfMonth()->toDateString(),
                'date_until' => $filters['date_until'] ?? now()->toDateString(),
                'outlet_name' => $this->outletFilterLabel($filters),
            ],
            'sales' => [
                'gross_sales' => $grossSales,
                'discount_amount' => $discountAmount,
                'sales_return_amount' => $salesReturnAmount,
                'net_sales' => $netSales,
                'partner_share_percent' => $salesTotals['partner_share_percent'],
                'partner_cut' => $partnerCut,
                'tiptop_cut' => $partnerCut,
                'gunsas_revenue' => $gunsasRevenue,
                'buah_sold_kg' => $buahSoldKg,
                'fresh_sold_kg' => $freshSoldKg,
                'frozen_sold_kg' => $frozenSoldKg,
            ],
            'costs' => [
                'avg_modal_buah' => $avgModalBuah,
                'avg_modal_fresh' => $avgModalFresh,
                'avg_modal_frozen' => $avgModalFrozen,
                'hpp_sales' => $hppSales,
                'expenses' => $expenses,
                'direct_expenses' => $expenseBreakdown['direct'],
                'allocated_global_expenses' => $expenseBreakdown['allocated_global'],
                'inventory_usage' => $inventoryUsage['amount'],
                'inventory_usage_items' => $inventoryUsage['items'],
                'opname_loss' => $opnameLoss['amount'],
                'opname_loss_kg' => $opnameLoss,
            ],
            'returns' => $returnSummary,
            'loss_breakdown' => $lossBreakdown,
            'profit' => [
                'gross_profit' => $grossProfit,
                'net_profit' => $netProfit,
                'net_margin' => $netMargin,
                'net_asset_position' => $netProfit + $inventory['amount'],
            ],
            'inventory' => $inventory,
            'production_efficiency' => $productionEfficiency,
            'sales_by_product' => $this->salesByProduct($filters, $outletFilter),
            'profit_by_outlet' => $this->profitByOutlet($filters, $outletFilter),
            'top_outlets' => $this->topOutlets($filters, $outletFilter),
            'expense_categories' => $this->expenseCategories($filters, $outletFilter),
        ];
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

    private function outletFilterLabel(array $filters): string
    {
        if (! empty($filters['outlet_id'])) {
            return Outlet::find($filters['outlet_id'])?->name ?? 'Outlet tidak ditemukan';
        }

        if (! empty($filters['outlet_group'])) {
            return Outlet::GROUPS[$filters['outlet_group']] ?? $filters['outlet_group'];
        }

        return 'Semua Outlet';
    }

    private function applyOutletFilter(Builder $query, mixed $outletFilter, string $column = 'outlet_id'): Builder
    {
        if (is_array($outletFilter)) {
            return count($outletFilter) > 0
                ? $query->whereIn($column, $outletFilter)
                : $query->whereRaw('1 = 0');
        }

        return $outletFilter ? $query->where($column, $outletFilter) : $query;
    }

    private function lossBreakdown(
        array $filters,
        mixed $outletId,
        array $returnSummary,
        array $opnameLoss,
        float $avgModalBuah,
        float $avgModalFresh
    ): array {
        $productionTotals = $this->periodQuery(Production::query(), $filters, $outletId)
            ->selectRaw('
                COALESCE(SUM(qty_buah_kg), 0) as input_kg,
                COALESCE(SUM(total_usable_meat_kg), 0) as usable_kg,
                COALESCE(SUM(CASE WHEN COALESCE(source_type, "normal") <> "return" THEN qty_buah_kg ELSE 0 END), 0) as normal_input_kg,
                COALESCE(SUM(CASE WHEN COALESCE(source_type, "normal") <> "return" THEN total_usable_meat_kg ELSE 0 END), 0) as normal_usable_kg
            ')
            ->first();
        $productionInputKg = (float) $productionTotals->input_kg;
        $productionUsableKg = (float) $productionTotals->usable_kg;
        $productionShrinkKg = max(0, $productionInputKg - $productionUsableKg);
        $normalProductionShrinkKg = max(0, (float) $productionTotals->normal_input_kg - (float) $productionTotals->normal_usable_kg);

        $conversionTotals = $this->periodQuery(ProductConversion::query(), $filters, $outletId)
            ->where('conversion_type', 'Kupas Fresh ke Kupas Frozen')
            ->selectRaw('
                COALESCE(SUM(from_qty_kg), 0) as input_kg,
                COALESCE(SUM(to_qty_kg), 0) as output_kg
            ')
            ->first();
        $conversionInputKg = (float) $conversionTotals->input_kg;
        $conversionOutputKg = (float) $conversionTotals->output_kg;
        $conversionShrinkKg = max(0, $conversionInputKg - $conversionOutputKg);

        $directLossKg = $returnSummary['rejected_kg'] + $opnameLoss['total_kg'];
        $processShrinkKg = $productionShrinkKg + $conversionShrinkKg;
        $productionShrinkAmount = $normalProductionShrinkKg * $avgModalBuah;

        return [
            'direct_loss_kg' => $directLossKg,
            'direct_loss_amount' => $returnSummary['loss_final'] + $opnameLoss['amount'],
            'process_shrink_kg' => $processShrinkKg,
            'process_shrink_amount' => $productionShrinkAmount + ($conversionShrinkKg * $avgModalFresh),
            'total_kg' => $directLossKg + $processShrinkKg,
            'items' => [
                [
                    'label' => 'Retur final ditolak supplier',
                    'kg' => $returnSummary['rejected_kg'],
                    'amount' => $returnSummary['loss_final'],
                    'impact' => 'Mengurangi profit',
                ],
                [
                    'label' => 'Stok opname minus',
                    'kg' => $opnameLoss['total_kg'],
                    'amount' => $opnameLoss['amount'],
                    'impact' => 'Mengurangi profit',
                ],
                [
                    'label' => 'Susut kupas buah ke fresh',
                    'kg' => $productionShrinkKg,
                    'amount' => $productionShrinkAmount,
                    'impact' => 'Normal masuk modal fresh; return tidak tambah HPP',
                ],
                [
                    'label' => 'Susut fresh ke durpas frozen',
                    'kg' => $conversionShrinkKg,
                    'amount' => $conversionShrinkKg * $avgModalFresh,
                    'impact' => 'Masuk ke modal frozen',
                ],
            ],
        ];
    }

    private function periodQuery(Builder $query, array $filters, mixed $outletId = null): Builder
    {
        return $this->applyOutletFilter($query, $outletId)
            ->when($filters['date_from'] ?? null, fn (Builder $query, $date) => $query->where('date', '>=', $date))
            ->when($filters['date_until'] ?? null, fn (Builder $query, $date) => $query->where('date', '<=', $date));
    }

    private function salesTotals(array $filters, mixed $outletId = null): array
    {
        $netSalesExpression = 'CASE WHEN sales.net_sales > 0 THEN sales.net_sales ELSE GREATEST(sales.grand_total_revenue - sales.discount_amount - COALESCE(sales.sales_return_amount, 0), 0) END';
        $partnerShareExpression = 'COALESCE(outlets.partner_share_percent, 15)';
        $fallbackPartnerShare = $this->configuredPartnerShare($outletId);

        $totals = $this->periodQuery(Sale::query(), $filters, $outletId)
            ->leftJoin('outlets', 'sales.outlet_id', '=', 'outlets.id')
            ->selectRaw('
                COALESCE(SUM(sales.grand_total_revenue), 0) as gross_sales,
                COALESCE(SUM(sales.discount_amount), 0) as discount_amount,
                COALESCE(SUM(COALESCE(sales.sales_return_amount, 0)), 0) as sales_return_amount,
                COALESCE(SUM(' . $netSalesExpression . '), 0) as net_sales,
                COALESCE(SUM(sales.buah_sold_kg), 0) as buah_sold_kg,
                COALESCE(SUM(sales.fresh_sold_kg), 0) as fresh_sold_kg,
                COALESCE(SUM(sales.frozen_sold_kg), 0) as frozen_sold_kg,
                COALESCE(SUM((' . $netSalesExpression . ') * (' . $partnerShareExpression . ') / 100), 0) as partner_cut,
                COALESCE(SUM((' . $netSalesExpression . ') * (100 - (' . $partnerShareExpression . ')) / 100), 0) as gunsas_revenue
            ')
            ->first();

        $netSales = (float) $totals->net_sales;
        $partnerCut = (float) $totals->partner_cut;

        return [
            'gross_sales' => (float) $totals->gross_sales,
            'discount_amount' => (float) $totals->discount_amount,
            'sales_return_amount' => (float) $totals->sales_return_amount,
            'net_sales' => $netSales,
            'buah_sold_kg' => (float) $totals->buah_sold_kg,
            'fresh_sold_kg' => (float) $totals->fresh_sold_kg,
            'frozen_sold_kg' => (float) $totals->frozen_sold_kg,
            'partner_cut' => $partnerCut,
            'gunsas_revenue' => (float) $totals->gunsas_revenue,
            'partner_share_percent' => $netSales > 0 ? ($partnerCut / $netSales) * 100 : $fallbackPartnerShare,
        ];
    }

    private function productionEfficiency(array $filters, mixed $outletId = null): array
    {
        $totals = $this->periodQuery(Production::query(), $filters, $outletId)
            ->selectRaw('
                COALESCE(SUM(qty_buah_kg), 0) as input_kg,
                COALESCE(SUM(qty_kupas_kg), 0) as fresh_kg,
                COALESCE(SUM(qty_olahan_kg), 0) as olahan_kg,
                COALESCE(SUM(total_usable_meat_kg), 0) as usable_kg,
                COALESCE(SUM(qty_kupas_kg + qty_olahan_kg), 0) as calculated_usable_kg,
                COUNT(*) as production_count
            ')
            ->first();

        $inputKg = (float) $totals->input_kg;
        $freshKg = (float) $totals->fresh_kg;
        $olahanKg = (float) $totals->olahan_kg;
        $usableKg = (float) $totals->usable_kg;

        if ($usableKg <= 0) {
            $usableKg = (float) $totals->calculated_usable_kg;
        }

        $shrinkKg = max(0, $inputKg - $usableKg);

        return [
            'input_kg' => $inputKg,
            'usable_kg' => $usableKg,
            'fresh_kg' => $freshKg,
            'olahan_kg' => $olahanKg,
            'shrink_kg' => $shrinkKg,
            'shrinkage_percentage' => $inputKg > 0 ? ($shrinkKg / $inputKg) * 100 : 0,
            'yield_percentage' => $inputKg > 0 ? ($usableKg / $inputKg) * 100 : 0,
            'fresh_yield_percentage' => $inputKg > 0 ? ($freshKg / $inputKg) * 100 : 0,
            'olahan_yield_percentage' => $inputKg > 0 ? ($olahanKg / $inputKg) * 100 : 0,
            'multiplier_factor' => $usableKg > 0 ? $inputKg / $usableKg : 0,
            'production_count' => (int) $totals->production_count,
        ];
    }

    private function configuredPartnerShare(mixed $outletId = null): float
    {
        if (! $outletId) {
            return 15;
        }

        if (is_array($outletId)) {
            return (float) (Outlet::query()->whereKey($outletId)->avg('partner_share_percent') ?? 15);
        }

        return (float) (Outlet::query()->whereKey($outletId)->value('partner_share_percent') ?? 15);
    }

    private function salesByProduct(array $filters, mixed $outletId = null): array
    {
        $records = $this->periodQuery(Sale::query(), $filters, $outletId)
            ->with(['durianVariety:id,name', 'outlet:id,partner_share_percent'])
            ->get([
                'id',
                'outlet_id',
                'durian_variety_id',
                'buah_sold_kg',
                'buah_sold_butir',
                'buah_subtotal',
                'fresh_sold_kg',
                'fresh_sold_pack',
                'fresh_subtotal',
                'frozen_sold_kg',
                'frozen_sold_pack',
                'frozen_subtotal',
                'grand_total_revenue',
                'discount_amount',
                'sales_return_amount',
                'net_sales',
            ]);

        $products = [];

        foreach ($records as $sale) {
            $grossByType = [
                'Buah Utuh' => (float) $sale->buah_subtotal,
                'Kupas Fresh' => (float) $sale->fresh_subtotal,
                'Durpas Frozen' => (float) $sale->frozen_subtotal,
            ];

            $calculatedGross = array_sum($grossByType);
            $rowGross = (float) $sale->grand_total_revenue > 0 ? (float) $sale->grand_total_revenue : $calculatedGross;
            $rowNet = (float) $sale->net_sales > 0
                ? (float) $sale->net_sales
                : max(0, $rowGross - (float) $sale->discount_amount - (float) $sale->sales_return_amount);
            $gunsasRate = (100 - (float) ($sale->outlet?->partner_share_percent ?? 15)) / 100;

            $variety = $sale->durianVariety?->name ?? 'Tanpa Varian';

            $this->addSalesProductRow(
                $products,
                'Buah Utuh',
                $variety,
                (float) $sale->buah_sold_kg,
                (float) $sale->buah_sold_butir,
                'butir',
                $grossByType['Buah Utuh'],
                $rowGross,
                $rowNet,
                $gunsasRate
            );

            $this->addSalesProductRow(
                $products,
                'Kupas Fresh',
                $variety,
                (float) $sale->fresh_sold_kg,
                (float) $sale->fresh_sold_pack,
                'pack',
                $grossByType['Kupas Fresh'],
                $rowGross,
                $rowNet,
                $gunsasRate
            );

            $this->addSalesProductRow(
                $products,
                'Durpas Frozen',
                $variety,
                (float) $sale->frozen_sold_kg,
                (float) $sale->frozen_sold_pack,
                'pack',
                $grossByType['Durpas Frozen'],
                $rowGross,
                $rowNet,
                $gunsasRate
            );
        }

        return collect($products)
            ->map(function (array $product): array {
                $product['avg_price_per_kg'] = $product['kg'] > 0 ? $product['net_sales'] / $product['kg'] : 0;

                return $product;
            })
            ->sortByDesc('net_sales')
            ->values()
            ->all();
    }

    private function profitByOutlet(array $filters, mixed $outletFilter): array
    {
        $outletIds = $this->outletIdsForProfitTable($filters, $outletFilter);
        $rows = [];

        foreach ($outletIds as $outletId) {
            $outlet = Outlet::query()->find($outletId);

            if (! $outlet) {
                continue;
            }

            $salesTotals = $this->salesTotals($filters, $outletId);
            $netSales = $salesTotals['net_sales'] > 0
                ? $salesTotals['net_sales']
                : max(0, $salesTotals['gross_sales'] - $salesTotals['discount_amount'] - $salesTotals['sales_return_amount']);

            $avgModalBuah = $this->weightedAverageModalBuah($filters, $outletId);
            $avgModalFresh = $this->averageModalFresh($filters, $outletId, $avgModalBuah);
            $avgModalFrozen = $this->averageModalFrozen($filters, $outletId, $avgModalFresh);

            $hppSales = ($salesTotals['buah_sold_kg'] * $avgModalBuah)
                + ($salesTotals['fresh_sold_kg'] * $avgModalFresh)
                + ($salesTotals['frozen_sold_kg'] * $avgModalFrozen);

            $expenseBreakdown = $this->expenseAmount($filters, $outletId);
            $expenses = $expenseBreakdown['total'];
            $returnSummary = $this->returnSummary($filters, $outletId, $avgModalBuah);
            $opnameLoss = $this->opnameLoss($filters, $outletId, $avgModalBuah, $avgModalFresh, $avgModalFrozen);
            $inventoryUsage = $this->inventoryUsage($filters, $outletId);
            $grossProfit = $salesTotals['gunsas_revenue'] - $hppSales;
            $netProfit = $grossProfit - $expenses - $inventoryUsage['amount'] - $returnSummary['loss_final'] - $opnameLoss['amount'];
            $margin = $salesTotals['gunsas_revenue'] > 0 ? ($netProfit / $salesTotals['gunsas_revenue']) * 100 : 0;
            $activityTotal = $netSales + $expenses + $hppSales + $returnSummary['asset_submitted'] + $opnameLoss['amount'] + $inventoryUsage['amount'];

            if ($activityTotal <= 0) {
                continue;
            }

            $rows[] = [
                'outlet_id' => $outlet->id,
                'outlet_name' => $outlet->name,
                'group_name' => $outlet->group_name ? (Outlet::GROUPS[$outlet->group_name] ?? $outlet->group_name) : '-',
                'gross_sales' => $salesTotals['gross_sales'],
                'net_sales' => $netSales,
                'sales_return_amount' => $salesTotals['sales_return_amount'],
                'partner_cut' => $salesTotals['partner_cut'],
                'gunsas_revenue' => $salesTotals['gunsas_revenue'],
                'hpp_sales' => $hppSales,
                'gross_profit' => $grossProfit,
                'expenses' => (float) $expenses,
                'direct_expenses' => $expenseBreakdown['direct'],
                'allocated_global_expenses' => $expenseBreakdown['allocated_global'],
                'inventory_usage' => $inventoryUsage['amount'],
                'return_loss' => $returnSummary['loss_final'],
                'return_kg' => $returnSummary['submitted_kg'],
                'opname_loss' => $opnameLoss['amount'],
                'opname_loss_kg' => $opnameLoss['total_kg'],
                'net_profit' => $netProfit,
                'net_margin' => $margin,
            ];
        }

        return collect($rows)
            ->sortByDesc('net_profit')
            ->values()
            ->all();
    }

    private function outletIdsForProfitTable(array $filters, mixed $outletFilter): array
    {
        if (is_array($outletFilter)) {
            $allowedOutletIds = $outletFilter;
        } elseif ($outletFilter) {
            $allowedOutletIds = [(int) $outletFilter];
        } else {
            $allowedOutletIds = null;
        }

        $ids = collect([
            ...$this->outletIdsFromDatedQuery(Sale::query(), $filters, $allowedOutletIds),
            ...$this->outletIdsFromDatedQuery(Expense::query()->whereNotNull('outlet_id'), $filters, $allowedOutletIds),
            ...$this->outletIdsFromDatedQuery(ProductReturn::query(), $filters, $allowedOutletIds),
            ...$this->outletIdsFromDatedQuery(StockOpname::query(), $filters, $allowedOutletIds),
            ...$this->outletIdsFromDatedQuery(Production::query(), $filters, $allowedOutletIds),
            ...$this->outletIdsFromDatedQuery(Shipment::query(), $filters, $allowedOutletIds),
        ])->filter()->unique()->values()->all();

        if ($ids === [] && $allowedOutletIds !== null) {
            return $allowedOutletIds;
        }

        return Outlet::query()
            ->whereKey($ids)
            ->orderBy('group_name')
            ->orderBy('name')
            ->pluck('id')
            ->all();
    }

    private function outletIdsFromDatedQuery(Builder $query, array $filters, ?array $allowedOutletIds = null): array
    {
        return $query
            ->when($filters['date_from'] ?? null, fn (Builder $query, $date) => $query->where('date', '>=', $date))
            ->when($filters['date_until'] ?? null, fn (Builder $query, $date) => $query->where('date', '<=', $date))
            ->when($allowedOutletIds !== null, fn (Builder $query) => $query->whereIn('outlet_id', $allowedOutletIds))
            ->distinct()
            ->pluck('outlet_id')
            ->all();
    }

    private function addSalesProductRow(
        array &$products,
        string $category,
        string $variety,
        float $kg,
        float $secondaryQty,
        string $secondaryUnit,
        float $grossSales,
        float $rowGross,
        float $rowNet,
        float $gunsasRate
    ): void {
        if ($kg <= 0 && $grossSales <= 0 && $secondaryQty <= 0) {
            return;
        }

        $key = $category . '|' . $variety;
        $products[$key] ??= [
            'product' => "{$category} {$variety}",
            'category' => $category,
            'variety' => $variety,
            'kg' => 0,
            'secondary_qty' => 0,
            'secondary_unit' => $secondaryUnit,
            'gross_sales' => 0,
            'net_sales' => 0,
            'gunsas_revenue' => 0,
            'avg_price_per_kg' => 0,
        ];

        $products[$key]['kg'] += $kg;
        $products[$key]['secondary_qty'] += $secondaryQty;
        $products[$key]['gross_sales'] += $grossSales;
        $allocatedNetSales = $rowGross > 0 ? ($grossSales / $rowGross) * $rowNet : $grossSales;

        $products[$key]['net_sales'] += $allocatedNetSales;
        $products[$key]['gunsas_revenue'] += $allocatedNetSales * $gunsasRate;
    }

    private function weightedAverageModalBuah(array $filters, mixed $outletId = null): float
    {
        $totals = $this->periodQuery(Shipment::query(), $filters, $outletId)
            ->where('shipment_direction', 'warehouse_to_outlet')
            ->where(fn (Builder $query) => $query->where('product_type', 'Buah Utuh')->orWhereNull('product_type'))
            ->selectRaw('
                COALESCE(SUM(qty_sent_kg), 0) as total_kg,
                COALESCE(SUM(qty_sent_kg * modal_price), 0) as total_cost
            ')
            ->first();

        $totalKg = (float) $totals->total_kg;

        if ($totalKg <= 0) {
            $fallback = Shipment::query()
                ->when($outletId, fn (Builder $query) => $query->where('outlet_id', $outletId))
                ->where('shipment_direction', 'warehouse_to_outlet')
                ->where(fn (Builder $query) => $query->where('product_type', 'Buah Utuh')->orWhereNull('product_type'))
                ->selectRaw('
                    COALESCE(SUM(qty_sent_kg), 0) as total_kg,
                    COALESCE(SUM(qty_sent_kg * modal_price), 0) as total_cost
                ')
                ->first();

            return $fallback->total_kg > 0 ? (float) $fallback->total_cost / (float) $fallback->total_kg : 66000;
        }

        return (float) $totals->total_cost / $totalKg;
    }

    private function averageModalFresh(array $filters, mixed $outletId, float $avgModalBuah): float
    {
        $totals = $this->periodQuery(Production::query(), $filters, $outletId)
            ->selectRaw('
                COALESCE(SUM(CASE WHEN COALESCE(source_type, "normal") <> "return" THEN qty_buah_kg ELSE 0 END), 0) as normal_buah_kg,
                COALESCE(SUM(qty_kupas_kg), 0) as fresh_kg,
                COALESCE(SUM(total_usable_meat_kg), 0) as usable_kg,
                COALESCE(SUM(qty_kupas_kg + qty_olahan_kg), 0) as calculated_usable_kg
            ')
            ->first();
        $buahKg = (float) $totals->normal_buah_kg;
        $usableKg = (float) $totals->usable_kg;

        if ($usableKg <= 0) {
            $usableKg = (float) $totals->calculated_usable_kg;
        }

        if ($usableKg <= 0) {
            $usableKg = (float) $totals->fresh_kg;
        }

        return $usableKg > 0 ? ($buahKg * $avgModalBuah) / $usableKg : $avgModalBuah * 2.64;
    }

    private function averageModalFrozen(array $filters, mixed $outletId, float $avgModalFresh): float
    {
        $totals = $this->periodQuery(ProductConversion::query(), $filters, $outletId)
            ->where('conversion_type', 'Kupas Fresh ke Kupas Frozen')
            ->selectRaw('
                COALESCE(SUM(from_qty_kg), 0) as from_kg,
                COALESCE(SUM(to_qty_kg), 0) as to_kg
            ')
            ->first();
        $fromKg = (float) $totals->from_kg;
        $toKg = (float) $totals->to_kg;

        return $toKg > 0 ? ($fromKg * $avgModalFresh) / $toKg : $avgModalFresh;
    }

    private function returnSummary(array $filters, mixed $outletId, float $avgModalBuah): array
    {
        $fallbackModal = (float) $avgModalBuah;
        $modalExpression = "COALESCE(shipments.modal_price, {$fallbackModal})";
        $query = ProductReturn::query()
            ->leftJoin('shipments', 'product_returns.shipment_id', '=', 'shipments.id')
            ->when($filters['date_from'] ?? null, fn (Builder $query, $date) => $query->where('product_returns.date', '>=', $date))
            ->when($filters['date_until'] ?? null, fn (Builder $query, $date) => $query->where('product_returns.date', '<=', $date));

        $totals = $this->applyOutletFilter($query, $outletId, 'product_returns.outlet_id')
            ->selectRaw("
                COALESCE(SUM(product_returns.qty_kg * {$modalExpression}), 0) as asset_submitted,
                COALESCE(SUM(product_returns.refund_amount), 0) as refund,
                COALESCE(SUM(CASE WHEN product_returns.status <> 'pending' THEN product_returns.qty_kg * {$modalExpression} ELSE 0 END), 0) as final_asset,
                COALESCE(SUM(CASE WHEN product_returns.status <> 'pending' THEN product_returns.refund_amount ELSE 0 END), 0) as final_refund,
                COALESCE(SUM(CASE WHEN product_returns.status = 'pending' THEN product_returns.qty_kg * {$modalExpression} ELSE 0 END), 0) as pending_asset,
                COALESCE(SUM(product_returns.qty_kg), 0) as submitted_kg,
                COALESCE(SUM(CASE WHEN product_returns.status = 'pending' THEN product_returns.qty_kg ELSE 0 END), 0) as pending_kg,
                COALESCE(SUM(CASE WHEN product_returns.status <> 'pending' THEN product_returns.qty_kg ELSE 0 END), 0) as final_kg,
                COALESCE(SUM(CASE WHEN product_returns.status <> 'pending' THEN product_returns.supplier_accepted_qty_kg ELSE 0 END), 0) as accepted_kg,
                COALESCE(SUM(CASE WHEN product_returns.status = 'pending' THEN 1 ELSE 0 END), 0) as pending_count,
                COALESCE(SUM(CASE WHEN product_returns.status <> 'pending' THEN 1 ELSE 0 END), 0) as final_count
            ")
            ->first();

        $assetSubmitted = (float) $totals->asset_submitted;
        $refund = (float) $totals->refund;
        $finalAsset = (float) $totals->final_asset;
        $finalRefund = (float) $totals->final_refund;
        $pendingAsset = (float) $totals->pending_asset;
        $submittedKg = (float) $totals->submitted_kg;
        $pendingKg = (float) $totals->pending_kg;
        $finalKg = (float) $totals->final_kg;
        $acceptedKg = (float) $totals->accepted_kg;
        $rejectedKg = max(0, $finalKg - $acceptedKg);

        return [
            'asset_submitted' => $assetSubmitted,
            'submitted_kg' => $submittedKg,
            'refund_received' => $refund,
            'loss_final' => max(0, $finalAsset - $finalRefund),
            'final_kg' => $finalKg,
            'accepted_kg' => $acceptedKg,
            'rejected_kg' => $rejectedKg,
            'pending_asset' => $pendingAsset,
            'pending_kg' => $pendingKg,
            'pending_count' => (int) $totals->pending_count,
            'final_count' => (int) $totals->final_count,
        ];
    }

    private function opnameLoss(array $filters, mixed $outletId, float $avgModalBuah, float $avgModalFresh, float $avgModalFrozen): array
    {
        $losses = $this->periodQuery(StockOpname::query(), $filters, $outletId)
            ->whereIn('product_type', ['Buah Utuh', 'Daging Fresh', 'Daging Frozen'])
            ->where('difference_qty_kg', '<', 0)
            ->selectRaw('product_type, ABS(COALESCE(SUM(difference_qty_kg), 0)) as loss_kg')
            ->groupBy('product_type')
            ->pluck('loss_kg', 'product_type');

        $buahLoss = (float) ($losses['Buah Utuh'] ?? 0);
        $freshLoss = (float) ($losses['Daging Fresh'] ?? 0);
        $frozenLoss = (float) ($losses['Daging Frozen'] ?? 0);

        return [
            'buah_kg' => $buahLoss,
            'fresh_kg' => $freshLoss,
            'frozen_kg' => $frozenLoss,
            'total_kg' => $buahLoss + $freshLoss + $frozenLoss,
            'amount' => ($buahLoss * $avgModalBuah) + ($freshLoss * $avgModalFresh) + ($frozenLoss * $avgModalFrozen),
        ];
    }

    private function inventoryUsage(array $filters, mixed $outletId): array
    {
        $records = $this->periodQuery(StockOpname::query(), $filters, $outletId)
            ->whereNotNull('inventory_item_id')
            ->with('inventoryItem:id,name,unit')
            ->get();

        return [
            'amount' => (float) $records->sum('generic_consumed_amount'),
            'items' => $records
                ->groupBy('inventory_item_id')
                ->map(fn ($rows) => [
                    'name' => $rows->first()->inventoryItem?->name ?? 'Produk Inventory',
                    'qty' => (float) $rows->sum('generic_consumed_qty'),
                    'unit' => $rows->first()->generic_unit ?: ($rows->first()->inventoryItem?->unit ?? 'pcs'),
                    'amount' => (float) $rows->sum('generic_consumed_amount'),
                ])
                ->sortByDesc('amount')
                ->values()
                ->all(),
        ];
    }

    private function expenseAmount(array $filters, mixed $outletFilter): array
    {
        $direct = (float) $this->periodQuery(
            Expense::query()->whereNotNull('outlet_id'),
            $filters,
            $outletFilter
        )->sum('amount');

        $allocatedGlobal = $this->allocatedGlobalExpenseAmount($filters, $outletFilter);

        return [
            'direct' => $direct,
            'allocated_global' => $allocatedGlobal,
            'total' => $direct + $allocatedGlobal,
        ];
    }

    private function allocatedGlobalExpenseAmount(array $filters, mixed $outletFilter): float
    {
        $globalTotal = (float) $this->periodQuery(Expense::query()->whereNull('outlet_id'), $filters)
            ->whereIn('category', self::GLOBAL_EXPENSE_ALLOCATION_CATEGORIES)
            ->sum('amount');

        if ($globalTotal <= 0) {
            return 0;
        }

        return $globalTotal * $this->globalExpenseAllocationRatio($filters, $outletFilter);
    }

    private function globalExpenseAllocationRatio(array $filters, mixed $outletFilter): float
    {
        if (! $outletFilter) {
            return 1;
        }

        $allActiveOutletIds = $this->activeOutletIdsForExpenseAllocation($filters);
        $allActiveCount = count($allActiveOutletIds);

        if ($allActiveCount === 0) {
            return 0;
        }

        $targetOutletIds = is_array($outletFilter) ? $outletFilter : [(int) $outletFilter];
        $targetActiveCount = collect($allActiveOutletIds)
            ->intersect($targetOutletIds)
            ->count();

        if ($targetActiveCount === 0 && ! is_array($outletFilter)) {
            $targetActiveCount = 1;
        }

        return $targetActiveCount / $allActiveCount;
    }

    private function activeOutletIdsForExpenseAllocation(array $filters): array
    {
        return collect([
            ...$this->outletIdsFromDatedQuery(Sale::query(), $filters),
            ...$this->outletIdsFromDatedQuery(Expense::query()->whereNotNull('outlet_id'), $filters),
            ...$this->outletIdsFromDatedQuery(ProductReturn::query(), $filters),
            ...$this->outletIdsFromDatedQuery(StockOpname::query(), $filters),
            ...$this->outletIdsFromDatedQuery(Production::query(), $filters),
            ...$this->outletIdsFromDatedQuery(Shipment::query(), $filters),
        ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function topOutlets(array $filters, mixed $outletId): array
    {
        return $this->periodQuery(Sale::query(), $filters, $outletId)
            ->selectRaw('outlet_id, SUM(grand_total_revenue) as revenue')
            ->with('outlet:id,name')
            ->groupBy('outlet_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn (Sale $sale) => [
                'name' => $sale->outlet?->name ?? 'Tanpa Outlet',
                'revenue' => (float) $sale->revenue,
            ])
            ->all();
    }

    private function inventoryValuation(array $filters, mixed $outletId, float $avgModalBuah, float $avgModalFresh, float $avgModalFrozen): array
    {
        $buahKg = $this->latestPhysicalStockKg('Buah Utuh', $filters, $outletId);
        $freshKg = $this->latestPhysicalStockKg('Daging Fresh', $filters, $outletId);
        $frozenKg = $this->latestPhysicalStockKg('Daging Frozen', $filters, $outletId);

        return [
            'buah_kg' => $buahKg,
            'fresh_kg' => $freshKg,
            'frozen_kg' => $frozenKg,
            'total_kg' => $buahKg + $freshKg + $frozenKg,
            'amount' => ($buahKg * $avgModalBuah) + ($freshKg * $avgModalFresh) + ($frozenKg * $avgModalFrozen),
        ];
    }

    private function latestPhysicalStockKg(string $productType, array $filters, mixed $outletId = null): float
    {
        $query = StockOpname::query()
            ->where('product_type', $productType)
            ->when($filters['date_until'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '<=', $date));

        $records = $this->applyOutletFilter($query, $outletId)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get(['id', 'outlet_id', 'durian_variety_id', 'product_type', 'physical_qty_kg'])
            ->unique(fn (StockOpname $record) => $record->outlet_id . ':' . $record->durian_variety_id . ':' . $record->product_type);

        return $records->sum('physical_qty_kg');
    }

    private function expenseCategories(array $filters, mixed $outletId): array
    {
        $categories = $this->periodQuery(Expense::query()->whereNotNull('outlet_id'), $filters, $outletId)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get()
            ->mapWithKeys(fn (Expense $expense) => [
                ($expense->category ?? 'Tanpa Kategori') => (float) $expense->total,
            ]);

        $globalAllocationRatio = $this->globalExpenseAllocationRatio($filters, $outletId);

        if ($globalAllocationRatio > 0) {
            $this->periodQuery(Expense::query()->whereNull('outlet_id'), $filters)
                ->whereIn('category', self::GLOBAL_EXPENSE_ALLOCATION_CATEGORIES)
                ->selectRaw('category, SUM(amount) as total')
                ->groupBy('category')
                ->get()
                ->each(function (Expense $expense) use ($categories, $globalAllocationRatio): void {
                    $category = $expense->category ?? 'Tanpa Kategori';

                    $categories[$category] = (float) ($categories[$category] ?? 0)
                        + ((float) $expense->total * $globalAllocationRatio);
                });
        }

        return $categories
            ->map(fn (float $total, string $category) => [
                'category' => $category,
                'total' => $total,
            ])
            ->sortByDesc('total')
            ->take(5)
            ->values()
            ->all();
    }
}
