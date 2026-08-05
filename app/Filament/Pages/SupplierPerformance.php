<?php

namespace App\Filament\Pages;

use App\Models\DurianVariety;
use App\Models\ProductReturn;
use App\Models\Purchase;
use App\Services\SupplierPerformanceCalculator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class SupplierPerformance extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Supplier Performance';

    protected static ?string $navigationGroup = 'Business';

    protected static ?string $title = 'Supplier Performance';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.supplier-performance';

    public ?array $filters = [];

    public array $performanceData = [];

    public function mount(): void
    {
        $this->form->fill([
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_until' => now()->toDateString(),
            'durian_variety_id' => null,
            'supplier_code' => null,
        ]);

        $this->refreshPerformance();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filter Supplier')
                    ->schema([
                        Select::make('supplier_code')
                            ->label('Supplier')
                            ->options(fn () => $this->supplierOptions())
                            ->placeholder('Semua Supplier')
                            ->searchable(),

                        Select::make('durian_variety_id')
                            ->label('Varian')
                            ->options(DurianVariety::query()->orderBy('name')->pluck('name', 'id'))
                            ->placeholder('Semua Varian'),

                        DatePicker::make('date_from')
                            ->label('Tanggal Mulai')
                            ->required(),

                        DatePicker::make('date_until')
                            ->label('Tanggal Akhir')
                            ->required(),
                    ])
                    ->columns(4),
            ])
            ->statePath('filters');
    }

    public function applyFilters(): void
    {
        $this->refreshPerformance();
    }

    public function getPerformanceProperty(): array
    {
        if ($this->performanceData === []) {
            $this->refreshPerformance();
        }

        return $this->performanceData;
    }

    public function refreshPerformance(): void
    {
        $this->performanceData = app(SupplierPerformanceCalculator::class)->calculate($this->filters ?? []);
    }

    private function supplierOptions(): array
    {
        $purchaseOptions = Purchase::query()
            ->whereNotNull('supplier_code')
            ->orderBy('supplier_code')
            ->get(['supplier_code', 'supplier_name'])
            ->mapWithKeys(function (Purchase $purchase): array {
                $code = trim((string) $purchase->supplier_code);
                $name = trim((string) $purchase->supplier_name);

                if ($code === '') {
                    return [];
                }

                return [$code => $name !== '' ? "{$code} - {$name}" : $code];
            });

        $returnOptions = ProductReturn::query()
            ->whereNotNull('supplier_code')
            ->orderBy('supplier_code')
            ->pluck('supplier_code')
            ->filter()
            ->mapWithKeys(fn (string $code): array => [$code => $purchaseOptions[$code] ?? $code]);

        return $purchaseOptions
            ->merge($returnOptions)
            ->sortKeys()
            ->all();
    }
}
