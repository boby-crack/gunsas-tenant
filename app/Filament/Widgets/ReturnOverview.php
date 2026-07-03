<?php

namespace App\Filament\Widgets;

use App\Models\ProductReturn;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class ReturnOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $pollingInterval = '10s';

    protected int | string | array $columns = [
        'xl' => 3,
    ];

    public function getHeading(): ?string
    {
        return 'AUDIT SIKLUS RETUR SUPPLIER & TOTAL KEBOCORAN MODAL';
    }

    protected function getStats(): array
    {
        $outletId = $this->filters['outlet_id'] ?? null;

        $returns = ProductReturn::query()
            ->when($outletId, fn (Builder $query) => $query->where('outlet_id', $outletId))
            ->when($this->filters['date_from'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '>=', $date))
            ->when($this->filters['date_until'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '<=', $date))
            ->with('shipment')
            ->get();

        $totalMasukBtr = $returns->sum('qty_butir');
        $totalDiajukanKg = $returns->sum('qty_kg');
        $nilaiAsetRusak = $returns->sum(fn ($return) => $return->qty_kg * ($return->shipment?->modal_price ?? 0));

        $totalDiterimaBtr = $returns->sum('supplier_accepted_qty_butir');
        $totalDiterimaKg = $returns->sum('supplier_accepted_qty_kg');
        $totalRefundCash = $returns->sum('refund_amount');

        $selisihKgRugi = max(0, $totalDiajukanKg - $totalDiterimaKg);
        $selisihButirRugi = max(0, $totalMasukBtr - $totalDiterimaBtr);
        $totalRugiUang = max(0, $nilaiAsetRusak - $totalRefundCash);

        return [
            Stat::make('1. Total Retur Diajukan', number_format($totalDiajukanKg, 3, ',', '.') . ' KG')
                ->description($totalMasukBtr . ' Butir | Estimasi Modal: Rp ' . number_format($nilaiAsetRusak, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('warning'),

            Stat::make('2. Total Refund Uang Supplier', number_format($totalDiterimaKg, 3, ',', '.') . ' KG')
                ->description($totalDiterimaBtr . ' Btr | Uang Kembali: Rp ' . number_format($totalRefundCash, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('3. Total Losses Kita (Hangus)', number_format($selisihKgRugi, 3, ',', '.') . ' KG')
                ->description($selisihButirRugi . ' Btr | Beban Hangus: Rp ' . number_format($totalRugiUang, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($totalRugiUang > 0 ? 'danger' : 'gray'),
        ];
    }
}
