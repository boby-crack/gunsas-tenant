<?php

namespace App\Filament\Widgets;

use App\Models\Outlet;
use App\Models\ProductReturn;
use App\Models\Production;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shipment;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class BuahUtuhOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $pollingInterval = '10s';

    protected int | string | array $columns = [
        'xl' => 5,
    ];

    public function getHeading(): ?string
    {
        return 'KATEGORI: BUAH UTUH & PERSEDIAAN INDUK';
    }

    protected function getStats(): array
    {
        $outletId = $this->filters['outlet_id'] ?? null;

        $gudangId = Outlet::where('name', 'like', '%Gudang%')
            ->orWhere('name', 'like', '%Pusat%')
            ->value('id');

        $avgModal = $this->weightedAverageModalBuah($outletId);

        $totalSub = $this->periodQuery(Sale::query(), $outletId)->sum('buah_subtotal');
        $totalKg = $this->periodQuery(Sale::query(), $outletId)->sum('buah_sold_kg');
        $avgPrice = $totalKg > 0 ? ($totalSub / $totalKg) : 0;

        $totalBeliSupplier = $this->stockUntilQuery(Purchase::query())->sum('qty_butir');
        $totalKeluarKeOutlet = $this->stockUntilQuery(Shipment::query())->sum('qty_received_butir');
        $stokGudangBesar = $totalBeliSupplier - $totalKeluarKeOutlet;

        $retailOutletScope = function (Builder $query) use ($gudangId) {
            if ($gudangId) {
                $query->where('outlet_id', '!=', $gudangId);
            }
        };

        $bMasuk = $this->stockUntilQuery(Shipment::query())
            ->when($outletId, fn (Builder $query) => $query->where('outlet_id', $outletId), $retailOutletScope)
            ->sum('qty_received_butir');
        $bKupas = $this->stockUntilQuery(Production::query())
            ->when($outletId, fn (Builder $query) => $query->where('outlet_id', $outletId), $retailOutletScope)
            ->sum('qty_buah_butir');
        $bRetur = $this->stockUntilQuery(ProductReturn::query())
            ->when($outletId, fn (Builder $query) => $query->where('outlet_id', $outletId), $retailOutletScope)
            ->sum('qty_butir');
        $bJual = $this->stockUntilQuery(Sale::query())
            ->when($outletId, fn (Builder $query) => $query->where('outlet_id', $outletId), $retailOutletScope)
            ->sum('buah_sold_butir');
        $stokOutlet = $bMasuk - $bKupas - $bRetur - $bJual;

        $subDescription = $outletId ? 'di cabang terpilih' : 'total di semua toko retail';

        return [
            Stat::make('Rata-rata Modal Buah', 'Rp ' . number_format($avgModal, 0, ',', '.'))
                ->description('Weighted average modal per KG')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('warning'),

            Stat::make('Harga Jual Avg Buah', 'Rp ' . number_format($avgPrice, 0, ',', '.'))
                ->description('Realisasi kasir per KG')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('warning'),

            Stat::make('Stok Gudang Besar', $stokGudangBesar . ' Butir')
                ->description('Sisa pasokan induk pusat')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('gray'),

            Stat::make('Stok di Outlet', $stokOutlet . ' Butir')
                ->description($subDescription)
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('warning'),

            Stat::make('Buah Utuh Terjual', number_format($totalKg, 3, ',', '.') . ' Kg')
                ->description('Volume penjualan pada periode')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('warning'),
        ];
    }

    private function periodQuery(Builder $query, mixed $outletId = null): Builder
    {
        return $query
            ->when($outletId, fn (Builder $query) => $query->where('outlet_id', $outletId))
            ->when($this->filters['date_from'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '>=', $date))
            ->when($this->filters['date_until'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '<=', $date));
    }

    private function stockUntilQuery(Builder $query): Builder
    {
        return $query->when($this->filters['date_until'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '<=', $date));
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
