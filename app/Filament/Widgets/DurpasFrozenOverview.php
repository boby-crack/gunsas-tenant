<?php

namespace App\Filament\Widgets;

use App\Models\ProductConversion;
use App\Models\Production;
use App\Models\Sale;
use App\Models\Shipment;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class DurpasFrozenOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $pollingInterval = '10s';

    public function getHeading(): ?string
    {
        return 'KATEGORI: DURPAS FROZEN (STOCK FREEZER)';
    }

    protected function getStats(): array
    {
        $outletId = $this->filters['outlet_id'] ?? null;

        $avgModalBuah = $this->weightedAverageModalBuah($outletId);
        $totalBuahKupas = $this->periodQuery(Production::query(), $outletId)->sum('qty_buah_kg');
        $totalDagingKupas = $this->periodQuery(Production::query(), $outletId)->sum('qty_kupas_kg');
        $avgModalFresh = $totalDagingKupas > 0
            ? (($totalBuahKupas * $avgModalBuah) / $totalDagingKupas)
            : ($avgModalBuah * 2.64);

        $freshConverted = $this->periodQuery(ProductConversion::query(), $outletId)
            ->where('conversion_type', 'Kupas Fresh ke Kupas Frozen')
            ->sum('from_qty_kg');
        $frozenProduced = $this->periodQuery(ProductConversion::query(), $outletId)
            ->where('conversion_type', 'Kupas Fresh ke Kupas Frozen')
            ->sum('to_qty_kg');
        $avgModalFrozen = $frozenProduced > 0
            ? (($freshConverted * $avgModalFresh) / $frozenProduced)
            : $avgModalFresh;

        $totalSub = $this->periodQuery(Sale::query(), $outletId)->sum('frozen_subtotal');
        $totalKg = $this->periodQuery(Sale::query(), $outletId)->sum('frozen_sold_kg');
        $avgPrice = $totalKg > 0 ? ($totalSub / $totalKg) : 0;

        $frozenProducedUntil = $this->stockUntilQuery(ProductConversion::query(), $outletId)
            ->where('conversion_type', 'Kupas Fresh ke Kupas Frozen')
            ->sum('to_qty_kg');
        $frozenSoldUntil = $this->stockUntilQuery(Sale::query(), $outletId)->sum('frozen_sold_kg');
        $stokFrozen = $frozenProducedUntil - $frozenSoldUntil;

        return [
            Stat::make('Rata-rata Modal Frozen', 'Rp ' . number_format($avgModalFrozen, 0, ',', '.'))
                ->description('Cost basis produk freezer')
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('info'),

            Stat::make('Harga Jual Avg Frozen', 'Rp ' . number_format($avgPrice, 0, ',', '.'))
                ->description('Realisasi kasir per KG')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info'),

            Stat::make('Total Stok Durpas Frozen', number_format($stokFrozen, 2, ',', '.') . ' Kg')
                ->description('Posisi stok sampai tanggal akhir')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('info'),

            Stat::make('Durpas Frozen Terjual', number_format($totalKg, 3, ',', '.') . ' Kg')
                ->description('Volume penjualan pada periode')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('info'),
        ];
    }

    private function periodQuery(Builder $query, mixed $outletId = null): Builder
    {
        return $query
            ->when($outletId, fn (Builder $query) => $query->where('outlet_id', $outletId))
            ->when($this->filters['date_from'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '>=', $date))
            ->when($this->filters['date_until'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '<=', $date));
    }

    private function stockUntilQuery(Builder $query, mixed $outletId = null): Builder
    {
        return $query
            ->when($outletId, fn (Builder $query) => $query->where('outlet_id', $outletId))
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
}
