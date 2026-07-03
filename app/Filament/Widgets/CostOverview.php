<?php

namespace App\Filament\Widgets;

use App\Models\Shipment;
use App\Models\Production;
use App\Models\ProductConversion;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class CostOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $pollingInterval = '10s'; // Auto-refresh data

    protected function getStats(): array
    {
        $outletId = $this->filters['outlet_id'] ?? null;

        $avgModalBuah = Shipment::when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->avg('modal_price') ?? 0;

        $totalBuahKupasKg = Production::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->sum('qty_buah_kg');
        $totalDagingKupasKg = Production::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->sum('qty_kupas_kg');
        
        $avgModalFresh = $totalDagingKupasKg > 0 
            ? (($totalBuahKupasKg * $avgModalBuah) / $totalDagingKupasKg) 
            : ($avgModalBuah * 2.64); // Fallback otomatis menggunakan angka pengkali ideal jika belum ada data produksi

        $freshConvertedKg = ProductConversion::when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->where('conversion_type', 'Kupas Fresh ke Kupas Frozen')->sum('from_qty_kg');
        $frozenProducedKg = ProductConversion::when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->where('conversion_type', 'Kupas Fresh ke Kupas Frozen')->sum('to_qty_kg');

        $avgModalFrozen = $frozenProducedKg > 0 
            ? (($freshConvertedKg * $avgModalFresh) / $frozenProducedKg) 
            : $avgModalFresh;

        $subDescription = $outletId ? 'di cabang terpilih' : 'rata-rata global bisnis';

        return [
            Stat::make('Rata-rata Modal Buah Utuh', 'Rp ' . number_format($avgModalBuah, 0, ',', '.'))
                ->description('Harga beli nota per KG ' . $subDescription)
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('warning'),

            Stat::make('Rata-rata Modal Kupas Fresh', 'Rp ' . number_format($avgModalFresh, 0, ',', '.'))
                ->description('Sudah hitung susut kulit & biji')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('success'),

            Stat::make('Rata-rata Modal Durpas Frozen', 'Rp ' . number_format($avgModalFrozen, 0, ',', '.'))
                ->description('Cost basis produk freezer per KG')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('info'),
        ];
    }
}
