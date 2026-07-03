<?php

namespace App\Filament\Pages;

use App\Models\Outlet;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('outlet_id')
                            ->label('Filter Tampilan Data Cabang')
                            ->options(Outlet::pluck('name', 'id'))
                            ->placeholder('Semua Outlet (Tampilan Global)')
                            ->live(),

                        DatePicker::make('date_from')
                            ->label('Tanggal Mulai')
                            ->default(now()->startOfMonth())
                            ->live(),

                        DatePicker::make('date_until')
                            ->label('Tanggal Akhir')
                            ->default(now())
                            ->live(),
                    ])
                    ->columns(3),
            ]);
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\BuahUtuhOverview::class,
            \App\Filament\Widgets\KupasFreshOverview::class,
            \App\Filament\Widgets\DurpasFrozenOverview::class,
            \App\Filament\Widgets\ReturnOverview::class,
            \App\Filament\Widgets\FinanceOverview::class,
        ];
    }
}
