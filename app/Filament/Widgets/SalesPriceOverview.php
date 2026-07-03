<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class SalesPriceOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $pollingInterval = '10s'; // Auto-refresh harian

    protected function getStats(): array
    {
        $outletId = $this->filters['outlet_id'] ?? null;

        $totalBuahSub = Sale::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->sum('buah_subtotal');
        $totalBuahKg = Sale::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->sum('buah_sold_kg');
        $avgPriceBuah = $totalBuahKg > 0 ? ($totalBuahSub / $totalBuahKg) : 0;

        $totalFreshSub = Sale::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->sum('fresh_subtotal');
        $totalFreshKg = Sale::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->sum('fresh_sold_kg');
        $avgPriceFresh = $totalFreshKg > 0 ? ($totalFreshSub / $totalFreshKg) : 0;

        $totalFrozenSub = Sale::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->sum('frozen_subtotal');
        $totalFrozenKg = Sale::when($outletId, fn($q) => $q->where('outlet_id', $outletId))->sum('frozen_sold_kg');
        $avgPriceFrozen = $totalFrozenKg > 0 ? ($totalFrozenSub / $totalFrozenKg) : 0;

        $subDescription = $outletId ? 'di cabang terpilih' : 'efektif global bisnis';

        return [
            Stat::make('Harga Jual Avg Buah Utuh', 'Rp ' . number_format($avgPriceBuah, 0, ',', '.'))
                ->description('Realisasi kasir per KG ' . $subDescription)
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('warning'),

            Stat::make('Harga Jual Avg Kupas Fresh', 'Rp ' . number_format($avgPriceFresh, 0, ',', '.'))
                ->description('Realisasi kasir per KG ' . $subDescription)
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('success'),

            Stat::make('Harga Jual Avg Durpas Frozen', 'Rp ' . number_format($avgPriceFrozen, 0, ',', '.'))
                ->description('Realisasi kasir per KG ' . $subDescription)
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('info'),
        ];
    }
}
