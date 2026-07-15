<?php

namespace App\Filament\Pages;

use App\Models\DurianVariety;
use App\Models\Outlet;
use App\Services\PriceSimulatorCalculator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class PriceSimulator extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Price Simulator';

    protected static ?string $navigationGroup = 'Business';

    protected static ?string $title = 'Price Simulator';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.price-simulator';

    public ?array $filters = [];

    public array $simulationData = [];

    public function mount(): void
    {
        $this->form->fill([
            'outlet_group' => null,
            'outlet_id' => null,
            'durian_variety_id' => null,
            'product_type' => 'all',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_until' => now()->toDateString(),
            'forecast_days' => now()->daysInMonth,
            'forecast_kg' => null,
            'buah_price_per_kg' => null,
            'fresh_price_per_kg' => null,
            'frozen_price_per_kg' => null,
            'target_margin_percent' => 25,
            'adjustment_percent' => null,
            'include_overhead' => true,
        ]);

        $this->refreshSimulation();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Parameter Simulasi')
                    ->schema([
                        Select::make('outlet_group')
                            ->label('Grup Outlet')
                            ->options(Outlet::GROUPS)
                            ->placeholder('Semua Grup')
                            ->live(),

                        Select::make('outlet_id')
                            ->label('Outlet')
                            ->options(Outlet::query()->orderBy('name')->pluck('name', 'id'))
                            ->placeholder('Semua Outlet')
                            ->live(),

                        Select::make('durian_variety_id')
                            ->label('Varian')
                            ->options(DurianVariety::query()->orderBy('name')->pluck('name', 'id'))
                            ->placeholder('Semua Varian')
                            ->live(),

                        DatePicker::make('date_from')
                            ->label('Basis Data Dari')
                            ->required()
                            ->live(onBlur: true),

                        DatePicker::make('date_until')
                            ->label('Sampai')
                            ->required()
                            ->live(onBlur: true),

                        TextInput::make('forecast_days')
                            ->label('Forecast Hari')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->required()
                            ->live(onBlur: true),

                        TextInput::make('buah_price_per_kg')
                            ->label('Harga Buah / Kg')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('Kosongkan untuk saran')
                            ->prefix('Rp')
                            ->live(onBlur: true),

                        TextInput::make('fresh_price_per_kg')
                            ->label('Harga Fresh / Kg')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('Kosongkan untuk saran')
                            ->prefix('Rp')
                            ->live(onBlur: true),

                        TextInput::make('frozen_price_per_kg')
                            ->label('Harga Frozen / Kg')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('Kosongkan untuk saran')
                            ->prefix('Rp')
                            ->live(onBlur: true),

                        TextInput::make('target_margin_percent')
                            ->label('Target Margin Bersih')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(90)
                            ->required()
                            ->suffix('%')
                            ->live(onBlur: true),

                        TextInput::make('adjustment_percent')
                            ->label('Cadangan Diskon/Return')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(80)
                            ->placeholder('Auto dari histori')
                            ->suffix('%')
                            ->live(onBlur: true),

                        Toggle::make('include_overhead')
                            ->label('Masukkan expense, retur loss, inventory, opname')
                            ->default(true)
                            ->live(),
                    ])
                    ->columns(4),
            ])
            ->statePath('filters');
    }

    public function updatedFilters(): void
    {
        $this->refreshSimulation();
    }

    public function getSimulationProperty(): array
    {
        if ($this->simulationData === []) {
            $this->refreshSimulation();
        }

        return $this->simulationData;
    }

    public function refreshSimulation(): void
    {
        $this->simulationData = app(PriceSimulatorCalculator::class)->calculate($this->filters ?? []);
    }
}
