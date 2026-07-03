<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\ProductConversion;
use App\Models\ProductReturn;
use App\Models\Production;
use App\Models\Sale;
use App\Models\Shipment;
use App\Models\StockOpname;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class FinanceOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $pollingInterval = '10s';

    protected int | string | array $columns = [
        'xl' => 4,
    ];

    public function getHeading(): ?string
    {
        $outletId = $this->filters['outlet_id'] ?? null;

        return $outletId
            ? 'AUDIT FINANSIAL & KEBOCORAN (OUTLET TERPILIH)'
            : 'AUDIT FINANSIAL & KEBOCORAN (GLOBAL GROUP)';
    }

    protected function getStats(): array
    {
        $outletId = $this->filters['outlet_id'] ?? null;

        $grossSales = $this->periodQuery(Sale::query(), $outletId)
            ->sum('grand_total_revenue');
        $tiptopCut = $grossSales * 0.15;
        $gunsasRevenue = $grossSales * 0.85;

        $avgModalBuah = $this->weightedAverageModalBuah($outletId);

        $totalBuahKupasKg = $this->periodQuery(Production::query(), $outletId)
            ->sum('qty_buah_kg');
        $totalDagingKupasKg = $this->periodQuery(Production::query(), $outletId)
            ->sum('qty_kupas_kg');
        $avgModalFresh = $totalDagingKupasKg > 0
            ? (($totalBuahKupasKg * $avgModalBuah) / $totalDagingKupasKg)
            : ($avgModalBuah * 2.64);

        $freshConvertedKg = $this->periodQuery(ProductConversion::query(), $outletId)
            ->where('conversion_type', 'Kupas Fresh ke Kupas Frozen')
            ->sum('from_qty_kg');
        $frozenProducedKg = $this->periodQuery(ProductConversion::query(), $outletId)
            ->where('conversion_type', 'Kupas Fresh ke Kupas Frozen')
            ->sum('to_qty_kg');
        $avgModalFrozen = $frozenProducedKg > 0
            ? (($freshConvertedKg * $avgModalFresh) / $frozenProducedKg)
            : $avgModalFresh;

        $buahSoldKg = $this->periodQuery(Sale::query(), $outletId)->sum('buah_sold_kg');
        $freshSoldKg = $this->periodQuery(Sale::query(), $outletId)->sum('fresh_sold_kg');
        $frozenSoldKg = $this->periodQuery(Sale::query(), $outletId)->sum('frozen_sold_kg');

        $hppSales = ($buahSoldKg * $avgModalBuah)
            + ($freshSoldKg * $avgModalFresh)
            + ($frozenSoldKg * $avgModalFrozen);
        $grossMargin = $gunsasRevenue - $hppSales;

        $returns = $this->periodQuery(ProductReturn::query(), $outletId)
            ->with('shipment')
            ->get();
        $totalAssetReturAwal = $returns->sum(fn ($return) => $return->qty_kg * ($return->shipment?->modal_price ?? $avgModalBuah));
        $totalRefundSupplier = $returns->sum('refund_amount');
        $lossReturHangusReal = max(0, $totalAssetReturAwal - $totalRefundSupplier);

        $opnameLossBuah = abs($this->periodQuery(StockOpname::query(), $outletId)
            ->where('product_type', 'Buah Utuh')
            ->where('difference_qty_kg', '<', 0)
            ->sum('difference_qty_kg'));
        $opnameLossFresh = abs($this->periodQuery(StockOpname::query(), $outletId)
            ->where('product_type', 'Daging Fresh')
            ->where('difference_qty_kg', '<', 0)
            ->sum('difference_qty_kg'));
        $opnameLossFrozen = abs($this->periodQuery(StockOpname::query(), $outletId)
            ->where('product_type', 'Daging Frozen')
            ->where('difference_qty_kg', '<', 0)
            ->sum('difference_qty_kg'));
        $lossSelisihOpname = ($opnameLossBuah * $avgModalBuah)
            + ($opnameLossFresh * $avgModalFresh)
            + ($opnameLossFrozen * $avgModalFrozen);

        $totalExpenses = $this->periodQuery(Expense::query(), $outletId)->sum('amount');
        $netProfit = $grossMargin - $lossReturHangusReal - $lossSelisihOpname - $totalExpenses;

        $actualBuahKg = $this->latestPhysicalStockKg('Buah Utuh', $outletId);
        $actualFreshKg = $this->latestPhysicalStockKg('Daging Fresh', $outletId);
        $actualFrozenKg = $this->latestPhysicalStockKg('Daging Frozen', $outletId);
        $inventoryValuationPrice = ($actualBuahKg * $avgModalBuah)
            + ($actualFreshKg * $avgModalFresh)
            + ($actualFrozenKg * $avgModalFrozen);
        $totalInventoryWeightKg = $actualBuahKg + $actualFreshKg + $actualFrozenKg;

        return [
            Stat::make('Total Omset Kasir', 'Rp ' . number_format($grossSales, 0, ',', '.'))
                ->description('Bruto kasir swalayan (100%)')
                ->color('gray'),

            Stat::make('Bagi Hasil TipTop (15%)', 'Rp ' . number_format($tiptopCut, 0, ',', '.'))
                ->description('Potongan komisi sewa tenant')
                ->color('warning'),

            Stat::make('Pendapatan Bersih Gunsas', 'Rp ' . number_format($gunsasRevenue, 0, ',', '.'))
                ->description('Hak dasar omset murni perusahaan (85%)')
                ->color('info'),

            Stat::make('Margin Kotor Gunsas', 'Rp ' . number_format($grossMargin, 0, ',', '.'))
                ->description('Pendapatan bersih dikurangi HPP terjual')
                ->color($grossMargin >= 0 ? 'success' : 'danger'),

            Stat::make('KELOMPOK BIAYA HPP & LOSSES RETUR', ' ')
                ->extraAttributes(['class' => 'col-span-full bg-gray-50 dark:bg-gray-800/50 py-1 text-center font-bold text-gray-500']),

            Stat::make('HPP Penjualan', 'Rp ' . number_format($hppSales, 0, ',', '.'))
                ->description('Nilai modal produk laku terjual')
                ->color('danger'),

            Stat::make('HPP Retur Diajukan', 'Rp ' . number_format($totalAssetReturAwal, 0, ',', '.'))
                ->description('Nilai total aset buah rusak di awal')
                ->color('gray'),

            Stat::make('HPP Retur Diterima', 'Rp ' . number_format($totalRefundSupplier, 0, ',', '.'))
                ->description('Refund / uang kembali dari supplier')
                ->color('success'),

            Stat::make('Losses Retur Ditanggung', 'Rp ' . number_format($lossReturHangusReal, 0, ',', '.'))
                ->description('Rugi bersih riil akibat klaim ditolak')
                ->color('danger'),

            Stat::make('KELOMPOK OPERASIONAL & LABA RIIL FINAL', ' ')
                ->extraAttributes(['class' => 'col-span-full bg-gray-50 dark:bg-gray-800/50 py-1 text-center font-bold text-gray-500']),

            Stat::make('Losses Selisih Opname', 'Rp ' . number_format($lossSelisihOpname, 0, ',', '.'))
                ->description('Penyusutan fisik / shrinkage toko')
                ->color('danger'),

            Stat::make('Beban Operasional', 'Rp ' . number_format($totalExpenses, 0, ',', '.'))
                ->description('Pengeluaran operasional outlet')
                ->color('danger'),

            Stat::make('Valuasi Persediaan (Inventory)', number_format($totalInventoryWeightKg, 2, ',', '.') . ' KG')
                ->description('Nilai sisa aset aktif: Rp ' . number_format($inventoryValuationPrice, 0, ',', '.'))
                ->color('success'),

            Stat::make('Laba Bersih Riil Gunsas', 'Rp ' . number_format($netProfit, 0, ',', '.'))
                ->description($netProfit >= 0 ? 'Profit bersih final masuk pusat' : 'Defisit operasional group')
                ->color($netProfit >= 0 ? 'success' : 'danger'),
        ];
    }

    private function periodQuery(Builder $query, mixed $outletId = null): Builder
    {
        return $query
            ->when($outletId, fn (Builder $query) => $query->where('outlet_id', $outletId))
            ->when($this->filters['date_from'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '>=', $date))
            ->when($this->filters['date_until'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '<=', $date));
    }

    private function weightedAverageModalBuah(mixed $outletId = null): float
    {
        $query = $this->periodQuery(Shipment::query(), $outletId);
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

    private function latestPhysicalStockKg(string $productType, mixed $outletId = null): float
    {
        $query = StockOpname::query()
            ->where('product_type', $productType)
            ->when($outletId, fn (Builder $query) => $query->where('outlet_id', $outletId))
            ->when($this->filters['date_until'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '<=', $date));

        $records = $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (StockOpname $record) => $record->outlet_id . ':' . $record->durian_variety_id . ':' . $record->product_type);

        return $records->sum('physical_qty_kg');
    }
}
