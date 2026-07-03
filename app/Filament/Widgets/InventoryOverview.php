<?php

namespace App\Filament\Widgets;

use App\Models\Shipment;
use App\Models\Production;
use App\Models\ProductReturn;
use App\Models\ProductConversion;
use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class InventoryOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $pollingInterval = '5s'; 

    protected function getStats(): array
    {
        $outletId = $this->filters['outlet_id'] ?? null;

        // 1. STOK BUAH UTUH
        $buahMasuk = Shipment::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->sum('qty_received_butir');
        $buahKupas = Production::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->sum('qty_buah_butir');
        $buahRetur = ProductReturn::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->sum('qty_butir');
        $buahJual  = Sale::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->sum('buah_sold_butir');
        $totalBuahButir = $buahMasuk - $buahKupas - $buahRetur - $buahJual;

        $buahMasukKg = Shipment::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->sum('qty_sent_kg');
        $buahKupasKg = Production::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->sum('qty_buah_kg');
        $buahReturKg = ProductReturn::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->sum('qty_kg');
        $buahJualKg  = Sale::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->sum('buah_sold_kg');
        $totalBuahKg = $buahMasukKg - $buahKupasKg - $buahReturKg - $buahJualKg;

        // 2. STOK DAGING FRESH
        $freshKupas  = Production::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->sum('qty_kupas_kg');
        $freshPindah = ProductConversion::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->where('conversion_type', 'Kupas Fresh ke Kupas Frozen')->sum('from_qty_kg');
        $freshJual   = Sale::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->sum('fresh_sold_kg');
        $totalFreshKg = $freshKupas - $freshPindah - $freshJual;

        // 3. STOK DURPAS FROZEN
        $frozenMasuk = ProductConversion::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->where('conversion_type', 'Kupas Fresh ke Kupas Frozen')->sum('to_qty_kg');
        $frozenJual  = Sale::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->sum('frozen_sold_kg');
        $totalFrozenKg = $frozenMasuk - $frozenJual;

        $subDescription = $outletId ? 'di outlet' : 'semua outlet';

        return [
            Stat::make('Total Stok Buah Utuh', $totalBuahButir . ' Butir')
                ->description(number_format($totalBuahKg, 2, ',', '.') . ' Kg ' . $subDescription)
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->chart([80, 75, 70, 68])
                ->color('warning'),

            Stat::make('Total Stok Daging Fresh', number_format($totalFreshKg, 2, ',', '.') . ' Kg')
                ->description('Siap jual ' . $subDescription)
                ->descriptionIcon('heroicon-m-sparkles')
                ->chart([2, 4, 7, 5.65])
                ->color('success'),

            Stat::make('Total Stok Durpas Frozen', number_format($totalFrozenKg, 2, ',', '.') . ' Kg')
                ->description('Di dalam freezer ' . $subDescription)
                ->descriptionIcon('heroicon-m-archive-box') 
                ->chart([0, 1.2, 0.9, 0.78])
                ->color('info'),
        ];
    }
}
