<?php

namespace App\Filament\Widgets;

use App\Models\Outlet;
use App\Services\SalesTargetCalculator;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

class OutletTargetPerformanceTable extends BaseWidget
{
    use InteractsWithPageFilters;

    public ?array $dashboardFilters = null;

    protected static ?string $heading = 'Kesimpulan Target Sales per Outlet';

    protected static ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected array $performanceCache = [];

    public function table(Table $table): Table
    {
        $filters = $this->dashboardFilters();
        $outletId = $filters['outlet_id'] ?? null;
        $outletGroup = Outlet::normalizeGroupName($filters['outlet_group'] ?? null);

        return $table
            ->query(
                Outlet::query()
                    ->when($outletId, fn ($query, $outletId) => $query->whereKey($outletId))
                    ->when(! $outletId && $outletGroup, fn ($query) => $query->where('group_name', $outletGroup))
                    ->orderBy('name')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Outlet')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('target')
                    ->label('Target')
                    ->getStateUsing(fn (Outlet $record) => $this->performance($record->id)['target'])
                    ->money('IDR'),

                Tables\Columns\TextColumn::make('actual')
                    ->label('Realisasi')
                    ->getStateUsing(fn (Outlet $record) => $this->performance($record->id)['actual'])
                    ->money('IDR'),

                Tables\Columns\TextColumn::make('achievement')
                    ->label('Pencapaian')
                    ->getStateUsing(fn (Outlet $record) => $this->performance($record->id)['achievement'])
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 1, ',', '.') . '%')
                    ->badge()
                    ->color(fn ($state) => (float) $state >= 100 ? 'success' : ((float) $state >= 80 ? 'warning' : 'danger')),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn (Outlet $record) => $this->performance($record->id)['status'])
                    ->badge()
                    ->color(fn ($state) => $state === 'Capai target' ? 'success' : ($state === 'Belum capai' ? 'danger' : 'gray')),

                Tables\Columns\TextColumn::make('gap')
                    ->label('Sisa / Lebih')
                    ->getStateUsing(fn (Outlet $record) => $this->performance($record->id)['gap'])
                    ->money('IDR')
                    ->color(fn ($state) => (float) $state >= 0 ? 'success' : 'danger'),
            ])
            ->paginated(false);
    }

    private function performance(int $outletId): array
    {
        if (isset($this->performanceCache[$outletId])) {
            return $this->performanceCache[$outletId];
        }

        $calculator = app(SalesTargetCalculator::class);
        $filters = $this->dashboardFilters();
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateUntil = $filters['date_until'] ?? now()->toDateString();

        $target = $calculator->targetAmount(
            'net_sales',
            $dateFrom,
            $dateUntil,
            $outletId,
        );

        $actual = $calculator->actual(
            'net_sales',
            $dateFrom,
            $dateUntil,
            $outletId,
        );

        return $this->performanceCache[$outletId] = [
            'target' => $target,
            'actual' => $actual,
            'achievement' => $target > 0 ? ($actual / $target) * 100 : 0,
            'status' => $target <= 0 ? 'Belum ada target' : ($actual >= $target ? 'Capai target' : 'Belum capai'),
            'gap' => $actual - $target,
        ];
    }

    private function dashboardFilters(): array
    {
        return $this->dashboardFilters ?? $this->filters ?? [];
    }
}
