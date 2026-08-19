<?php

namespace App\Filament\Pages;

use App\Exports\StockSnapshotExport;
use App\Models\DurianVariety;
use App\Models\InventoryItem;
use App\Models\Outlet;
use App\Services\StockSnapshotCalculator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;

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
            'date_from' => now()->toDateString(),
            'date_until' => now()->toDateString(),
            'outlet_group' => null,
            'outlet_ids' => [],
            'product_category' => null,
            'product_type' => null,
            'durian_variety_id' => null,
            'inventory_item_id' => null,
        ]);

        $this->refreshSnapshot();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filter Stok')
                    ->schema([
                        DatePicker::make('date_from')
                            ->label('Tanggal Awal')
                            ->required(),

                        DatePicker::make('date_until')
                            ->label('Tanggal Akhir')
                            ->required(),

                        Select::make('outlet_group')
                            ->label('Grup Outlet')
                            ->options(Outlet::GROUPS)
                            ->placeholder('Semua Grup')
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('outlet_ids', [])),

                        Select::make('outlet_ids')
                            ->label('Outlet')
                            ->options(fn (Get $get) => $this->outletOptions($get('outlet_group')))
                            ->placeholder('Semua Outlet')
                            ->multiple()
                            ->searchable()
                            ->preload(),

                        Select::make('product_category')
                            ->label('Kategori Produk')
                            ->options([
                                'durian' => 'Produk Durian',
                                'non_durian' => 'Produk Non-durian',
                            ])
                            ->placeholder('Semua Kategori')
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('product_type', null);
                                $set('durian_variety_id', null);
                                $set('inventory_item_id', null);
                            }),

                        Select::make('product_type')
                            ->label('Produk Durian')
                            ->options([
                                'Buah Utuh' => 'Buah Utuh',
                                'Daging Fresh' => 'Kupas Fresh',
                                'Daging Frozen' => 'Durpas Frozen',
                            ])
                            ->placeholder('Semua Produk Durian')
                            ->visible(fn (Get $get): bool => $get('product_category') !== 'non_durian'),

                        Select::make('durian_variety_id')
                            ->label('Varian')
                            ->options(fn () => DurianVariety::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->placeholder('Semua Varian')
                            ->searchable()
                            ->visible(fn (Get $get): bool => $get('product_category') !== 'non_durian'),

                        Select::make('inventory_item_id')
                            ->label('Produk Non-durian')
                            ->options(fn () => InventoryItem::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->placeholder('Semua Produk Non-durian')
                            ->searchable()
                            ->visible(fn (Get $get): bool => $get('product_category') === 'non_durian'),
                    ])
                    ->columns(3),
            ])
            ->statePath('filters');
    }

    public function applyFilters(): void
    {
        $this->refreshSnapshot();
    }

    public function export()
    {
        $this->refreshSnapshot();

        $filters = $this->snapshotData['filters'] ?? [];
        $dateFrom = $filters['date_from'] ?? $filters['date'] ?? now()->toDateString();
        $dateUntil = $filters['date_until'] ?? $dateFrom;

        return Excel::download(
            new StockSnapshotExport($this->snapshotData),
            "laporan-sisa-stok-{$dateFrom}-{$dateUntil}.xlsx",
        );
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

    private function outletOptions(?string $group = null): array
    {
        return Outlet::query()
            ->when(filled($group), fn ($query) => $query->where('group_name', Outlet::normalizeGroupName($group)))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
