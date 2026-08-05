<?php

namespace App\Filament\Pages;

use App\Models\Outlet;
use App\Services\StockSnapshotCalculator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class StockSnapshot extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Sisa Stok';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $title = 'Sisa Stok';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.stock-snapshot';

    public ?array $filters = [];

    public array $snapshotData = [];

    public function mount(): void
    {
        $this->form->fill([
            'date' => now()->toDateString(),
            'outlet_group' => null,
            'outlet_id' => null,
        ]);

        $this->refreshSnapshot();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filter Stok')
                    ->schema([
                        DatePicker::make('date')
                            ->label('Tanggal')
                            ->required(),

                        Select::make('outlet_group')
                            ->label('Grup Outlet')
                            ->options(Outlet::GROUPS)
                            ->placeholder('Semua Grup'),

                        Select::make('outlet_id')
                            ->label('Outlet')
                            ->options(fn () => $this->outletOptions())
                            ->placeholder('Semua Outlet')
                            ->searchable(),
                    ])
                    ->columns(3),
            ])
            ->statePath('filters');
    }

    public function applyFilters(): void
    {
        $this->refreshSnapshot();
    }

    public function getSnapshotProperty(): array
    {
        if ($this->snapshotData === []) {
            $this->refreshSnapshot();
        }

        return $this->snapshotData;
    }

    private function refreshSnapshot(): void
    {
        $this->snapshotData = app(StockSnapshotCalculator::class)->calculate($this->filters ?? []);
    }

    private function outletOptions(): array
    {
        return Outlet::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
