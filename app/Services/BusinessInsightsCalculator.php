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
    public function calculate(array $filters): array
    {
        $outletId = $filters['outlet_id'] ?? null;

        $grossSales = $this->periodQuery(Sale::query(), $filters, $outletId)->sum('grand_total_revenue');
        $tiptopCut = $grossSales * 0.15;
        $gunsasRevenue = $grossSales * 0.85;

        $avgModalBuah = $this->weightedAverageModalBuah($filters, $outletId);
        $avgModalFresh = $this->averageModalFresh($filters, $outletId, $avgModalBuah);
        $avgModalFrozen = $this->averageModalFrozen($filters, $outletId, $avgModalFresh);

        $salesQuery = $this->periodQuery(Sale::query(), $filters, $outletId);
        $buahSoldKg = (clone $salesQuery)->sum('buah_sold_kg');
        $freshSoldKg = (clone $salesQuery)->sum('fresh_sold_kg');
        $frozenSoldKg = (clone $salesQuery)->sum('frozen_sold_kg');

        $hppSales = ($buahSoldKg * $avgModalBuah)
            + ($freshSoldKg * $avgModalFresh)
            + ($frozenSoldKg * $avgModalFrozen);

        $grossProfit = $gunsasRevenue - $hppSales;
        $expenses = $this->periodQuery(Expense::query(), $filters, $outletId)->sum('amount');
        $returnSummary = $this->returnSummary($filters, $outletId, $avgModalBuah);
        $opnameLoss = $this->opnameLoss($filters, $outletId, $avgModalBuah, $avgModalFresh, $avgModalFrozen);
        $lossBreakdown = $this->lossBreakdown($filters, $outletId, $returnSummary, $opnameLoss, $avgModalBuah, $avgModalFresh);
        $netProfit = $grossProfit - $expenses - $returnSummary['loss_final'] - $opnameLoss['amount'];
        $netMargin = $gunsasRevenue > 0 ? ($netProfit / $gunsasRevenue) * 100 : 0;
        $inventory = $this->inventoryValuation($filters, $outletId, $avgModalBuah, $avgModalFresh, $avgModalFrozen);

        return [
            'filters' => [
                'date_from' => $filters['date_from'] ?? now()->startOfMonth()->toDateString(),
                'date_until' => $filters['date_until'] ?? now()->toDateString(),
                'outlet_name' => $outletId ? Outlet::find($outletId)?->name : 'Semua Outlet',
            ],
            'sales' => [
                'gross_sales' => $grossSales,
                'tiptop_cut' => $tiptopCut,
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
            'top_outlets' => $this->topOutlets($filters),
            'expense_categories' => $this->expenseCategories($filters, $outletId),
        ];
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
        $productionInputKg = (clone $productionQuery)->sum('qty_buah_kg');
        $productionUsableKg = (clone $productionQuery)->sum('total_usable_meat_kg');
        $productionShrinkKg = max(0, $productionInputKg - $productionUsableKg);

        $conversionQuery = $this->periodQuery(ProductConversion::query(), $filters, $outletId)
            ->where('conversion_type', 'Kupas Fresh ke Kupas Frozen');
        $conversionInputKg = (clone $conversionQuery)->sum('from_qty_kg');
        $conversionOutputKg = (clone $conversionQuery)->sum('to_qty_kg');
        $conversionShrinkKg = max(0, $conversionInputKg - $conversionOutputKg);

        $directLossKg = $returnSummary['rejected_kg'] + $opnameLoss['total_kg'];
        $processShrinkKg = $productionShrinkKg + $conversionShrinkKg;

        return [
            'direct_loss_kg' => $directLossKg,
            'direct_loss_amount' => $returnSummary['loss_final'] + $opnameLoss['amount'],
            'process_shrink_kg' => $processShrinkKg,
            'process_shrink_amount' => ($productionShrinkKg * $avgModalBuah) + ($conversionShrinkKg * $avgModalFresh),
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
                    'amount' => $productionShrinkKg * $avgModalBuah,
                    'impact' => 'Masuk ke modal fresh',
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
        return $query
            ->when($outletId, fn (Builder $query) => $query->where('outlet_id', $outletId))
            ->when($filters['date_from'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '>=', $date))
            ->when($filters['date_until'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '<=', $date));
    }

    private function weightedAverageModalBuah(array $filters, mixed $outletId = null): float
    {
        $query = $this->periodQuery(Shipment::query(), $filters, $outletId);
        $totalKg = (clone $query)->sum('qty_sent_kg');

        if ($totalKg <= 0) {
            $fallbackQuery = Shipment::query()->when($outletId, fn (Builder $query) => $query->where('outlet_id', $outletId));
            $fallbackKg = (clone $fallbackQuery)->sum('qty_sent_kg');
            $fallbackCost = (clone $fallbackQuery)->selectRaw('SUM(qty_sent_kg * modal_price) as total_cost')->value('total_cost') ?? 0;

            return $fallbackKg > 0 ? $fallbackCost / $fallbackKg : 66000;
        }

        $totalCost = (clone $query)->selectRaw('SUM(qty_sent_kg * modal_price) as total_cost')->value('total_cost') ?? 0;

        return $totalCost / $totalKg;
    }

    private function averageModalFresh(array $filters, mixed $outletId, float $avgModalBuah): float
    {
        $query = $this->periodQuery(Production::query(), $filters, $outletId);
        $buahKg = (clone $query)->sum('qty_buah_kg');
        $freshKg = (clone $query)->sum('qty_kupas_kg');

        return $freshKg > 0 ? ($buahKg * $avgModalBuah) / $freshKg : $avgModalBuah * 2.64;
    }

    private function averageModalFrozen(array $filters, mixed $outletId, float $avgModalFresh): float
    {
        $query = $this->periodQuery(ProductConversion::query(), $filters, $outletId)
            ->where('conversion_type', 'Kupas Fresh ke Kupas Frozen');
        $fromKg = (clone $query)->sum('from_qty_kg');
        $toKg = (clone $query)->sum('to_qty_kg');

        return $toKg > 0 ? ($fromKg * $avgModalFresh) / $toKg : $avgModalFresh;
    }

    private function returnSummary(array $filters, mixed $outletId, float $avgModalBuah): array
    {
        $returns = $this->periodQuery(ProductReturn::query(), $filters, $outletId)
            ->with('shipment')
            ->get();

        $assetSubmitted = $returns->sum(fn (ProductReturn $return) => $return->qty_kg * ($return->shipment?->modal_price ?? $avgModalBuah));
        $refund = $returns->sum('refund_amount');
        $finalReturns = $returns->where('status', '!=', 'pending');
        $pendingReturns = $returns->where('status', 'pending');
        $finalAsset = $finalReturns->sum(fn (ProductReturn $return) => $return->qty_kg * ($return->shipment?->modal_price ?? $avgModalBuah));
        $finalRefund = $finalReturns->sum('refund_amount');
        $pendingAsset = $pendingReturns->sum(fn (ProductReturn $return) => $return->qty_kg * ($return->shipment?->modal_price ?? $avgModalBuah));
        $submittedKg = $returns->sum('qty_kg');
        $pendingKg = $pendingReturns->sum('qty_kg');
        $finalKg = $finalReturns->sum('qty_kg');
        $acceptedKg = $finalReturns->sum('supplier_accepted_qty_kg');
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
            'pending_count' => $pendingReturns->count(),
            'final_count' => $finalReturns->count(),
        ];
    }

    private function opnameLoss(array $filters, mixed $outletId, float $avgModalBuah, float $avgModalFresh, float $avgModalFrozen): array
    {
        $buahLoss = abs($this->periodQuery(StockOpname::query(), $filters, $outletId)
            ->where('product_type', 'Buah Utuh')
            ->where('difference_qty_kg', '<', 0)
            ->sum('difference_qty_kg'));
        $freshLoss = abs($this->periodQuery(StockOpname::query(), $filters, $outletId)
            ->where('product_type', 'Daging Fresh')
            ->where('difference_qty_kg', '<', 0)
            ->sum('difference_qty_kg'));
        $frozenLoss = abs($this->periodQuery(StockOpname::query(), $filters, $outletId)
            ->where('product_type', 'Daging Frozen')
            ->where('difference_qty_kg', '<', 0)
            ->sum('difference_qty_kg'));

        return [
            'buah_kg' => $buahLoss,
            'fresh_kg' => $freshLoss,
            'frozen_kg' => $frozenLoss,
            'total_kg' => $buahLoss + $freshLoss + $frozenLoss,
            'amount' => ($buahLoss * $avgModalBuah) + ($freshLoss * $avgModalFresh) + ($frozenLoss * $avgModalFrozen),
        ];
    }

    private function topOutlets(array $filters): array
    {
        return $this->periodQuery(Sale::query(), $filters)
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
            ->when($outletId, fn (Builder $query) => $query->where('outlet_id', $outletId))
            ->when($filters['date_until'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '<=', $date));

        $records = $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (StockOpname $record) => $record->outlet_id . ':' . $record->durian_variety_id . ':' . $record->product_type);

        return $records->sum('physical_qty_kg');
    }

    private function expenseCategories(array $filters, mixed $outletId): array
    {
        return $this->periodQuery(Expense::query(), $filters, $outletId)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn (Expense $expense) => [
                'category' => $expense->category ?? 'Tanpa Kategori',
                'total' => (float) $expense->total,
            ])
            ->all();
    }
}
