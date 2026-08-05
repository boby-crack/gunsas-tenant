<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\DurianVariety;
use App\Models\InventoryItem;
use App\Models\Outlet;
use App\Models\ProductConversion;
use App\Models\ProductReturn;
use App\Models\Production;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shipment;
use App\Models\StockOpname;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class BusinessInsightsCalculator
{
    public function expenseBreakdown(array $filters): array
    {
        return $this->expenseAmount($filters, $this->outletFilter($filters));
    }

    public function calculate(array $filters, bool $includeOperationalReports = false): array
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
        $avgModalFreshBreakdown = $this->averageModalFreshBreakdown($filters, $outletFilter, $avgModalBuah);
        $avgModalFresh = $avgModalFreshBreakdown['amount'];
        $avgModalFrozen = $this->averageModalFrozen($filters, $outletFilter, $avgModalFresh);

        $buahSoldKg = $salesTotals['buah_sold_kg'];
        $freshSoldKg = $salesTotals['fresh_sold_kg'];
        $frozenSoldKg = $salesTotals['frozen_sold_kg'];

        $hppBreakdown = $this->hppSalesBySaleDate($filters, $outletFilter);
        $hppSales = $hppBreakdown['total'];

        $grossProfit = $gunsasRevenue - $hppSales;
        $expenseBreakdown = $this->expenseAmount($filters, $outletFilter);
        $expenses = $expenseBreakdown['total'];
        $returnSummary = $this->returnSummary($filters, $outletFilter, $avgModalBuah);
        $opnameLoss = $this->opnameLoss($filters, $outletFilter, $avgModalBuah, $avgModalFresh, $avgModalFrozen);
        $inventoryUsage = $this->inventoryUsage($filters, $outletFilter);
        $returnRecovery = $this->returnRecovery($filters, $outletFilter, $avgModalFresh, $hppBreakdown);
        $returnSummary['recovery'] = $returnRecovery;
        $lossBreakdown = $this->lossBreakdown($filters, $outletFilter, $returnSummary, $opnameLoss, $avgModalBuah, $avgModalFresh);
        $netProfit = $grossProfit - $expenses - $inventoryUsage['amount'] - $returnSummary['loss_final'] - $opnameLoss['amount'];
        $netMargin = $gunsasRevenue > 0 ? ($netProfit / $gunsasRevenue) * 100 : 0;
        $inventory = $this->inventoryValuation($filters, $outletFilter, $avgModalBuah, $avgModalFresh, $avgModalFrozen);
        $productionEfficiency = $this->productionEfficiency($filters, $outletFilter);

        $insights = [
            'filters' => [
                'date_from' => $filters['date_from'] ?? now()->startOfMonth()->toDateString(),
                'date_until' => $filters['date_until'] ?? now()->toDateString(),
                'outlet_name' => $this->outletFilterLabel($filters),
                'durian_variety_name' => $this->durianVarietyFilterLabel($filters),
                'product_type_label' => $this->productTypeLabel($this->selectedProductType($filters)),
                'inventory_item_name' => $this->inventoryItemFilterLabel($filters),
                'product_label' => $this->productFilterLabel($filters),
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
                'avg_modal_fresh_breakdown' => $avgModalFreshBreakdown,
                'hpp_sales' => $hppSales,
                'hpp_breakdown' => $hppBreakdown,
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
                'return_recovery_hpp_saved' => $returnRecovery['hpp_saved_amount'] ?? 0.0,
            ],
            'inventory' => $inventory,
            'purchases' => $this->purchaseSummary($filters),
            'shipments' => $this->shipmentSummary($filters, $outletFilter),
            'production_efficiency' => $productionEfficiency,
            'sales_by_product' => $this->salesByProduct($filters, $outletFilter),
            'profit_by_outlet' => $this->profitByOutlet($filters, $outletFilter),
            'top_outlets' => $this->topOutlets($filters, $outletFilter),
            'expense_categories' => $this->expenseCategories($filters, $outletFilter),
        ];

        if ($includeOperationalReports) {
            $insights['stock_movement'] = $this->stockMovement($filters, $outletFilter);
        }

        return $insights;
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

    private function selectedVarietyId(array $filters): ?int
    {
        return ! empty($filters['durian_variety_id']) ? (int) $filters['durian_variety_id'] : null;
    }

    private function selectedProductType(array $filters): ?string
    {
        if (($filters['product_category'] ?? null) === 'non_durian') {
            return null;
        }

        $productType = $filters['product_type'] ?? null;

        return in_array($productType, ['Buah Utuh', 'Daging Fresh', 'Daging Frozen'], true)
            ? $productType
            : null;
    }

    private function selectedProductCategory(array $filters): ?string
    {
        $category = $filters['product_category'] ?? null;

        return in_array($category, ['durian', 'non_durian'], true) ? $category : null;
    }

    private function selectedInventoryItemId(array $filters): ?int
    {
        if (($filters['product_category'] ?? null) !== 'non_durian') {
            return null;
        }

        return ! empty($filters['inventory_item_id']) ? (int) $filters['inventory_item_id'] : null;
    }

    private function applyVarietyFilter(Builder $query, array $filters, string $column = 'durian_variety_id'): Builder
    {
        $varietyId = $this->selectedVarietyId($filters);

        return $varietyId ? $query->where($column, $varietyId) : $query;
    }

    private function productTypeLabel(?string $productType): string
    {
        return match ($productType) {
            'Buah Utuh' => 'Buah Utuh',
            'Daging Fresh' => 'Kupas Fresh',
            'Daging Frozen' => 'Durpas Frozen',
            default => 'Semua Produk',
        };
    }

    private function productFilterLabel(array $filters): string
    {
        $category = $this->selectedProductCategory($filters);

        if ($category === 'non_durian') {
            if ($inventoryItemId = $this->selectedInventoryItemId($filters)) {
                return 'Non-durian / ' . (InventoryItem::query()->whereKey($inventoryItemId)->value('name') ?? 'Produk tidak ditemukan');
            }

            return 'Non-durian / Semua Produk Jualan';
        }

        if ($category === 'durian') {
            return 'Durian / ' . $this->productTypeLabel($this->selectedProductType($filters)) . ' / ' . $this->durianVarietyFilterLabel($filters);
        }

        return 'Semua Kategori / Semua Produk';
    }

    private function inventoryItemFilterLabel(array $filters): string
    {
        $inventoryItemId = $this->selectedInventoryItemId($filters);

        return $inventoryItemId
            ? (InventoryItem::query()->whereKey($inventoryItemId)->value('name') ?? 'Produk tidak ditemukan')
            : 'Semua Produk Jualan';
    }

    private function durianVarietyFilterLabel(array $filters): string
    {
        $varietyId = $this->selectedVarietyId($filters);

        return $varietyId
            ? (DurianVariety::query()->whereKey($varietyId)->value('name') ?? 'Varian tidak ditemukan')
            : 'Semua Varian';
    }

    private function selectedProductTypes(array $filters): array
    {
        $productType = $this->selectedProductType($filters);

        if ($productType) {
            return [$productType => $this->productTypeLabel($productType)];
        }

        return [
            'Buah Utuh' => 'Buah Utuh',
            'Daging Fresh' => 'Kupas Fresh',
            'Daging Frozen' => 'Durpas Frozen',
        ];
    }

    private function zeroReturnSummary(): array
    {
        return [
            'asset_submitted' => 0.0,
            'submitted_kg' => 0.0,
            'refund_received' => 0.0,
            'loss_final' => 0.0,
            'final_kg' => 0.0,
            'accepted_kg' => 0.0,
            'rejected_kg' => 0.0,
            'pending_asset' => 0.0,
            'pending_kg' => 0.0,
            'pending_count' => 0,
            'final_count' => 0,
        ];
    }

    private function filteredSalesRatio(array $filters, mixed $outletFilter): float
    {
        if (! $this->selectedProductType($filters) && ! $this->selectedVarietyId($filters)) {
            return 1.0;
        }

        $allFilters = $filters;
        unset($allFilters['product_type'], $allFilters['durian_variety_id']);

        $filteredNetSales = $this->salesTotals($filters, $outletFilter)['net_sales'];
        $allNetSales = $this->salesTotals($allFilters, $outletFilter)['net_sales'];

        if ($allNetSales <= 0) {
            return $filteredNetSales > 0 ? 1.0 : 0.0;
        }

        return max(0.0, min(1.0, $filteredNetSales / $allNetSales));
    }

    private function lossBreakdown(
        array $filters,
        mixed $outletId,
        array $returnSummary,
        array $opnameLoss,
        float $avgModalBuah,
        float $avgModalFresh
    ): array {
        $productionQuery = $this->periodQuery(Production::query(), $filters, $outletId);
        $this->applyVarietyFilter($productionQuery, $filters);

        $productionTotals = $productionQuery
            ->selectRaw('
                COALESCE(SUM(qty_buah_kg), 0) as input_kg,
                COALESCE(SUM(total_usable_meat_kg), 0) as usable_kg,
                COALESCE(SUM(CASE WHEN COALESCE(source_type, "normal") <> "return" THEN qty_buah_kg ELSE 0 END), 0) as normal_input_kg,
                COALESCE(SUM(CASE WHEN COALESCE(source_type, "normal") <> "return" THEN total_usable_meat_kg ELSE 0 END), 0) as normal_usable_kg,
                COALESCE(SUM(CASE WHEN COALESCE(source_type, "normal") <> "return" THEN qty_kupas_kg + qty_olahan_kg ELSE 0 END), 0) as normal_calculated_usable_kg
            ')
            ->first();
        $productionInputKg = (float) $productionTotals->input_kg;
        $productionUsableKg = (float) $productionTotals->usable_kg;
        $productionShrinkKg = max(0, $productionInputKg - $productionUsableKg);
        $normalUsableKg = (float) $productionTotals->normal_usable_kg;

        if ($normalUsableKg <= 0) {
            $normalUsableKg = (float) $productionTotals->normal_calculated_usable_kg;
        }

        $normalProductionShrinkKg = max(0, (float) $productionTotals->normal_input_kg - $normalUsableKg);

        $conversionQuery = $this->periodQuery(ProductConversion::query(), $filters, $outletId)
            ->where('conversion_type', ProductConversion::TYPE_FRESH_TO_FROZEN);
        $this->applyVarietyFilter($conversionQuery, $filters);

        $conversionTotals = $conversionQuery
            ->selectRaw('
                COALESCE(SUM(from_qty_kg), 0) as input_kg,
                COALESCE(SUM(to_qty_kg), 0) as output_kg
            ')
            ->first();
        $conversionInputKg = (float) $conversionTotals->input_kg;
        $conversionOutputKg = (float) $conversionTotals->output_kg;
        $conversionShrinkKg = max(0, $conversionInputKg - $conversionOutputKg);

        $freshLossQuery = $this->periodQuery(ProductConversion::query(), $filters, $outletId)
            ->where('conversion_type', ProductConversion::TYPE_FRESH_LOSS);
        $this->applyVarietyFilter($freshLossQuery, $filters);
        $freshLossKg = (float) $freshLossQuery->sum('from_qty_kg');
        $freshLossAmount = $freshLossKg * $avgModalFresh;

        $directLossKg = $returnSummary['rejected_kg'] + $opnameLoss['total_kg'] + $freshLossKg;
        $processShrinkKg = $productionShrinkKg;
        $productionShrinkAmount = $normalProductionShrinkKg * $avgModalBuah;

        return [
            'direct_loss_kg' => $directLossKg,
            'direct_loss_amount' => $returnSummary['loss_final'] + $opnameLoss['amount'] + $freshLossAmount,
            'process_shrink_kg' => $processShrinkKg,
            'process_shrink_amount' => $productionShrinkAmount,
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
                    'label' => 'Fresh busuk / olahan',
                    'kg' => $freshLossKg,
                    'amount' => $freshLossAmount,
                    'impact' => 'Mengurangi profit',
                ],
                [
                    'label' => 'Susut kupas buah ke fresh',
                    'kg' => $productionShrinkKg,
                    'amount' => $productionShrinkAmount,
                    'impact' => 'Normal masuk modal fresh; return tidak tambah HPP',
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
        if ($this->selectedProductCategory($filters) === 'non_durian') {
            $inventoryItemId = $this->selectedInventoryItemId($filters);

            return $this->salesTotalsForInventoryItem($filters, $outletId, $inventoryItemId);
        }

        $netSalesExpression = 'CASE WHEN sales.net_sales > 0 THEN sales.net_sales ELSE GREATEST(sales.grand_total_revenue - sales.discount_amount - COALESCE(sales.sales_return_amount, 0), 0) END';
        $partnerShareExpression = 'COALESCE(outlets.partner_share_percent, 15)';
        $fallbackPartnerShare = $this->configuredPartnerShare($outletId);
        $productType = $this->selectedProductType($filters);
        $productCategory = $this->selectedProductCategory($filters);

        $query = $this->periodQuery(Sale::query(), $filters, $outletId)
            ->leftJoin('outlets', 'sales.outlet_id', '=', 'outlets.id');

        $this->applyVarietyFilter($query, $filters, 'sales.durian_variety_id');

        if ($productType || $productCategory === 'durian') {
            $grossColumn = match ($productType) {
                'Daging Fresh' => 'sales.fresh_subtotal',
                'Daging Frozen' => 'sales.frozen_subtotal',
                default => 'sales.buah_subtotal',
            };
            $durianGrossExpression = '(COALESCE(sales.buah_subtotal, 0) + COALESCE(sales.fresh_subtotal, 0) + COALESCE(sales.frozen_subtotal, 0))';
            $rowGrossExpression = "(CASE WHEN COALESCE(sales.grand_total_revenue, 0) > {$durianGrossExpression} THEN sales.grand_total_revenue ELSE {$durianGrossExpression} END)";
            $grossExpression = $productType ? "COALESCE({$grossColumn}, 0)" : $durianGrossExpression;
            $ratioExpression = "(CASE WHEN {$rowGrossExpression} > 0 THEN {$grossExpression} / {$rowGrossExpression} ELSE 0 END)";
            $categoryNetExpression = "({$netSalesExpression}) * {$ratioExpression}";
            $buahKgExpression = $productType === 'Buah Utuh' ? 'COALESCE(sales.buah_sold_kg, 0)' : '0';
            $freshKgExpression = $productType === 'Daging Fresh' ? 'COALESCE(sales.fresh_sold_kg, 0)' : '0';
            $frozenKgExpression = $productType === 'Daging Frozen' ? 'COALESCE(sales.frozen_sold_kg, 0)' : '0';

            if (! $productType) {
                $buahKgExpression = 'COALESCE(sales.buah_sold_kg, 0)';
                $freshKgExpression = 'COALESCE(sales.fresh_sold_kg, 0)';
                $frozenKgExpression = 'COALESCE(sales.frozen_sold_kg, 0)';
            }

            $totals = $query
                ->selectRaw("
                    COALESCE(SUM({$grossExpression}), 0) as gross_sales,
                    COALESCE(SUM(COALESCE(sales.discount_amount, 0) * {$ratioExpression}), 0) as discount_amount,
                    COALESCE(SUM(COALESCE(sales.sales_return_amount, 0) * {$ratioExpression}), 0) as sales_return_amount,
                    COALESCE(SUM({$categoryNetExpression}), 0) as net_sales,
                    COALESCE(SUM({$buahKgExpression}), 0) as buah_sold_kg,
                    COALESCE(SUM({$freshKgExpression}), 0) as fresh_sold_kg,
                    COALESCE(SUM({$frozenKgExpression}), 0) as frozen_sold_kg,
                    COALESCE(SUM(({$categoryNetExpression}) * ({$partnerShareExpression}) / 100), 0) as partner_cut,
                    COALESCE(SUM(({$categoryNetExpression}) * (100 - ({$partnerShareExpression})) / 100), 0) as gunsas_revenue
                ")
                ->first();
        } else {
            $totals = $query
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
        }

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

    private function salesTotalsForInventoryItem(array $filters, mixed $outletId, ?int $inventoryItemId = null): array
    {
        $fallbackPartnerShare = $this->configuredPartnerShare($outletId);
        $partnerShareExpression = 'COALESCE(outlets.partner_share_percent, 15)';
        $itemNetExpression = 'CASE WHEN sale_items.net_sales > 0 THEN sale_items.net_sales ELSE GREATEST(sale_items.gross_sales - COALESCE(sale_items.discount_amount, 0) - COALESCE(sale_items.sales_return_amount, 0), 0) END';

        $query = SaleItem::query()
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->leftJoin('outlets', 'sales.outlet_id', '=', 'outlets.id')
            ->when($inventoryItemId, fn (Builder $query) => $query->where('sale_items.inventory_item_id', $inventoryItemId))
            ->when($filters['date_from'] ?? null, fn (Builder $query, $date) => $query->where('sales.date', '>=', $date))
            ->when($filters['date_until'] ?? null, fn (Builder $query, $date) => $query->where('sales.date', '<=', $date));

        $this->applyOutletFilter($query, $outletId, 'sales.outlet_id');

        $totals = $query
            ->selectRaw("
                COALESCE(SUM(sale_items.gross_sales), 0) as gross_sales,
                COALESCE(SUM(COALESCE(sale_items.discount_amount, 0)), 0) as discount_amount,
                COALESCE(SUM(COALESCE(sale_items.sales_return_amount, 0)), 0) as sales_return_amount,
                COALESCE(SUM({$itemNetExpression}), 0) as net_sales,
                COALESCE(SUM(({$itemNetExpression}) * ({$partnerShareExpression}) / 100), 0) as partner_cut,
                COALESCE(SUM(({$itemNetExpression}) * (100 - ({$partnerShareExpression})) / 100), 0) as gunsas_revenue
            ")
            ->first();

        $netSales = (float) $totals->net_sales;
        $partnerCut = (float) $totals->partner_cut;

        return [
            'gross_sales' => (float) $totals->gross_sales,
            'discount_amount' => (float) $totals->discount_amount,
            'sales_return_amount' => (float) $totals->sales_return_amount,
            'net_sales' => $netSales,
            'buah_sold_kg' => 0.0,
            'fresh_sold_kg' => 0.0,
            'frozen_sold_kg' => 0.0,
            'partner_cut' => $partnerCut,
            'gunsas_revenue' => (float) $totals->gunsas_revenue,
            'partner_share_percent' => $netSales > 0 ? ($partnerCut / $netSales) * 100 : $fallbackPartnerShare,
        ];
    }

    private function hppSalesBySaleDate(array $filters, mixed $outletId = null): array
    {
        if ($this->selectedProductCategory($filters) === 'non_durian') {
            $inventoryItemId = $this->selectedInventoryItemId($filters);
            $query = SaleItem::query()
                ->when($inventoryItemId, fn (Builder $query) => $query->where('inventory_item_id', $inventoryItemId))
                ->whereHas('sale', function (Builder $query) use ($filters, $outletId): void {
                    $this->periodQuery($query, $filters, $outletId);
                });

            $items = (float) $query->sum('total_cost');

            return [
                'buah' => 0.0,
                'fresh' => 0.0,
                'frozen' => 0.0,
                'items' => $items,
                'total' => $items,
                'normal_fresh_sold_kg' => 0.0,
                'return_recovery_sold_kg' => 0.0,
                'return_recovery_hpp_saved' => 0.0,
            ];
        }

        $productType = $this->selectedProductType($filters);
        $productCategory = $this->selectedProductCategory($filters);
        $query = $this->periodQuery(Sale::query(), $filters, $outletId);
        $this->applyVarietyFilter($query, $filters, 'sales.durian_variety_id');

        $totals = [
            'buah' => 0.0,
            'fresh' => 0.0,
            'frozen' => 0.0,
            'items' => 0.0,
            'total' => 0.0,
            'normal_fresh_sold_kg' => 0.0,
            'return_recovery_sold_kg' => 0.0,
            'return_recovery_hpp_saved' => 0.0,
        ];

        $modalCache = [];
        $freshUsesRecovery = ! $productType || $productType === 'Daging Fresh';
        $recoveryBySaleId = $freshUsesRecovery
            ? $this->freshRecoveryFlow($filters, $outletId)['sales_allocations']
            : [];

        $query
            ->select([
                'id',
                'outlet_id',
                'durian_variety_id',
                'date',
                'buah_sold_kg',
                'fresh_sold_kg',
                'frozen_sold_kg',
            ])
            ->orderBy('date')
            ->orderBy('id')
            ->chunk(200, function ($sales) use (&$totals, &$modalCache, $filters, $productType, $recoveryBySaleId): void {
                foreach ($sales as $sale) {
                    $saleDate = Carbon::parse($sale->date)->toDateString();
                    $saleOutletId = (int) $sale->outlet_id;
                    $saleVarietyId = $sale->durian_variety_id ? (int) $sale->durian_variety_id : null;
                    $cacheKey = $saleOutletId . '|' . ($saleVarietyId ?: 'all') . '|' . $saleDate;

                    if (! isset($modalCache[$cacheKey])) {
                        $costFilters = $filters;
                        unset($costFilters['date_from']);
                        $costFilters['date_until'] = $saleDate;

                        if ($saleVarietyId) {
                            $costFilters['durian_variety_id'] = $saleVarietyId;
                        }

                        $avgModalBuah = $this->weightedAverageModalBuah($costFilters, $saleOutletId);
                        $avgModalFresh = $this->averageModalFresh($costFilters, $saleOutletId, $avgModalBuah);

                        $modalCache[$cacheKey] = [
                            'buah' => $avgModalBuah,
                            'fresh' => $avgModalFresh,
                            'frozen' => $this->averageModalFrozen($costFilters, $saleOutletId, $avgModalFresh),
                        ];
                    }

                    $modal = $modalCache[$cacheKey];

                    if (! $productType || $productType === 'Buah Utuh') {
                        $totals['buah'] += (float) $sale->buah_sold_kg * $modal['buah'];
                    }

                    if (! $productType || $productType === 'Daging Fresh') {
                        $freshSoldKg = (float) $sale->fresh_sold_kg;
                        $recoverySoldKg = min($freshSoldKg, (float) ($recoveryBySaleId[(int) $sale->id] ?? 0));
                        $normalFreshKg = max(0, $freshSoldKg - $recoverySoldKg);

                        $totals['fresh'] += $normalFreshKg * $modal['fresh'];
                        $totals['normal_fresh_sold_kg'] += $normalFreshKg;
                        $totals['return_recovery_sold_kg'] += $recoverySoldKg;
                        $totals['return_recovery_hpp_saved'] += $recoverySoldKg * $modal['fresh'];
                    }

                    if (! $productType || $productType === 'Daging Frozen') {
                        $totals['frozen'] += (float) $sale->frozen_sold_kg * $modal['frozen'];
                    }
                }
            });

        if (! $productType && $productCategory !== 'durian') {
            $itemQuery = SaleItem::query()
                ->whereHas('sale', function (Builder $query) use ($filters, $outletId): void {
                    $this->periodQuery($query, $filters, $outletId);
                    $this->applyVarietyFilter($query, $filters, 'sales.durian_variety_id');
                });

            $totals['items'] = (float) $itemQuery->sum('total_cost');
        }

        $totals['total'] = $totals['buah'] + $totals['fresh'] + $totals['frozen'] + $totals['items'];

        return $totals;
    }

    private function purchaseSummary(array $filters): array
    {
        $amountExpression = 'CASE WHEN purchase_mode = "inventory" THEN COALESCE(generic_total_amount, 0) ELSE CASE WHEN COALESCE(total_amount, 0) > 0 THEN total_amount ELSE COALESCE(qty_kg, 0) * COALESCE(price_per_kg, 0) END END';
        $durianAmountExpression = 'CASE WHEN COALESCE(purchase_mode, "durian") <> "inventory" THEN CASE WHEN COALESCE(total_amount, 0) > 0 THEN total_amount ELSE COALESCE(qty_kg, 0) * COALESCE(price_per_kg, 0) END ELSE 0 END';

        $base = $this->periodQuery(Purchase::query(), $filters);
        $this->applyVarietyFilter($base, $filters, 'durian_variety_id');

        $summary = (clone $base)
            ->selectRaw("
                COUNT(*) as records,
                COALESCE(SUM(CASE WHEN COALESCE(purchase_mode, 'durian') <> 'inventory' THEN 1 ELSE 0 END), 0) as durian_records,
                COALESCE(SUM(CASE WHEN purchase_mode = 'inventory' THEN 1 ELSE 0 END), 0) as inventory_records,
                COALESCE(SUM(qty_butir), 0) as durian_butir,
                COALESCE(SUM(qty_kg), 0) as durian_kg,
                COALESCE(SUM({$durianAmountExpression}), 0) as durian_amount,
                COALESCE(SUM(generic_total_amount), 0) as inventory_amount,
                COALESCE(SUM({$amountExpression}), 0) as total_amount
            ")
            ->first();

        $durianKg = (float) ($summary->durian_kg ?? 0);
        $durianAmount = (float) ($summary->durian_amount ?? 0);

        return [
            'records' => (int) ($summary->records ?? 0),
            'durian_records' => (int) ($summary->durian_records ?? 0),
            'inventory_records' => (int) ($summary->inventory_records ?? 0),
            'durian_butir' => (float) ($summary->durian_butir ?? 0),
            'durian_kg' => $durianKg,
            'durian_amount' => $durianAmount,
            'inventory_amount' => (float) ($summary->inventory_amount ?? 0),
            'total_amount' => (float) ($summary->total_amount ?? 0),
            'avg_price_per_kg' => $durianKg > 0 ? $durianAmount / $durianKg : 0,
            'by_supplier' => $this->purchaseBySupplier($filters, $amountExpression),
            'by_variety' => $this->purchaseByVariety($filters, $durianAmountExpression),
        ];
    }

    private function shipmentSummary(array $filters, mixed $outletId): array
    {
        $amountExpression = '
            CASE
                WHEN shipment_mode = "inventory" THEN COALESCE(generic_total_amount, COALESCE(generic_qty_sent, 0) * COALESCE(generic_unit_cost, 0))
                ELSE CASE
                    WHEN COALESCE(value_purchase, 0) > 0 THEN value_purchase
                    ELSE COALESCE(qty_sent_kg, 0) * COALESCE(modal_price, 0)
                END
            END
        ';

        $query = $this->periodQuery(Shipment::query(), $filters, $outletId)
            ->where('shipment_direction', 'warehouse_to_outlet');

        $this->applyVarietyFilter($query, $filters);

        if ($productType = $this->selectedProductType($filters)) {
            $query->where('product_type', $productType);
        }

        $summary = $query
            ->selectRaw("
                COUNT(*) as records,
                COALESCE(SUM({$amountExpression}), 0) as total_modal,
                COALESCE(SUM(qty_sent_kg), 0) as durian_kg,
                COALESCE(SUM(qty_sent_butir), 0) as durian_butir,
                COALESCE(SUM(CASE WHEN shipment_mode = 'inventory' THEN generic_qty_sent ELSE 0 END), 0) as inventory_qty
            ")
            ->first();

        return [
            'records' => (int) ($summary->records ?? 0),
            'total_modal' => (float) ($summary->total_modal ?? 0),
            'durian_kg' => (float) ($summary->durian_kg ?? 0),
            'durian_butir' => (float) ($summary->durian_butir ?? 0),
            'inventory_qty' => (float) ($summary->inventory_qty ?? 0),
        ];
    }

    private function purchaseBySupplier(array $filters, string $amountExpression): array
    {
        $query = $this->periodQuery(Purchase::query(), $filters);
        $this->applyVarietyFilter($query, $filters, 'durian_variety_id');

        return $query
            ->selectRaw("
                COALESCE(NULLIF(supplier_code, ''), NULLIF(supplier_name, ''), 'Tanpa Supplier') as supplier,
                COALESCE(MAX(NULLIF(supplier_name, '')), '') as supplier_name,
                COUNT(*) as records,
                COALESCE(SUM(qty_butir), 0) as butir,
                COALESCE(SUM(qty_kg), 0) as kg,
                COALESCE(SUM({$amountExpression}), 0) as amount
            ")
            ->groupBy('supplier')
            ->orderByDesc('amount')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'supplier' => $row->supplier,
                'supplier_name' => $row->supplier_name,
                'records' => (int) $row->records,
                'butir' => (float) $row->butir,
                'kg' => (float) $row->kg,
                'amount' => (float) $row->amount,
                'avg_price_per_kg' => (float) $row->kg > 0 ? (float) $row->amount / (float) $row->kg : 0,
            ])
            ->all();
    }

    private function purchaseByVariety(array $filters, string $durianAmountExpression): array
    {
        $query = $this->periodQuery(Purchase::query(), $filters);
        $this->applyVarietyFilter($query, $filters, 'purchases.durian_variety_id');

        return $query
            ->where(fn (Builder $query) => $query->whereNull('purchase_mode')->orWhere('purchase_mode', 'durian'))
            ->leftJoin('durian_varieties', 'purchases.durian_variety_id', '=', 'durian_varieties.id')
            ->selectRaw("
                COALESCE(durian_varieties.name, 'Tanpa Varian') as variety,
                COALESCE(SUM(purchases.qty_butir), 0) as butir,
                COALESCE(SUM(purchases.qty_kg), 0) as kg,
                COALESCE(SUM({$durianAmountExpression}), 0) as amount
            ")
            ->groupBy('variety')
            ->orderByDesc('amount')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'variety' => $row->variety,
                'butir' => (float) $row->butir,
                'kg' => (float) $row->kg,
                'amount' => (float) $row->amount,
                'avg_price_per_kg' => (float) $row->kg > 0 ? (float) $row->amount / (float) $row->kg : 0,
            ])
            ->all();
    }

    private function productionEfficiency(array $filters, mixed $outletId = null): array
    {
        $query = $this->periodQuery(Production::query(), $filters, $outletId);
        $this->applyVarietyFilter($query, $filters);

        $totals = $query
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

    private function stockMovement(array $filters, mixed $outletFilter): array
    {
        $productTypes = $this->selectedProductTypes($filters);
        $outletIds = $this->stockMovementOutletIds($filters, $outletFilter);
        $outlets = Outlet::query()
            ->whereKey($outletIds)
            ->orderBy('group_name')
            ->orderBy('name')
            ->get(['id', 'name', 'group_name'])
            ->keyBy('id');

        $rows = [];
        $summary = [
            'start_kg' => 0.0,
            'received_kg' => 0.0,
            'sold_kg' => 0.0,
            'estimated_stock_kg' => 0.0,
            'physical_stock_kg' => 0.0,
            'variance_kg' => 0.0,
        ];

        $varieties = DurianVariety::query()->orderBy('name')->get(['id', 'name'])->keyBy('id');

        foreach ($outlets as $outlet) {
            foreach ($this->stockMovementVarietyIds($filters, (int) $outlet->id) as $varietyId) {
                foreach ($productTypes as $productType => $label) {
                    $movement = $this->stockMovementForOutletProduct($filters, (int) $outlet->id, (int) $varietyId, $productType);

                    if (! $this->hasStockMovement($movement)) {
                        continue;
                    }

                    $varietyName = $varieties[$varietyId]->name ?? '-';
                    $row = [
                        'outlet_id' => (int) $outlet->id,
                        'outlet_name' => $outlet->name,
                        'group_name' => $outlet->group_name ? (Outlet::GROUPS[$outlet->group_name] ?? $outlet->group_name) : '-',
                        'durian_variety_id' => (int) $varietyId,
                        'variety_name' => $varietyName,
                        'product_type' => $productType,
                        'product_label' => $label . ' ' . $varietyName,
                        ...$movement,
                    ];

                    $rows[] = $row;

                    $summary['start_kg'] += $row['start_kg'];
                    $summary['received_kg'] += $row['received_kg'];
                    $summary['sold_kg'] += $row['sold_kg'];
                    $summary['estimated_stock_kg'] += $row['estimated_stock_kg'];
                    $summary['physical_stock_kg'] += $row['physical_stock_kg'];
                    $summary['variance_kg'] += $row['variance_kg'];
                }
            }
        }

        return [
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    private function stockMovementOutletIds(array $filters, mixed $outletFilter): array
    {
        if (is_array($outletFilter)) {
            return collect($outletFilter)->map(fn ($id) => (int) $id)->values()->all();
        }

        if ($outletFilter) {
            return [(int) $outletFilter];
        }

        return collect([
            ...$this->outletIdsFromDatedQuery(Shipment::query(), $filters),
            ...$this->outletIdsFromDatedQuery(Sale::query(), $filters),
            ...$this->outletIdsFromDatedQuery(Production::query(), $filters),
            ...$this->outletIdsFromDatedQuery(ProductConversion::query(), $filters),
            ...$this->outletIdsFromDatedQuery(ProductReturn::query(), $filters),
            ...$this->outletIdsFromDatedQuery(StockOpname::query(), $filters),
        ])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function stockMovementVarietyIds(array $filters, int $outletId): array
    {
        if ($varietyId = $this->selectedVarietyId($filters)) {
            return [$varietyId];
        }

        return collect([
            ...$this->varietyIdsFromDatedQuery(Shipment::query(), $filters, $outletId),
            ...$this->varietyIdsFromDatedQuery(Sale::query(), $filters, $outletId),
            ...$this->varietyIdsFromDatedQuery(Production::query(), $filters, $outletId),
            ...$this->varietyIdsFromDatedQuery(ProductConversion::query(), $filters, $outletId),
            ...$this->varietyIdsFromDatedQuery(ProductReturn::query(), $filters, $outletId),
            ...$this->varietyIdsFromDatedQuery(StockOpname::query(), $filters, $outletId),
        ])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function varietyIdsFromDatedQuery(Builder $query, array $filters, int $outletId): array
    {
        return $query
            ->where('outlet_id', $outletId)
            ->when($filters['date_until'] ?? null, fn (Builder $query, $date) => $query->where('date', '<=', $date))
            ->distinct()
            ->pluck('durian_variety_id')
            ->all();
    }

    private function stockMovementForOutletProduct(array $filters, int $outletId, int $varietyId, string $productType): array
    {
        $dateFrom = $filters['date_from'] ?? $filters['date_until'] ?? now()->toDateString();
        $startDate = Carbon::parse($dateFrom)->subDay()->toDateString();
        $startKg = app(StockSnapshotCalculator::class)->durianStockForDate($startDate, $outletId, $varietyId, $productType);
        $shipmentInKg = $this->shipmentKg($filters, $outletId, $varietyId, $productType, 'warehouse_to_outlet');
        $shipmentOutKg = $this->shipmentKg($filters, $outletId, $varietyId, $productType, 'outlet_to_warehouse');
        $soldKg = $this->soldKg($filters, $outletId, $varietyId, $productType);
        $returnKg = $productType === 'Buah Utuh'
            ? (float) $this->periodQuery(ProductReturn::query(), $filters, $outletId)->where('durian_variety_id', $varietyId)->sum('qty_kg')
            : 0.0;
        $productionInKg = 0.0;
        $productionOutKg = 0.0;
        $conversionInKg = 0.0;
        $conversionOutKg = 0.0;

        if ($productType === 'Buah Utuh') {
            $productionOutKg = (float) $this->periodQuery(Production::query(), $filters, $outletId)
                ->where('durian_variety_id', $varietyId)
                ->where(fn (Builder $query) => $query->whereNull('source_type')->orWhere('source_type', Production::SOURCE_NORMAL))
                ->sum('qty_buah_kg');
        }

        if ($productType === 'Daging Fresh') {
            $productionInKg = (float) $this->periodQuery(Production::query(), $filters, $outletId)
                ->where('durian_variety_id', $varietyId)
                ->sum('qty_kupas_kg');
            $conversionOutKg = (float) $this->periodQuery(ProductConversion::query(), $filters, $outletId)
                ->where('durian_variety_id', $varietyId)
                ->sum('from_qty_kg');
        }

        if ($productType === 'Daging Frozen') {
            $conversionInKg = (float) $this->periodQuery(ProductConversion::query(), $filters, $outletId)
                ->where('durian_variety_id', $varietyId)
                ->where('conversion_type', ProductConversion::TYPE_FRESH_TO_FROZEN)
                ->sum('to_qty_kg');
        }

        $receivedKg = $shipmentInKg + $productionInKg + $conversionInKg;
        $outKg = $soldKg + $shipmentOutKg + $returnKg + $productionOutKg + $conversionOutKg;
        $estimatedStockKg = $startKg + $receivedKg - $outKg;
        $physicalStockKg = $this->latestPhysicalStockKgForOutletProduct($filters, $outletId, $varietyId, $productType);

        return [
            'start_kg' => $startKg,
            'shipment_in_kg' => $shipmentInKg,
            'shipment_out_kg' => $shipmentOutKg,
            'production_in_kg' => $productionInKg,
            'production_out_kg' => $productionOutKg,
            'conversion_in_kg' => $conversionInKg,
            'conversion_out_kg' => $conversionOutKg,
            'received_kg' => $receivedKg,
            'sold_kg' => $soldKg,
            'return_kg' => $returnKg,
            'out_kg' => $outKg,
            'estimated_stock_kg' => $estimatedStockKg,
            'physical_stock_kg' => $physicalStockKg,
            'variance_kg' => $physicalStockKg - $estimatedStockKg,
        ];
    }

    private function shipmentKg(array $filters, int $outletId, int $varietyId, string $productType, string $direction): float
    {
        return (float) $this->periodQuery(Shipment::query(), $filters, $outletId)
            ->where('durian_variety_id', $varietyId)
            ->where('shipment_direction', $direction)
            ->when(
                $productType === 'Buah Utuh',
                fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('product_type', 'Buah Utuh')->orWhereNull('product_type')),
                fn (Builder $query) => $query->where('product_type', $productType)
            )
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(qty_received_kg, 0) > 0 THEN qty_received_kg ELSE qty_sent_kg END), 0) as total_kg')
            ->value('total_kg');
    }

    private function soldKg(array $filters, int $outletId, int $varietyId, string $productType): float
    {
        $column = match ($productType) {
            'Daging Fresh' => 'fresh_sold_kg',
            'Daging Frozen' => 'frozen_sold_kg',
            default => 'buah_sold_kg',
        };

        return (float) $this->periodQuery(Sale::query(), $filters, $outletId)
            ->where('durian_variety_id', $varietyId)
            ->sum($column);
    }

    private function latestPhysicalStockKgForOutletProduct(array $filters, int $outletId, int $varietyId, string $productType): float
    {
        return (float) StockOpname::query()
            ->where('outlet_id', $outletId)
            ->where('durian_variety_id', $varietyId)
            ->where('product_type', $productType)
            ->when($filters['date_until'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '<=', $date))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->value('physical_qty_kg');
    }

    private function hasStockMovement(array $movement): bool
    {
        foreach (['start_kg', 'received_kg', 'sold_kg', 'out_kg', 'estimated_stock_kg', 'physical_stock_kg', 'variance_kg'] as $key) {
            if (abs((float) ($movement[$key] ?? 0)) > 0.0001) {
                return true;
            }
        }

        return false;
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
        $selectedProductCategory = $this->selectedProductCategory($filters);
        $query = $this->periodQuery(Sale::query(), $filters, $outletId);
        if ($selectedProductCategory !== 'non_durian') {
            $this->applyVarietyFilter($query, $filters);
        }

        $records = $query
            ->with(['durianVariety:id,name', 'outlet:id,partner_share_percent', 'items.inventoryItem:id,name,category,unit'])
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
        $selectedProductType = $this->selectedProductType($filters);
        $selectedInventoryItemId = $this->selectedInventoryItemId($filters);
        $allowedCategory = match ($selectedProductType) {
            'Buah Utuh' => 'Buah Utuh',
            'Daging Fresh' => 'Kupas Fresh',
            'Daging Frozen' => 'Durpas Frozen',
            default => null,
        };
        $allowedCategory = $selectedInventoryItemId ? null : $allowedCategory;

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

            if ($selectedProductCategory !== 'non_durian' && (! $allowedCategory || $allowedCategory === 'Buah Utuh')) {
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
            }

            if ($selectedProductCategory !== 'non_durian' && (! $allowedCategory || $allowedCategory === 'Kupas Fresh')) {
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
            }

            if ($selectedProductCategory !== 'non_durian' && (! $allowedCategory || $allowedCategory === 'Durpas Frozen')) {
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

            if (! $allowedCategory && $selectedProductCategory !== 'durian') {
                foreach ($sale->items as $item) {
                    if ($selectedInventoryItemId && (int) $item->inventory_item_id !== $selectedInventoryItemId) {
                        continue;
                    }

                    $this->addGenericSalesProductRow(
                        $products,
                        (string) ($item->item_name ?: $item->inventoryItem?->name ?: 'Produk Lain'),
                        (string) ($item->category ?: $item->inventoryItem?->category ?: 'produk_jualan'),
                        (string) ($item->unit ?: $item->inventoryItem?->unit ?: 'pcs'),
                        (float) $item->quantity,
                        (float) $item->gross_sales,
                        (float) ($item->net_sales > 0 ? $item->net_sales : max(0, $item->gross_sales - $item->discount_amount - $item->sales_return_amount)),
                        $gunsasRate
                    );
                }
            }
        }

        return collect($products)
            ->map(function (array $product): array {
                $product['avg_price_per_kg'] = $product['kg'] > 0 ? $product['net_sales'] / $product['kg'] : 0;
                $product['avg_price_per_unit'] = $product['quantity'] > 0 ? $product['net_sales'] / $product['quantity'] : 0;

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

            $hppBreakdown = $this->hppSalesBySaleDate($filters, $outletId);
            $hppSales = $hppBreakdown['total'];

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
            'quantity' => 0,
            'unit' => 'kg',
            'gross_sales' => 0,
            'net_sales' => 0,
            'gunsas_revenue' => 0,
            'avg_price_per_kg' => 0,
        ];

        $products[$key]['kg'] += $kg;
        $products[$key]['quantity'] += $kg;
        $products[$key]['secondary_qty'] += $secondaryQty;
        $products[$key]['gross_sales'] += $grossSales;
        $allocatedNetSales = $rowGross > 0 ? ($grossSales / $rowGross) * $rowNet : $grossSales;

        $products[$key]['net_sales'] += $allocatedNetSales;
        $products[$key]['gunsas_revenue'] += $allocatedNetSales * $gunsasRate;
    }

    private function addGenericSalesProductRow(
        array &$products,
        string $name,
        string $category,
        string $unit,
        float $quantity,
        float $grossSales,
        float $netSales,
        float $gunsasRate
    ): void {
        if ($quantity <= 0 && $grossSales <= 0) {
            return;
        }

        $categoryLabel = str($category)->replace('_', ' ')->title()->toString();
        $key = 'item|' . $name . '|' . $unit;

        $products[$key] ??= [
            'product' => $name,
            'category' => $categoryLabel,
            'variety' => '-',
            'kg' => $unit === 'kg' ? 0 : 0,
            'secondary_qty' => 0,
            'secondary_unit' => $unit,
            'quantity' => 0,
            'unit' => $unit,
            'gross_sales' => 0,
            'net_sales' => 0,
            'gunsas_revenue' => 0,
            'avg_price_per_kg' => 0,
            'avg_price_per_unit' => 0,
        ];

        if ($unit === 'kg') {
            $products[$key]['kg'] += $quantity;
        }

        $products[$key]['quantity'] += $quantity;
        $products[$key]['secondary_qty'] += $quantity;
        $products[$key]['gross_sales'] += $grossSales;
        $products[$key]['net_sales'] += $netSales;
        $products[$key]['gunsas_revenue'] += $netSales * $gunsasRate;
    }

    private function weightedAverageModalBuah(array $filters, mixed $outletId = null): float
    {
        $query = $this->periodQuery(Shipment::query(), $filters, $outletId)
            ->where('shipment_direction', 'warehouse_to_outlet')
            ->where(fn (Builder $query) => $query->where('product_type', 'Buah Utuh')->orWhereNull('product_type'));
        $this->applyVarietyFilter($query, $filters);

        $totals = $query
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
                ->where(fn (Builder $query) => $query->where('product_type', 'Buah Utuh')->orWhereNull('product_type'));
            $this->applyVarietyFilter($fallback, $filters);

            $fallback = $fallback
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
        return $this->averageModalFreshBreakdown($filters, $outletId, $avgModalBuah)['amount'];
    }

    private function averageModalFreshBreakdown(array $filters, mixed $outletId, float $avgModalBuah): array
    {
        $query = $this->periodQuery(Production::query(), $filters, $outletId);
        $this->applyVarietyFilter($query, $filters);

        $totals = $query
            ->selectRaw('
                COALESCE(SUM(CASE WHEN COALESCE(source_type, "normal") <> "return" THEN qty_buah_kg ELSE 0 END), 0) as normal_buah_kg,
                COALESCE(SUM(CASE WHEN COALESCE(source_type, "normal") <> "return" THEN qty_kupas_kg ELSE 0 END), 0) as normal_fresh_kg,
                COALESCE(SUM(CASE WHEN COALESCE(source_type, "normal") <> "return" THEN qty_olahan_kg ELSE 0 END), 0) as normal_olahan_kg,
                COALESCE(SUM(CASE WHEN COALESCE(source_type, "normal") <> "return" THEN total_usable_meat_kg ELSE 0 END), 0) as normal_usable_kg,
                COALESCE(SUM(CASE WHEN COALESCE(source_type, "normal") <> "return" THEN qty_kupas_kg + qty_olahan_kg ELSE 0 END), 0) as normal_calculated_usable_kg,
                COALESCE(SUM(CASE WHEN COALESCE(source_type, "normal") = "return" THEN qty_buah_kg ELSE 0 END), 0) as return_buah_kg,
                COALESCE(SUM(CASE WHEN COALESCE(source_type, "normal") = "return" THEN qty_kupas_kg + qty_olahan_kg ELSE 0 END), 0) as return_output_kg
            ')
            ->first();
        $buahKg = (float) $totals->normal_buah_kg;
        $usableKg = (float) $totals->normal_usable_kg;

        if ($usableKg <= 0) {
            $usableKg = (float) $totals->normal_calculated_usable_kg;
        }

        if ($usableKg <= 0) {
            $usableKg = (float) $totals->normal_fresh_kg;
        }

        $effectiveMultiplier = $usableKg > 0 ? $buahKg / $usableKg : 2.64;

        return [
            'amount' => $usableKg > 0 ? ($buahKg * $avgModalBuah) / $usableKg : $avgModalBuah * 2.64,
            'effective_multiplier' => $effectiveMultiplier,
            'normal_input_kg' => $buahKg,
            'normal_output_kg' => $usableKg,
            'normal_fresh_kg' => (float) $totals->normal_fresh_kg,
            'normal_olahan_kg' => (float) $totals->normal_olahan_kg,
            'return_input_kg_excluded' => (float) $totals->return_buah_kg,
            'return_output_kg_excluded' => (float) $totals->return_output_kg,
        ];
    }

    private function averageModalFrozen(array $filters, mixed $outletId, float $avgModalFresh): float
    {
        $query = $this->periodQuery(ProductConversion::query(), $filters, $outletId)
            ->where('conversion_type', ProductConversion::TYPE_FRESH_TO_FROZEN);
        $this->applyVarietyFilter($query, $filters);

        $totals = $query
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
        if ($this->selectedProductType($filters) && $this->selectedProductType($filters) !== 'Buah Utuh') {
            return $this->zeroReturnSummary();
        }

        $fallbackModal = (float) $avgModalBuah;
        $modalExpression = "COALESCE(shipments.modal_price, {$fallbackModal})";
        $query = ProductReturn::query()
            ->leftJoin('shipments', 'product_returns.shipment_id', '=', 'shipments.id')
            ->when($filters['date_from'] ?? null, fn (Builder $query, $date) => $query->where('product_returns.date', '>=', $date))
            ->when($filters['date_until'] ?? null, fn (Builder $query, $date) => $query->where('product_returns.date', '<=', $date));
        $this->applyVarietyFilter($query, $filters, 'product_returns.durian_variety_id');

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

    private function freshRecoveryFlow(array $filters, mixed $outletId): array
    {
        if ($this->selectedProductCategory($filters) === 'non_durian') {
            return $this->zeroFreshRecoveryFlow();
        }

        $selectedProductType = $this->selectedProductType($filters);

        if (in_array($selectedProductType, ['Buah Utuh', 'Daging Frozen'], true)) {
            return $this->zeroFreshRecoveryFlow();
        }

        $dateFrom = $filters['date_from'] ?? null;
        $dateUntil = $filters['date_until'] ?? now()->toDateString();

        $productionQuery = Production::query()
            ->where('source_type', Production::SOURCE_RETURN)
            ->where('date', '<=', $dateUntil);
        $this->applyOutletFilter($productionQuery, $outletId);
        $this->applyVarietyFilter($productionQuery, $filters);

        $saleQuery = Sale::query()
            ->where('date', '<=', $dateUntil)
            ->where('fresh_sold_kg', '>', 0);
        $this->applyOutletFilter($saleQuery, $outletId);
        $this->applyVarietyFilter($saleQuery, $filters);

        $events = [];

        foreach ($productionQuery->get(['id', 'outlet_id', 'durian_variety_id', 'date', 'qty_buah_kg', 'qty_kupas_kg', 'qty_olahan_kg']) as $production) {
            $date = Carbon::parse($production->date)->toDateString();
            $events[] = [
                'kind' => 'production',
                'date' => $date,
                'order' => 0,
                'id' => (int) $production->id,
                'key' => $this->freshRecoveryPoolKey((int) $production->outlet_id, $production->durian_variety_id ? (int) $production->durian_variety_id : null),
                'input_kg' => (float) $production->qty_buah_kg,
                'fresh_kg' => (float) $production->qty_kupas_kg,
                'olahan_kg' => (float) $production->qty_olahan_kg,
                'in_period' => $this->dateWithinFilter($date, $dateFrom, $dateUntil),
            ];
        }

        foreach ($saleQuery->get(['id', 'outlet_id', 'durian_variety_id', 'date', 'fresh_sold_kg']) as $sale) {
            $date = Carbon::parse($sale->date)->toDateString();
            $events[] = [
                'kind' => 'sale',
                'date' => $date,
                'order' => 1,
                'id' => (int) $sale->id,
                'key' => $this->freshRecoveryPoolKey((int) $sale->outlet_id, $sale->durian_variety_id ? (int) $sale->durian_variety_id : null),
                'fresh_sold_kg' => (float) $sale->fresh_sold_kg,
                'in_period' => $this->dateWithinFilter($date, $dateFrom, $dateUntil),
            ];
        }

        usort($events, fn (array $left, array $right): int => [$left['date'], $left['order'], $left['id']] <=> [$right['date'], $right['order'], $right['id']]);

        $pool = [];
        $salesAllocations = [];
        $inputKg = 0.0;
        $freshKg = 0.0;
        $olahanKg = 0.0;
        $records = 0;
        $soldKg = 0.0;

        foreach ($events as $event) {
            $key = $event['key'];
            $pool[$key] ??= 0.0;

            if ($event['kind'] === 'production') {
                $pool[$key] += $event['fresh_kg'];

                if ($event['in_period']) {
                    $inputKg += $event['input_kg'];
                    $freshKg += $event['fresh_kg'];
                    $olahanKg += $event['olahan_kg'];
                    $records++;
                }

                continue;
            }

            $recoverySoldKg = min($pool[$key], $event['fresh_sold_kg']);
            $pool[$key] -= $recoverySoldKg;

            if ($event['in_period'] && $recoverySoldKg > 0) {
                $salesAllocations[$event['id']] = $recoverySoldKg;
                $soldKg += $recoverySoldKg;
            }
        }

        return [
            'input_kg' => $inputKg,
            'fresh_kg' => $freshKg,
            'olahan_kg' => $olahanKg,
            'records' => $records,
            'sold_kg' => $soldKg,
            'remaining_kg' => array_sum($pool),
            'sales_allocations' => $salesAllocations,
        ];
    }

    private function freshRecoveryPoolKey(int $outletId, ?int $varietyId): string
    {
        return $outletId . '|' . ($varietyId ?: 'all');
    }

    private function dateWithinFilter(string $date, ?string $dateFrom, ?string $dateUntil): bool
    {
        return (! $dateFrom || $date >= $dateFrom) && (! $dateUntil || $date <= $dateUntil);
    }

    private function zeroFreshRecoveryFlow(): array
    {
        return [
            'input_kg' => 0.0,
            'fresh_kg' => 0.0,
            'olahan_kg' => 0.0,
            'records' => 0,
            'sold_kg' => 0.0,
            'remaining_kg' => 0.0,
            'sales_allocations' => [],
        ];
    }

    private function returnRecovery(array $filters, mixed $outletId, float $avgModalFresh, array $hppBreakdown = []): array
    {
        if ($this->selectedProductCategory($filters) === 'non_durian') {
            return $this->zeroReturnRecovery($avgModalFresh);
        }

        $selectedProductType = $this->selectedProductType($filters);

        if ($selectedProductType === 'Daging Frozen') {
            return $this->zeroReturnRecovery($avgModalFresh);
        }

        $flow = $this->freshRecoveryFlow($filters, $outletId);

        $freshKg = $selectedProductType === 'Buah Utuh' ? 0.0 : (float) $flow['fresh_kg'];
        $soldKg = $selectedProductType === 'Buah Utuh' ? 0.0 : (float) ($hppBreakdown['return_recovery_sold_kg'] ?? $flow['sold_kg']);
        $remainingKg = $selectedProductType === 'Buah Utuh' ? 0.0 : (float) $flow['remaining_kg'];
        $hppSavedAmount = (float) ($hppBreakdown['return_recovery_hpp_saved'] ?? ($soldKg * $avgModalFresh));

        return [
            'input_kg' => (float) $flow['input_kg'],
            'fresh_kg' => $freshKg,
            'olahan_kg' => (float) $flow['olahan_kg'],
            'records' => (int) $flow['records'],
            'sold_kg' => $soldKg,
            'remaining_kg' => $remainingKg,
            'modal_fresh' => $avgModalFresh,
            'estimated_amount' => $hppSavedAmount,
            'hpp_saved_amount' => $hppSavedAmount,
            'remaining_modal_excluded' => $remainingKg * $avgModalFresh,
        ];
    }

    private function zeroReturnRecovery(float $avgModalFresh = 0): array
    {
        return [
            'input_kg' => 0.0,
            'fresh_kg' => 0.0,
            'olahan_kg' => 0.0,
            'records' => 0,
            'sold_kg' => 0.0,
            'remaining_kg' => 0.0,
            'modal_fresh' => $avgModalFresh,
            'estimated_amount' => 0.0,
            'hpp_saved_amount' => 0.0,
            'remaining_modal_excluded' => 0.0,
        ];
    }

    private function opnameLoss(array $filters, mixed $outletId, float $avgModalBuah, float $avgModalFresh, float $avgModalFrozen): array
    {
        $productTypes = array_keys($this->selectedProductTypes($filters));
        $productCategory = $this->selectedProductCategory($filters);
        $includeDurianLoss = $productCategory !== 'non_durian';
        $includeSellableInventoryLoss = $productCategory !== 'durian';

        $losses = collect();

        if ($includeDurianLoss) {
            $lossQuery = $this->periodQuery(StockOpname::query(), $filters, $outletId)
                ->whereIn('product_type', $productTypes);
            $this->applyVarietyFilter($lossQuery, $filters);

            $losses = $lossQuery
                ->where('difference_qty_kg', '<', 0)
                ->selectRaw('product_type, ABS(COALESCE(SUM(difference_qty_kg), 0)) as loss_kg')
                ->groupBy('product_type')
                ->pluck('loss_kg', 'product_type');
        }

        $buahLoss = (float) ($losses['Buah Utuh'] ?? 0);
        $freshLoss = (float) ($losses['Daging Fresh'] ?? 0);
        $frozenLoss = (float) ($losses['Daging Frozen'] ?? 0);
        $freshRecoveryLossExemptKg = $includeDurianLoss
            ? $this->freshRecoveryOpnameLossExemptKg($filters, $outletId)
            : 0.0;
        $valuedFreshLoss = max(0, $freshLoss - $freshRecoveryLossExemptKg);
        $sellableInventoryLoss = $includeSellableInventoryLoss
            ? $this->sellableInventoryOpnameLoss($filters, $outletId)
            : ['qty' => 0.0, 'amount' => 0.0, 'items' => []];
        $grossAmount = ($buahLoss * $avgModalBuah)
            + ($valuedFreshLoss * $avgModalFresh)
            + ($frozenLoss * $avgModalFrozen)
            + $sellableInventoryLoss['amount'];

        $corrections = collect();

        if ($includeDurianLoss) {
            $correctionQuery = $this->periodQuery(StockOpname::query(), $filters, $outletId)
                ->whereIn('product_type', $productTypes);
            $this->applyVarietyFilter($correctionQuery, $filters);

            $corrections = $correctionQuery
                ->where('system_qty_kg', '<', 0)
                ->where('difference_qty_kg', '>', 0)
                ->selectRaw('product_type, COALESCE(SUM(LEAST(ABS(system_qty_kg), difference_qty_kg)), 0) as correction_kg')
                ->groupBy('product_type')
                ->pluck('correction_kg', 'product_type');
        }

        $buahCorrection = (float) ($corrections['Buah Utuh'] ?? 0);
        $freshCorrection = (float) ($corrections['Daging Fresh'] ?? 0);
        $frozenCorrection = (float) ($corrections['Daging Frozen'] ?? 0);
        $correctionAmount = ($buahCorrection * $avgModalBuah)
            + ($freshCorrection * $avgModalFresh)
            + ($frozenCorrection * $avgModalFrozen);

        return [
            'buah_kg' => $buahLoss,
            'fresh_kg' => $freshLoss,
            'fresh_recovery_loss_exempt_kg' => $freshRecoveryLossExemptKg,
            'fresh_valued_loss_kg' => $valuedFreshLoss,
            'frozen_kg' => $frozenLoss,
            'total_kg' => $buahLoss + $freshLoss + $frozenLoss,
            'gross_amount' => $grossAmount,
            'correction_buah_kg' => $buahCorrection,
            'correction_fresh_kg' => $freshCorrection,
            'correction_frozen_kg' => $frozenCorrection,
            'correction_total_kg' => $buahCorrection + $freshCorrection + $frozenCorrection,
            'correction_amount' => $correctionAmount,
            'inventory_item_qty' => $sellableInventoryLoss['qty'],
            'inventory_item_amount' => $sellableInventoryLoss['amount'],
            'inventory_item_items' => $sellableInventoryLoss['items'],
            'amount' => max(0, $grossAmount - $correctionAmount),
        ];
    }

    private function freshRecoveryOpnameLossExemptKg(array $filters, mixed $outletId): float
    {
        if ($this->selectedProductCategory($filters) === 'non_durian') {
            return 0.0;
        }

        $selectedProductType = $this->selectedProductType($filters);

        if ($selectedProductType && $selectedProductType !== 'Daging Fresh') {
            return 0.0;
        }

        $query = $this->periodQuery(StockOpname::query(), $filters, $outletId)
            ->where('product_type', 'Daging Fresh')
            ->where('difference_qty_kg', '<', 0);
        $this->applyVarietyFilter($query, $filters);

        return (float) $query
            ->get(['id', 'outlet_id', 'durian_variety_id', 'date', 'system_qty_kg', 'difference_qty_kg'])
            ->sum(function (StockOpname $opname) use ($filters): float {
                $recordFilters = $filters;
                $recordFilters['date_until'] = Carbon::parse($opname->date)->toDateString();
                unset($recordFilters['date_from']);

                if ($opname->durian_variety_id) {
                    $recordFilters['durian_variety_id'] = (int) $opname->durian_variety_id;
                }

                $recoveryRemainingKg = (float) $this->freshRecoveryFlow($recordFilters, (int) $opname->outlet_id)['remaining_kg'];
                $systemKg = max(0, (float) $opname->system_qty_kg);
                $missingKg = abs((float) $opname->difference_qty_kg);
                $normalAvailableKg = max(0, $systemKg - $recoveryRemainingKg);
                $valuedMissingKg = min($missingKg, $normalAvailableKg);

                return max(0, $missingKg - $valuedMissingKg);
            });
    }

    private function inventoryUsage(array $filters, mixed $outletId): array
    {
        $records = $this->periodQuery(StockOpname::query(), $filters, $outletId)
            ->whereNotNull('inventory_item_id')
            ->whereHas('inventoryItem', fn (Builder $query) => $query->whereNotIn('category', InventoryItem::SELLABLE_CATEGORIES))
            ->with('inventoryItem:id,name,unit')
            ->get();
        $ratio = $this->filteredSalesRatio($filters, $outletId);

        return [
            'amount' => (float) $records->sum('generic_consumed_amount') * $ratio,
            'items' => $records
                ->groupBy('inventory_item_id')
                ->map(fn ($rows) => [
                    'name' => $rows->first()->inventoryItem?->name ?? 'Produk Inventory',
                    'qty' => (float) $rows->sum('generic_consumed_qty'),
                    'unit' => $rows->first()->generic_unit ?: ($rows->first()->inventoryItem?->unit ?? 'pcs'),
                    'amount' => (float) $rows->sum('generic_consumed_amount') * $ratio,
                ])
                ->sortByDesc('amount')
                ->values()
                ->all(),
        ];
    }

    private function sellableInventoryOpnameLoss(array $filters, mixed $outletId): array
    {
        $inventoryItemId = $this->selectedInventoryItemId($filters);
        $varietyId = $this->selectedVarietyId($filters);

        $records = $this->periodQuery(StockOpname::query(), $filters, $outletId)
            ->whereNotNull('inventory_item_id')
            ->where('difference_qty_kg', '<', 0)
            ->when($inventoryItemId, fn (Builder $query) => $query->where('inventory_item_id', $inventoryItemId))
            ->whereHas('inventoryItem', function (Builder $query) use ($varietyId): void {
                $query->whereIn('category', InventoryItem::SELLABLE_CATEGORIES)
                    ->when($varietyId, fn (Builder $query, int $id) => $query->where('durian_variety_id', $id));
            })
            ->with('inventoryItem:id,name,unit')
            ->get();

        return [
            'qty' => (float) $records->sum('generic_consumed_qty'),
            'amount' => (float) $records->sum('generic_consumed_amount'),
            'items' => $records
                ->groupBy('inventory_item_id')
                ->map(fn ($rows) => [
                    'name' => $rows->first()->inventoryItem?->name ?? 'Produk Jualan',
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
        $ratio = $this->filteredSalesRatio($filters, $outletFilter);
        $direct *= $ratio;
        $allocatedGlobal *= $ratio;

        return [
            'direct' => $direct,
            'allocated_global' => $allocatedGlobal,
            'total' => $direct + $allocatedGlobal,
        ];
    }

    private function allocatedGlobalExpenseAmount(array $filters, mixed $outletFilter): float
    {
        return $this->periodQuery(Expense::query()->whereNull('outlet_id'), $filters)
            ->get(['amount', 'allocation_scope', 'allocation_group'])
            ->sum(fn (Expense $expense) => (float) $expense->amount * $this->expenseAllocationRatio($filters, $outletFilter, $expense));
    }

    private function expenseAllocationRatio(array $filters, mixed $outletFilter, Expense $expense): float
    {
        $scope = $expense->allocation_scope ?: 'all';

        if ($scope === 'group' && blank($expense->allocation_group)) {
            return 0;
        }

        if (! $outletFilter) {
            return 1;
        }

        if ($scope === 'none') {
            return 0;
        }

        $poolOutletIds = $scope === 'group'
            ? $this->activeOutletIdsForExpenseAllocation($filters, $expense->allocation_group)
            : $this->activeOutletIdsForExpenseAllocation($filters);

        $poolCount = count($poolOutletIds);

        if ($poolCount === 0) {
            return 0;
        }

        $targetOutletIds = collect(is_array($outletFilter) ? $outletFilter : [(int) $outletFilter])
            ->map(fn ($id) => (int) $id)
            ->all();

        $targetActiveCount = collect($poolOutletIds)
            ->intersect($targetOutletIds)
            ->count();

        if ($targetActiveCount === 0 && ! is_array($outletFilter) && in_array((int) $outletFilter, $poolOutletIds, true)) {
            $targetActiveCount = 1;
        }

        return $targetActiveCount / $poolCount;
    }

    private function activeOutletIdsForExpenseAllocation(array $filters, ?string $groupName = null): array
    {
        $allowedOutletIds = $groupName
            ? Outlet::query()
                ->where('group_name', Outlet::normalizeGroupName($groupName))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : null;

        $activeOutletIds = collect([
            ...$this->outletIdsFromDatedQuery(Sale::query(), $filters),
            ...$this->outletIdsFromDatedQuery(Expense::query()->whereNotNull('outlet_id'), $filters),
            ...$this->outletIdsFromDatedQuery(ProductReturn::query(), $filters),
            ...$this->outletIdsFromDatedQuery(StockOpname::query(), $filters),
            ...$this->outletIdsFromDatedQuery(Production::query(), $filters),
            ...$this->outletIdsFromDatedQuery(Shipment::query(), $filters),
        ])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->when($allowedOutletIds !== null, fn ($ids) => $ids->intersect($allowedOutletIds))
            ->unique()
            ->values()
            ->all();

        if ($activeOutletIds !== []) {
            return $activeOutletIds;
        }

        return Outlet::query()
            ->when($allowedOutletIds !== null, fn (Builder $query) => $query->whereKey($allowedOutletIds))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function topOutlets(array $filters, mixed $outletId): array
    {
        $productType = $this->selectedProductType($filters);
        $revenueExpression = match ($productType) {
            'Buah Utuh' => 'COALESCE(buah_subtotal, 0)',
            'Daging Fresh' => 'COALESCE(fresh_subtotal, 0)',
            'Daging Frozen' => 'COALESCE(frozen_subtotal, 0)',
            default => 'COALESCE(grand_total_revenue, 0)',
        };
        $query = $this->periodQuery(Sale::query(), $filters, $outletId);
        $this->applyVarietyFilter($query, $filters);

        return $query
            ->selectRaw("outlet_id, SUM({$revenueExpression}) as revenue")
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
        $productCategory = $this->selectedProductCategory($filters);
        $productType = $this->selectedProductType($filters);
        $snapshotRows = app(StockSnapshotCalculator::class)->calculate($this->stockSnapshotFilters($filters, $outletId))['rows'] ?? [];
        $varietyId = $this->selectedVarietyId($filters);

        $includeDurian = $productCategory !== 'non_durian';
        $includeInventoryItems = $productCategory !== 'durian';

        $buahKg = $includeDurian && (! $productType || $productType === 'Buah Utuh') ? $this->snapshotStockKg($snapshotRows, 'Buah Utuh', $varietyId) : 0.0;
        $freshKg = $includeDurian && (! $productType || $productType === 'Daging Fresh') ? $this->snapshotStockKg($snapshotRows, 'Daging Fresh', $varietyId) : 0.0;
        $frozenKg = $includeDurian && (! $productType || $productType === 'Daging Frozen') ? $this->snapshotStockKg($snapshotRows, 'Daging Frozen', $varietyId) : 0.0;
        $freshRecoveryRemainingKg = $includeDurian && (! $productType || $productType === 'Daging Fresh')
            ? min($freshKg, (float) $this->freshRecoveryFlow($filters, $outletId)['remaining_kg'])
            : 0.0;
        $valuedFreshKg = max(0, $freshKg - $freshRecoveryRemainingKg);
        $durianAmount = ($buahKg * $avgModalBuah) + ($valuedFreshKg * $avgModalFresh) + ($frozenKg * $avgModalFrozen);
        $inventoryItems = $includeInventoryItems ? $this->sellableInventoryValuationItems($snapshotRows, $filters) : [];
        $inventoryItemAmount = array_sum(array_column($inventoryItems, 'amount'));

        return [
            'buah_kg' => $buahKg,
            'fresh_kg' => $freshKg,
            'fresh_valued_kg' => $valuedFreshKg,
            'fresh_recovery_kg' => $freshRecoveryRemainingKg,
            'fresh_recovery_amount_excluded' => $freshRecoveryRemainingKg * $avgModalFresh,
            'frozen_kg' => $frozenKg,
            'total_kg' => $buahKg + $freshKg + $frozenKg,
            'durian_amount' => $durianAmount,
            'inventory_item_qty' => array_sum(array_column($inventoryItems, 'qty')),
            'inventory_item_amount' => $inventoryItemAmount,
            'inventory_item_items' => $inventoryItems,
            'amount' => $durianAmount + $inventoryItemAmount,
        ];
    }

    private function sellableInventoryValuationItems(array $snapshotRows, array $filters): array
    {
        $selectedItemId = $this->selectedInventoryItemId($filters);
        $itemIds = collect($snapshotRows)
            ->filter(fn (array $row): bool => ($row['product_type'] ?? null) === 'Inventory Item')
            ->pluck('inventory_item_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedItemId) {
            $itemIds = $itemIds->filter(fn (int $id): bool => $id === $selectedItemId)->values();
        }

        if ($itemIds->isEmpty()) {
            return [];
        }

        $items = InventoryItem::query()
            ->whereKey($itemIds->all())
            ->whereIn('category', ['produk_jualan', 'produk_olahan'])
            ->get(['id', 'name', 'unit', 'category', 'default_unit_cost'])
            ->keyBy('id');

        return collect($snapshotRows)
            ->filter(fn (array $row): bool => ($row['product_type'] ?? null) === 'Inventory Item')
            ->filter(fn (array $row): bool => isset($items[(int) ($row['inventory_item_id'] ?? 0)]))
            ->groupBy(fn (array $row): int => (int) $row['inventory_item_id'])
            ->map(function ($rows, int $itemId) use ($items): array {
                $item = $items[$itemId];
                $qty = (float) collect($rows)->sum(fn (array $row): float => $this->snapshotPositiveQty($row));
                $unitCost = (float) $item->default_unit_cost;

                return [
                    'id' => $itemId,
                    'name' => $item->name,
                    'category' => InventoryItem::categoryLabel($item->category),
                    'qty' => $qty,
                    'unit' => $item->unit ?: 'pcs',
                    'unit_cost' => $unitCost,
                    'amount' => $qty * $unitCost,
                ];
            })
            ->filter(fn (array $item): bool => $item['qty'] > 0.0005)
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    private function stockSnapshotFilters(array $filters, mixed $outletId): array
    {
        $snapshotFilters = [
            'date' => $filters['date_until'] ?? now()->toDateString(),
        ];

        if (is_array($outletId)) {
            $snapshotFilters['outlet_ids'] = $outletId;
        } elseif ($outletId) {
            $snapshotFilters['outlet_id'] = $outletId;
        } elseif (! empty($filters['outlet_group'])) {
            $snapshotFilters['outlet_group'] = $filters['outlet_group'];
        }

        return $snapshotFilters;
    }

    private function snapshotStockKg(array $snapshotRows, string $productType, ?int $varietyId = null): float
    {
        return collect($snapshotRows)
            ->filter(fn (array $row): bool => ($row['product_type'] ?? null) === $productType)
            ->filter(fn (array $row): bool => ! $varietyId || (int) ($row['durian_variety_id'] ?? 0) === $varietyId)
            ->sum(fn (array $row): float => $this->snapshotPositiveQty($row));
    }

    private function snapshotPositiveQty(array $row): float
    {
        $qty = $row['physical_qty'] !== null
            ? (float) $row['physical_qty']
            : (float) ($row['end_qty'] ?? 0);

        return max(0.0, $qty);
    }

    private function latestPhysicalStockKg(string $productType, array $filters, mixed $outletId = null): float
    {
        $query = StockOpname::query()
            ->where('product_type', $productType)
            ->when($filters['date_until'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '<=', $date));
        $this->applyVarietyFilter($query, $filters);

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

        $this->periodQuery(Expense::query()->whereNull('outlet_id'), $filters)
            ->get(['category', 'amount', 'allocation_scope', 'allocation_group'])
            ->each(function (Expense $expense) use ($categories, $filters, $outletId): void {
                $allocatedAmount = (float) $expense->amount * $this->expenseAllocationRatio($filters, $outletId, $expense);

                if ($allocatedAmount > 0) {
                    $category = $expense->category ?? 'Tanpa Kategori';

                    $categories[$category] = (float) ($categories[$category] ?? 0)
                        + $allocatedAmount;
                }
            });

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
