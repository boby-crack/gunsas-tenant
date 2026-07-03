<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class SalesWeightOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $pollingInterval = '10s'; // Refresh otomatis setiap 10 detik

    protected function getStats(): array
    {
        $outletId = $this->filters['outlet_id'] ?? null;

        $buahSoldKg = Sale::when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->sum('buah_sold_kg');

        $freshSoldKg = Sale::when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->sum('fresh_sold_kg');

        $frozenSoldKg = Sale::when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->sum('frozen_sold_kg');

        $totalVolumeKg = $buahSoldKg + $freshSoldKg + $frozenSoldKg;

        $subDescription = $outletId ? 'di outlet terpilih' : 'akumulasi semua outlet';

        return [
            Stat::make('Buah Utuh Terjual', number_format($buahSoldKg, 3, ',', '.') . ' Kg')
                ->description('Total volume ' . $subDescription)
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('warning'),

            Stat::make('Kupas Fresh Terjual', number_format($freshSoldKg, 3, ',', '.') . ' Kg')
                ->description('Total volume ' . $subDescription)
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('success'),

            Stat::make('Durpas Frozen Terjual', number_format($frozenSoldKg, 3, ',', '.') . ' Kg')
                ->description('Total volume ' . $subDescription)
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('info'),
        ];
    }
}
